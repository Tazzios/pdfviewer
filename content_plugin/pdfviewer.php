<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pdfviewer
 * @copyright   (C) Your Name / Company
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

//namespace Tazzios\Plugin\Content\pdfviewer;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Version;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Plugin to embed PDF files into Joomla articles using {pdfviewer ...}
 */
class PlgContentPdfviewer extends CMSPlugin 
{
    /**
     * Event: onContentPrepare
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Don't process when indexing for smart search
        if ($context === 'com_finder.indexer') {
            return true;
        }

        if (strpos($article->text, 'pdfviewer') === false) {
            return true;
        }

        $regex = '/{\s*pdfviewer\s*(.*?)}/i';

        preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

        if (!$matches) {
            return true;
        }

        $app = Factory::getApplication();
        $input = $app->input;

        foreach ($matches as $match) {
            $output = ''; // Ensure no double placement

            $tagparameters = $this->parseTagParameters($match[1]);

            // Debug (optional, replace with logging if needed)
             if ($this->params->get('debug')) { Factory::getApplication()->enqueueMessage(print_r($tagparameters, true)); }

            $showpdfpreview = isset($tagparameters['showpdfpreview']) ? strtolower($tagparameters['showpdfpreview']) : 'yes';


            // Check filename is PDF
            if (isset($tagparameters['filename'])) {
                $ext = strtolower(trim(pathinfo($tagparameters['filename'], PATHINFO_EXTENSION), '\'"'));
                if ($ext !== 'pdf') {
                    $showpdfpreview = 'no';
                }
            }

            if ($showpdfpreview === 'yes') {
                $viewer = strtolower($tagparameters['viewer'] ?? $this->params->get('viewer', 'pdfjs'));
                $style = strtolower($tagparameters['style'] ?? $this->params->get('style', 'embed'));
                $linktext = isset($tagparameters['linktext']) ? str_replace('%20', ' ', trim($tagparameters['linktext'], '"')) : $this->params->get('linktext');
                $height = $tagparameters['height'] ?? ($style === 'embed' ? $this->params->get('embedheight') : ($style === 'popup' ? $this->params->get('popupheight') : ''));
                $width = $tagparameters['width'] ?? ($style === 'embed' ? $this->params->get('embedwidth') : ($style === 'popup' ? $this->params->get('popupwidth') : ''));

                // Build file link for jdownloads or direct
                $filelink = '';
                $jdownloadsid = '';
                if (isset($tagparameters['jdownloadsid'])) {
                    $jdownloadsPath = JPATH_ADMINISTRATOR . '/components/com_jdownloads';
                    if (file_exists($jdownloadsPath)) {
                        $jdownloadsid = trim($tagparameters['jdownloadsid']);
                        $filelink = Uri::base() . 'index.php?option=com_jdownloads&task=download.send&id=' . $jdownloadsid;
                    } else {
                        $showpdfpreview = 'no';
                        $output = "jdownloads is not installed";
                    }
                } elseif (isset($tagparameters['file'])) {
                    $filelink = trim($tagparameters['file']);
                }

                if ($showpdfpreview === 'yes') {
                    switch ($viewer) {
                        case 'pdfimage':
                            if ($jdownloadsid !== '') {
                                $output = static::createPdfImage($jdownloadsid, $tagparameters['page'] ?? '', $height, $width, $style, $linktext);
                            } else {
                                $output = 'Only jdownloads PDF files can be converted to image';
                            }
                            break;
                        default:
                            // pdfjs or default
                            $pagereference = static::buildPageReference($input, $tagparameters);
                            $pdfjsviewsettings = static::buildPdfJsSettings($input, $tagparameters, $pagereference);
                            $output = static::createPdfViewer($filelink, $pagereference, $pdfjsviewsettings, $height, $width, $style, $linktext);
                            break;
                    }
                }
            }

            // Replace only first occurrence
            $article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
        }
    }

    /**
     * Parse and normalize tag parameters from the {pdfviewer ...} tag
     */
    private function parseTagParameters(string $raw): array
    {
        $tagparams = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $raw);
        $tagparams = strip_tags($tagparams);
        $tagparams = str_replace(' =', '=', $tagparams);
        $tagparams = preg_replace('~\s+(?=([^"]*"[^"]*")*[^"]*$)~', ',', $tagparams);
        $tagparams = str_replace(' ', '%20', $tagparams);

        preg_match_all("/([^,= ]+)=([^,= ]+)/", $tagparams, $r);
        $tagparameters = array_combine($r[1], $r[2]);
        return is_array($tagparameters) ? array_change_key_case($tagparameters, CASE_LOWER) : [];
    }

    /**
     * Build the #search, #page, etc. part for PDF.js viewer
     */
    private static function buildPageReference($input, array $tagparameters): string
    {
				
        $search = '';
        $pagereference = '';
		
		/*page, search and nameddest order priority
		1 highlight search 
		2 url search
		3 url namedest
		4 url page 
		5 param search
		6 param nameddest
		7 param page					
		*/

        if ($input->get('highlight', '', 'BASE64')) {
            $search = base64_decode(htmlspecialchars($input->get('highlight', '', 'BASE64')));
            $search = str_replace(['[', ']', '"', ','], ['', '', '', ' '], $search);
            $pagereference = '#search=' . $search;
        } elseif ($input->getString('search')) {
            $search = $input->getString('search');
            $pagereference = '#search=' . $search;
        } elseif ($input->getString('nameddest')) {
            $pagereference = $input->getString('nameddest');
        } elseif ($input->getInt('page')) {
            $pagereference = '#page=' . $input->getInt('page');
        } elseif (!empty($tagparameters['search'])) {
            $search = str_replace('%20', ' ', trim($tagparameters['search'], '"'));
            $pagereference = '#search=' . $search;
        } elseif (!empty($tagparameters['nameddest'])) {
            $pagereference = '#nameddest=' . trim($tagparameters['nameddest']);
        } elseif (!empty($tagparameters['page']) && $tagparameters['page'] != 0) {
            $pagereference = '#page=' . trim($tagparameters['page']);
        }

        if ($search !== '') {
            $phrase = $input->getString('phrase', $tagparameters['phrase'] ?? '');
            if ($phrase) {
                $pagereference .= '&phrase=' . trim($phrase);
            }
        }

        return $pagereference;
    }

    /*
     * Build PDF.js viewer options (zoom, pagemode, etc)
     */
    private static function buildPdfJsSettings($input, array $tagparameters, string $pagereference): string
    {
        $settings = '';

        // Zoom
        $zoom = $input->getString('zoom', $tagparameters['zoom'] ?? '');
        if ($zoom) {
            $settings .= ($pagereference === '' ? '#zoom=' : '&zoom=') . trim($zoom);
        }

        // Pagemode
        $pagemode = $input->getString('pagemode', $tagparameters['pagemode'] ?? '');
        if ($pagemode) {
            $settings .= ($pagereference === '' && $settings === '' ? '#pagemode=' : '&pagemode=') . trim($pagemode);
        } elseif ($pagereference === '' && $settings === '') {
            $settings .= '#pagemode=none';
        } else {
            $settings .= '&pagemode=none';
        }

        return $settings;
    }

    /*
     * Render the PDF viewer (iframe, popup, new window)
     */
    private static function createPdfViewer(string $filelink, string $pagereference, string $pdfjsviewsettings, $height, $width, $style, $linktext): string
    {
        $app = Factory::getApplication();
        $template = $app->getTemplate();
        $base = Uri::base();

        // Path to PDF.js viewer (allow template override)
        $pdfjsOverride = JPATH_ROOT . "/templates/$template/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html";
        $viewerPath = file_exists($pdfjsOverride)
            ? $base . "templates/$template/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html"
            : $base . "plugins/content/pdfviewer/assets/pdfjs/web/viewer.html";

        $filelink = urlencode($filelink);

        if ($style === 'embed') {
            $heightStyle = 'height:' . (int)$height . 'px;';
            $widthStyle = is_numeric($width) ? 'width:' . (int)$width . 'px;' : 'width:' . $width . ';';
            return '<iframe src="' . $viewerPath . '?file=' . $filelink . $pagereference . $pdfjsviewsettings . '" style="' . $widthStyle . $heightStyle . '" frameborder="0"></iframe>';
        }

        if ($style === 'popup') {
            $randomId = rand(0, 1000);
            HTMLHelper::_('bootstrap.modal', '.selector', []);
            return '<a data-bs-toggle="modal" data-bs-target="#pdfModal' . $randomId . '">' . $linktext . '</a>
                <div id="pdfModal' . $randomId . '" class="modal fade" tabindex="-1">
                    <div class="modal-dialog" style="transform: translateX(-50%); left: 0px;">
                        <div class="modal-content" style="height:' . (int)$height . 'px;width:' . (int)$width . 'px;">
                            <div class="modal-header">
                                ' . $linktext . '
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <iframe width="100%" height="100%" src="' . $viewerPath . '?file=' . $filelink . $pagereference . $pdfjsviewsettings . '" title="' . $linktext . '" frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        if ($style === 'new') {
            return '<a class="pdfviewer_button" target="_blank" href="' . $viewerPath . '?file=' . $filelink . $pagereference . $pdfjsviewsettings . '">' . $linktext . '</a>';
        }

        return '';
    }

    /**
     * Render a PDF page as an image (requires Imagick and jDownloads)
     */
    private static function createPdfImage($file_id, $pagenumber, $height, $width, $style, $linktext): string
    {
        // Get jDownloads upload dir
        $params = ComponentHelper::getParams('com_jdownloads');
        $files_uploaddir = $params->get('files_uploaddir');

		// get categorie path
		$db = Factory::getDbo();
		$db->setQuery("WITH RECURSIVE n AS 
			( SELECT id, parent_id, concat('/', title ,'/') AS path  
			FROM #__jdownloads_categories 
			WHERE parent_id = 0 
			union all 
			SELECT c.id, c.parent_id, concat(n.path, c.title, '/') 
			FROM n 
			join #__jdownloads_categories c on c.parent_id = n.id 
			WHERE n.path not like concat('%/', c.id, '/%') -- cycle pruning here! 
			) 
			SELECT REPLACE(path,'/ROOT','') AS path, file.url_download AS filename 
			FROM n 
			INNER JOIN #__jdownloads_files AS file ON n.id=file.catid WHERE file.id=". $file_id );
	
		$fileDB = $db->loadAssocList();
	

        $filelink = '';
        foreach ($fileDB as $file) {
            $filelink = $files_uploaddir . $file['path'] . $file['filename'];
        }

        $pagenumber = $pagenumber !== '' ? (int)$pagenumber - 1 : 0;

        $imgk = new \Imagick();
        $imgk->setResolution(150, 150);

        try {
            $imgk->readImage("{$filelink}[{$pagenumber}]");
        } catch (\Exception $ex) {
            return 'cannot convert file to image. Make sure  page ' . ($pagenumber + 1) . ' exists. <br> and the filelink is correct: ' . $filelink;
        }

        if ($style === 'popup') {
            $imgk->scaleImage($width - 40, 0);
        } else {
            $imgk->scaleImage(1000, 0);
        }

        $imgk->setImageFormat('jpeg');
        $img = $imgk->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imgk->clear();

        $imgBase64 = base64_encode($img);

        if ($style === 'embed') {
            $heightStyle = 'height:' . (int)$height . 'px;';
            $widthStyle = is_numeric($width) ? 'width:' . (int)$width . 'px;' : 'width:' . $width . '%;';
            return '<img src="data:image/jpg;base64,' . $imgBase64 . '" style="' . $widthStyle . $heightStyle . '" class="pdfimage_embed_image">';
        }

        if ($style === 'popup') {
            $randomId = rand(0, 1000);
            HTMLHelper::_('bootstrap.modal', '.selector', []);
            return '<a data-bs-toggle="modal" data-bs-target="#imgModal' . $randomId . '">' . $linktext . '</a>
                <div id="imgModal' . $randomId . '" class="modal fade" tabindex="-1">
                    <div class="modal-dialog" style="transform: translateX(-50%); left: 0px;">
                        <div class="modal-content" style="max-height:' . (int)$height . 'px;max-width:' . (int)$width . 'px;">
                            <div class="modal-header">
                                ' . $linktext . '
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <img src="data:image/jpg;base64,' . $imgBase64 . '" >
                            </div>
                        </div>
                    </div>
                </div>';
        }

        if ($style === 'new') {
            return '<a class="pdfviewer_button" target="_blank" href="data:image/jpg;base64,' . $imgBase64 . '">' . $linktext . '</a>';
        }

        return '';
    }
}