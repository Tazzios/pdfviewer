<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Version;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;


/**
 * Plug-in to enable loading pdf files into content (e.g. articles)
 * This uses the {pdfviewer} syntax
 * Licensed under the GNU General Public License version 2 or later; see LICENSE.txt
 */

class PlgContentpdfviewer extends CMSPlugin
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
				$tagparams = strip_tags($tagparams); //Remove htmlcode see: https://github.com/Tazzios/pdfviewer/issues/6
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
					
					
					//Get viewer type
					$viewer = $this->params->get('viewer');
					if (isset($tagparameters['viewer']) ) {
						$viewer =  $tagparameters['viewer'];
					}
					$viewer = strtolower($viewer); // to lower to avoid mis match
					
					$pagereference = ''; //value that returns the pageref at the end off this if
					$pdfjsviewsettings = ''; //returns pdfjs viewer settings					
					$pagenumber= '';
					
					// only needed when pdfjs
					if ($viewer=='pdfjs') {
						

					
					// get the parameters from the url if exist
					$search ='';
					$nameddest ='';
					
					
						/*page, search and nameddest order priority
						1 highlight search 
						2 url search
						3 url namedest
						4 url page 
						5 param search
						6 param nameddest
						7 param page					
						*/
							
						
						// 1. Get search from joomla smart search 
						if (isset($_GET["highlight"])) {
							$search= base64_decode(htmlspecialchars($_GET["highlight"]));
							$search= str_replace('[', '' , $search);
							$search= str_replace(']', '' , $search);
							$search= str_replace('"', '' , $search);
							$search= str_replace(',', ' ' , $search);
							$pagereference = '#search=' . $search ;
						}						
						//2. get search from url
						elseif (isset($_GET["search"]) ) {
							$search = $_GET["search"]; 
							$pagereference = '#search=' . $_GET["search"];
						}				
						//3. get nameddest from url
						elseif (isset($_GET["nameddest"]) ) {
							$pagereference= $_GET["nameddest"];
						}			
						//4. get page from url
						elseif (isset($_GET["page"]) ) {
							$pagereference= '#page='.$_GET["page"];
						}	
						//5. get searchterm from tagparameters if not set yet  by url
						elseif (isset($tagparameters['search']) and trim($tagparameters['search'],'"') <>'' ) {
							$search = str_replace('%20', ' ' ,$tagparameters['search']); //replace dummy space
							$search = trim($search);
							$search = trim($search,'"'); // any combination of ' and "
							$pagereference = '#search=' . $search ;
						}												
						//6. tagparameters nameddest
						elseif (isset($tagparameters['nameddest']) ) {
							$pagereference =  '#nameddest='.trim($tagparameters['nameddest']);
						}
						//7.get page from tagparameters if no other page redirect is set
						elseif (isset($tagparameters['page'])  and $tagparameters['page']<>0) {
							$pagereference = '#page='.trim($tagparameters['page']);
						}
					
						// get search phrase or seperate words: true/false(default)
						// only usefull if search was used
						if 	($search<>''){
							$phrase = $this->params->get('phrase');						
							if (isset($_GET["phrase"])  ) {
								$phrase= '&phrase='.$_GET["phrase"];
							}
							elseif (isset($tagparameters['phrase']) ) {
								$phrase =  '&phrase='.trim($tagparameters['phrase']);
							}
							$pagereference .= $phrase;
						}
						
						
						// get zoom url: page-width,page-height,page-fit,auto(default)						
						if (isset($_GET["zoom"])  ) {
							if ($pagereference=='') {
								$pdfjsviewsettings = '#zoom='.$_GET["zoom"];
							}
							else {
								$pdfjsviewsettings = '&zoom='.$_GET["zoom"];
							}
						}
						// get zoom tagparameter: page-width,page-height,page-fit,auto(default)	
						if (isset($tagparameters['zoom']) ) {
							if ($pagereference=='') {
								$pdfjsviewsettings .= '#zoom='.trim($tagparameters['zoom']);
							}
							else {
								$pdfjsviewsettings .= '&zoom='.trim($tagparameters['zoom']);
							}							
						}
												
						// get Pagemode, left sidebar: thumbs,bookmarks,attachments, none (default)
						if (isset($_GET["pagemode"])  ) {
							if ($pagereference=='' and $pdfjsviewsettings=='' ) {
								$pdfjsviewsettings .= '#pagemode='.$_GET["pagemode"];
							}
							else {
								$pdfjsviewsettings .= '&pagemode='.$_GET["pagemode"];
							}								
						}
						elseif (isset($tagparameters['pagemode']) ) {							
							if ($pagereference=='' and $pdfjsviewsettings=='' ) {
								$pdfjsviewsettings .=  '#pagemode='.trim($tagparameters['pagemode']);
							}
							else {
								$pdfjsviewsettings .=  '&pagemode='.trim($tagparameters['pagemode']);
							}
						}
						else{		
							//prevents that a following viewer on the same page grabs the pagemode from the prvious one.
							if ($pagereference=='' and $pdfjsviewsettings=='' ) {
								$pdfjsviewsettings .=  '#pagemode=none';
							}
							else {
								$pdfjsviewsettings .=  '&pagemode=none';
							}
						}				

						
					}
					elseif ($viewer=='pdfimage') {
						//set page to create an image from
						if ( $pagenumber =='' and isset($tagparameters['page'])  and $tagparameters['page']<>0) {
							$pagenumber = trim($tagparameters['page']);
						}
					}
					
										
	
					//style 
					$style = $this->params->get('style');
					if (isset($tagparameters['style']) ) {
						$style =  trim($tagparameters['style']);
					}
					$style = strtolower($style); // to lower to avoid mis match
					
					
					//linktext
					$linktext = $this->params->get('linktext');
					if (isset($tagparameters['linktext']) ) {
						$linktext =  str_replace('%20',' ', $tagparameters['linktext']); //replace dummy space back to space
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
					
					// get settings from tag if present
					if (isset($tagparameters['height']) ) {
						$height =  trim($tagparameters['height']);
					}
					if (isset($tagparameters['width']) ) {
						$width =  trim($tagparameters['width']);
					}
					
					
					$filelink = '' ;
					$jdownloadsid = '';
					// check if there is a jdownloadsid or file tag parameters
					if ( isset($tagparameters['jdownloadsid']) ) {
						$path= JPATH_ROOT . '/administrator/components/com_jdownloads';
						if (file_exists( $path )) {
							$jdownloadsid = trim($tagparameters['jdownloadsid']);
							$filelink = Uri::base().'index.php?option=com_jdownloads&task=download.send&id='. $jdownloadsid ;
						} else {
							$showpdfpreview ='no';
							$output = "jdownloads is not installed (anymore)";
						}
					} elseif ( isset($tagparameters['file']) ) {
						$filelink = trim($tagparameters['file']);
					}
					
					IF  ($showpdfpreview=='yes') {
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
								$output = CreatePdfviewer($filelink,$pagereference,$pdfjsviewsettings,$height,$width,$style,$linktext);
								break;
						}


					}
				}
				
				// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
				$article->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $article->text, 1);
			
				//cleanup before next loop
				unset($tagparameters,$jdownloadsid,$filelink,$search,$pagenumber,$pdfjsviewsettings,$height,$width,$style,$linktext);
			
			} // end foreach matches
			
		} // end matches
	} // end onContentPrepare
}// end class

function CreatePdfviewer($filelink,$pagereference,$pdfjsviewsettings,$height,$width,$style,$linktext) {
	// set Path to pdfjs viewer.html file and check if there is an override
	
	//Set default path
	$Path_pdfjs = Uri::base().'plugins/content/pdfviewer/assets/pdfjs/web/viewer.html' ;
	
	// Get active template path from Joomla: 
	$app    = Factory::getApplication();
	$path   = URI::base(true).'templates/'.$app->getTemplate().'/';
	
	// determine override patch
	$pdfjs_override =  JPATH_ROOT  .'/templates/'.$app->getTemplate().   '/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html'; 
	
	//Check for override
	if (file_exists($pdfjs_override)) {
		$Path_pdfjs = Uri::base().'templates/'.$app->getTemplate().  '/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html';
	}
		
		
	// the pdfjs needs encode url
	$filelink = urlencode($filelink);
	
	
	//PDF viewer embed settings:
	IF ($style=='embed')  {
		
		$height = 'height:'. $height . 'px;' ;
		
		// If width is numeric then px else assume there is a %
		if (is_numeric($width)) {
				$width = 'width:' .$width. 'px;';
		}	else {
			$width = 'width:' .$width. ';';
		}
		return '<iframe src="' . $Path_pdfjs . '?file=' . $filelink . $pagereference . $pdfjsviewsettings . '" style="'.$width.$height.'" frameborder=0> </iframe>'; 
	}	
	// Popup
	IF ($style=='popup')  {
		
		
		if (str_starts_with(JVersion::MAJOR_VERSION, 3)) {
			
				JHTML::_('behavior.modal');		
				return '<a class="modal" rel="{handler: \'iframe\', size: {x:'. $width .', y:'. $height .'}}" /*x is width */ href="'. $Path_pdfjs .'?file='. $filelink . $pagereference . $pdfjsviewsettings .'">'. $linktext .'</a>';

		} else {
		$randomId = rand(0, 1000); // important when there are multiple popup pdfs on one page with different settings

		HTMLHelper::_('bootstrap.modal', '.selector', []);
		
		return '<a data-bs-toggle="modal" data-bs-target="#exampleModal'. (string)$randomId .'" > '. $linktext .' </a>
				<div id="exampleModal'. (string)$randomId .'" class="modal fade" tabindex="-1" >
					<div class="modal-dialog" style="transform: translateX(-50%); left: 0px;" >
						<div class="modal-content" style="height:'.$height.'px;width:'.$width.'px;">
							<div class="modal-header">
								'. $linktext .'
								<button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body" >
								        <iframe
										width="100%"
										height="100%"
										src="'. $Path_pdfjs .'?file='. $filelink . $pagereference . $pdfjsviewsettings .'"
										title="'. $linktext .'"
										frameborder="0"
										allowfullscreen
									></iframe>
							</div>
						</div>
					</div>
				</div>';
		}
	

	}
	// New window
	IF ($style=='new')  {
		return	'<a class="pdfviewer_button" target=_blank href="'. $Path_pdfjs .'?file='. $filelink . $pagereference . $pdfjsviewsettings .'">'. $linktext .'</a>';  
	}

}

function Createpdfimage($file_id,$pagenumber,$height,$width,$style,$linktext) {
	
	// code based on https://www.binarytides.com/convert-pdf-image-imagemagick-php/

	//imagick needs a local path 

	// get root dir from jdownloads
	$jdownloads_params = JComponentHelper::getParams( 'com_jdownloads' );
	$files_uploaddir = $jdownloads_params->get( 'files_uploaddir' );

	// get categorie path
	$db = Factory::getDbo();
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
	
	//Full local file link
	$filelink = '' ;
	foreach ($fileDB as $file) {
		$filelink = $files_uploaddir . $file['path'] . $file['filename'];
	}
	

	// Imagick starts with page 0
	if ($pagenumber <>'') {
		$pagenumber =  (int) $pagenumber-1;
	} else {
		$pagenumber =0;
	}	

	$imgk = new imagick();
	
	//this must be called before reading the image, otherwise has no effect - &quot;-density {$x_resolution}x{$y_resolution}&quot;
	//this is important to give good quality output, otherwise text might be unclear
	$imgk->setResolution(150,150);
	
	//read the pdf
	try {
	$imgk->readImage("{$filelink}[$pagenumber]");
	} catch (Exception $ex) {
		// if an exception occurred
		return 'cannot convert file to image. Make sure  page '. $pagenumber. ' exists. <br> and the filelink is correct: ' . $filelink ;
	}

	//reduce the dimensions - scaling will lead to black color in transparent regions
	IF ($style=='popup')  {
		$imgk->scaleImage($width-40,0); // -40 to prevent vertical scroll when zoomed in.
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
		
		$height = ' height:'. $height . 'px;' ;
		
		// If width is numeric then px else assume there is a %
		if (is_numeric($width)) {
				$width = ' width:' . $width . '';
		}	else {
			$width = ' width:' . $width . '%';
		}
		//return 'test'; 
		return '<img src=data:image/jpg;base64,'.base64_encode($img) . $width . $height . ' class=pdfimage_embed_image>'; 
		//return   "<img src=data:image/jpg;base64,".base64_encode($img). ">"; 
	}	
	// Popup
	IF ($style=='popup')  {
		
	
		if (str_starts_with(JVersion::MAJOR_VERSION, 3)) {
			
			JHTML::_('behavior.modal');
			return '<a class="modal" rel="{handler: \'iframe\', size: {x:'. $width .', y:'. $height .'}}" href="data:image/jpg;base64,'.  base64_encode($img) . '">'. $linktext .'</a>';
			
		}ELSE {
					
			$randomId = rand(0, 1000); // important when there are multiple popup pdfs on one page with different settings
		
			HTMLHelper::_('bootstrap.modal', '.selector', []);
			
			return '<a data-bs-toggle="modal" data-bs-target="#exampleModal'. (string)$randomId .'" > '. $linktext .' </a>
					<div id="exampleModal'. (string)$randomId .'" class="modal fade" tabindex="-1" >
						<div class="modal-dialog" style="transform: translateX(-50%); left: 0px;" >
							<div class="modal-content" style="max-height:'.$height.'px;max-width:'.$width.'px;">
								<div class="modal-header">
									'. $linktext .'
									<button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body" >
									<img src="data:image/jpg;base64,'.  base64_encode($img) . '" >
								</div>
							</div>
						</div>
					</div>';
		}

	}
	// New window
	IF ($style=='new')  {
		return	'<a class="pdfviewer_button" target=_blank href="data:image/jpg;base64,' . base64_encode($img) . '">'. $linktext .'</a>';  
	}

	
}
