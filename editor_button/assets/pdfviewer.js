/**
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 */
(function() {
	"use strict";

	window.insertPagebreak = function(editor) {


		
		
		//file_link
		var file_link = '';
		var viewer = document.getElementById("viewer").value;
		if (document.getElementById("jdownloadsid_radio").checked ==true ) { 
			//jdownloads file
			file_link = 'jdownloadsid=' + document.getElementById("jdownloadsid").value + ' ';
			if (viewer != 'default'  ) {
				viewer = 'viewer=' + viewer + ' ' ;			
			}  else{
				viewer = '';
			}
		} else { 
			// exernal file
			file_link = 'file=' + document.getElementById("file").value + ' ';
			viewer = ' viewer=pdfjs '; // external pdf can only be shown with pdfjs
		}
	

		//Get form values for next if`s
		var height = document.getElementById("height").value;
		var width = document.getElementById("width").value;
		var style = document.getElementById("style").value;
		
		//height
		if ( style != 'new'  || (style == 'default' && document.getElementById("plugindefault_viewer").value != 'new') ) {
			if (height != '' && !isNaN(height) && height != null){ //height only in number
				 height = 'height=' + height + ' ';
			}
		//width			
			if (width != '' && width != null){ //width can also be in %
				width = 'width=' + document.getElementById("width").value + ' ';
			}
		}
		// Linktext
		var linktext = '';
		if (style != 'embed' && document.getElementById("plugindefault_style").value != 'embed' ) {
			linktext = 'linktext="' + document.getElementById("linktext").value + '" ';
		}
		//style
		if (style != 'default'){
		   style = 'style=' + style + ' ';
		} else {
			style ='';
		}
		
		//search
		var search = '';
		var searchphrase = '';
		var page = '';
		if (document.getElementById("search").value !='') {
			search = 'search="' + document.getElementById("search").value + '" ';
			if (document.getElementById("searchphrase").checked ==true) {
				searchphrase = 'phrase=true ';
			}
		}
		else if (document.getElementById("page").value !='') {
		//page
			var page = 'page=' + document.getElementById("page").value + ' ';
		}
		
		//zoom
		var zoom = '';
		if (document.getElementById("zoom").value != 'default' ) {
			zoom = 'zoom=' + document.getElementById("zoom").value + ' ';
		}
		
		//pagemode
		var pagemode = '';
		if (document.getElementById("pagemode").value != 'default'  ) {
			pagemode = 'pagemode=' + document.getElementById("pagemode").value + ' ';
		}
		
		//build complete tag
		var tag = '{pdfviewer ' + 
			file_link +  
			viewer + 
			style + 
			height + 
			width +
			linktext +			
			search +
			searchphrase +			
			page + 
			zoom + 
			pagemode + 			
			'}';
		
			

		/** Use the API, if editor supports it **/
		if (window.parent.Joomla && window.parent.Joomla.editors && window.parent.Joomla.editors.instances && window.parent.Joomla.editors.instances.hasOwnProperty(editor)) {
			window.parent.Joomla.editors.instances[editor].replaceSelection(tag)
		} else {
			window.parent.jInsertEditorText(tag, editor);
		}

		window.parent.jModalClose();
		return false;
	};
	
	
	
	
	
})();
