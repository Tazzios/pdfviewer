'use strict';

// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { PATCHES, applyPatches, currentVersion } = require(path.join(__dirname, '../../tools/update-pdfjs.js'));

const pdfjs = path.join(__dirname, '../../content_plugin/assets/pdfjs');

const html = [
  '<button id="secondaryOpenFile" class="toolbarButton labeled" type="button">',
  '<a href="#" id="viewBookmark" class="toolbarButton labeled" tabindex="0">',
].join('\n');

test('hides Open file and Current view in viewer.html', () => {
  const out = applyPatches('web/viewer.html', html);
  assert.match(out, /id="secondaryOpenFile" class="toolbarButton labeled hidden"/);
  assert.match(out, /id="viewBookmark" class="toolbarButton labeled hidden"/);
});

test('patches can be applied twice without changing the result', () => {
  const once = applyPatches('web/viewer.html', html);
  assert.equal(applyPatches('web/viewer.html', once), once);
  const mjs = applyPatches('web/viewer.mjs', 'if (fileOrigin === viewerOrigin) {');
  assert.equal(mjs, 'if (fileOrigin === fileOrigin) {');
  assert.equal(applyPatches('web/viewer.mjs', mjs), mjs);
});

test('fails clearly when PDF.js changed the code', () => {
  assert.throws(() => applyPatches('web/viewer.html', '<div></div>'), /hide Open file button/);
  assert.throws(() => applyPatches('web/viewer.mjs', 'nothing here'), /allow PDF files from other origins/);
});

test('the bundled PDF.js has all patches applied', () => {
  assert.ok(currentVersion(pdfjs), 'version found in build/pdf.mjs');
  for (const file of Object.keys(PATCHES)) {
    const text = fs.readFileSync(path.join(pdfjs, file), 'utf8');
    assert.equal(applyPatches(file, text), text, `${file} is missing a patch, run node tools/update-pdfjs.js --force`);
  }
});
