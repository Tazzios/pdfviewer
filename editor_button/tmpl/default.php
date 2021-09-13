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

//$document->setTitle('pdf viewer');


// get all published jdownloads files
$db = JFactory::getDbo();
$query = "SELECT id, title FROM #__jdownloads_files WHERE published = 1  ORDER BY publish_up desc";
$db->setQuery($query);
$fields = $db->loadAssocList();

// create an one row array with paramtofind to use for the while check
$dropdown = '';
foreach ($fields as $field) {
	
	$dropdown .= '<option value="' . $field['id'] . '">' . $field['title'] . '</option>';  
}
$dropdown .= '</select>';

?>



<div class="container-popup">
	<form class="form-horizontal">
	
			<input type="radio" id="jdownloadsid_radio" name="filetype" value="jdownloadsid" onchange="filesettings()">
			<label for="jdownloads">Jdownloads</label>
			<input type="radio" id="file_radio" name="filetype" value="file" onchange="filesettings()">
			<label for="file">external pdf</label>
						
		<div id="form_div" style="display: none;">
		<table style="width:100%" >

			<tr>
				<td valign=top>
				
						

						

				
		
						<div id="jdownloadsid_div">
						<label for="jdownloadsid">jDownloadsid</label>
						<select id="jdownloadsid" name="jdownloadsid" onchange="filesettings()">
						<?php
						echo $dropdown;
						?>
						
					<!--	
						<label for="jdownloadsid">jDownloadsid</label>	 <input label="jDownloadsid" type="text" id="jdownloadsid" name="jdownloadsid" min="1" style="width:60px" onchange="filesettings()">
						 -->
						 </div>
						
						<div id="file_div">
						<label for="file">file</label>	 <input label="Link to external pdf" type="text" id="file" name="file" min="1" onchange="filesettings()">
						</div>
						
						<div id="viewer_div">
						<label for="viewer">Viewer</label>	
						<select id="viewer" name="viewer" onchange="viewersettings()">
							  <option value="default">default</option>
							  <option value="pdfjs">pdfjs</option>
							  <option value="pdfimage">pdfimage</option>
							</select>
						<br><br>
						</div>

						
						
						<label for="style">Style</label>
						<select id="style" name="style" onchange="stylesettings()"  >
							  <option value="default">default</option>
							  <option value="embed">embed</option>
							  <option value="popup">popup</option>
							  <option value="new">new</option>
							</select>
						<br><br>
						
						<div id="sizesettings_div" >
						<label for="height">Height</label>	
							<input label="height" type="text" id="height" name="height" min="0" style="width:40px" >
						<br>
						
						<label for="width">Width</label> 
							<input label="width" type="text" id="width" name="width" style="width:40px"  >
						</div>
						

					</td>
					
					<td valign=top>
						<div id="search_div">
							<label for="search">Search</label>		<input label="search" type="text" id="search" name="search" onchange="searchsettings()"  >
						</div>
						<div id="pagenumber_div">
							<label for="pagenumber">Pagenumber</label>	 <input label="page" type="number" id="page" name="page" min="0" style="width:60px" >
							<br><br>
						</div>
						<br><br>
						<div id="linktext_div">
						<label for="Linktext">Linktext</label>	 <input label="linktext" type="text" id="linktext" name="search" >
						</div>
					</td>
			  </tr>


		</table>
		</div>
		
									

	<button onclick="insertPagebreak('<?php echo $this->eName; ?>');" class="btn btn-success pull-right">
		Insert
	</button>

	</form>
</div>

<script>

	function filesettings() {
		var file = document.getElementById("jdownloadsid_radio").checked;
		 

		if (file ==true ) { //jdownloads file
			document.getElementById("file_div").style.display = "none";
			document.getElementById("jdownloadsid_div").style.display = "block";
			document.getElementById("viewer_div").style.display = "block";
					
			document.getElementById("form_div").style.display = "block";
							

		} else { // external file
			document.getElementById("jdownloadsid_div").style.display = "none";
			document.getElementById("file_div").style.display = "block";
			document.getElementById("viewer_div").style.display = "none";
			
			document.getElementById("form_div").style.display = "block";

		}
	
	}


	function viewersettings() {
		var viewer = document.getElementById("viewer").value;
		 

		if (viewer == 'pdfimage' ) {
			document.getElementById("sizesettings_div").style.display = "none";
			//document.getElementById("file_div").style.display = "none";	
			document.getElementById("linktext_div").style.display = "none";
			document.getElementById("search_div").style.display = "none";		

		} else {
			document.getElementById("sizesettings_div").style.display = "block";
			document.getElementById("linktext_div").style.display = "block";
			document.getElementById("search_div").style.display = "block";	
		}
	 
	}


	function stylesettings() {
		var style = document.getElementById("style").value;
		 
		 
		if (style == 'embed' || style == 'popup') {
			document.getElementById("sizesettings_div").style.display = "block";

		} else {
			document.getElementById("sizesettings_div").style.display = "none";
		}
		
		if (style == 'embed' ) {	
			document.getElementById("linktext_div").style.display = "none";

		} else {
			document.getElementById("linktext_div").style.display = "block";
		}	
	 
	}
	
		function searchsettings() {
		var search = document.getElementById("search").value;
		 

		if (search != '' ) {
			document.getElementById("pagenumber_div").style.display = "none";
		

		} else {
			document.getElementById("pagenumber_div").style.display = "block";

		}
		}
	 
	
</script>