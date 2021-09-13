/**
 * @copyright  (C) 2017 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
(function() {
	"use strict";

	window.insertPagebreak = function(editor) {
		/** Get the pagebreak title **/

		var file = document.getElementById("jdownloadsid_radio").checked;
		if (file ==true ) { //jdownloads file
			var jdownloadsid = document.getElementById("jdownloadsid").value;
			var file = '';
			var viewer = document.getElementById("viewer").value;
		} else {
			var jdownloadsid = '';
			var file = document.getElementById("file").value;
			var viewer = 'pdfjs';
		}
		
		
		var style = document.getElementById("style").value;

		if (style =='embed' || style =='popup' ) {
			var height = document.getElementById("height").value;
			var width = document.getElementById("width").value;	
		}
		
		
		var search = document.getElementById("search").value;
		
		if (search =='') {
			var page = document.getElementById("page").value;
		}
		
		if (style =='embed' || style =='new' ) {
			var linktext = document.getElementById("linktext").value;
		}

		
		jdownloadsid  = (!isNaN(jdownloadsid) ) ? 'jdownloadsid=' + jdownloadsid + ' ' : '';
		file  = (file != '' ) ? 'file=' + file + ' ' : '';
		
		viewer    = (viewer != '' && viewer!='default' && viewer != null) ? 'viewer=' + viewer + ' ' : '';		
		style    = (style != '' && style!='default') ? 'style=' + style + ' ' : '';

		height    = (height != '' && !isNaN(height) && height != null) ? 'height=' + height + ' ' : '';
		width    = (width != '' && width != null) ? 'width=' + width + ' ' : ''; // n numeric 80% is also allowed
		page    = (page != '' && !isNaN(page) && page != null) ? 'page=' + page + ' ' : '';
		search    = (search != '' ) ? 'search="' + search + '" ' : '';
		linktext    = (linktext != '' && linktext != null) ? 'linktext="' + linktext + '" ' : '';
		

		var tag = '{pdfviewer ' + 
			jdownloadsid + 
			file + 
			viewer + 
			style + 
			height + 
			width + 
			page + 
			search + 
			linktext + 
			' }';

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
