#!/usr/bin/env node
'use strict';

/*
 * Release helper for the pdfviewer package (content plugin + editor button).
 *
 *   node tools/release.js prepare 1.6.1
 *       Sets the version and creation date in the three manifests and turns the
 *       "Unreleased" section of Changelog.md into "V1.6.1 <Month> <Year>".
 *
 *   node tools/release.js build
 *       Builds dist/pkg_pdfviewer_<version>.zip: one Joomla package with the
 *       content plugin and the editor button inside.
 *
 *   node tools/release.js notes 1.6.1
 *       Prints the release notes for 1.6.1 (its section of Changelog.md).
 *
 *   node tools/release.js update-xml 1.6.1 [--sha256 <hash>]
 *       Offers 1.6.1 to existing installs: adds it to updates_plugin.xml and
 *       updates_button.xml, and adds its changes (from Changelog.md) to
 *       changelog.xml, which Joomla shows behind the Changelog button in the
 *       update list. Do this only after the GitHub release exists.
 *
 * Needs Node.js 18 or newer, no npm packages.
 */

const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');

const ROOT = path.resolve(__dirname, '..');
const REPO = 'Tazzios/pdfviewer';
const MANIFESTS = {
  content: 'content_plugin/pdfviewer.xml',
  button: 'editor_button/pdfviewer.xml',
  package: 'package/pkg_pdfviewer.xml',
};
const CHANGELOG_URL = `https://raw.githubusercontent.com/${REPO}/main/changelog.xml`;
// Joomla versions that are offered the update (regular expression on the Joomla version).
const TARGET_PLATFORM = '(5|6)\\..*';
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
  'August', 'September', 'October', 'November', 'December'];

const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const write = (file, text) => fs.writeFileSync(path.join(ROOT, file), text);

function checkVersion(version) {
  if (!/^\d+\.\d+\.\d+$/.test(version || '')) {
    throw new Error(`"${version}" is not a version like 1.6.1`);
  }
  return version;
}

const packageZipName = (version) => `pkg_pdfviewer_${version}.zip`;
const downloadUrl = (version) =>
  `https://github.com/${REPO}/releases/download/v${version}/${packageZipName(version)}`;

/* ---------- prepare ---------- */

function manifestVersion(xml) {
  const m = xml.match(/<version>([^<]*)<\/version>/);
  if (!m) throw new Error('manifest has no <version>');
  return m[1].trim();
}

function setManifestVersion(xml, version, date) {
  return xml
    .replace(/<version>[^<]*<\/version>/, `<version>${version}</version>`)
    .replace(/<creationDate>[^<]*<\/creationDate>/, `<creationDate>${date}</creationDate>`);
}

// Turns the "Unreleased" heading into "V<version> <date>". Fails when there is
// no Unreleased section or it is empty, so a release always has notes.
function releaseChangelog(text, version, date) {
  const lines = text.split(/\r?\n/);
  const i = lines.findIndex((l) => /^unreleased\s*$/i.test(l.trim()));
  if (i === -1) throw new Error('Changelog.md has no "Unreleased" section');
  const body = sectionLines(lines, i);
  if (!body.some((l) => l.trim())) throw new Error('the Unreleased section of Changelog.md is empty');
  lines[i] = `V${version} ${date}  `;
  return lines.join('\n');
}

// Lines below heading i, up to the next version heading.
function sectionLines(lines, i) {
  const out = [];
  for (let j = i + 1; j < lines.length; j++) {
    if (/^(v\d+\.\d|unreleased\s*$)/i.test(lines[j].trim())) break;
    out.push(lines[j]);
  }
  return out;
}

function prepare(version, now = new Date()) {
  checkVersion(version);
  const date = `${MONTHS[now.getUTCMonth()]} ${now.getUTCFullYear()}`;
  for (const file of Object.values(MANIFESTS)) {
    write(file, setManifestVersion(read(file), version, date));
  }
  write('Changelog.md', releaseChangelog(read('Changelog.md'), version, date));
  console.log(`Version ${version} (${date}) set in ${Object.values(MANIFESTS).join(', ')} and Changelog.md`);
}

/* ---------- notes ---------- */

// The lines of the Changelog.md section for this version, trimmed.
function sectionBody(text, version) {
  const lines = text.split(/\r?\n/);
  const re = new RegExp(`^v${version.replace(/\./g, '\\.')}(\\s|$)`, 'i');
  const i = lines.findIndex((l) => re.test(l.trim()));
  if (i === -1) throw new Error(`Changelog.md has no section for V${version}`);
  const body = sectionLines(lines, i).map((l) => l.replace(/\s+$/, ''));
  while (body.length && !body[body.length - 1]) body.pop();
  while (body.length && !body[0]) body.shift();
  return body;
}

function releaseNotes(text, version) {
  const body = sectionBody(text, version);
  const items = body.map((l) => (/^(New|Fix|Fixed|Improved|Update|Security|Removed|Change|Changed):/i.test(l) ? `- ${l}` : l));
  return [
    ...items,
    '',
    '### Installation',
    `Install **${packageZipName(version)}** via System, Install, Extensions. It contains the content plugin and the editor button, and updates an existing installation of either of them.`,
    '',
  ].join('\n');
}

/* ---------- build ---------- */

const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();

function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

// Builds a zip file from [name, Buffer] pairs. Fixed timestamps, so the same
// files always give the same zip.
function zip(entries) {
  const local = [];
  const central = [];
  let offset = 0;
  const dosTime = 0; // 00:00:00
  const dosDate = ((2020 - 1980) << 9) | (1 << 5) | 1; // 2020-01-01
  for (const [name, data] of entries) {
    const nameBuf = Buffer.from(name, 'utf8');
    const deflated = zlib.deflateRawSync(data, { level: 9 });
    const stored = deflated.length >= data.length;
    const body = stored ? data : deflated;
    const crc = crc32(data);
    const header = Buffer.alloc(30);
    header.writeUInt32LE(0x04034b50, 0);
    header.writeUInt16LE(20, 4);
    header.writeUInt16LE(0x0800, 6); // utf-8 names
    header.writeUInt16LE(stored ? 0 : 8, 8);
    header.writeUInt16LE(dosTime, 10);
    header.writeUInt16LE(dosDate, 12);
    header.writeUInt32LE(crc, 14);
    header.writeUInt32LE(body.length, 18);
    header.writeUInt32LE(data.length, 22);
    header.writeUInt16LE(nameBuf.length, 26);
    header.writeUInt16LE(0, 28);
    local.push(header, nameBuf, body);

    const c = Buffer.alloc(46);
    c.writeUInt32LE(0x02014b50, 0);
    c.writeUInt16LE(20, 4);
    c.writeUInt16LE(20, 6);
    c.writeUInt16LE(0x0800, 8);
    c.writeUInt16LE(stored ? 0 : 8, 10);
    c.writeUInt16LE(dosTime, 12);
    c.writeUInt16LE(dosDate, 14);
    c.writeUInt32LE(crc, 16);
    c.writeUInt32LE(body.length, 20);
    c.writeUInt32LE(data.length, 24);
    c.writeUInt16LE(nameBuf.length, 28);
    c.writeUInt32LE(offset, 42);
    central.push(c, nameBuf);
    offset += header.length + nameBuf.length + body.length;
  }
  const centralBuf = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuf.length, 12);
  end.writeUInt32LE(offset, 16);
  return Buffer.concat([...local, centralBuf, end]);
}

function filesIn(dir) {
  const out = [];
  const walk = (rel) => {
    for (const e of fs.readdirSync(path.join(ROOT, dir, rel), { withFileTypes: true })
      .sort((a, b) => a.name.localeCompare(b.name))) {
      const r = rel ? `${rel}/${e.name}` : e.name;
      if (e.isDirectory()) walk(r);
      else if (e.isFile()) out.push([r, fs.readFileSync(path.join(ROOT, dir, r))]);
    }
  };
  walk('');
  return out;
}

function build() {
  const versions = Object.fromEntries(Object.entries(MANIFESTS).map(([k, f]) => [k, manifestVersion(read(f))]));
  const version = versions.package;
  for (const [k, v] of Object.entries(versions)) {
    if (v !== version) throw new Error(`version mismatch: ${MANIFESTS[k]} has ${v}, the package has ${version}`);
  }
  const pkg = zip([
    ['pkg_pdfviewer.xml', Buffer.from(read(MANIFESTS.package))],
    ['packages/plg_content_pdfviewer.zip', zip(filesIn('content_plugin'))],
    ['packages/plg_editors-xtd_pdfviewer.zip', zip(filesIn('editor_button'))],
  ]);
  const out = path.join(ROOT, 'dist', packageZipName(version));
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, pkg);
  console.log(`${path.relative(ROOT, out)} (${(pkg.length / 1048576).toFixed(1)} MB)`);
  return out;
}

/* ---------- update-xml ---------- */

// Who gets the update: the package, and installs of the separate plugins from
// before 1.6.1 (they download the package, which then takes them over).
const UPDATE_TARGETS = [
  { name: 'PDF viewer', element: 'pkg_pdfviewer', type: 'package', folder: null },
  { name: 'Content - pdfviewer', element: 'pdfviewer', type: 'plugin', folder: 'content' },
  { name: 'Button - pdfviewer', element: 'pdfviewer', type: 'plugin', folder: 'editors-xtd' },
];

function updateEntry(target, version, sha256) {
  return [
    '\t<update>',
    `\t\t<name>${target.name}</name>`,
    '\t\t<description></description>',
    `\t\t<element>${target.element}</element>`,
    `\t\t<type>${target.type}</type>`,
    ...(target.folder ? [`\t\t<folder>${target.folder}</folder>`] : []),
    '\t\t<client>0</client>',
    `\t\t<version>${version}</version>`,
    `\t\t<infourl title="Release notes">https://github.com/${REPO}/releases/tag/v${version}</infourl>`,
    // Joomla 4+ shows a Changelog button in the update list from this, also on
    // sites whose installed version has no <changelogurl> in its manifest yet.
    `\t\t<changelogurl>${CHANGELOG_URL}</changelogurl>`,
    '\t\t<downloads>',
    `\t\t\t<downloadurl type="full" format="zip">${downloadUrl(version)}</downloadurl>`,
    '\t\t</downloads>',
    ...(sha256 ? [`\t\t<sha256>${sha256}</sha256>`] : []),
    '\t\t<tags>',
    '\t\t\t<tag>stable</tag>',
    '\t\t</tags>',
    '\t\t<maintainer>Tazzios</maintainer>',
    `\t\t<maintainerurl>https://github.com/Tazzios/</maintainerurl>`,
    `\t\t<targetplatform name="joomla" version="${TARGET_PLATFORM}"/>`,
    '\t\t<php_minimum>8.1</php_minimum>',
    '\t</update>',
  ].join('\n');
}

// Adds the entries for this version (replacing earlier entries for the same
// version), keeps all other entries.
function addUpdate(xml, version, sha256) {
  const tag = (block, name) => (block.match(new RegExp(`<${name}>([^<]*)</${name}>`)) || [])[1];
  let out = xml.replace(/[ \t]*<update>[\s\S]*?<\/update>[ \t]*\r?\n?/g, (block) => {
    const same = tag(block, 'version') === version &&
      UPDATE_TARGETS.some((t) => tag(block, 'element') === t.element && tag(block, 'type') === t.type &&
        (tag(block, 'folder') || null) === t.folder);
    return same ? '' : block;
  });
  if (!out.includes('</updates>')) throw new Error('no </updates> in the update file');
  const entries = UPDATE_TARGETS.map((t) => updateEntry(t, version, sha256)).join('\n');
  out = out.replace(/\s*<\/updates>\s*$/, `\n\n${entries}\n\n</updates>\n`);
  return out;
}

function updateXml(version, sha256) {
  checkVersion(version);
  if (sha256 && !/^[0-9a-f]{64}$/i.test(sha256)) throw new Error('--sha256 must be 64 hex characters');
  // updates_plugin.xml: content plugin installs and the package.
  // updates_button.xml: editor button installs from before 1.6.1 still look here.
  for (const file of ['updates_plugin.xml', 'updates_button.xml']) {
    const current = fs.existsSync(path.join(ROOT, file)) ? read(file) : '<?xml version="1.0" encoding="utf-8"?>\n<updates>\n</updates>\n';
    write(file, addUpdate(current, version, sha256 && sha256.toLowerCase()));
    console.log(`${file}: offers ${version} (${downloadUrl(version)})`);
  }
  const current = fs.existsSync(path.join(ROOT, 'changelog.xml')) ? read('changelog.xml') : '';
  write('changelog.xml', addChangelog(current, changelogEntry(read('Changelog.md'), version), version));
  console.log(`changelog.xml: changes of ${version}`);
}

/* ---------- changelog.xml ---------- */

// Changelog.md prefix -> Joomla changelog type.
const CHANGE_TYPES = [
  [/^security$/i, 'security'],
  [/^fix(ed)?$/i, 'fix'],
  [/^(new|added)$/i, 'addition'],
  [/^(improved|update|updated|change|changed)$/i, 'change'],
  [/^removed?$/i, 'remove'],
  [/^language$/i, 'language'],
];
const TYPE_ORDER = ['security', 'fix', 'language', 'addition', 'change', 'remove', 'note'];

const escapeXml = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// changelog.xml is plain text in Joomla: drop markdown so no [text](url), `code`,
// **bold**, trailing line-break spaces or pull request numbers (" #57",
// " #46, #47" at the end of a line) end up in it.
const plainText = (t) => t
  .replace(/\s+#\d+(?:\s*,?\s*#\d+)*\s*$/, '')
  .replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1') // [text](url) -> text
  .replace(/<(https?:\/\/[^>\s]+)>/g, '$1') // <https://...> -> https://...
  .replace(/`([^`]*)`/g, '$1')
  .replace(/(\*\*|__)(.+?)\1/g, '$2')
  .replace(/\s+$/, '');

// The Joomla <changelog> block for one version, made from its Changelog.md section.
function changelogEntry(text, version) {
  const groups = {};
  for (const line of sectionBody(text, version)) {
    if (!line.trim()) continue;
    const m = line.match(/^\s*([A-Za-z]+):\s*(.+)$/);
    const type = (m && (CHANGE_TYPES.find(([re]) => re.test(m[1])) || [])[1]) || 'note';
    let item = m && type !== 'note' ? m[2] : line.trim();
    if (m && /^update/i.test(m[1])) item = `Update: ${m[2]}`;
    (groups[type] = groups[type] || []).push(plainText(item).trim());
  }
  const blocks = TYPE_ORDER.filter((t) => groups[t]).map((t) => [
    `\t\t<${t}>`,
    ...groups[t].map((i) => `\t\t\t<item>${escapeXml(i)}</item>`),
    `\t\t</${t}>`,
  ].join('\n'));
  return [
    '\t<changelog>',
    '\t\t<element>pkg_pdfviewer</element>',
    '\t\t<type>package</type>',
    `\t\t<version>${version}</version>`,
    ...blocks,
    '\t</changelog>',
  ].join('\n');
}

// Adds (or replaces) the entry for this version, newest first.
function addChangelog(xml, entry, version) {
  const tag = (block, name) => (block.match(new RegExp(`<${name}>([^<]*)</${name}>`)) || [])[1];
  const kept = (xml.match(/[ \t]*<changelog>[\s\S]*?<\/changelog>/g) || [])
    .filter((b) => tag(b, 'version') !== version);
  return ['<?xml version="1.0" encoding="utf-8"?>', '<changelogs>', entry, ...kept, '</changelogs>', ''].join('\n');
}

/* ---------- main ---------- */

function main(args) {
  const [cmd, version, ...rest] = args;
  switch (cmd) {
    case 'prepare': return prepare(version);
    case 'build': return build();
    case 'notes': return process.stdout.write(releaseNotes(read('Changelog.md'), checkVersion(version)));
    case 'update-xml': {
      const i = rest.indexOf('--sha256');
      return updateXml(version, i === -1 ? undefined : rest[i + 1]);
    }
    default:
      console.error('Usage: node tools/release.js prepare <version> | build | notes <version> | update-xml <version> [--sha256 <hash>]');
      process.exit(1);
  }
}

module.exports = { addUpdate, addChangelog, changelogEntry, releaseChangelog, releaseNotes, setManifestVersion, zip, crc32, packageZipName };

if (require.main === module) {
  try {
    main(process.argv.slice(2));
  } catch (e) {
    console.error(`Error: ${e.message}`);
    process.exit(1);
  }
}
