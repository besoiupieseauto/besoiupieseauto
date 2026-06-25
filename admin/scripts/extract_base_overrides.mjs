import fs from 'fs';

const htmlPath = process.argv[2] || 'c:/laragon/www/besoiupieseauto.ro/admin/data/import_base_source.html';
const dataDir = 'c:/laragon/www/besoiupieseauto.ro/admin/data';
const html = fs.readFileSync(htmlPath, 'utf8');

const m1 = html.match(/const DEFAULT_NAME_OVERRIDES_TEXT = `([\s\S]*?)`;\s*const DEFAULT_ALLOWED_CAR_BRANDS/);
const m2 = html.match(/const DEFAULT_ALLOWED_PART_BRANDS = `([\s\S]*?)`;\s*\/\/ Daca avem/);

if (!m1 || !m2) {
  console.error('extract failed');
  process.exit(1);
}

fs.writeFileSync(`${dataDir}/import_base_name_overrides.txt`, m1[1].trim());
fs.writeFileSync(`${dataDir}/import_base_allowed_part_brands.txt`, m2[1].trim());
console.log('ok', m1[1].length, m2[1].length);
