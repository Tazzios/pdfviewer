<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pdfviewer
 * @copyright   (C) Tazzios
 * @license     GNU General Public License version 3 or later
 */

namespace Tazzios\Plugin\Content\Pdfviewer\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Input\Input;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Plugin to embed PDF files into Joomla articles using {pdfviewer ...}
 */
final class Pdfviewer extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Allowed values for the PDF.js zoom and pagemode hash parameters.
     */
    private const ZOOM_VALUES = ['auto', 'page-actual', 'page-fit', 'page-width', 'page-height'];
    private const PAGEMODE_VALUES = ['none', 'thumbs', 'bookmarks', 'attachments', 'layers'];

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Counter used to give every popup on a page its own id.
     */
    private static int $instance = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Event: onContentPrepare
     */
    public function onContentPrepare(EventInterface $event): void
    {
        // Joomla 5+ ContentPrepareEvent, or a generic event with the old positional arguments
        if (method_exists($event, 'getContext')) {
            $context = $event->getContext();
            $article = $event->getItem();
        } else {
            $context = $event->getArgument('context', $event->getArgument(0));
            $article = $event->getArgument('subject', $event->getArgument(1));
        }

        // Don't process when indexing for smart search
        if ($context === 'com_finder.indexer' || !\is_object($article) || !isset($article->text)) {
            return;
        }

        $article->text = $this->replaceTags((string) $article->text);
    }

    /**
     * Replaces every {pdfviewer ...} tag in the text with the viewer.
     */
    public function replaceTags(string $text): string
    {
        if (strpos($text, 'pdfviewer') === false) {
            return $text;
        }

        preg_match_all('/{\s*pdfviewer\s*(.*?)}/i', $text, $matches, PREG_SET_ORDER);

        if (!$matches) {
            return $text;
        }

        $app   = $this->getApplication();
        $input = $app->getInput();

        foreach ($matches as $match) {
            $output        = '';
            $tagparameters = $this->parseTagParameters($match[1]);

            if ($this->params->get('debug')) {
                $app->enqueueMessage(htmlspecialchars(print_r($tagparameters, true), ENT_QUOTES, 'UTF-8'));
            }

            $output = $this->renderTag($tagparameters, $input);

            // Replace only first occurrence
            $pos = strpos($text, $match[0]);

            if ($pos !== false) {
                $text = substr_replace($text, $output, $pos, \strlen($match[0]));
            }
        }

        return $text;
    }

    /**
     * Returns the html for one tag.
     */
    private function renderTag(array $tagparameters, Input $input): string
    {
        $showpdfpreview = strtolower($tagparameters['showpdfpreview'] ?? 'yes');

        // Check filename is PDF
        if (isset($tagparameters['filename'])) {
            $ext = strtolower(trim(pathinfo($tagparameters['filename'], PATHINFO_EXTENSION), '\'"'));

            if ($ext !== 'pdf') {
                $showpdfpreview = 'no';
            }
        }

        if ($showpdfpreview !== 'yes') {
            return '';
        }

        $viewer   = strtolower($tagparameters['viewer'] ?? $this->params->get('viewer', 'pdfjs'));
        $style    = strtolower($tagparameters['style'] ?? $this->params->get('style', 'embed'));
        $linktext = isset($tagparameters['linktext'])
            ? str_replace('%20', ' ', trim($tagparameters['linktext'], '"'))
            : $this->params->get('linktext');

        $sizeDefault = fn (string $dimension) => match ($style) {
            'embed' => $this->params->get('embed' . $dimension),
            'popup' => $this->params->get('popup' . $dimension),
            default => '',
        };

        $height = $tagparameters['height'] ?? $sizeDefault('height');
        $width  = $tagparameters['width'] ?? $sizeDefault('width');

        // Build file link for jdownloads or direct
        $filelink     = '';
        $jdownloadsid = '';

        if (isset($tagparameters['jdownloadsid'])) {
            if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_jdownloads')) {
                return Text::_('PLG_CONTENT_PDFVIEWER_JDOWNLOADS_NOT_INSTALLED');
            }

            $jdownloadsid = (string) (int) trim($tagparameters['jdownloadsid'], " \t\n\r\0\x0B\"'");
            $filelink     = Uri::base() . 'index.php?option=com_jdownloads&task=download.send&id=' . $jdownloadsid;
        } elseif (isset($tagparameters['file'])) {
            $filelink = static::buildFileLink($tagparameters['file']);
        }

        if ($viewer === 'pdfimage') {
            if ($jdownloadsid === '') {
                return Text::_('PLG_CONTENT_PDFVIEWER_IMAGE_NEEDS_JDOWNLOADS');
            }

            $page = $tagparameters['page'] ?? '';

            return $this->createPdfImage((int) $jdownloadsid, $page, $height, $width, $style, $linktext);
        }

        // pdfjs or default
        $pagereference     = static::buildPageReference($input, $tagparameters);
        $pdfjsviewsettings = static::buildPdfJsSettings($input, $tagparameters, $pagereference);

        return $this->createPdfViewer(
            $filelink,
            $pagereference,
            $pdfjsviewsettings,
            $height,
            $width,
            $style,
            $linktext
        );
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

        preg_match_all('/([^,= ]+)=([^,= ]+)/', $tagparams, $r);
        $tagparameters = array_combine($r[1], $r[2]);

        return \is_array($tagparameters) ? array_change_key_case($tagparameters, CASE_LOWER) : [];
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
     *
     * Priority of page, search and nameddest:
     * 1 highlight search, 2 url search, 3 url nameddest, 4 url page,
     * 5 tag search, 6 tag nameddest, 7 tag page
     */
    private static function buildPageReference(Input $input, array $tagparameters): string
    {
        $search        = '';
        $pagereference = '';

        // All values end up in the hash of the viewer url, so they are url encoded here
        // and the complete url is HTML escaped when it is placed in the page.
        if ($input->get('highlight', '', 'BASE64')) {
            $search        = (string) base64_decode($input->get('highlight', '', 'BASE64'));
            $search        = trim(str_replace(['[', ']', '"', ','], ['', '', '', ' '], $search));
            $pagereference = '#search=' . rawurlencode($search);
        } elseif ($input->getString('search')) {
            $search        = $input->getString('search');
            $pagereference = '#search=' . rawurlencode($search);
        } elseif ($input->getString('nameddest')) {
            $pagereference = '#nameddest=' . rawurlencode($input->getString('nameddest'));
        } elseif ($input->getInt('page')) {
            $pagereference = '#page=' . $input->getInt('page');
        } elseif (!empty($tagparameters['search'])) {
            $search        = str_replace('%20', ' ', trim($tagparameters['search'], '"'));
            $pagereference = '#search=' . rawurlencode($search);
        } elseif (!empty($tagparameters['nameddest'])) {
            $nameddest     = str_replace('%20', ' ', trim($tagparameters['nameddest'], " \"'"));
            $pagereference = '#nameddest=' . rawurlencode($nameddest);
        } elseif ((int) ($tagparameters['page'] ?? 0) > 0) {
            $pagereference = '#page=' . (int) $tagparameters['page'];
        }

        if ($search !== '') {
            $phrase = strtolower(trim($input->getString('phrase', $tagparameters['phrase'] ?? '')));

            if (\in_array($phrase, ['true', '1', 'yes'], true)) {
                $pagereference .= '&phrase=true';
            }
        }

        return $pagereference;
    }

    /**
     * Build PDF.js viewer options (zoom, pagemode, etc)
     */
    private static function buildPdfJsSettings(Input $input, array $tagparameters, string $pagereference): string
    {
        $settings = '';

        // Zoom: a keyword or a percentage, optionally followed by a position (150,0,0)
        $zoom = strtolower(trim($input->getString('zoom', $tagparameters['zoom'] ?? '')));

        if (
            $zoom !== ''
            && (\in_array($zoom, self::ZOOM_VALUES, true) || preg_match('/^\d+(\.\d+)?(,-?\d+(\.\d+)?){0,2}$/', $zoom))
        ) {
            $settings .= ($pagereference === '' ? '#zoom=' : '&zoom=') . $zoom;
        }

        // Pagemode
        $pagemode = strtolower(trim($input->getString('pagemode', $tagparameters['pagemode'] ?? '')));

        if (!\in_array($pagemode, self::PAGEMODE_VALUES, true)) {
            $pagemode = 'none';
        }

        $settings .= ($pagereference === '' && $settings === '' ? '#pagemode=' : '&pagemode=') . $pagemode;

        return $settings;
    }

    /**
     * Render the PDF viewer (iframe, popup, new window)
     */
    private function createPdfViewer(
        string $filelink,
        string $pagereference,
        string $pdfjsviewsettings,
        $height,
        $width,
        $style,
        $linktext
    ): string {
        $template = $this->getApplication()->getTemplate();
        $base     = Uri::base();

        // Path to PDF.js viewer (allow template override)
        $overridePath = 'templates/' . $template . '/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html';
        $viewerPath   = file_exists(JPATH_ROOT . '/' . $overridePath)
            ? $base . $overridePath
            : $base . 'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html';

        $filelink = strtr(rawurlencode($filelink), ['%2F' => '/', '%3A' => ':']);
        $src      = static::escape($viewerPath . '?file=' . $filelink . $pagereference . $pdfjsviewsettings);
        $linktext = static::escape((string) $linktext);

        if ($style === 'embed') {
            $heightStyle = 'height:' . (int) $height . 'px;';
            $widthStyle  = 'width:' . static::cssSize($width) . ';';

            return '<iframe src="' . $src . '" style="' . $widthStyle . $heightStyle . '" frameborder="0"></iframe>';
        }

        if ($style === 'popup') {
            $body = '<iframe width="100%" height="100%" src="' . $src . '" title="' . $linktext
                . '" frameborder="0" allowfullscreen></iframe>';

            return static::modal(
                'pdfModal',
                $linktext,
                'height:' . (int) $height . 'px;width:' . (int) $width . 'px;',
                $body
            );
        }

        if ($style === 'new') {
            return '<a class="pdfviewer_button" target="_blank" href="' . $src . '">' . $linktext . '</a>';
        }

        return '';
    }

    /**
     * Link that opens a Bootstrap modal with the given body.
     */
    private static function modal(string $idPrefix, string $linktext, string $contentStyle, string $body): string
    {
        $id = $idPrefix . ++self::$instance;

        HTMLHelper::_('bootstrap.modal', '.selector', []);

        return '<a data-bs-toggle="modal" data-bs-target="#' . $id . '">' . $linktext . '</a>
                <div id="' . $id . '" class="modal fade" tabindex="-1">
                    <div class="modal-dialog" style="transform: translateX(-50%); left: 0px;">
                        <div class="modal-content" style="' . $contentStyle . '">
                            <div class="modal-header">
                                ' . $linktext . '
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="'
                                    . static::escape(Text::_('PLG_CONTENT_PDFVIEWER_CLOSE')) . '"></button>
                            </div>
                            <div class="modal-body">
                                ' . $body . '
                            </div>
                        </div>
                    </div>
                </div>';
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
     * Style for an embedded page image. The height is the size of the image and the
     * width is only a maximum, so the image keeps the aspect ratio of the page and is
     * never stretched (object-fit covers the case where the maximum width kicks in).
     */
    private static function imageEmbedStyle($width, $height): string
    {
        return 'width:auto;max-width:' . static::cssSize($width) . ';height:' . (int) $height . 'px;'
            . 'object-fit:contain;';
    }

    /**
     * Path of a jDownloads file on the server, built from its category tree.
     */
    private function jdownloadsFilePath(int $fileId): string
    {
        $uploaddir = ComponentHelper::getParams('com_jdownloads')->get('files_uploaddir');

        // The query builder has no recursive CTE, so the SQL is set as a whole and the id is bound.
        $sql = "WITH RECURSIVE n AS (
                SELECT id, parent_id, concat('/', title, '/') AS path
                FROM #__jdownloads_categories
                WHERE parent_id = 0
                UNION ALL
                SELECT c.id, c.parent_id, concat(n.path, c.title, '/')
                FROM n
                JOIN #__jdownloads_categories c ON c.parent_id = n.id
                WHERE n.path NOT LIKE concat('%/', c.id, '/%')
            )
            SELECT REPLACE(path, '/ROOT', '') AS path, file.url_download AS filename
            FROM n
            INNER JOIN #__jdownloads_files AS file ON n.id = file.catid
            WHERE file.id = :fileid";

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->setQuery($sql)
            ->bind(':fileid', $fileId, ParameterType::INTEGER);

        $filelink = '';

        foreach ((array) $db->setQuery($query)->loadAssocList() as $file) {
            $filelink = $uploaddir . $file['path'] . $file['filename'];
        }

        return $filelink;
    }

    /**
     * Render a PDF page as an image (requires Imagick and jDownloads)
     */
    private function createPdfImage(int $fileId, $pagenumber, $height, $width, $style, $linktext): string
    {
        $filelink   = $this->jdownloadsFilePath($fileId);
        $pagenumber = max(0, $pagenumber !== '' ? (int) $pagenumber - 1 : 0);

        if (!class_exists('Imagick')) {
            return Text::_('PLG_CONTENT_PDFVIEWER_IMAGE_NEEDS_IMAGICK');
        }

        $imgk = new \Imagick();
        $imgk->setResolution(150, 150);

        try {
            $imgk->readImage("{$filelink}[{$pagenumber}]");
        } catch (\Exception $ex) {
            // Do not show the server path to visitors, only in debug mode
            $message = Text::sprintf('PLG_CONTENT_PDFVIEWER_IMAGE_CONVERT_FAILED', $pagenumber + 1);

            if ($this->getApplication()->get('debug')) {
                $message .= '<br> '
                    . Text::sprintf('PLG_CONTENT_PDFVIEWER_IMAGE_CHECK_FILELINK', static::escape($filelink));
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
            return '<img src="data:image/jpg;base64,' . $imgBase64 . '" style="'
                . static::imageEmbedStyle($width, $height) . '" class="pdfimage_embed_image">';
        }

        $linktext = static::escape((string) $linktext);

        if ($style === 'popup') {
            return static::modal(
                'imgModal',
                $linktext,
                'max-height:' . (int) $height . 'px;max-width:' . (int) $width . 'px;',
                '<img src="data:image/jpg;base64,' . $imgBase64 . '" >'
            );
        }

        if ($style === 'new') {
            return '<a class="pdfviewer_button" target="_blank" href="data:image/jpg;base64,' . $imgBase64 . '">'
                . $linktext . '</a>';
        }

        return '';
    }
}
