async page => {
    const cfg = { op: 'OP-105', observaciones: 'editado desde la UI en Borrador', guardar: true };
    const log = [];
    const T = 8000;
    await page.evaluate(() => { [...document.querySelectorAll('div.fixed button')].find(x => /✕|×/.test(x.textContent))?.click(); });
    await page.waitForTimeout(900);
    await page.evaluate(() => { const c = [...document.querySelectorAll('div.fixed button')].find(x => /^\s*(cerrar|cancelar)\s*$/i.test(x.innerText)); c?.click(); });
    await page.waitForTimeout(900);
    if (await page.evaluate(() => [...document.querySelectorAll('div.fixed')].some(d => d.innerText.trim().length > 5))) { await page.reload(); await page.waitForTimeout(3500); }

    const filas = page.locator('table tbody tr');
    let row = null;
    for (let i = 0; i < await filas.count(); i++) {
        if ((await filas.nth(i).locator('td').first().innerText().catch(() => '')).includes(cfg.op)) { row = filas.nth(i); break; }
    }
    if (!row) return 'FILA NO ENCONTRADA: ' + cfg.op + ' (filas: ' + await filas.count() + ')';
    log.push('fila: ' + (await row.innerText()).replace(/\s+/g, ' ').slice(0, 240));
    await row.locator('button[aria-label="Más acciones"]').click({ timeout: T });
    await page.waitForTimeout(1200);
    await page.getByRole('menuitem', { name: /Editar/i }).first().click({ timeout: T });
    await page.waitForTimeout(2200);
    const cont = () => page.locator('div.fixed').filter({ hasText: 'Editar OrdenProduccion' }).last();
    log.push('cabecera modal: ' + (await cont().innerText().catch(() => 'NO MODAL')).replace(/\s+/g, ' ').slice(0, 260));
    const ta = cont().locator('textarea').first();
    log.push('textarea presente: ' + await ta.count());
    await ta.fill(cfg.observaciones, { timeout: T });
    await page.waitForTimeout(600);
    const save = cont().getByRole('button', { name: /^guardar$/i }).last();
    log.push('guardar disabled: ' + await save.isDisabled().catch(() => 'n/a'));
    if (cfg.guardar) {
        const resp = page.waitForResponse(r => /OrdenProduccion/.test(r.url()) && r.request().method() !== 'GET', { timeout: 25000 });
        await save.click({ timeout: T });
        const r = await resp.catch(() => null);
        log.push('update: ' + (r ? r.request().method() + ' ' + r.status() + ' ' + r.url() : 'SIN_PETICION'));
        await page.waitForTimeout(3500);
    }
    log.push('modal tras guardar: ' + (await cont().innerText().catch(() => 'CERRADO')).replace(/\s+/g, ' ').slice(0, 150));
    log.push('errores UI: ' + JSON.stringify((await page.locator('[class*=text-red]').allInnerTexts()).filter(t => t && t.length > 4).slice(0, 5)).slice(0, 500));
    const f2 = page.locator('table tbody tr');
    for (let i = 0; i < await f2.count(); i++) {
        if ((await f2.nth(i).locator('td').first().innerText().catch(() => '')).includes(cfg.op)) { log.push('fila final: ' + (await f2.nth(i).innerText()).replace(/\s+/g, ' ').slice(0, 250)); break; }
    }
    return log.join('\n');
}
