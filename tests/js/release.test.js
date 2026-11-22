'use strict';

// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const { addUpdate, releaseChangelog, releaseNotes, setManifestVersion, zip, crc32 } =
  require(path.join(__dirname, '../../tools/release.js'));

const ROOT = path.join(__dirname, '../..');

test('crc32 matches the known value', () => {
  assert.equal(crc32(Buffer.from('123456789')), 0xcbf43926);
});

test('zip can be read back', () => {
  const data = Buffer.from('hello '.repeat(100));
  const z = zip([['a/b.txt', data], ['c.txt', Buffer.from('x')]]);
  assert.equal(z.readUInt32LE(0), 0x04034b50);
  const nameLen = z.readUInt16LE(26);
  const size = z.readUInt32LE(18);
  const body = z.subarray(30 + nameLen, 30 + nameLen + size);
  assert.equal(z.subarray(30, 30 + nameLen).toString(), 'a/b.txt');
  assert.deepEqual(zlib.inflateRawSync(body), data);
  assert.equal(z.readUInt32LE(z.length - 22), 0x06054b50);
  assert.equal(z.readUInt16LE(z.length - 12), 2);
});

test('prepare sets version and date in a manifest', () => {
  const xml = '<version>1.6.0</version>\n<creationDate>September 2026</creationDate>';
  assert.equal(setManifestVersion(xml, '1.6.1', 'November 2026'),
    '<version>1.6.1</version>\n<creationDate>November 2026</creationDate>');
});

test('the Unreleased section becomes the release', () => {
  const log = 'Unreleased  \nFix: a  \n\nV1.6.0 September 2026  \nNew: b  \n';
  const out = releaseChangelog(log, '1.6.1', 'November 2026');
  assert.match(out, /^V1\.6\.1 November 2026 {2}\nFix: a/);
  assert.equal(releaseNotes(out, '1.6.1').split('\n')[0], '- Fix: a');
  assert.doesNotMatch(releaseNotes(out, '1.6.1'), /New: b/);
});

test('a release without changelog entries is refused', () => {
  assert.throws(() => releaseChangelog('Unreleased  \n\nV1.6.0 x\nNew: b\n', '1.6.1', 'x'), /empty/);
  assert.throws(() => releaseChangelog('V1.6.0 x\nNew: b\n', '1.6.1', 'x'), /no "Unreleased"/);
});

test('update-xml keeps old entries and adds the package and both plugins', () => {
  const current = fs.readFileSync(path.join(ROOT, 'updates_plugin.xml'), 'utf8');
  const out = addUpdate(current, '1.6.1', 'ab'.repeat(32));
  for (const v of ['1.3.2', '1.6.0']) assert.match(out, new RegExp(`<version>${v.replace(/\./g, '\\.')}</version>`));
  assert.equal(out.split('<version>1.6.1</version>').length - 1, 3);
  assert.match(out, /<element>pkg_pdfviewer<\/element>\s*<type>package<\/type>/);
  assert.match(out, /<folder>editors-xtd<\/folder>/);
  assert.match(out, /releases\/download\/v1\.6\.1\/pkg_pdfviewer_1\.6\.1\.zip/);
  assert.match(out, /<sha256>(ab){32}<\/sha256>/);
  // Running it again replaces the entries instead of adding them twice.
  assert.equal(addUpdate(out, '1.6.1', 'ab'.repeat(32)), out);
  assert.match(out, /<\/updates>\n$/);
});

test('plugin manifests no longer have their own update server', () => {
  for (const f of ['content_plugin/pdfviewer.xml', 'editor_button/pdfviewer.xml']) {
    assert.doesNotMatch(fs.readFileSync(path.join(ROOT, f), 'utf8'), /updateservers/);
  }
  assert.match(fs.readFileSync(path.join(ROOT, 'package/pkg_pdfviewer.xml'), 'utf8'),
    /main\/updates_plugin\.xml/);
});
