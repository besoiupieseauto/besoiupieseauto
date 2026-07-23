/** Simulare logică shell-guard vs deschidere modal (fără DOM). */
function oldCloseModal(style) {
  style.display = 'none';
  style.displayPriority = 'important';
}

function newCloseModal(id, style, classes) {
  classes.delete('is-open');
  classes.add('hidden');
  if (id === 'importQueueEditModal') {
    delete style.display;
    delete style.displayPriority;
    return;
  }
  oldCloseModal(style);
}

function openModalWithoutClear(style, classes) {
  classes.delete('hidden');
  classes.add('is-open');
}

function openModalWithClear(style, classes) {
  delete style.display;
  delete style.displayPriority;
  classes.delete('hidden');
  classes.add('is-open');
}

function visible(style, classes) {
  if (style.display === 'none' && style.displayPriority === 'important') return false;
  return classes.has('is-open') && !classes.has('hidden');
}

let fail = 0;
function assert(name, cond) {
  if (cond) console.log('PASS:', name);
  else { console.error('FAIL:', name); fail = 1; }
}

{
  const style = {};
  const classes = new Set(['hidden']);
  oldCloseModal(style);
  openModalWithoutClear(style, classes);
  assert('BUG vechi reprodus: is-open dar display:none!important', !visible(style, classes));
}

{
  const style = {};
  const classes = new Set(['hidden']);
  newCloseModal('importQueueEditModal', style, classes);
  openModalWithClear(style, classes);
  assert('FIX: modal vizibil după shell-guard + open', visible(style, classes));
}

process.exit(fail);
