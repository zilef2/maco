async page => {
    const log = [];
    const cont = () => page.locator('div.fixed').filter({ hasText: /Registrar compra/ }).last();
    const btn = cont().getByRole('button', { name: /REGISTRAR COMPRA|Registrar compra/i }).last();
    if (!(await btn.count())) return 'BOTON REGISTRAR COMPRA NO ENCONTRADO (modal abierto: ' + (await cont().count()) + ')';
    log.push('disabled: ' + await btn.isDisabled().catch(() => 'n/a'));
    const resp = page.waitForResponse(r => /Material\/compra/.test(r.url()), { timeout: 25000 });
    await btn.click({ timeout: 8000 });
    const r = await resp.catch(() => null);
    log.push('compra: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
    await page.waitForTimeout(3500);
    log.push('modal tras guardar: ' + (await cont().innerText().catch(() => 'CERRADO')).replace(/\n+/g, ' | ').slice(0, 160));
    log.push('flash: ' + JSON.stringify(await page.locator('[role=status]').allInnerTexts().catch(() => [])).slice(0, 600));
    log.push('errores: ' + JSON.stringify((await page.locator('[class*=text-red]').allInnerTexts()).filter(Boolean).slice(0, 6)));
    return log.join('\n');
}
