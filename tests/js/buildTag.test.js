'use strict';

// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { buildTag, insertInEditor } = require(path.join(__dirname, '../../editor_button/media/js/pdfviewer.js'));

const sizes = (ew, eh, pw, ph) => ({ embed: { width: ew, height: eh }, popup: { width: pw, height: ph } });
const plain = { viewer: 'pdfjs', style: 'embed', sizes: sizes('100%', '800', '800', '800') };
// Button and content plugin with the same (shipped) defaults
const defaults = { button: plain, content: plain };
const form = (values) => Object.assign({
  source: 'file', jdownloadsid: '', file: '', viewer: 'default', style: 'default',
  width: '', height: '', linktext: '', search: '', phrase: false, page: '',
  zoom: 'default', pagemode: 'default',
}, values);

test('link with defaults only', () => {
  assert.equal(buildTag(form({ file: '/images/a.pdf' }), defaults), '{pdfviewer file=/images/a.pdf}');
});

test('works without settings', () => {
  assert.equal(buildTag(form({ file: '/a.pdf' })), '{pdfviewer file=/a.pdf}');
});

test('spaces in a link become %20', () => {
  assert.equal(buildTag(form({ file: ' /images/my file.pdf ' }), defaults), '{pdfviewer file=/images/my%20file.pdf}');
});

test('link gets viewer=pdfjs when the content plugin uses pdfimage', () => {
  const content = { ...plain, viewer: 'pdfimage' };
  assert.equal(buildTag(form({ file: '/a.pdf' }), { button: content, content }), '{pdfviewer file=/a.pdf viewer=pdfjs}');
});

test('button defaults are written when they differ from the content plugin', () => {
  const button = { viewer: 'pdfimage', style: 'popup', sizes: sizes('100%', '800', '90%', '600') };
  const tag = buildTag(form({ source: 'jdownloads', jdownloadsid: '4', linktext: 'Read' }), { button, content: plain });
  assert.equal(tag, '{pdfviewer jdownloadsid=4 viewer=pdfimage style=popup height=600 width=90% linktext="Read"}');
});

test('button size is used for the chosen style', () => {
  const button = { ...plain, sizes: sizes('75%', '500', '800', '800') };
  assert.equal(buildTag(form({ file: '/a.pdf' }), { button, content: plain }), '{pdfviewer file=/a.pdf height=500 width=75%}');
  assert.equal(buildTag(form({ file: '/a.pdf', style: 'popup' }), { button, content: plain }), '{pdfviewer file=/a.pdf style=popup}');
});

test('values equal to the content plugin are left out', () => {
  const content = { viewer: 'pdfjs', style: 'popup', sizes: sizes('100%', '800', '900', '700') };
  const tag = buildTag(form({ file: '/a.pdf', style: 'popup', width: '900', height: '700' }), { button: plain, content });
  assert.equal(tag, '{pdfviewer file=/a.pdf}');
});

test('jdownloads file with every pdf.js option', () => {
  const tag = buildTag(form({
    source: 'jdownloads', jdownloadsid: '12', viewer: 'pdfjs', style: 'popup', width: '100%', height: '600',
    linktext: 'Read it', search: 'annual report', phrase: true, page: '4', zoom: 'page-width', pagemode: 'thumbs',
  }), defaults);
  assert.equal(tag, '{pdfviewer jdownloadsid=12 style=popup height=600 width=100% linktext="Read it" search="annual report" phrase=true zoom=page-width pagemode=thumbs}');
});

test('page is used when there is no search', () => {
  assert.equal(buildTag(form({ file: '/a.pdf', page: '3' }), defaults), '{pdfviewer file=/a.pdf page=3}');
});

test('embed has no link text, new window has no size', () => {
  assert.equal(buildTag(form({ file: '/a.pdf', linktext: 'x', height: '500' }), defaults), '{pdfviewer file=/a.pdf height=500}');
  assert.equal(
    buildTag(form({ file: '/a.pdf', style: 'new', linktext: 'Open', height: '500', width: '50%' }), defaults),
    '{pdfviewer file=/a.pdf style=new linktext="Open"}',
  );
});

test('pdfimage skips pdf.js only options', () => {
  const tag = buildTag(form({
    source: 'jdownloads', jdownloadsid: '5', viewer: 'pdfimage', search: 'x', page: '2', zoom: 'auto', pagemode: 'thumbs',
  }), defaults);
  assert.equal(tag, '{pdfviewer jdownloadsid=5 viewer=pdfimage page=2}');
});

test('quotes, braces, commas and = cannot break the tag', () => {
  const tag = buildTag(form({ file: '/a.pdf', style: 'popup', linktext: 'a "b" {c},d=e', search: 'x"}' }), defaults);
  assert.equal(tag, '{pdfviewer file=/a.pdf style=popup linktext="a b c d e" search="x"}');
});

test('invalid size and id are dropped', () => {
  assert.equal(buildTag(form({ file: '/a.pdf', width: '10;x', height: 'abc' }), defaults), '{pdfviewer file=/a.pdf}');
  assert.equal(buildTag(form({ source: 'jdownloads', jdownloadsid: '1 OR 1' }), defaults), '{pdfviewer jdownloadsid=1}');
});

test('insert uses the Joomla editor API and closes the modal', () => {
  const calls = [];
  const parent = {
    Joomla: {
      editors: { instances: { jform_articletext: { replaceSelection: (t) => calls.push(['insert', t]) } } },
      Modal: { getCurrent: () => ({ close: () => calls.push(['close']) }) },
    },
  };
  insertInEditor(parent, 'jform_articletext', '{pdfviewer file=/a.pdf}');
  assert.deepEqual(calls, [['insert', '{pdfviewer file=/a.pdf}'], ['close']]);
});

test('insert prefers the Joomla 5 JoomlaEditor API', () => {
  const calls = [];
  const parent = {
    JoomlaEditor: { get: (id) => (id === 'ed' ? { replaceSelection: (t) => calls.push(t) } : null) },
    Joomla: { editors: { instances: { ed: { replaceSelection: () => calls.push('old') } } } },
  };
  insertInEditor(parent, 'ed', 'tag');
  assert.deepEqual(calls, ['tag']);
});

test('modal is sized to the form, at most 90% of the window', () => {
  const { fitToContent } = require(path.join(__dirname, '../../editor_button/media/js/pdfviewer.js'));
  const rect = (height, bottom) => () => ({ height, bottom });
  const box = { style: {}, getBoundingClientRect: rect(680) };
  const frame = { closest: (sel) => (sel === 'dialog' ? box : null), getBoundingClientRect: rect(620) };
  const doc = { querySelector: () => ({ getBoundingClientRect: rect(0, 500) }) };
  const win = { frameElement: frame, pageYOffset: 0, parent: { innerHeight: 1000 } };

  fitToContent(win, doc);
  assert.equal(box.style.height, '580px'); // 500 + 20 + 60 header

  win.parent.innerHeight = 400;
  fitToContent(win, doc);
  assert.equal(box.style.height, '360px');
});

test('fitting does nothing outside a modal', () => {
  const { fitToContent } = require(path.join(__dirname, '../../editor_button/media/js/pdfviewer.js'));
  assert.doesNotThrow(() => fitToContent({ frameElement: null }, { querySelector: () => null }));
});
