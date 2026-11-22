<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pdfviewer
 * @copyright   (C) Tazzios
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
     * Allowed values for the PDF.js zoom and pagemode hash parameters.
     */
    private const ZOOM_VALUES = ['auto', 'page-actual', 'page-fit', 'page-width', 'page-height'];
    private const PAGEMODE_VALUES = ['none', 'thumbs', 'bookmarks', 'attachments', 'layers'];

    /**
     * Counter used to give every popup on a page its own id.
     */
    private static int $instance = 0;

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
        $input = method_exists($app, 'getInput') ? $app->getInput() : $app->input;

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
                        $jdownloadsid = (string) (int) trim($tagparameters['jdownloadsid'], " \t\n\r\0\x0B\"'");
                        $filelink = Uri::base() . 'index.php?option=com_jdownloads&task=download.send&id=' . $jdownloadsid;
                    } else {
                        $showpdfpreview = 'no';
                        $output = "jdownloads is not installed";
                    }
                } elseif (isset($tagparameters['file'])) {
                    $filelink = static::buildFileLink($tagparameters['file']);
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
            $pos = strpos($article->text, $match[0]);

			if ($pos !== false) {
				$article->text = substr_replace($article->text, $output, $pos, strlen($match[0]));
			}
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
	
	
	private static function buildFileLink(string $file): string
	{
		$file = trim(trim($file), '"\'');
		$file = str_replace('%20', ' ', $file);

		if ($file === '') {
			return '';
		}

		// Absolute or protocol relative URL: leave untouched
		if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $file) || strpos($file, 'data:') === 0) {
			return $file;
		}

		$basepath = rtrim(Uri::root(true), '/');

		if (strpos($file, '/') === 0) {
			if ($basepath !== '' && strpos($file, $basepath . '/') !== 0) {
				$file = $basepath . $file;
			}

			return $file;
		}

		return $basepath . '/' . ltrim($file, '/');
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
		

        // All values end up in the hash of the viewer url, so they are url encoded here
        // and the complete url is HTML escaped when it is placed in the page.
        if ($input->get('highlight', '', 'BASE64')) {
            $search = (string) base64_decode($input->get('highlight', '', 'BASE64'));
            $search = trim(str_replace(['[', ']', '"', ','], ['', '', '', ' '], $search));
            $pagereference = '#search=' . rawurlencode($search);
        } elseif ($input->getString('search')) {
            $search = $input->getString('search');
            $pagereference = '#search=' . rawurlencode($search);
        } elseif ($input->getString('nameddest')) {
            $pagereference = '#nameddest=' . rawurlencode($input->getString('nameddest'));
        } elseif ($input->getInt('page')) {
            $pagereference = '#page=' . $input->getInt('page');
        } elseif (!empty($tagparameters['search'])) {
            $search = str_replace('%20', ' ', trim($tagparameters['search'], '"'));
            $pagereference = '#search=' . rawurlencode($search);
        } elseif (!empty($tagparameters['nameddest'])) {
            $pagereference = '#nameddest=' . rawurlencode(str_replace('%20', ' ', trim($tagparameters['nameddest'], " \"'")));
        } elseif ((int) ($tagparameters['page'] ?? 0) > 0) {
            $pagereference = '#page=' . (int) $tagparameters['page'];
        }

        if ($search !== '') {
            $phrase = strtolower(trim($input->getString('phrase', $tagparameters['phrase'] ?? '')));
            if (in_array($phrase, ['true', '1', 'yes'], true)) {
                $pagereference .= '&phrase=true';
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

        // Zoom: a keyword or a percentage, optionally followed by a position (150,0,0)
        $zoom = strtolower(trim($input->getString('zoom', $tagparameters['zoom'] ?? '')));
        if ($zoom !== '' && (in_array($zoom, self::ZOOM_VALUES, true) || preg_match('/^\d+(\.\d+)?(,-?\d+(\.\d+)?){0,2}$/', $zoom))) {
            $settings .= ($pagereference === '' ? '#zoom=' : '&zoom=') . $zoom;
        }

        // Pagemode
        $pagemode = strtolower(trim($input->getString('pagemode', $tagparameters['pagemode'] ?? '')));
        if (!in_array($pagemode, self::PAGEMODE_VALUES, true)) {
            $pagemode = '';
        }
        if ($pagemode) {
            $settings .= ($pagereference === '' && $settings === '' ? '#pagemode=' : '&pagemode=') . $pagemode;
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

        $filelink = strtr(rawurlencode($filelink), ['%2F' => '/', '%3A' => ':']);
        $src = static::escape($viewerPath . '?file=' . $filelink . $pagereference . $pdfjsviewsettings);
        $linktext = static::escape((string) $linktext);

        if ($style === 'embed') {
            $heightStyle = 'height:' . (int)$height . 'px;';
            $widthStyle = 'width:' . static::cssSize($width) . ';';
            return '<iframe src="' . $src . '" style="' . $widthStyle . $heightStyle . '" frameborder="0"></iframe>';
        }

        if ($style === 'popup') {
            $randomId = ++self::$instance;
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
                                <iframe width="100%" height="100%" src="' . $src . '" title="' . $linktext . '" frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        if ($style === 'new') {
            return '<a class="pdfviewer_button" target="_blank" href="' . $src . '">' . $linktext . '</a>';
        }

        return '';
    }

    /**
     * Escape a value for use inside an HTML attribute or as text.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Turn a width/height value into a safe CSS size: a number becomes pixels,
     * a number with px or % is kept, anything else falls back to 100%.
     */
    private static function cssSize($value): string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"'");

        if (is_numeric($value)) {
            return (int) $value . 'px';
        }

        if (preg_match('/^\d+(\.\d+)?(px|%)$/', $value)) {
            return $value;
        }

        return '100%';
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
		$file_id = (int) $file_id;
		$db = Factory::getContainer()->get(DatabaseInterface::class);
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

        $pagenumber = max(0, $pagenumber !== '' ? (int)$pagenumber - 1 : 0);

        if (!class_exists('Imagick')) {
            return 'The pdfimage viewer needs the PHP Imagick extension';
        }

        $imgk = new \Imagick();
        $imgk->setResolution(150, 150);

        try {
            $imgk->readImage("{$filelink}[{$pagenumber}]");
        } catch (\Exception $ex) {
            // Do not show the server path to visitors, only in debug mode
            $message = 'cannot convert file to image. Make sure page ' . ($pagenumber + 1) . ' exists.';
            if (Factory::getApplication()->get('debug')) {
                $message .= '<br> and the filelink is correct: ' . static::escape($filelink);
            }
            return $message;
        }

        if ($style === 'popup') {
            $imgk->scaleImage(max(100, (int) $width - 40), 0);
        } else {
            $imgk->scaleImage(1000, 0);
        }

        $imgk->setImageFormat('jpeg');
        $img = $imgk->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imgk->clear();

        $imgBase64 = base64_encode($img);

        if ($style === 'embed') {
            $heightStyle = 'height:' . (int)$height . 'px;';
            $widthStyle = 'width:' . static::cssSize($width) . ';';
            return '<img src="data:image/jpg;base64,' . $imgBase64 . '" style="' . $widthStyle . $heightStyle . '" class="pdfimage_embed_image">';
        }

        $linktext = static::escape((string) $linktext);

        if ($style === 'popup') {
            $randomId = ++self::$instance;
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