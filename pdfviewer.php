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
		$regex		= '/{\s*pdfviewer \s*(.*?)}/i';
		//$regex    = '/{loadposin\s(.*?)}/i';
		//$style		= $this->params->def('style', 'none');

		// Expression to search for(modules)
		$regexmod	= '/{\s*pdfviewer \s*(.*?)}/i';
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
				//$tagparams = str_replace('= ','=', $tagparams); //avoid that key and value are seprated. This one is tricky becasue in jdownloads not every kay gest an value, like search.
				$tagparams = str_replace(' =','=', $tagparams); //avoid that key and value are seprated
				
				//$tagparams = preg_replace('~(?:\G(?!\A)|")[^"\s]*\K(?:\s|"(*SKIP)(*F))~','', $tagparams);
				
				// replace space for , if the text is not between qoutes. Special for the linktext
				$tagparams = preg_replace('~\s+(?=([^"]*"[^"]*")*[^"]*$)~',',', $tagparams); 
				
				// replace existing spaces which should be between qoutes for %20 before output it will be changed back
				$tagparams = str_replace(' ','%20', $tagparams); //replace space for dummy space
				
				//$tagparams = str_replace(' ',',', $tagparams);					
				//$tagparams = preg_replace('!\s+!', '', $tagparams); //remove all spaces

												
				// create named array for key and values , key to lower case
				preg_match_all("/([^,= ]+)=([^,= ]+)/", $tagparams, $r); 
				$tagparameters = array_combine($r[1], $r[2]);
				$tagparameters = array_change_key_case($tagparameters, CASE_LOWER); //keys to lower to avoid mismatch
							
				$output= ''; //clear to avoid placing a pdfviewer double if the tag parameter are incorrect after first loop
				
				// debug option
				if ( $this->params->get('debug')==1) {
						var_dump($tagparameters);
				}
				
				$Showpdfpreview = 'yes';
				if (isset($tagparameters['showpdfpreview'])) {
						$Showpdfpreview = strtolower($tagparameters['showpdfpreview']);
				}
				
			// should we show the preview?
			IF  ($Showpdfpreview=='yes') {
				
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
					$search = str_replace('%20', ' ' ,$tagparameters['search']); //replace dummy space
					$search = trim($search);
					$search = trim($search,'"'); // any combination of ' and "
					$search = '#search=' . $search ;
				}
				

				//Page
				// If there is a search term ignore the goto page
				$Pagenumber= '';
				if (isset($tagparameters['page']) and $search =='' and $tagparameters['page']<>0) {
					$Pagenumber = '#page=' . $tagparameters['page'];
				}
				
				
				//style 
				$style = $this->params->get('style');
				if (isset($tagparameters['style']) ) {
					$style =  $tagparameters['style'];
				}
				$style = strtolower($style); // to lower to avoid mis match
				
				//linktext
				$linktext = $this->params->get('linktext');
				if (isset($tagparameters['linktext']) ) {
					$linktext =  str_replace('%20',' ', $tagparameters['linktext']); //replace dummy space
					$linktext = trim($linktext,'"'); // any combination of ' and "
				}
				
				
				//PDF viewer size settings:
				$height = '' ;
				$width = '';
				
				// set plugin default for embed
				if ($style=='embed') {	
					$height =  $this->params->get('embedheight');
					$width =  $this->params->get('embedwidth');
				}
				
				// set plugin default for popup
				if ($style=='popup') {
					$height =  $this->params->get('popupheight');
					$width =  $this->params->get('popupwidth');
				}
				
				// get settings from tag is present
				if (isset($tagparameters['height']) ) {
					$height =  $tagparameters['height'];
				}
				
				if (isset($tagparameters['width']) ) {
					$width =  $tagparameters['width'];
				}
				
				$filelink = '' ;			
				// check tag parameters jdownloadsid  			
				if ( isset($tagparameters['jdownloadsid']) ) {
						$filelink = JUri::base().'index.php?option=com_jdownloads&task=download.send&id='. $tagparameters['jdownloadsid'] ;
				}
				
				// check if filename is given only logic in jdownloads layouts
				if (isset($tagparameters['filename']) ) {
								
					// get file extension
					$filename = explode(".", $tagparameters['filename']);
					$filename = strtolower(end($filename));
					$filename =trim($filename,'\'"');
					if($filename == 'pdf') {
								
						//Call create viewer function
						$output = CreatePdfviewer($filelink,$search,$Pagenumber,$height,$width,$style,$linktext);
					}
				}
				else { //if filename is not given it is a article tag which place the user by him self so i assume it is a pdf file.
					//Call create viewer function
					$output = CreatePdfviewer($filelink,$search,$Pagenumber,$height,$width,$style,$linktext);
					
				}
					
				//cleanup before next loop
				unset($tagparameters);
			}
				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
			
			}
			//$article->text .= 'x'.$ID;
		}

		
	}


}

function CreatePdfviewer($filelink,$search,$Pagenumber,$height,$width,$style,$linktext) {
	// Path to pdfjs/web/viewer.html from the base of joomla
	$Path_pdfjs = JUri::base().'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html' ;
	
	// the pdfjs needs encode url
	$filelink = urlencode($filelink);
	
	//PDF viewer embed settings:
	IF ($style=='embed')  {
		
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
	IF ($style=='popup')  {
	
		JHTML::_('behavior.modal');
		
		return '<a class="modal" rel="{handler: \'iframe\', size: {x:'. $width .', y:'. $height .'}}" /*x is width */ href="'. $Path_pdfjs .'?file='. $filelink . $search . $Pagenumber .'">'. $linktext .'</a>';
	}
	// New window
	IF ($style=='new')  {
		return	'<a class="pdfviewer_button" target=_blank href="'. $Path_pdfjs .'?file='. $filelink . $search . $Pagenumber .'">'. $linktext .'</a>';  
	}

}


