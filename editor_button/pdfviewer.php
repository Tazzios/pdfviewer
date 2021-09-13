<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.pagebreak
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Editor Pagebreak button
 *
 * @since  1.5
 */
class  PlgEditorsXtdpdfviewer extends JPlugin
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
	 * @return  JObject  The button options as JObject
	 *
	 * @since   1.5
	 */
	public function onDisplay($name)
	{
		$input = JFactory::getApplication()->input;
		$user  = JFactory::getUser();

		// Can create in any category (component permission) or at least in one category
		$canCreateRecords = $user->authorise('core.create', 'com_content')
			|| count($user->getAuthorisedCategories('com_content', 'core.create')) > 0;

		// Instead of checking edit on all records, we can use **same** check as the form editing view
		$values = (array) JFactory::getApplication()->getUserState('com_content.edit.article.id');
		$isEditingRecords = count($values);

		// This ACL check is probably a double-check (form view already performed checks)
		$hasAccess = $canCreateRecords || $isEditingRecords;
		if (!$hasAccess)
		{
			return;
		}

		JFactory::getDocument()->addScriptOptions('xtd-pdfviewer', array('editor' => $name));
		//$link = 'index.php?option=com_content&amp;view=article&amp;layout=pagebreak2&amp;tmpl=component&amp;e_name=' . $name;
		//$link = '../pagebreak2.php&amp;tmpl=component&amp;e_name=' . $name;
		//$link = 'index.php?option=com_pdfviewer&amp;view=button&amp;layout=pagebreak2&amp;tmpl=component&amp;e_name=' . $name;
		
		//$link = '../plugins/editors-xtd/pdfviewer/pagebreak2.php?tmpl=component&amp;e_name=' . $name;
		
		
		$link = 'index.php?option=com_ajax&amp;plugin=pdfviewer&amp;group=editors-xtd&amp;format=html&amp;tmpl=component&amp;'
    . JSession::getFormToken() . '=1&amp;e_name=' . $name;
		
	
		

		$button          = new JObject;
		$button->modal   = true;
		$button->class   = 'btn';
		$button->link    = $link;
		$button->text    = 'pdf viewer';
		$button->name    = 'copy';
		$button->options = "{handler: 'iframe', size: {x: 600, y: 400}}";

		return $button;
	}
	
	public function onAjaxpdfviewer()
{
    // Renders plugins/editors-xtd/pdfviewer/tmpl/default.php.
    ob_start();
    include JPluginHelper::getLayoutPath($this->_type, $this->_name);
    return ob_get_clean();
}
	
	
}
