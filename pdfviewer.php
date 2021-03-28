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
				
				//get searchterm from tagparameters if exist
				if (isset($tagparameters['search']) and $search =='') {
					$search = '#search=' . str_replace('-', ' ' ,$tagparameters['search']);
				}

				// If there is a search term ignore the goto page
				$Pagenumber= '';
				if (isset($tagparameters['page']) and $search =='') {
					$Pagenumber = '#page=' . $tagparameters['page'];
				}
				

				//PDF viewer settings:
				$height = '800' ;
				$height =  $this->params->get('height');
				$width = '100%';
				$width = $this->params->get('width');
				
								
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
							$output = CreatePdfviewer('%22index.php%3Foption%3Dcom_jdownloads%26task%3Ddownload.send%26id%3D' . $tagparameters['jdownloadsid'],$search,$Pagenumber,$height,$width,$this->params->get('Style'));
						} 
					
				}
				// Is it a other pdf file?
				if ( isset($tagparameters['file']) ) {
					
						//check or it is an PDF file
						$ch = curl_init($tagparameters['file']);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_exec($ch);
						# get the content type
						$Filetype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
						// output should be application/pdf			
						if ( $Filetype == 'application/pdf' ){
									
							//replace the file_pdfviewer with the pdfjsviewer
							$output = CreatePdfviewer(/*urlencode*/($tagparameters['file']),$search,$Pagenumber,$height,$width);
						} 
					
				}
			}
				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
			
			}
			//$article->text .= 'x'.$ID;
		}

		
	}


}

function CreatePdfviewer($filelink,$search,$Pagenumber,$height,$width,$style) {
	// Path to pdfjs/web/viewer.html from the base of joomla
	$Path_pdfjs = JUri::base().'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html' ;
	
	//PDF viewer embed settings:
	IF ($style=='Embed')  {
		
		$height = 'height:'. $height . 'px;' ;
		
		// If width is numeric then px else asume there is a %
		if (is_numeric($width)) {
				$width = 'width:' .$width. 'px;';
		}	else {
			$width = 'width:' .$width. ';';
		}
		return '<iframe src="' . $Path_pdfjs . '?file=' . $filelink . $search . $Pagenumber . '" style="'.$width.$height.'" frameborder=0> </iframe>'; 
	}
	// Popup
	IF ($style=='Popup')  {
	
		JHTML::_('behavior.modal');

		return '/*Popup*/ <a class="modal" rel="{handler: \'iframe\', size: {x:' . str_replace('%','',$width) . ', y:' . $height . '}}" /*x is width */ href="' . $Path_pdfjs . '?file=' . $filelink . $search . $Pagenumber . '">open in modal</a>';
	}
	// New window
	IF ($style=='Blank')  {
		return	'/*New windows*/ <a target=_blank href="' . $Path_pdfjs . '?file=' . $filelink . $search . $Pagenumber . '">open in new window</a>';  
	}

}


