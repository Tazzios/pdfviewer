#!/usr/bin/env node
'use strict';

/*
 * Updates the bundled PDF.js viewer in content_plugin/assets/pdfjs.
 *
 *   node tools/update-pdfjs.js            -> newest PDF.js release
 *   node tools/update-pdfjs.js 6.5.438    -> a specific release
 *   node tools/update-pdfjs.js --check    -> only print current and newest version
 *
 * It downloads pdfjs-<version>-dist.zip from the PDF.js GitHub releases,
 * replaces the assets/pdfjs folder with its contents and then applies the
 * plugin's own changes (PATCHES below) to web/viewer.html and web/viewer.mjs.
 * If a change can no longer be applied (because PDF.js changed that code),
 * the script stops with an error and leaves the old files in place.
 * Finally it adds "Update: PDFjs to <version>" to the Unreleased section of
 * Changelog.md.
 *
 * Needs Node.js 18 or newer, no npm packages.
 */

const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');

const ROOT = path.resolve(__dirname, '..');
const TARGET = path.join(ROOT, 'content_plugin', 'assets', 'pdfjs');
const RELEASES = 'https://api.github.com/repos/mozilla/pdf.js/releases/latest';
const DOWNLOAD = (v) => `https://github.com/mozilla/pdf.js/releases/download/v${v}/pdfjs-${v}-dist.zip`;

// Adds a CSS class to the element with the given id, if it does not have it yet.
function addClass(id, cls) {
  return (text) => {
    const re = new RegExp(`(<[a-z]+\\b[^>]*\\bid="${id}"[^>]*\\bclass=")([^"]*)(")`);
    const m = text.match(re);
    if (!m) throw new Error(`element with id="${id}" not found`);
    if (m[2].split(/\s+/).includes(cls)) return text;
    return text.replace(re, `$1$2 ${cls}$3`);
  };
}

// Replaces text that must occur exactly once.
function replaceOnce(search, replacement) {
  return (text) => {
    const count = text.split(search).length - 1;
    if (count === 0 && text.includes(replacement)) return text; // already applied
    if (count !== 1) throw new Error(`expected "${search}" once, found it ${count} times`);
    return text.replace(search, () => replacement);
  };
}

// The plugin's own changes to the PDF.js viewer.
const PATCHES = {
  'web/viewer.html': [
    // Hide "Open file" in the secondary toolbar: visitors only see the PDF from the article.
    { name: 'hide Open file button', apply: addClass('secondaryOpenFile', 'hidden') },
    // Hide "Current view" (bookmark link): it points to the viewer, not to the article.
    { name: 'hide Current view link', apply: addClass('viewBookmark', 'hidden') },
  ],
  'web/viewer.mjs': [
    // Allow PDF files from other domains (for example a CDN or a download manager).
    {
      name: 'allow PDF files from other origins',
      apply: replaceOnce('if (fileOrigin === viewerOrigin) {', 'if (fileOrigin === fileOrigin) {'),
    },
  ],
};

function applyPatches(file, text) {
  for (const patch of PATCHES[file] || []) {
    try {
      text = patch.apply(text);
    } catch (e) {
      throw new Error(`${file}: cannot apply "${patch.name}": ${e.message}`);
    }
  }
  return text;
}

// Adds "Update: PDFjs to <version>" to the Unreleased section of the changelog,
// or replaces the PDF.js line that is already there.
function updateChangelog(text, version) {
  const entry = `Update: PDFjs to ${version}  `;
  const eol = text.includes('\r\n') ? '\r\n' : '\n';
  const lines = text.split(/\r?\n/);
  if (!/^unreleased\b/i.test(lines[0] || '')) lines.unshift('Unreleased  ', '');
  let end = lines.findIndex((l, i) => i > 0 && l.trim() === '');
  if (end < 0) end = lines.length;
  const existing = lines.slice(1, end).findIndex((l) => /^Update: PDF\.?js to/i.test(l));
  if (existing >= 0) lines[existing + 1] = entry;
  else lines.splice(end, 0, entry);
  return lines.join(eol);
}

// Version of the PDF.js that is now in assets/pdfjs, or null.
function currentVersion(dir = TARGET) {
  try {
    const head = fs.readFileSync(path.join(dir, 'build', 'pdf.mjs'), 'utf8').slice(0, 4000);
    return (head.match(/pdfjsVersion = ([\d.]+)/) || [])[1] || null;
  } catch {
    return null;
  }
}

async function latestVersion() {
  const headers = { 'User-Agent': 'pdfviewer-update-script', Accept: 'application/vnd.github+json' };
  if (process.env.GITHUB_TOKEN) headers.Authorization = `Bearer ${process.env.GITHUB_TOKEN}`;
  const res = await fetch(RELEASES, { headers });
  if (!res.ok) throw new Error(`cannot read the latest PDF.js release: HTTP ${res.status}`);
  return (await res.json()).tag_name.replace(/^v/, '');
}

async function download(url) {
  const res = await fetch(url, { headers: { 'User-Agent': 'pdfviewer-update-script' } });
  if (!res.ok) throw new Error(`download failed: ${url} (HTTP ${res.status})`);
  return Buffer.from(await res.arrayBuffer());
}

// Minimal zip reader (stored and deflated entries), so no npm packages are needed.
function unzip(buf) {
  let eocd = -1;
  for (let i = buf.length - 22; i >= Math.max(0, buf.length - 65557); i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error('not a zip file');
  const count = buf.readUInt16LE(eocd + 10);
  let p = buf.readUInt32LE(eocd + 16);
  const files = new Map();
  for (let n = 0; n < count; n++) {
    if (buf.readUInt32LE(p) !== 0x02014b50) throw new Error('corrupt zip central directory');
    const method = buf.readUInt16LE(p + 10);
    const size = buf.readUInt32LE(p + 20);
    const nameLen = buf.readUInt16LE(p + 28);
    const extraLen = buf.readUInt16LE(p + 30);
    const commentLen = buf.readUInt16LE(p + 32);
    const local = buf.readUInt32LE(p + 42);
    const name = buf.toString('utf8', p + 46, p + 46 + nameLen);
    p += 46 + nameLen + extraLen + commentLen;
    if (name.endsWith('/')) continue;
    if (name.split(/[\\/]/).some((s) => s === '..') || path.isAbsolute(name)) {
      throw new Error(`unsafe path in zip: ${name}`);
    }
    const start = local + 30 + buf.readUInt16LE(local + 26) + buf.readUInt16LE(local + 28);
    const data = buf.subarray(start, start + size);
    if (method === 0) files.set(name, data);
    else if (method === 8) files.set(name, zlib.inflateRawSync(data));
    else throw new Error(`unsupported zip compression ${method} for ${name}`);
  }
  return files;
}

async function main(args) {
  const current = currentVersion();
  const requested = args.find((a) => !a.startsWith('-'));
  const version = requested ? requested.replace(/^v/, '') : await latestVersion();

  if (args.includes('--check')) {
    console.log(`current=${current || 'none'}`);
    console.log(`latest=${version}`);
    return;
  }
  if (version === current && !args.includes('--force')) {
    console.log(`PDF.js ${current} is already installed (use --force to install it again).`);
    return;
  }

  console.log(`Downloading PDF.js ${version} ...`);
  const files = unzip(await download(DOWNLOAD(version)));
  if (!files.has('web/viewer.html') || !files.has('build/pdf.mjs')) {
    throw new Error('the zip does not look like a PDF.js dist release');
  }

  // Patch in memory first, so nothing is changed when a patch fails.
  for (const file of Object.keys(PATCHES)) {
    const text = applyPatches(file, files.get(file).toString('utf8'));
    files.set(file, Buffer.from(text, 'utf8'));
  }

  fs.rmSync(TARGET, { recursive: true, force: true });
  for (const [name, data] of files) {
    const dest = path.join(TARGET, name);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest, data);
  }
  const changelog = path.join(ROOT, 'Changelog.md');
  if (fs.existsSync(changelog)) {
    fs.writeFileSync(changelog, updateChangelog(fs.readFileSync(changelog, 'utf8'), version));
  }
  console.log(`PDF.js ${current || 'none'} -> ${version}: ${files.size} files written to ${path.relative(ROOT, TARGET)}`);
  for (const [file, patches] of Object.entries(PATCHES)) {
    for (const p of patches) console.log(`  applied to ${file}: ${p.name}`);
  }
  console.log(`  Changelog.md: Update: PDFjs to ${version}`);
}

module.exports = { PATCHES, applyPatches, currentVersion, unzip, updateChangelog };

if (require.main === module) {
  main(process.argv.slice(2)).catch((e) => {
    console.error(`Error: ${e.message}`);
    process.exit(1);
  });
}
