async page => {
    const log = [];
    const cont = () => page.locator('div.fixed').filter({ hasText: 'Cambiar estado —' }).last();
    const av = cont().getByRole('button', { name: /guardar avance/i });
    if (!(await av.count())) return 'boton avance ausente';
    const antes = await cont().innerText();
    log.push('resumen antes: ' + (antes.match(/APROBADAS[\s\S]{0,60}/) || [''])[0].replace(/\n+/g, ' '));
    const resp = page.waitForResponse(r => /recepcion-borrador/.test(r.url()), { timeout: 25000 });
    await av.click({ timeout: 8000 });
    const r = await resp.catch(() => null);
    log.push('avance: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
    await page.waitForTimeout(3500);
    log.push('modal tras avance: ' + (await cont().innerText().catch(() => 'CERRADO')).replace(/\n+/g, ' | ').slice(0, 200));
    log.push('flash: ' + JSON.stringify(await page.locator('[role=status], [class*=success]').allInnerTexts().catch(() => [])).slice(0, 700));
    log.push('errores: ' + JSON.stringify((await page.locator('[class*=text-red]').allInnerTexts()).filter(t => t && t.length > 3).slice(0, 5)));
    const filas = page.locator('table tbody tr');
    const nf = await filas.count();
    for (let i = 0; i < nf; i++) {
        const t = await filas.nth(i).locator('td').first().innerText().catch(() => '');
        if (t.includes('OP-104')) { log.push('fila final: ' + (await filas.nth(i).innerText()).replace(/\n+/g, ' | ').slice(0, 260)); break; }
    }
    return log.join('\n');
}
