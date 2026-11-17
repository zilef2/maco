async page => {
    const cfg = { accion: 'Terminar después', observaciones: 'PRUEBA-QODER borrador' };
    const log = [];
    const T = 8000;
    await page.evaluate(() => { [...document.querySelectorAll('div.fixed button')].find(x => /✕|×/.test(x.textContent))?.click(); });
    await page.waitForTimeout(600);
    await page.getByRole('button', { name: /nueva orden/i }).first().click({ timeout: T });
    await page.waitForTimeout(2500);
    const cont = () => page.locator('div.fixed').filter({ hasText: /Nueva |Crear |producci/ }).last();
    log.push('modal: ' + (await cont().innerText().catch(() => 'NO MODAL')).replace(/\s+/g, ' ').slice(0, 700));
    log.push('botones: ' + JSON.stringify(await cont().locator('button').allInnerTexts().catch(() => [])).slice(0, 400));
    log.push('campos: ' + JSON.stringify(await cont().locator('input,select,textarea').evaluateAll(es => es.slice(0, 16).map(e => ({ t: e.type || e.tagName, aria: e.getAttribute('aria-label'), ph: e.getAttribute('placeholder'), v: e.value, label: (e.closest('div')?.innerText || '').replace(/\s+/g, ' ').slice(0, 42) })))));

    const ta = cont().locator('textarea').first();
    if (await ta.count()) { await ta.fill(cfg.observaciones, { timeout: T }); log.push('observaciones escritas'); }
    else log.push('sin textarea');

    const btn = cont().getByRole('button', { name: new RegExp(cfg.accion.replace(/[^\wáéíóúñ]/gi, '\\s*'), 'i') }).last();
    log.push('boton accion presente: ' + await btn.count());
    const resp = page.waitForResponse(r => /borrador/.test(r.url()), { timeout: 25000 });
    await btn.click({ timeout: T });
    const r = await resp.catch(() => null);
    log.push('peticion: ' + (r ? r.request().method() + ' ' + r.status() + ' ' + decodeURIComponent((await r.request().postData() || '').slice(0, 220)) : 'SIN_PETICION'));
    await page.waitForTimeout(3500);
    log.push('modal tras guardar: ' + (await cont().innerText().catch(() => 'CERRADO')).replace(/\s+/g, ' ').slice(0, 160));
    log.push('errores UI: ' + JSON.stringify((await page.locator('[class*=text-red]').allInnerTexts()).filter(t => t && t.length > 4).slice(0, 6)));
    log.push('continuar: ' + JSON.stringify(await page.locator('button').filter({ hasText: /continuar/i }).allInnerTexts()));
    return log.join('\n');
}
