const fs = require('fs');
const path = require('path');

const root = path.join(__dirname);
const versionPath = path.join(root, 'version.json');
const files = [];

function walk(dir) {
  for (const name of fs.readdirSync(dir)) {
    if (name === 'node_modules' || name === 'vendor') continue;
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p);
    else if (name.endsWith('.md')) files.push(p);
  }
}

function bumpPatch(version) {
  const parts = String(version || '1.0.0').split('.').map((n) => parseInt(n, 10) || 0);
  while (parts.length < 3) parts.push(0);
  parts[2] += 1;
  return parts.slice(0, 3).join('.');
}

walk(root);

const docs = {};
for (const f of files) {
  const key = path.relative(root, f).split(path.sep).join('/');
  docs[key] = fs.readFileSync(f, 'utf8');
}

let versionInfo = {
  version: '1.0.0',
  updatedAt: null,
  revision: 0,
  notes: 'Bump automatically when running: node docs/build-docs-data.js',
};

if (fs.existsSync(versionPath)) {
  try {
    versionInfo = { ...versionInfo, ...JSON.parse(fs.readFileSync(versionPath, 'utf8')) };
  } catch (_) {}
}

const stamp = new Date().toISOString().replace('T', ' ').slice(0, 19);
versionInfo.version = bumpPatch(versionInfo.version);
versionInfo.revision = (Number(versionInfo.revision) || 0) + 1;
versionInfo.updatedAt = stamp;
versionInfo.fileCount = files.length;

fs.writeFileSync(versionPath, JSON.stringify(versionInfo, null, 2) + '\n');

const payload =
  '/* Auto-generated ' +
  stamp +
  ' — docs v' +
  versionInfo.version +
  ' — run: node docs/build-docs-data.js */\n' +
  'window.DOCS_DATA = ' +
  JSON.stringify(docs) +
  ';\n' +
  'window.DOCS_BUILD = ' +
  JSON.stringify(stamp) +
  ';\n' +
  'window.DOCS_VERSION = ' +
  JSON.stringify(versionInfo.version) +
  ';\n' +
  'window.DOCS_META = ' +
  JSON.stringify(versionInfo) +
  ';\n';

fs.writeFileSync(path.join(root, 'docs-data.js'), payload);

const indexPath = path.join(root, 'index.html');
let html = fs.readFileSync(indexPath, 'utf8');

const start = '<!-- DOCS_DATA_START -->';
const end = '<!-- DOCS_DATA_END -->';
const block = start + '\n<script>\n' + payload + '</script>\n' + end;

if (html.includes(start) && html.includes(end)) {
  html = html.replace(new RegExp(start + '[\\s\\S]*?' + end), block);
} else if (html.includes('<script src="docs-data.js')) {
  html = html.replace(/<script src="docs-data\.js[^"]*"><\/script>/, block);
} else if (html.includes('vendor/marked.min.js')) {
  html = html.replace(/(<script src="vendor\/marked\.min\.js[^"]*"><\/script>)/, '$1\n  ' + block);
} else {
  html = html.replace('</head>', '  ' + block + '\n</head>');
}

const versionLabel = `v${versionInfo.version}`;
const stampTitle = `Docs ${versionLabel} · revised ${stamp} · run: node docs/build-docs-data.js`;
const stampHtml = `<span class="header-pill docs-build-stamp" title="${stampTitle}"><i class="bi bi-tag"></i> ${versionLabel} · ${stamp}</span>`;

if (html.includes('docs-build-stamp')) {
  html = html.replace(/<span class="header-pill docs-build-stamp"[^>]*>[\s\S]*?<\/span>/, stampHtml);
} else {
  html = html.replace(
    /(<span class="header-pill"><i class="bi bi-journal-code"><\/i> Module Docs<\/span>)/,
    `$1\n        ${stampHtml}`
  );
}

html = html.replace(
  /<title>[^<]*<\/title>/,
  `<title>ASELCO Inc. — Module Docs ${versionLabel}</title>`
);

html = html.replace(
  /src="https:\/\/cdn\.jsdelivr\.net\/npm\/marked@[^\"]+"/,
  'src="vendor/marked.min.js"'
);
html = html.replace(/src="vendor\/marked\.min\.js\?v=[^"]+"/, 'src="vendor/marked.min.js"');

fs.writeFileSync(indexPath, html);

// Keep README version line in sync when present
const readmePath = path.join(root, 'README.md');
if (fs.existsSync(readmePath)) {
  let readme = fs.readFileSync(readmePath, 'utf8');
  const versionLine = `**Docs version:** \`${versionLabel}\` (revised ${stamp})`;
  if (/^\*\*Docs version:\*\*/m.test(readme)) {
    readme = readme.replace(/^\*\*Docs version:\*\*.*$/m, versionLine);
  } else {
    readme = readme.replace(
      /^# ASELCO Zenny — Module Documentation\s*\n/,
      `# ASELCO Zenny — Module Documentation\n\n${versionLine}\n\n`
    );
  }
  fs.writeFileSync(readmePath, readme);
}

console.log(
  'Docs',
  versionLabel,
  '· embedded',
  files.length,
  'markdown files (' + Math.round(payload.length / 1024) + ' KB) @',
  stamp
);
