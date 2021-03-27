<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.loadmodule
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Plug-in to enable loading modules into content (e.g. articles)
 * This uses the {loadmodule} syntax
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.loadmodule
 * @since       1.5
 */
class PlgContentpdfviewer extends JPlugin
{
	protected static $modules = array();

	protected static $mods = array();

	/**
	 * Plugin that loads module positions within content
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   mixed    &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return  mixed   true if there is an error. Void otherwise.
	 *
	 * @since   1.6
	 */
	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		// Don't run this plugin when the content is being indexed
		if ($context == 'com_finder.indexer')
		{
			return true;
		}
		
		// Simple performance check to determine whether bot should process further
		if (strpos($article->text, 'loadposition') === false && strpos($article->text, 'pdfviewer') === false)
		{
			return true;
		}

		// Expression to search for (positions)
		$regex		= '/{pdfviewer\s*(.*?)}/i';
		//$regex    = '/{loadposin\s(.*?)}/i';
		//$style		= $this->params->def('style', 'none');

		// Expression to search for(modules)
		$regexmod	= '/{pdfviewer\s*(.*?)}/i';
		//$stylemod	= $this->params->def('style', 'none');

		// Find all instances of plugin and put in $matches for loadposition
		// $matches[0] is full pattern match, $matches[1] is the id
		preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

		// No matches, skip this
		if ($matches)
		{
					
			foreach ($matches as $match)
			{
				$matcheslist = explode(',', trim($match[1]));
				
				//Transform  the keys and values from the tag to an array
				
				//Delete space around the = and replace others by , to put then in an array
				// there are still scenarios where it goes wrong
				$tagparams = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $match[1]); // remove blank 
				$tagparams = str_replace('= ','=', $tagparams); //avoid that key and value are seprated
				$tagparams = str_replace(' =','=', $tagparams); //avoid that key and value are seprated
				$tagparams = str_replace(' ',',', $tagparams);					
				$tagparams = preg_replace('!\s+!', '', $tagparams); //remove all spaces
								
				// create named array for key and values
				preg_match_all("/([^,= ]+)=([^,= ]+)/", $tagparams, $r); 
				$tagparameters = array_combine($r[1], $r[2]);
				
			$output= ''; //clear to avoid placing a pdfviewer double if the tag parameter are incorrect
			
			$Showpdfpreview = 'Yes';
			if (isset($tagparameters['showpdfpreview'])) {
					$Showpdfpreview = $tagparameters['showpdfpreview'];
			}
			
			// should we show the preview?
			iF  ($Showpdfpreview=='Yes') {
				
				// get the smartsearch from the url if exist
				$search ='';
				if (isset($_GET["highlight"])) {
					$search= base64_decode(htmlspecialchars($_GET["highlight"]));
					$search= str_replace('[', '' , $search);
					$search= str_replace(']', '' , $search);
					$search= str_replace('"', '' , $search);
					$search= str_replace(',', ' ' , $search);
					$search = '#search=' . $search ;
				}

				// If there is a search term ignore the goto page
				$Pagenumber= '';
				if (isset($tagparameters['page']) and $search =='') {
					$Pagenumber = '#page=' . $tagparameters['page'];
				}
				
				//PDF viewer settings:
				$height = 'height:800px;' ;
				$height = 'height:'. $this->params->get('height') . 'px;' ;
				
				// If width is numeric then px else asume there is a %
				$width = '100%';
				$width = $this->params->get('width');
				
				if (is_numeric($width)) {
						$width = 'width:' .$width. 'px;';
				}	else {
					$width = 'width:' .$width. ';';
				}
				
				
				// check tag parameters jdownloadsid  			
				if ( isset($tagparameters['jdownloadsid']) ) {
					
						//check or it is an PDF file
						$ch = curl_init(JUri::base().'index.php?option=com_jdownloads&task=download.send&id='. $tagparameters['jdownloadsid']);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_exec($ch);
						# get the content type
						$Filetype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
						// output should be application/pdf			
						if ( $Filetype == 'application/pdf' ){
									
							//replace the file_pdfviewer with the pdfjsviewer
							$output = CreatePdfviewer('%22index.php%3Foption%3Dcom_jdownloads%26task%3Ddownload.send%26id%3D' . $tagparameters['jdownloadsid'],$search,$Pagenumber,$height,$width);
						} 
						
					
				}
			}
				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
			

			
			}
			//$article->text .= 'x'.$ID;
		}

		// Find all instances of plugin and put in $matchesmod for loadmodule
		/*preg_match_all($regexmod, $article->text, $matchesmod, PREG_SET_ORDER);

		// If no matches, skip this
		if ($matchesmod)
		{
			foreach ($matchesmod as $matchmod)
			{
				$matchesmodlist = explode(',', $matchmod[1]);

				// We may not have a specific module so set to null
				if (!array_key_exists(1, $matchesmodlist))
				{
					$matchesmodlist[1] = null;
				}


				$module = trim($matchesmodlist[0]);
				$name   = htmlspecialchars_decode(trim($matchesmodlist[1]));

				// $match[0] is full pattern match, $match[1] is the module,$match[2] is the title
				$output = $this->_loadmod($module, $name, $stylemod);

				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$matchmod[0]|", addcslashes($output, '\\$'), $article->text, 1);

			}
			
		}*/
		
	}
	

	

	/**
	 * Loads and renders the module
	 *
	 * @param   string  $position  The position assigned to the module
	 * @param   string  $style     The style assigned to the module
	 *
	 * @return  mixed
	 *
	 * @since   1.6
	 */
	protected function _load($position, $style = 'none')
	{
		self::$modules[$position] = '';
		$document	= JFactory::getDocument();
		$renderer	= $document->loadRenderer('module');
		$modules	= JModuleHelper::getModules($position);
		$params		= array('style' => $style);
		ob_start();

		foreach ($modules as $module)
		{
			echo $renderer->render($module, $params);
		}

		self::$modules[$position] = ob_get_clean();

		return self::$modules[$position];
	}

	/**
	 * This is always going to get the first instance of the module type unless
	 * there is a title.
	 *
	 * @param   string  $module  The module title
	 * @param   string  $title   The title of the module
	 * @param   string  $style   The style of the module
	 *
	 * @return  mixed
	 *
	 * @since   1.6
	 */
	protected function _loadmod($module, $title, $style = 'none')
	{
		self::$mods[$module] = '';
		$document	= JFactory::getDocument();
		$renderer	= $document->loadRenderer('module');
		$mod		= JModuleHelper::getModule($module, $title);

		// If the module without the mod_ isn't found, try it with mod_.
		// This allows people to enter it either way in the content
		if (!isset($mod))
		{
			$name = 'mod_'.$module;
			$mod  = JModuleHelper::getModule($name, $title);
		}

		$params = array('style' => $style);
		ob_start();

		echo $renderer->render($mod, $params);

		self::$mods[$module] = ob_get_clean();

		return self::$mods[$module];
	}
}

function CreatePdfviewer($filelink,$search,$Pagenumber,$height,$width) {
	// Path to pdfjs/web/viewer.html from the base of joomla
	$Path_pdfjs = JUri::base().'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html' ;


	return '<iframe src="' . $Path_pdfjs . '?file=%22index.php%3Foption%3Dcom_jdownloads%26task%3Ddownload.send%26id%3D' . $filelink . $search . $Pagenumber . '" style="'.$width.$height.'" frameborder=0> </iframe><a href="javascript:void(0);" data-href="getContent.php?id=1" class="openPopup">About Us</a>

<!-- Modal -->
<div class="modal fade" id="myModal" role="dialog">
    <div class="modal-dialog">
    
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Bootstrap Modal with Dynamic Content</h4>
            </div>
            <div class="modal-body">

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
      
    </div>
</div>';  
	// ***** the style parameters should be configurable at the backend 
}
