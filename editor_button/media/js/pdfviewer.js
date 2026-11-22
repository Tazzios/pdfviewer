/**
 * Dialog of the pdfviewer editor button (Joomla 4, 5 and 6).
 *
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 */
(function (root) {
  'use strict';

  /**
   * Text for a quoted tag value: no quotes or braces, and no commas or "="
   * because the content plugin uses those to split the parameters.
   */
  function quoted(value) {
    return '"' + String(value).replace(/["{}]/g, '').replace(/[,=]/g, ' ').replace(/\s+/g, ' ').trim() + '"';
  }

  function size(value) {
    var v = String(value || '').trim();
    return /^\d+(\.\d+)?(px|%)?$/.test(v) ? v : '';
  }

  /**
   * Builds the {pdfviewer ...} tag.
   *
   * @param {object} v         Form values: source, jdownloadsid, file, viewer, style,
   *                           width, height, linktext, search, phrase, page, zoom, pagemode
   * @param {object} defaults  Defaults of the content plugin: viewer, style
   * @return {string}
   */
  function buildTag(v, defaults) {
    var parts = [];
    var isJd = v.source === 'jdownloads';

    if (isJd) {
      parts.push('jdownloadsid=' + (parseInt(v.jdownloadsid, 10) || 0));
    } else {
      parts.push('file=' + String(v.file || '').trim().replace(/[\s"{}]/g, function (c) {
        return c === ' ' ? '%20' : encodeURIComponent(c);
      }));
    }

    // A link can only be shown with pdf.js
    var viewer = isJd ? (v.viewer === 'default' ? defaults.viewer : v.viewer) : 'pdfjs';
    if (isJd ? v.viewer !== 'default' : defaults.viewer !== 'pdfjs') {
      parts.push('viewer=' + viewer);
    }

    var style = v.style === 'default' ? defaults.style : v.style;
    if (v.style !== 'default') {
      parts.push('style=' + v.style);
    }

    if (style !== 'new') {
      if (size(v.height)) {
        parts.push('height=' + size(v.height));
      }
      if (size(v.width)) {
        parts.push('width=' + size(v.width));
      }
    }

    if (style !== 'embed' && String(v.linktext || '').trim() !== '') {
      parts.push('linktext=' + quoted(v.linktext));
    }

    var search = viewer === 'pdfjs' ? String(v.search || '').trim() : '';
    var page = parseInt(v.page, 10);

    if (search !== '') {
      parts.push('search=' + quoted(search));
      if (v.phrase) {
        parts.push('phrase=true');
      }
    } else if (page > 0) {
      parts.push('page=' + page);
    }

    if (viewer === 'pdfjs') {
      if (v.zoom && v.zoom !== 'default') {
        parts.push('zoom=' + v.zoom);
      }
      if (v.pagemode && v.pagemode !== 'default') {
        parts.push('pagemode=' + v.pagemode);
      }
    }

    return '{pdfviewer ' + parts.join(' ') + '}';
  }

  /**
   * Puts the tag in the editor of the parent window and closes the modal.
   */
  function insertInEditor(parent, editorId, tag) {
    if (parent.JoomlaEditor && typeof parent.JoomlaEditor.get === 'function' && parent.JoomlaEditor.get(editorId)) {
      parent.JoomlaEditor.get(editorId).replaceSelection(tag);
    } else if (parent.Joomla && parent.Joomla.editors && parent.Joomla.editors.instances
      && parent.Joomla.editors.instances[editorId]) {
      parent.Joomla.editors.instances[editorId].replaceSelection(tag);
    } else if (typeof parent.jInsertEditorText === 'function') {
      parent.jInsertEditorText(tag, editorId);
    }

    if (parent.Joomla && parent.Joomla.Modal && typeof parent.Joomla.Modal.getCurrent === 'function'
      && parent.Joomla.Modal.getCurrent()) {
      parent.Joomla.Modal.getCurrent().close();
    } else if (typeof parent.jModalClose === 'function') {
      parent.jModalClose();
    }
  }

  function initDialog(doc) {
    var form = doc.getElementById('pdfviewer-form');
    if (!form) {
      return;
    }

    var $ = function (id) { return doc.getElementById('pdfviewer-' + id); };
    var val = function (id) { return $(id) ? $(id).value : ''; };
    var show = function (id, visible) { if ($(id)) { $(id).hidden = !visible; } };
    var defaults = { viewer: form.dataset.defaultViewer || 'pdfjs', style: form.dataset.defaultStyle || 'embed' };
    var lastTitle = '';

    function values() {
      return {
        source: $('source-jd') && $('source-jd').checked ? 'jdownloads' : 'file',
        jdownloadsid: val('jdownloadsid'),
        file: val('file'),
        viewer: val('viewer'),
        style: val('style'),
        width: val('width'),
        height: val('height'),
        linktext: val('linktext'),
        search: val('search'),
        phrase: $('phrase').checked,
        page: val('page'),
        zoom: val('zoom'),
        pagemode: val('pagemode'),
      };
    }

    function refresh() {
      var v = values();
      var isJd = v.source === 'jdownloads';
      var viewer = isJd ? (v.viewer === 'default' ? defaults.viewer : v.viewer) : 'pdfjs';
      var style = v.style === 'default' ? defaults.style : v.style;

      // Use the jDownloads title as link text, unless the user typed one
      if (isJd && $('jdownloadsid')) {
        var select = $('jdownloadsid');
        var title = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';
        if ($('linktext').value === '' || $('linktext').value === lastTitle) {
          $('linktext').value = title;
        }
        lastTitle = title;
      } else if (lastTitle !== '' && $('linktext').value === lastTitle) {
        // Do not keep the jDownloads title as link text for a PDF link
        $('linktext').value = '';
        lastTitle = '';
      }

      show('jd-group', isJd);
      show('file-group', !isJd);
      show('viewer-group', isJd);
      show('size-group', style !== 'new');
      show('linktext-group', style !== 'embed');
      show('search-group', viewer === 'pdfjs');
      show('phrase-group', v.search.trim() !== '');
      show('page-group', viewer !== 'pdfjs' || v.search.trim() === '');
      show('pdfjs-group', viewer === 'pdfjs');

      $('preview').textContent = buildTag(values(), defaults);
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var v = values();
      if (v.source === 'file' && v.file.trim() === '') {
        $('file').focus();
        return;
      }
      insertInEditor(root.parent || root, form.dataset.editor, buildTag(v, defaults));
    });

    refresh();
  }

  var api = { buildTag: buildTag, insertInEditor: insertInEditor, initDialog: initDialog };

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root && root.document) {
    root.PdfviewerButton = api;
    if (root.document.readyState === 'loading') {
      root.document.addEventListener('DOMContentLoaded', function () { initDialog(root.document); });
    } else {
      initDialog(root.document);
    }
  }
}(typeof window !== 'undefined' ? window : null));
