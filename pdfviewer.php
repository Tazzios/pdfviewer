<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Categories\Categories; // needed for retrieving full file pathfor pdfimage

/**
 * Plug-in to enable loading pdf files into content (e.g. articles)
 * This uses the {pdfviewer} syntax
 * Licensed under the GNU General Public License version 2 or later; see LICENSE.txt
 */
class PlgContentpdfviewer extends JPlugin
{
	protected static $modules = array();

	protected static $mods = array();

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
		$regex		= '/{\s*pdfviewer\s*(.*?)}/i';

		// Expression to search for(modules)
		$regexmod	= '/{\s*pdfviewer\s*(.*?)}/i';
		//$stylemod	= $this->params->def('style', 'none');

		// Find all instances of plugin and put in $matches 
		// $matches[0] is full pattern match, $matches[1] is the id
		preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

		// No matches, skip this
		if ($matches) {
					
			foreach ($matches as $match) {
				
				$output= ''; //clear to avoid placing a pdfviewer double if the tag parameter are incorrect after first loop
				
				$matcheslist = explode(',', trim($match[1]));
				
				//Transform  the keys and values from the tag to an array
				
				//Delete space around the = and replace others by , to put then in an array
				$tagparams = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $match[1]); // remove blank 
				$tagparams = str_replace(' =','=', $tagparams); //avoid that key and value are seprated
				
				
				// replace space for , if the text is not between qoutes. Special for the linktext
				$tagparams = preg_replace('~\s+(?=([^"]*"[^"]*")*[^"]*$)~',',', $tagparams); 
				
				// replace existing spaces which should only exist between qoutes for %20. Before output it will be changed back
				$tagparams = str_replace(' ','%20', $tagparams); //replace space for dummy space
																
				// create named array for key and values , key to lower case
				preg_match_all("/([^,= ]+)=([^,= ]+)/", $tagparams, $r); 
				$tagparameters = array_combine($r[1], $r[2]);
				$tagparameters = array_change_key_case($tagparameters, CASE_LOWER); //keys to lower to avoid mismatch
							
				//var_dump( $tagparameters); 
				
				// debug option
				if ( $this->params->get('debug')==1) {
						var_dump($tagparameters);
				}
				
				$showpdfpreview = 'yes';
				if (isset($tagparameters['showpdfpreview'])) {
						$showpdfpreview = strtolower($tagparameters['showpdfpreview']);
				}
				
				// check if filename is given, if it is given it should be pdf.
				if (isset($tagparameters['filename']) ) {
								
					$fileext = explode(".", $tagparameters['filename']);
					$fileext = strtolower(end($fileext));
					$fileext =trim($fileext,'\'"');
					if($fileext <> 'pdf') {
						$showpdfpreview= 'no';							
					}
				}
				
				
				// should we show the preview?
				IF  ($showpdfpreview=='yes') {
					
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
					$pagenumber= '';
					if (isset($tagparameters['page']) and $search =='' and $tagparameters['page']<>0) {
						$pagenumber = $tagparameters['page'];
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
						$linktext =  str_replace('%20',' ', $tagparameters['linktext']); //replace dummy space back to space
						$linktext = trim($linktext,'"'); // any combination of ' and "
					}
					
					
					//determine viewer with a case statement
					$viewer = $this->params->get('viewer');
					if (isset($tagparameters['viewer']) ) {
						$viewer =  $tagparameters['viewer'];
					}
					$viewer = strtolower($viewer); // to lower to avoid mis match
					
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
					
					// get settings from tag if present
					if (isset($tagparameters['height']) ) {
						$height =  $tagparameters['height'];
					}
					if (isset($tagparameters['width']) ) {
						$width =  $tagparameters['width'];
					}
					
					
					$filelink = '' ;
					$jdownloadsid = '';
					// check if there is a jdownloadsid or file tag parameters
					if ( isset($tagparameters['jdownloadsid']) ) {
						$jdownloadsid = $tagparameters['jdownloadsid'];
						$filelink = JUri::base().'index.php?option=com_jdownloads&task=download.send&id='. $jdownloadsid ;
					} elseif ( isset($tagparameters['file']) ) {
						$filelink = $tagparameters['file'];
					}
					
					switch  ($viewer) {
						case "pdfimage":
								
							if ( $jdownloadsid<>'' ) {
								$output = Createpdfimage($jdownloadsid,$pagenumber,$height,$width,$style,$linktext);
							} else {
								$output = 'Only jdownloads pdf files can beconverted to image';
							}
							break;
						/*case "pdfimages":								
							if ( $jdownloadsid<>'' ) {
								
								 multipage
								 https://stackoverflow.com/questions/45720472/converting-a-multi-page-pdf-to-multiple-jpg-images-with-imagick-and-php

								$output = Createpdfimages($jdownloadsid,$pagenumber,$height,$width,$style,$linktext);
							} else {
								$output = 'Only jdownloads pdf files can beconverted to image';

							}
							break;*/
						default:
							// Default pdfjs
							$output = CreatePdfviewer($filelink,$search,$pagenumber,$height,$width,$style,$linktext);
							break;
					}

					//cleanup before next loop
					unset($tagparameters);
				}
				
				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
			
			} // end foreach matches
			
		} // end matches
	} // end onContentPrepare
}// end class

function CreatePdfviewer($filelink,$search,$pagenumber,$height,$width,$style,$linktext) {
	// set Path to pdfjs viewer.html file and check if there is an override
	
	//Set default path
	$Path_pdfjs = JUri::base().'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html' ;
	
	// Get active template path from Joomla: 
	$app    = JFactory::getApplication();
	$path   = JURI::base(true).'templates/'.$app->getTemplate().'/';
	
	// determine override patch
	$pdfjs_override =  JPATH_ROOT  .'/templates/'.$app->getTemplate().   '/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html'; 
	
	//Check for override
	if (file_exists($pdfjs_override)) {
		$Path_pdfjs = JUri::base().'templates/'.$app->getTemplate().  '/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html';
	}
		
		
	// the pdfjs needs encode url
	$filelink = urlencode($filelink);
	
	
	if ($pagenumber<>''){
		$pagenumber = '#page=' . (int) $pagenumber;
	}
	
	
	//PDF viewer embed settings:
	IF ($style=='embed')  {
		
		$height = 'height:'. $height . 'px;' ;
		
		// If width is numeric then px else asume there is a %
		if (is_numeric($width)) {
				$width = 'width:' .$width. 'px;';
		}	else {
			$width = 'width:' .$width. ';';
		}
		return '<iframe src="' . $Path_pdfjs . '?file=' . $filelink . $search . $pagenumber . '" style="'.$width.$height.'" frameborder=0> </iframe>'; 
	}	
	// Popup
	IF ($style=='popup')  {
	
		JHTML::_('behavior.modal');
		
		return '<a class="modal" rel="{handler: \'iframe\', size: {x:'. $width .', y:'. $height .'}}" /*x is width */ href="'. $Path_pdfjs .'?file='. $filelink . $search . $pagenumber .'">'. $linktext .'</a>';
	}
	// New window
	IF ($style=='new')  {
		return	'<a class="pdfviewer_button" target=_blank href="'. $Path_pdfjs .'?file='. $filelink . $search . $pagenumber .'">'. $linktext .'</a>';  
	}

}

function Createpdfimage($file_id,$pagenumber,$height,$width,$style,$linktext) {
	
	// code based on https://www.binarytides.com/convert-pdf-image-imagemagick-php/

	//imagick needs a local path 
	$filelink = '';
	
	// get root dir from jdownloads
	$jdownloads_params = JComponentHelper::getParams( 'com_jdownloads' );
	$root_dir = $jdownloads_params->get( 'root_dir' );

	// get categorie ID and file name
	$db = JFactory::getDbo();
	$query = $db->getQuery(true)
	->select(' cat.id, url_download ')	
	->from('#__jdownloads_files as file')
	->join('INNER',' #__jdownloads_categories as cat ON file.catid=cat.id' )
	->where('file.id = '. $file_id );
	//	->order('ordering ASC');
	$db->setQuery($query);
	
	$fileDB = $db->loadAssocList();
	
	$cat_id = ''; 
	$cat_path = '';
	$filename = '' ;
	foreach ($fileDB as $file) {
		$filename  =  $file['url_download'];
		$cat_id = $file['id'];
	}
	
	//Retrieve categories file path with the help of category ID
	$categories = \Joomla\CMS\Categories\Categories::getInstance('jdownloads');
    $cat        = $categories->get($cat_id);
	
	//Full file link
	$filelink = $root_dir . '/' . $cat->cat_dir_parent . '/'. $cat->title. '/' . $filename;


	// Imagick starts with page 0
	if ($pagenumber <>'') {
		$pagenumber =  (int) $pagenumber-1;
	} else {
		$pagenumber =0;
	}	

	$imgk = new imagick();
	
	//this must be called before reading the image, otherwise has no effect - &quot;-density {$x_resolution}x{$y_resolution}&quot;
	//this is important to give good quality output, otherwise text might be unclear
	$imgk->setResolution(200,200);
	
	//read the pdf
	try {
	$imgk->readImage("{$filelink}[$pagenumber]");
	} catch (Exception $ex) {
		// if an exception occurred
		return 'cannot convert file to image';
	}

	//reduce the dimensions - scaling will lead to black color in transparent regions
	IF ($style=='popup')  {
		$imgk->scaleImage($width-18,0); // -18 to prevent vertical scroll when zoomed in.
	} else {
		$imgk->scaleImage(1000,0); //only testes with a4 pfds
	}

	//set new format
	$imgk->setImageFormat('jpeg');

	// flatten option, this is necessary for images with transparency, it will produce white background for transparent regions
	$img = '' ; 
	$img = $imgk->mergeImageLayers(imagick::LAYERMETHOD_FLATTEN);
	$imgk->clear();

	
	//PDF viewer embed settings:
	IF ($style=='embed')  {
		
		$height = ' height='. $height . 'px;' ;
		
		// If width is numeric then px else asume there is a %
		if (is_numeric($width)) {
				$width = ' width=' . $width . '';
		}	else {
			$width = ' width=' . $width . '%';
		}
		//return 'test'; 
		return '<img src=data:image/jpg;base64,'.base64_encode($img) . $width . $height . ' class=pdfimage_embed_image>'; 
		//return   "<img src=data:image/jpg;base64,".base64_encode($img). ">"; 
	}	
	// Popup
	IF ($style=='popup')  {
	
		JHTML::_('behavior.modal');
		
		return '<a class="modal" rel="{handler: \'iframe\', size: {x:'. $width .', y:'. $height .'}}" /*x is width */ href="data:image/jpg;base64,'.  base64_encode($img) . '">'. $linktext .'</a>';
	}
	// New window
	IF ($style=='new')  {
		return	'<a class="pdfviewer_button" target=_blank href="data:image/jpg;base64,' . base64_encode($img) . '">'. $linktext .'</a>';  
	}

	
}




