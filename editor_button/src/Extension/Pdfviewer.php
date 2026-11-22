<?php

/**
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 */

namespace Tazzios\Plugin\EditorsXtd\Pdfviewer\Extension;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editor button that opens a dialog to insert a {pdfviewer ...} tag.
 *
 * Works on Joomla 4.4, 5 and 6:
 * - Joomla 5 and 6 call onEditorButtonsSetup and get a Button object.
 * - Joomla 4 calls onDisplay directly and gets a CMSObject.
 *
 * The dialog itself is served through com_ajax (onAjaxPdfviewer) and is only
 * shown to logged in users who may write articles, with a valid form token.
 */
final class Pdfviewer extends CMSPlugin implements SubscriberInterface
{
    /**
     * Script asset with the dialog logic.
     */
    public const SCRIPT_ASSET = 'plg_editors-xtd_pdfviewer.dialog';

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
     * Joomla 5+: add the button to the registry.
     */
    public function onEditorButtonsSetup(EventInterface $event): void
    {
        if (\in_array($this->_name, (array) $event->getDisabledButtons(), true)) {
            return;
        }

        $button = $this->onDisplay($event->getEditorId());

        if ($button) {
            $event->getButtonsRegistry()->add($button);
        }
    }

    /**
     * Builds the button. Called directly by Joomla 4.
     *
     * @param   string  $name  The id of the editor the button belongs to
     *
     * @return  Button|CMSObject|null
     */
    public function onDisplay($name, $asset = 0, $author = 0)
    {
        if (!$this->canUseButton()) {
            return null;
        }

        $link = 'index.php?option=com_ajax&plugin=pdfviewer&group=editors-xtd&format=html&tmpl=component&'
            . Session::getFormToken() . '=1&e_name=' . rawurlencode((string) $name);

        $props = [
            'action'  => 'modal',
            'modal'   => true,
            'link'    => $link,
            'text'    => Text::_('PLG_EDITORSXTD_PDFVIEWER_BUTTON'),
            'icon'    => 'file',
            'iconSVG' => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M6 2h8l6 6v14H4V2h2zm7 1.5V9h5.5L13 3.5zM7 13v5h1.5v-1.6h.9c1.1 0 1.9-.7 1.9-1.7S10.5 13 9.4 13H7zm1.5 1.2h.8c.3 0 .5.2.5.5s-.2.5-.5.5h-.8v-1zM12 13v5h1.8c1.4 0 2.4-1 2.4-2.5S15.2 13 13.8 13H12zm1.5 1.3h.3c.6 0 1 .5 1 1.2s-.4 1.2-1 1.2h-.3v-2.4zM17 13v5h1.5v-1.9h1.6v-1.2h-1.6v-.7h1.8V13H17z"/></svg>',
            'name'    => $this->_type . '_' . $this->_name,
        ];

        $options = [
            'height'     => '520px',
            'width'      => '800px',
            'bodyHeight' => '70',
            'modalWidth' => '80',
        ];

        // Joomla 5 and later
        if (class_exists(Button::class)) {
            return new Button($this->_name, $props, $options);
        }

        return $this->legacyButton($props, $options);
    }

    /**
     * Joomla 4 has no Button class and expects a CMSObject.
     */
    public function legacyButton(array $props, array $options): CMSObject
    {
        $button = new CMSObject();

        foreach ($props as $key => $value) {
            $button->set($key, $value);
        }

        $button->set('options', $options);

        return $button;
    }

    /**
     * com_ajax entry point: renders the dialog that is shown in the modal.
     */
    public function onAjaxPdfviewer(EventInterface $event): void
    {
        $html = $this->renderDialog();

        // Joomla 5+ AjaxEvent
        if (method_exists($event, 'addResult')) {
            $event->addResult($html);

            return;
        }

        // Joomla 4 generic event
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
        $input = method_exists($app, 'getInput') ? $app->getInput() : $app->input;

        $displayData = [
            'editor'          => preg_replace('#[^A-Z0-9\-\_\[\]]#i', '', (string) $input->getCmd('e_name', '')),
            'jdownloadsFiles' => $this->getJdownloadsFiles(),
            'defaults'        => $this->getContentDefaults(),
        ];

        $app->getDocument()->getWebAssetManager()->registerAndUseScript(
            self::SCRIPT_ASSET,
            'plg_editors-xtd_pdfviewer/pdfviewer.js',
            // version=auto adds a hash of the file, so browsers fetch a changed script
            ['version' => 'auto'],
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

        if ($user->authorise('core.create', 'com_content')
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

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
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
     * Default viewer and style of the content plugin, which is what a tag
     * without viewer= or style= will use.
     *
     * @return  array{viewer: string, style: string}
     */
    public function getContentDefaults(): array
    {
        $params = new Registry();
        $plugin = PluginHelper::getPlugin('content', 'pdfviewer');

        if (\is_object($plugin) && !empty($plugin->params)) {
            $params = $plugin->params instanceof Registry ? $plugin->params : new Registry($plugin->params);
        }

        $viewer = strtolower((string) $params->get('viewer', 'pdfjs'));
        $style  = strtolower((string) $params->get('style', 'embed'));

        return [
            'viewer' => \in_array($viewer, ['pdfjs', 'pdfimage'], true) ? $viewer : 'pdfjs',
            'style'  => \in_array($style, ['embed', 'popup', 'new'], true) ? $style : 'embed',
        ];
    }
}
