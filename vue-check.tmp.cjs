const fs = require('fs');
const { parse, compileTemplate, compileScript } = require('@vue/compiler-sfc');

const files = [
  'resources/js/pages/User/Create.vue',
  'resources/js/pages/User/Edit.vue',
  'resources/js/pages/User/Index.vue',
  'resources/js/pages/User/ImportarUsuarioModal.vue',
];

for (const f of files) {
  try {
    const src = fs.readFileSync(f, 'utf8');
    const { descriptor, errors } = parse(src, { filename: f });
    const tpl = compileTemplate({ source: descriptor.template.content, filename: f, id: 'x' });
    let scriptErrors = [];
    try {
      compileScript(descriptor, { id: 'x' });
    } catch (e) {
      scriptErrors = [e.message];
    }
    console.log(f, '| parse:', errors.length, '| template:', tpl.errors.length, '| script:', scriptErrors.length);
    for (const e of errors.concat(tpl.errors).map(String)) console.log('   ', e);
    for (const e of scriptErrors) console.log('   ', e);
  } catch (e) {
    console.log(f, 'THREW:', e.message);
  }
}
