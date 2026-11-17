async page => {
    const cfg = { op: 'OP-104', accion: 'Editar' };
    const log = [];
    const filas = page.locator('table tbody tr');
    const nf = await filas.count();
    let row = null;
    for (let i = 0; i < nf; i++) {
        const t = await filas.nth(i).locator('td').first().innerText().catch(() => '');
        if (t.includes(cfg.op)) { row = filas.nth(i); break; }
    }
    if (!row) return 'FILA NO ENCONTRADA';
    await row.locator('button[aria-label="Más acciones"]').click({ timeout: 8000 }).catch(async () => {
        log.push('aria-label no servia, probando ultimo boton de la fila');
        await row.locator('button').last().click({ timeout: 8000 });
    });
    await page.waitForTimeout(1200);
    log.push('opciones menu: ' + JSON.stringify(await page.locator('[role=menu] *, [data-radix-popper-content-wrapper] *').allInnerTexts().catch(() => [])).slice(0, 400));
    const item = page.getByRole('menuitem', { name: cfg.accion }).first();
    const alt = page.locator('div[role=menu], [data-radix-popper-content-wrapper]').getByText(cfg.accion, { exact: false }).first();
    if (await item.count()) { await item.click({ timeout: 8000 }); log.push('menuitem clickeado'); }
    else if (await alt.count()) { await alt.click({ timeout: 8000 }); log.push('texto del menu clickeado'); }
    else return 'ACCION NO ENCONTRADA en el menu contextual';
    await page.waitForTimeout(2500);
    const cont = () => page.locator('div.fixed').filter({ hasText: /Editar/ }).last();
    log.push('modal abierto: ' + await cont().count());
    const campos = await cont().evaluate(d => [...d.querySelectorAll('input,select,textarea')].slice(0, 30).map(e => ({
        t: e.type || e.tagName.toLowerCase(),
        v: (e.value || '').slice(0, 22),
        dis: !!e.disabled || !!e.readOnly,
        cerca: (e.closest('div')?.innerText || '').replace(/\n+/g, ' ').slice(0, 40),
    })));
    log.push('campos: ' + JSON.stringify(campos).slice(0, 1600));
    const txt = (await cont().innerText().catch(() => '')).replace(/\n+/g, ' | ');
    log.push('avisos: ' + JSON.stringify((txt.match(/[^|]*(congela|no se puede|retroced|editabl|Bloquead|bloquead)[^|]*/g) || []).slice(0, 6)));
    log.push('texto: ' + txt.slice(0, 800));
    return log.join('\n');
}
