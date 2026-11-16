async page => {
    const cfg = JSON.parse(require('fs').readFileSync('op_driver_cfg.json', 'utf8'));
    const log = [];
    const T = 8000;
    let dlgRoot = page.locator("[role=dialog]"); await page.waitForTimeout(100); if (!(await dlgRoot.count())) dlgRoot = page.locator("div.fixed").last();
    const dlg = () => dlgRoot;

    const row = page.locator('table tbody tr').filter({ hasText: cfg.op });
    await row.locator('button[aria-label="Cambiar estado"]').click({ timeout: T });
    await page.waitForTimeout(1800);
    log.push('modal inicial: ' + (await dlg().innerText().catch(() => 'sin dialog')).replace(/\n+/g, ' | ').slice(0, 240));

    const chip = dlg().locator('button').filter({ hasText: cfg.destino }).first();
    if (!(await chip.count())) { log.push('CHIP NO ENCONTRADO: ' + cfg.destino); return log.join('\n'); }
    await chip.click({ timeout: T });
    await page.waitForTimeout(cfg.afterChipWait || 1600);
    log.push('modal destino: ' + (await dlg().innerText().catch(() => '')).replace(/\n+/g, ' | ').slice(0, 850));

    if (cfg.observarBotonAvanzar) {
        const b = dlg().getByRole('button', { name: /Pasar a «Lista para taller»/ });
        log.push('boton pasar-a-lista visible: ' + (await b.count()));
    }

    if (cfg.fechaRecepcion) {
        const d = dlg().locator('input[type=date]');
        const n = await d.count();
        for (let i = 0; i < n; i++) await d.nth(i).fill(cfg.fechaRecepcion, { timeout: T }).catch(() => log.push('fecha no rellenable'));
        log.push('fechas rellenadas: ' + n);
    }

    if (cfg.recepcion) {
        const filas = dlg().locator('table tbody tr');
        let i = 0;
        for (const [cab, val] of Object.entries(cfg.recepcion)) {
            const inputs = filas.nth(i).locator('input[type=number]');
            const k = await inputs.count();
            const idx = cab === 'aprobada' ? 0 : cab === 'reparacion' ? 1 : 2;
            if (k > idx) { await inputs.nth(idx).fill(String(val), { timeout: T }); log.push(`fila${i} ${cab}=${val}`); }
            i++;
        }
        await page.waitForTimeout(900);
    }

    const save = dlg().getByRole('button', { name: /Guardar|Enviar/ }).last();
    log.push('guardar disabled: ' + (await save.isDisabled().catch(() => 'n/a')));

    if (cfg.avance) {
        const av = dlg().getByRole('button', { name: /Guardar avance/ });
        if (await av.count()) {
            const resp = page.waitForResponse(r => /recepcion-borrador/.test(r.url()), { timeout: 20000 });
            await av.click({ timeout: T });
            const r = await resp.catch(() => null);
            log.push('avance: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
            await page.waitForTimeout(2000);
        } else log.push('avance: boton ausente');
    }

    if (cfg.guardar) {
        const resp = page.waitForResponse(r => /OrdenProduccion\/\d+\/estado/.test(r.url()), { timeout: 20000 });
        await save.click({ timeout: T });
        const r = await resp.catch(() => null);
        log.push('estado: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
        await page.waitForTimeout(2500);
        log.push('dialog abierto tras guardar: ' + (await page.locator('[role=dialog]').count()));
        const errs = await page.locator('[class*=text-red]').allInnerTexts();
        log.push('errores UI: ' + JSON.stringify(errs.filter(Boolean).slice(0, 6)));
        const flash = await page.locator('[role=status]').allInnerTexts().catch(() => []);
        log.push('flash: ' + JSON.stringify(flash.filter(Boolean).slice(0, 3)));
    }
    return log.join('\n');
}
