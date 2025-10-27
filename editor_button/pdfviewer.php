<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;

/**
 * Editor pdfviewer button plugin
 */
class PlgEditorsXtdPdfviewer extends CMSPlugin
{
    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Display the button
     *
     * @param   string  $name  The name of the button to add
     *
     * @return  object  The button options as an object
     *
     * @since   1.5
     */
    public function onDisplay($name)
    {
        $app   = Factory::getApplication();
        $user  = Factory::getUser();

        // Can create in any category (component permission) or at least in one category
        $canCreateRecords = $user->authorise('core.create', 'com_content')
            || count($user->getAuthorisedCategories('com_content', 'core.create')) > 0;

        // Instead of checking edit on all records, we can use **same** check as the form editing view
        $values = (array) $app->getUserState('com_content.edit.article.id');
        $isEditingRecords = count($values);

        // ACL check
        $hasAccess = $canCreateRecords || $isEditingRecords;
        if (!$hasAccess)
        {
            return;
        }

        // AJAX entry to render the layout
        $link = 'index.php?option=com_ajax&plugin=pdfviewer&group=editors-xtd&format=html&tmpl=component&'
            . Session::getFormToken() . '=1&e_name=' . rawurlencode($name);

        $button = new \stdClass;
        $button->modal   = true;
        $button->class   = 'btn';
        $button->link    = $link;
        $button->text    = 'pdf viewer';
        $button->name    = 'copy';
        $button->options = "{handler: 'iframe', size: {x: 600, y: 600}}";

        return $button;
    }

    /**
     * AJAX plugin entry - renders tmpl/default.php
     *
     * com_ajax will trigger onAjaxPdfviewer (plugin name is pdfviewer)
     *
     * @return  string  HTML
     */
    public function onAjaxPdfviewer()
    {
        // Renders plugins/editors-xtd/pdfviewer/tmpl/default.php
        ob_start();
        include PluginHelper::getLayoutPath($this->_type, $this->_name);
        return ob_get_clean();
    }
}