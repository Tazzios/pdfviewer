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

  var BUILTIN = {
    viewer: 'pdfjs',
    style: 'embed',
    sizes: { embed: { width: '100%', height: '800' }, popup: { width: '800', height: '800' } },
  };

  /**
   * Fills missing settings so buildTag always has a button and a content set.
   */
  function normalise(settings) {
    var s = settings || {};
    var content = s.content || BUILTIN;
    return { button: s.button || content, content: content };
  }

  /**
   * Builds the {pdfviewer ...} tag.
   *
   * "default" in the form means the default of this button. A parameter is
   * only written to the tag when it differs from the content plugin setting,
   * because that is what the content plugin uses when it is left out.
   *
   * @param {object} v         Form values: source, jdownloadsid, file, viewer, style,
   *                           width, height, linktext, search, phrase, page, zoom, pagemode
   * @param {object} settings  { button: {...}, content: {...} }, each with viewer, style
   *                           and sizes.embed/popup.width/height
   * @return {string}
   */
  function buildTag(v, settings) {
    var s = normalise(settings);
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
    var viewer = isJd ? (v.viewer === 'default' ? s.button.viewer : v.viewer) : 'pdfjs';
    if (viewer !== s.content.viewer) {
      parts.push('viewer=' + viewer);
    }

    var style = v.style === 'default' ? s.button.style : v.style;
    if (style !== s.content.style) {
      parts.push('style=' + style);
    }

    if (style !== 'new') {
      ['height', 'width'].forEach(function (dimension) {
        var value = size(v[dimension]) || size(s.button.sizes[style][dimension]);
        if (value && value !== s.content.sizes[style][dimension]) {
          parts.push(dimension + '=' + value);
        }
      });
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
    var settings;
    try {
      settings = normalise(JSON.parse(form.dataset.settings || '{}'));
    } catch (e) {
      settings = normalise(null);
    }
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
      var viewer = isJd ? (v.viewer === 'default' ? settings.button.viewer : v.viewer) : 'pdfjs';
      var style = v.style === 'default' ? settings.button.style : v.style;

      // Show the default size of this style as placeholder
      if (style !== 'new') {
        $('width').placeholder = settings.button.sizes[style].width;
        $('height').placeholder = settings.button.sizes[style].height;
      }

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

      $('preview').textContent = buildTag(values(), settings);
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
      insertInEditor(root.parent || root, form.dataset.editor, buildTag(v, settings));
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
