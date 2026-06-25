import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');

const CSS_FILES = [
  'home.css',
  'assets/css/site-layout.css',
  'assets/css/site-mobile.css',
  'assets/css/catalog-page.css',
  'assets/css/cart-page.css',
  'assets/css/product-page.css',
  'assets/css/account-page.css',
  'assets/css/static-pages.css',
  'assets/css/contact-page.css',
  'assets/css/about-page.css',
  'assets/css/blog-article-page.css',
];

const SCAN_DIRS = ['.', 'system', 'assets/js'];
const SKIP_DIRS = new Set(['admin', 'bes', 'node_modules', 'vendor', 'tools', '.git']);
const EXT = new Set(['.php', '.html', '.js']);

function walk(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const name of fs.readdirSync(dir)) {
    if (SKIP_DIRS.has(name)) continue;
    const full = path.join(dir, name);
    const st = fs.statSync(full);
    if (st.isDirectory()) {
      if (dir === ROOT && !SCAN_DIRS.includes(name) && name !== 'system' && name !== 'assets') continue;
      if (dir === ROOT && name === 'assets' && !full.includes('assets\\js') && !full.endsWith('assets/js')) {
        if (name === 'assets') {
          const jsDir = path.join(full, 'js');
          if (fs.existsSync(jsDir)) walk(jsDir, out);
        }
        continue;
      }
      walk(full, out);
    } else if (EXT.has(path.extname(name))) {
      out.push(full);
    }
  }
  return out;
}

function extractCssSelectors(css) {
  const classes = new Set();
  const ids = new Set();
  const re = /([.#])([a-zA-Z_][\w-]*)/g;
  let m;
  while ((m = re.exec(css)) !== null) {
    if (m[1] === '.') classes.add(m[2]);
    else ids.add(m[2]);
  }
  return { classes, ids };
}

function extractMarkupTokens(content) {
  const classes = new Set();
  const ids = new Set();
  const classAttr = /class\s*=\s*["']([^"']+)["']/gi;
  const idAttr = /\bid\s*=\s*["']([^"']+)["']/gi;
  const jsClass = /classList\.(?:add|remove|toggle)\(\s*['"]([^'"]+)['"]/g;
  const jsQuery = /querySelector(?:All)?\(\s*['"]\.([^'"]+)['"]/g;
  const closest = /\.closest\(\s*['"]([^'"]+)['"]/g;
  let m;
  while ((m = classAttr.exec(content)) !== null) {
    m[1].split(/\s+/).filter(Boolean).forEach((c) => classes.add(c));
  }
  while ((m = idAttr.exec(content)) !== null) ids.add(m[1]);
  while ((m = jsClass.exec(content)) !== null) classes.add(m[1]);
  while ((m = jsQuery.exec(content)) !== null) {
    m[1].split(/[,\s#[]+/).filter((c) => c && !c.startsWith('#')).forEach((c) => classes.add(c.replace(/:.*/, '')));
  }
  while ((m = closest.exec(content)) !== null) {
    m[1].split(/[,\s]/).forEach((c) => {
      if (c.startsWith('.')) classes.add(c.slice(1));
    });
  }
  const templateClass = /className\s*=\s*[`'"]([^`'"]+)[`'"]/g;
  while ((m = templateClass.exec(content)) !== null) {
    m[1].match(/\.([a-zA-Z_][\w-]*)/g)?.forEach((c) => classes.add(c.slice(1)));
  }
  const innerClass = /class\s*:\s*['"]([^'"]+)['"]/g;
  while ((m = innerClass.exec(content)) !== null) {
    m[1].split(/\s+/).forEach((c) => classes.add(c));
  }
  return { classes, ids };
}

const usedGlobal = { classes: new Set(), ids: new Set() };
const files = [];
for (const d of SCAN_DIRS) {
  const base = d === '.' ? ROOT : path.join(ROOT, d);
  if (d === '.') {
    for (const name of fs.readdirSync(ROOT)) {
      if (EXT.has(path.extname(name))) files.push(path.join(ROOT, name));
    }
  } else {
    walk(base, files);
  }
}

for (const f of files) {
  const t = extractMarkupTokens(fs.readFileSync(f, 'utf8'));
  t.classes.forEach((c) => usedGlobal.classes.add(c));
  t.ids.forEach((i) => usedGlobal.ids.add(i));
}

const SAFE_PREFIXES = ['fa-', 'owl-', 'is-', 'nav-', 'tab-', 'fade', 'show', 'active', 'collapse', 'modal', 'btn-', 'col-', 'row', 'g-', 'd-', 'm', 'p', 'text-', 'bg-', 'border-', 'rounded', 'container', 'navbar'];
const SAFE_EXACT = new Set(['hidden', 'active', 'collapsed', 'fade', 'show', 'page', 'container', 'row', 'col', 'nav', 'main']);

function isSafe(name) {
  if (SAFE_EXACT.has(name)) return true;
  return SAFE_PREFIXES.some((p) => name.startsWith(p) || name === p.replace(/-$/, ''));
}

const report = { deadByFile: {}, orphans: [], unusedFiles: [] };

for (const rel of CSS_FILES) {
  const fp = path.join(ROOT, rel);
  if (!fs.existsSync(fp)) continue;
  const { classes } = extractCssSelectors(fs.readFileSync(fp, 'utf8'));
  const dead = [...classes].filter((c) => !usedGlobal.classes.has(c) && !isSafe(c)).sort();
  if (dead.length) report.deadByFile[rel] = dead;
}

for (const orphan of ['assets/css/bootstrap.min.css', 'assets/css/animate.min.css', 'assets/css/style.min.css']) {
  if (fs.existsSync(path.join(ROOT, orphan))) report.unusedFiles.push(orphan);
}

const allCssClasses = new Set();
for (const rel of CSS_FILES) {
  const fp = path.join(ROOT, rel);
  if (!fs.existsSync(fp)) continue;
  extractCssSelectors(fs.readFileSync(fp, 'utf8')).classes.forEach((c) => allCssClasses.add(c));
}

report.markupWithoutCss = [...usedGlobal.classes]
  .filter((c) => !allCssClasses.has(c) && !isSafe(c) && !c.startsWith('_') === false)
  .filter((c) => !allCssClasses.has(c) && !isSafe(c))
  .sort()
  .slice(0, 120);

console.log(JSON.stringify(report, null, 2));
