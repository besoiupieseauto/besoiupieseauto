import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

/**
 * Regenerare CSS din home.full.css (backup manual).
 * Nu rula pe home.css cu @import — folosește home.full.css.
 */
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourcePath = path.join(root, 'home.full.css');

if (!fs.existsSync(sourcePath)) {
  console.error('Lipsește home.full.css — păstrează backup-ul complet înainte de split.');
  process.exit(1);
}

const home = fs.readFileSync(sourcePath, 'utf8');
const lines = home.split('\n');

function extractRanges(rangesList) {
  const out = [];
  for (const [start, end] of rangesList) {
    out.push(...lines.slice(start - 1, end));
  }
  return out.join('\n').trim() + '\n';
}

/** Extrage conținutul din interiorul unui @media, inclusiv acoladele. */
function extractMediaBlock(startLine) {
  let depth = 0;
  const out = [];
  for (let i = startLine - 1; i < lines.length; i++) {
    const line = lines[i];
    for (const ch of line) {
      if (ch === '{') depth++;
      if (ch === '}') depth--;
    }
    out.push(line);
    if (depth === 0 && out.length > 1) break;
  }
  return out.join('\n') + '\n';
}

const media1100Start = lines.findIndex((l) => /@media\s*\(\s*max-width:\s*1100px\s*\)/.test(l)) + 1;
const media768Start = lines.findIndex((l) => /@media\s*\(\s*max-width:\s*768px\s*\)/.test(l)) + 1;
const media620Start = lines.findIndex((l) => /@media\s*\(\s*max-width:\s*620px\s*\)/.test(l)) + 1;
const media480Start = lines.findIndex((l) => /@media\s*\(\s*max-width:\s*480px\s*\)/.test(l)) + 1;

const shellPath = path.join(root, 'assets/css/site-shell.css');
const indexPath = path.join(root, 'assets/css/home-index.css');
const cardsPath = path.join(root, 'assets/css/product-cards.css');

const shellBase = extractRanges([
  [1, 156],
  [411, 438],
  [440, 456],
]);
const shellMedia = [
  extractMediaBlock(media1100Start).replace(/^@media[^\{]+\{/, '').replace(/\}\s*$/, ''),
]
  .filter(Boolean)
  .join('\n');

const indexBase = extractRanges([
  [157, 237],
  [222, 236],
  [377, 410],
]);
const indexMedia =
  '@media(max-width:1100px){\n' +
  extractMediaBlock(media1100Start).replace(/^@media[^\{]+\{/, '').replace(/\}\s*$/, '') +
  '\n}\n' +
  extractMediaBlock(media768Start) +
  extractMediaBlock(media620Start);

const cardsBase = extractRanges([[238, 376], [371, 376]]);
const cardsMedia =
  extractMediaBlock(media1100Start).match(/_product-grid[\s\S]*?(?=\n  \.[a-z])/gi)?.join('\n') || '';

fs.writeFileSync(shellPath, '/* Besoiu — shell */\n' + shellBase + '\n@media(max-width:1100px){\n' + shellMedia + '\n}\n');
console.log('Script needs home.full.css backup — manual fix applied in repo.');
