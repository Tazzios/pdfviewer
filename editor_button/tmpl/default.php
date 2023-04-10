<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   (C) 2010 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JHtml::_('behavior.core');
JHtml::_('behavior.polyfill', array('event'), 'lt IE 9');


$document    = JFactory::getDocument();
$this->eName = JFactory::getApplication()->input->getCmd('e_name', '');
$this->eName = preg_replace('#[^A-Z0-9\-\_\[\]]#i', '', $this->eName);

$document->addScript( JUri::root() .'/plugins/editors-xtd/pdfviewer/assets/pdfviewer.js');


//check if jdownloads is installed
$path= JPATH_ROOT . '/administrator/components/com_jdownloads';
$dropdown = '';
$radiojdownload = '';
$radioexternalpdf = '';
if (file_exists( $path )) {
	// get all published jdownloads files
	$db = JFactory::getDbo();
	$query = "SELECT id, title FROM #__jdownloads_files WHERE published = 1 ORDER BY publish_up desc";
	$db->setQuery($query);
	$fields = $db->loadAssocList();

	// create dropdown with jdownloads files
	$dropdown = '						<div id="jdownloadsid_div">
						<label for="jdownloadsid">jDownloads file</label>
						<select id="jdownloadsid" name="jdownloadsid" onchange="filesettings()">';
	foreach ($fields as $field) {
		$dropdown .= '<option value="' . $field['id'] . '">' . $field['title'] . '</option>';  
	}
	
	$dropdown .= '</select>  </div>';
	$radiojdownload = 'checked';
	
} else {
	$radioexternalpdf = 'checked';
	$radiojdownload = 'disabled' ;
}


// Get plugin 'my_plugin' of plugin type 'my_plugin_type'
$plugin = JPluginHelper::getPlugin('editors-xtd', 'pdfviewer');


// Check if plugin is enabled
if ($plugin)
{
    // Get plugin params
    $pluginParams = new JRegistry($plugin->params);


	//select default in viewer dropdown
	 $plugindefault_viewer = $pluginParams->get('viewer');
/*	$selectpdfjs = '';
	$selectpdfimage = '';	
	switch  ($paramviewer) {
		case "pdfjs":
			$selectpdfjs = 'selected';		
		break;
	
		case "pdfimage":
			$selectpdfimage = 'selected';
		break;
		
		default:
			$selectpdfjs = 'selected';	
		break;

	}
	*/

		

	//set default from config in style dropdown
	$plugindefault_style = $pluginParams->get('style');
/*	$selectembed = '';
	$selectpopup = '';	
	$selectnew = '';

	$setwidth = '';
	$setheight = '';
	
	switch ($paramstyle) {
		case "embed":
			$selectembed = 'selected';
			$setwidth = 'value="'. $pluginParams->get('embedwidth') .'"';
			$setheight = 'value="'. $pluginParams->get('embedheight') .'"';
			break;	
		case "popup":
			$selectpopup = 'selected';
			$setwidth = 'value="'. $pluginParams->get('popupwidth') .'"';
			$setheight = 'value="'. $pluginParams->get('popupheight') .'"';
			break;
	
		case "new":
			$selectnew = 'selected';
			break;		
		default:			
			$selectpopup = 'selected';	
			break;
	}
	*/
		

}
?>

<body onload="filesettings();">

<div class="container-popup">
	<form class="form-horizontal">
			<div class="form-check form-check-inline">
			<input type="radio" id="jdownloadsid_radio" name="filetype" value="jdownloadsid" onchange="filesettings()" <?php echo $radiojdownload; ?>><label for="jdownloads">Jdownloads</label>
			</div>
			<div class="form-check form-check-inline">
			<input type="radio" id="file_radio" name="filetype" value="file" onchange="filesettings()" <?php echo $radioexternalpdf; ?> ><label for="file">external pdf</label>
						
		<div id="form_div" style="display: none;">
			<table style="width:100%" >

				<tr>
					<td valign=top>
					
						<input label="plugindefault_viewer" type="hidden" id="plugindefault_viewer" name="plugindefault_viewer" value="<?php echo $plugindefault_viewer; ?>" >
						<input label="plugindefault_style" type="hidden" id="plugindefault_style" name="plugindefault_style" value="<?php echo $plugindefault_style; ?>"   >
						
						<!--jdownloads dropdown -->
						<?php	echo $dropdown;	?>			
						
						<div id="file_div" title="Insert full link (https://) or relative link (/)">
						<label for="file">url file link</label>	 <input label="Link to external pdf" type="text" id="file" name="file" min="1" onchange="filesettings()">
						</div>
						
						<div id="viewer_div">
						<label for="viewer">Viewer</label>	
						<select id="viewer" name="viewer" onchange="viewersettings()">
							  <option value="default" selected >default (<?php echo $plugindefault_viewer; ?>)</option>
							  <option value="pdfjs" >pdfjs</option>
							  <option value="pdfimage" >pdfimage</option>
							</select>
						<br><br>
						</div>
						
						<label for="style">Display Style</label>
						<select id="style" name="style" onchange="stylesettings()"  >
							<option value="default" selected >default (<?php echo $plugindefault_style; ?>)</option>
							<option value="embed" >embed</option>
							<option value="popup" >popup</option>
							<option value="new" >new</option>
						</select>
						<br><br>
						
						<div id="pdfjssettings_div">
							<b>Advanced PDF.js options</b> <br>
							<label for="zoom">Page zoom</label>
							<select id="zoom" name="zoom"   >
								<option value="default" >default (auto)</option>
								<option value="auto-fit" >auto</option>
								<option value="page-width" >fit width</option>
								<option value="page-height" >fit height</option>
								<option value="page-fit" >fit page</option>
							</select>
							
							<label for="pagemode">Sidebar pagemode</label>
							<select id="pagemode" name="pagemode"   >
								<option value="default" >default (None)</option>
								<option value="none" >none</option>
								<option value="bookmarks" >bookmarks</option>
								<option value="thumbs" >thumbs</option>
								<option value="attachments" >attachments</option>
							</select>	  
						</div>
						
						<div id="sizesettings_div" >				
							<label for="width">Width</label> 
							<input label="width" type="text" id="width" name="width" style="width:40px" > <div id="width_info" > </div>
							<br>
							<label for="height">Height</label>	
							<input label="height" type="text" id="height" name="height" min="0" style="width:40px" >	<div id="height_info" > </div>						
						</div>

					</td>
						
					<td valign=top>
						<div id="search_div">
							<label for="search">Search</label>		
							<input label="search" type="text" id="search" name="search" onchange="searchsettings()"  >
						</div>
						<div id="searchphrase_div">
							<label for="searchphrase">phrase</label>		
							<input label="searchphrase" type="checkbox" id="searchphrase" name="searchphrase" >
						</div>
						<div id="pagenumber_div">
							<label for="pagenumber">Pagenumber</label>	 
							<input label="page" type="number" id="page" name="page" min="0" style="width:60px" >
						</div>
						<div id="linktext_div">
							<br>
							<label for="Linktext">Linktext</label>	
							<input label="linktext" type="text" id="linktext" name="search" >
						</div>
						
					</td>
				</tr>

			</table>
		</div>					

	<button onclick="insertPagebreak('<?php echo $this->eName; ?>');" class="btn btn-success pull-right">Insert</button>

	</form>
</div>

</body>

<script>



	function filesettings() {
		var file = document.getElementById("jdownloadsid_radio").checked;
		
		if(!document.getElementById("jdownloadsid_div")){
			document.getElementById("jdownloadsid_radio").disabled=true;
		}
		


		if (file ==true ) { //jdownloads file
			document.getElementById("file_div").style.display = "none";
			if(document.getElementById("jdownloadsid_div")){
				document.getElementById("jdownloadsid_div").style.display = "block";
			}
			document.getElementById("viewer_div").style.display = "block";
					
			document.getElementById("form_div").style.display = "block";
			
			// set filename as default linktext		
			var select = document.getElementById('jdownloadsid');
			var value = select.options[select.selectedIndex].text;			
			document.getElementById("linktext").value = value;
							

		} else { // external file
			
			if(document.getElementById("jdownloadsid_div")){
				document.getElementById("jdownloadsid_div").style.display = "none";
			}
			
			document.getElementById("file_div").style.display = "block";
			document.getElementById("viewer_div").style.display = "none";
			
			document.getElementById("form_div").style.display = "block";
			
			document.getElementById("linktext").value = "";

		}
		
		stylesettings()
	
	}


	function viewersettings() {
		var viewer = document.getElementById("viewer").value;
		 

		if (viewer == 'pdfimage' ) {
			document.getElementById("sizesettings_div").style.display = "none";
			//document.getElementById("file_div").style.display = "none";	
			//document.getElementById("linktext_div").style.display = "none";
			document.getElementById("search_div").style.display = "none";
			document.getElementById("searchphrase_div").style.display = "none";

			document.getElementById("pdfjssettings_div").style.display = "none";	


		} else {
			document.getElementById("sizesettings_div").style.display = "block";
			//document.getElementById("linktext_div").style.display = "block";
			document.getElementById("search_div").style.display = "block";
			document.getElementById("searchphrase_div").style.display = "block";

			document.getElementById("pdfjssettings_div").style.display = "block";	
			
		}
	 
	}


	function stylesettings() {
		
		// options depending on style
		var style = document.getElementById("style").value;
		
		// reset width and height after change
		document.getElementById("width").value = ""; 
		document.getElementById("height").value = ""; 
		 
		// width and height settings shown when embed or popup.
		if (style == 'embed' || style == 'popup' || (style == 'default' && document.getElementById("plugindefault_style").value!='embed') ) {
			document.getElementById("sizesettings_div").style.display = "block";	
		} else {
			document.getElementById("sizesettings_div").style.display = "none";
		}
		
		// link text option not shown when style is embed
		if (style == 'embed' || (style == 'default' && document.getElementById("plugindefault_style").value)=='embed') {	
			document.getElementById("linktext_div").style.display = "none";

		} else {
			document.getElementById("linktext_div").style.display = "block";
		}	
	 
	}
	
	function searchsettings() {
		var search = document.getElementById("search").value;
		 

		if (search != '' ) {
			document.getElementById("pagenumber_div").style.display = "none";
			document.getElementById("searchphrase_div").style.display = "block";
		

		} else {
			document.getElementById("pagenumber_div").style.display = "block";
			document.getElementById("searchphrase_div").style.display = "none";

		}
	}
	 
	
</script>