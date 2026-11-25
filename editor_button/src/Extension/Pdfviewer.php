<?php

/**
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 */

namespace Tazzios\Plugin\EditorsXtd\Pdfviewer\Extension;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editor button that opens a dialog to insert a {pdfviewer ...} tag.
 *
 * Joomla 5 and 6 call onEditorButtonsSetup, which adds a Button object.
 *
 * The dialog itself is served through com_ajax (onAjaxPdfviewer) and is only
 * shown to logged in users who may write articles, with a valid form token.
 */
final class Pdfviewer extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Script asset with the dialog logic.
     */
    public const SCRIPT_ASSET = 'plg_editors-xtd_pdfviewer.dialog';

    /**
     * Style asset with the layout of the dialog content (inside the iframe).
     */
    public const STYLE_ASSET = 'plg_editors-xtd_pdfviewer.dialog';

    /**
     * Style asset for the page with the editor: fallback styles for Joomla's
     * dialog, for site templates that do not style it (only Atum and
     * Cassiopeia do).
     */
    public const HOST_STYLE_ASSET = 'plg_editors-xtd_pdfviewer.dialog-host';

    /**
     * Icon of the button: a page with "PDF".
     */
    private const ICON_SVG = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">'
        . '<path fill="currentColor" d="'
        . 'M6 2h8l6 6v14H4V2h2zm7 1.5V9h5.5L13 3.5zM7 13v5h1.5v-1.6h.9c1.1 0 1.9-.7 1.9-1.7S10.5 13 '
        . '9.4 13H7zm1.5 1.2h.8c.3 0 .5.2.5.5s-.2.5-.5.5h-.8v-1zM12 13v5h1.8c1.4 0 2.4-1 2.4-2.5S15.2 '
        . '13 13.8 13H12zm1.5 1.3h.3c.6 0 1 .5 1 1.2s-.4 1.2-1 1.2h-.3v-2.4zM17 '
        . '13v5h1.5v-1.9h1.6v-1.2h-1.6v-.7h1.8V13H17z'
        . '"/></svg>';

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onEditorButtonsSetup' => 'onEditorButtonsSetup',
            'onAjaxPdfviewer'      => 'onAjaxPdfviewer',
        ];
    }

    /**
     * Adds the button to the registry of the editor.
     */
    public function onEditorButtonsSetup(EventInterface $event): void
    {
        if (\in_array($this->_name, (array) $event->getDisabledButtons(), true)) {
            return;
        }

        $button = $this->buildButton((string) $event->getEditorId());

        if ($button) {
            $event->getButtonsRegistry()->add($button);
        }
    }

    /**
     * Builds the button, or null when the user may not use it.
     *
     * @param   string  $name  The id of the editor the button belongs to
     */
    public function buildButton(string $name): ?Button
    {
        if (!$this->canUseButton()) {
            return null;
        }

        $link = 'index.php?option=com_ajax&plugin=pdfviewer&group=editors-xtd&format=html&tmpl=component&'
            . Session::getFormToken() . '=1&e_name=' . rawurlencode((string) $name);

        $this->useHostStyle();

        $props = [
            'action'  => 'modal',
            'modal'   => true,
            'link'    => $link,
            'text'    => Text::_('PLG_EDITORSXTD_PDFVIEWER_BUTTON'),
            'icon'    => 'file',
            'iconSVG' => self::ICON_SVG,
            'name'    => $this->_type . '_' . $this->_name,
        ];

        // Size of Joomla's dialog, as CSS values
        $options = [
            'height' => 'min(680px, 90vh)',
            'width'  => '900px',
        ];

        return new Button($this->_name, $props, $options);
    }

    /**
     * com_ajax entry point: renders the dialog that is shown in the modal.
     */
    public function onAjaxPdfviewer(EventInterface $event): void
    {
        $html = $this->renderDialog();

        // AjaxEvent of com_ajax
        if (method_exists($event, 'addResult')) {
            $event->addResult($html);

            return;
        }

        // Generic event, when another extension triggers it
        $result   = (array) $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Returns the html of the dialog after checking the token and permissions.
     *
     * @throws  \RuntimeException  when the user may not use the button
     */
    public function renderDialog(): string
    {
        if (!Session::checkToken('get') || !$this->canUseButton()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app   = $this->getApplication();
        $input = $app->getInput();

        $displayData = [
            'editor'          => preg_replace('#[^A-Z0-9\-\_\[\]]#i', '', (string) $input->getCmd('e_name', '')),
            'jdownloadsFiles' => $this->getJdownloadsFiles(),
            'content'         => $content = $this->getContentDefaults(),
            'defaults'        => $this->getButtonDefaults($content),
        ];

        $app->getDocument()->getWebAssetManager()
            ->registerAndUseStyle(
                self::STYLE_ASSET,
                'plg_editors-xtd_pdfviewer/pdfviewer.css',
                ['version' => static::mediaVersion('css/pdfviewer.css')]
            )
            ->registerAndUseScript(
                self::SCRIPT_ASSET,
                'plg_editors-xtd_pdfviewer/pdfviewer.js',
                ['version' => static::mediaVersion('js/pdfviewer.js')],
                ['defer' => true],
                ['core']
            );

        $layout = PluginHelper::getLayoutPath($this->_type, $this->_name);

        return (static function (string $layout, array $displayData): string {
            ob_start();
            include $layout;

            return (string) ob_get_clean();
        })($layout, $displayData);
    }

    /**
     * Loads the fallback dialog styles on the page with the editor.
     */
    private function useHostStyle(): void
    {
        $app = $this->getApplication();

        if (!$app || !method_exists($app, 'getDocument')) {
            return;
        }

        $document = $app->getDocument();

        if (!$document || !method_exists($document, 'getWebAssetManager')) {
            return;
        }

        $document->getWebAssetManager()->registerAndUseStyle(
            self::HOST_STYLE_ASSET,
            'plg_editors-xtd_pdfviewer/dialog-host.css',
            ['version' => static::mediaVersion('css/dialog-host.css')]
        );
    }

    /**
     * The file time as version, so browsers fetch a file again after a change.
     */
    private static function mediaVersion(string $file): string
    {
        return (string) (@filemtime(JPATH_ROOT . '/media/plg_editors-xtd_pdfviewer/' . $file) ?: 'auto');
    }

    /**
     * Same check as the core pagebreak button: the user may create or edit
     * articles, or is editing an article right now. Guests never get access.
     */
    public function canUseButton(): bool
    {
        $app = $this->getApplication();

        if (!$app || !method_exists($app, 'getIdentity')) {
            return false;
        }

        $user = $app->getIdentity();

        if (!$user || $user->guest || !$user->id) {
            return false;
        }

        if (
            $user->authorise('core.create', 'com_content')
            || $user->authorise('core.edit', 'com_content')
            || $user->authorise('core.edit.own', 'com_content')
            || \count($user->getAuthorisedCategories('com_content', 'core.create')) > 0
        ) {
            return true;
        }

        return \count((array) $app->getUserState('com_content.edit.article.id')) > 0;
    }

    /**
     * Published jDownloads files (newest first), or null when jDownloads is not installed.
     *
     * @return  array<int, array{id: int, title: string}>|null
     */
    public function getJdownloadsFiles(): ?array
    {
        if (!is_dir(JPATH_ADMINISTRATOR . '/components/com_jdownloads')) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title']))
            ->from($db->quoteName('#__jdownloads_files'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('publish_up') . ' DESC');

        $files = [];

        foreach ((array) $db->setQuery($query)->loadAssocList() as $row) {
            $files[] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
        }

        return $files;
    }

    /**
     * Settings of the content plugin: what a tag without viewer=, style=,
     * width= or height= will use.
     *
     * @return  array{viewer: string, style: string, sizes: array}
     */
    public function getContentDefaults(): array
    {
        $params = new Registry();
        $plugin = PluginHelper::getPlugin('content', 'pdfviewer');

        if (\is_object($plugin) && !empty($plugin->params)) {
            $params = $plugin->params instanceof Registry ? $plugin->params : new Registry($plugin->params);
        }

        return static::normaliseDefaults($params, static::normaliseDefaults(new Registry(), null));
    }

    /**
     * Defaults of this button, set in its plugin settings. Empty settings
     * fall back to the content plugin.
     *
     * @return  array{viewer: string, style: string, sizes: array}
     */
    public function getButtonDefaults(?array $contentDefaults = null): array
    {
        return static::normaliseDefaults($this->params, $contentDefaults ?? $this->getContentDefaults());
    }

    /**
     * Reads viewer, style and the embed/popup sizes from plugin parameters.
     * Unknown or empty values take the fallback (or the built-in defaults).
     */
    private static function normaliseDefaults(Registry $params, ?array $fallback): array
    {
        $fallback ??= [
            'viewer' => 'pdfjs',
            'style'  => 'embed',
            'sizes'  => [
                'embed' => ['width' => '100%', 'height' => '800'],
                'popup' => ['width' => '800', 'height' => '800'],
            ],
        ];

        $viewer = strtolower(trim((string) $params->get('viewer', '')));
        $style  = strtolower(trim((string) $params->get('style', '')));

        $result = [
            'viewer' => \in_array($viewer, ['pdfjs', 'pdfimage'], true) ? $viewer : $fallback['viewer'],
            'style'  => \in_array($style, ['embed', 'popup', 'new'], true) ? $style : $fallback['style'],
            'sizes'  => [],
        ];

        foreach (['embed', 'popup'] as $type) {
            foreach (['width', 'height'] as $dimension) {
                $value = trim((string) $params->get($type . $dimension, ''));

                $result['sizes'][$type][$dimension] = preg_match('/^\d+(\.\d+)?(px|%)?$/', $value)
                    ? $value
                    : $fallback['sizes'][$type][$dimension];
            }
        }

        return $result;
    }
}
