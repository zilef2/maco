async page => {
    const log = [];
    const abrir = page.getByRole('button', { name: /Comprar faltantes/ }).first();
    if (!(await abrir.count())) return 'BOTON COMPRAR FALTANTES NO ENCONTRADO';
    await abrir.click({ timeout: 8000 });
    await page.waitForTimeout(2000);
    const cont = () => page.locator('div.fixed').filter({ hasText: /ompra/ }).last();
    log.push('modal: ' + (await cont().innerText().catch(() => 'NO MODAL')).replace(/\n+/g, ' | ').slice(0, 900));
    const filas = cont().locator('table tbody tr');
    const n = await filas.count();
    log.push('filas: ' + n);
    for (let i = 0; i < Math.min(n, 8); i++) {
        const r = filas.nth(i);
        log.push('fila ' + i + ': ' + (await r.innerText().catch(() => '')).replace(/\n+/g, ' | ').slice(0, 160)
            + ' || inputs=' + JSON.stringify(await r.locator('input').evaluateAll(es => es.map(e => (e.getAttribute('placeholder') || '') + ':' + e.type)))
            + ' || selects=' + await r.locator('select').count());
    }
    log.push('botones: ' + JSON.stringify(await cont().locator('button').allInnerTexts()));
    return log.join('\n');
}
