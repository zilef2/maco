async page => {
    const cfg = {
        op: 'OP-104',
        destino: 'Recibiendo',
        permitirSinChip: true,
        guardar: true,
        observarBotonAvanzar: false,
        dumpTabla: false,
        okFilas: [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19],
        okTodas: false,
        restarAprobada: [3],
        sumarAprobada: [2],
        restarReparacion: [2],
        sumarReparacion: null,
        sumarNoRecuperable: [3],
        volcar: true,
        compromiso: null,
        compromisoIdx: 1,
        avance: false,
    };
    const log = [];
    const T = 8000;
    const cont = () => page.locator('div.fixed').filter({ hasText: 'Cambiar estado —' }).last();
    const tabla = () => cont().locator('table tbody tr');
    const btnFila = (i, aria) => tabla().nth(i).locator('button[aria-label="' + aria + '"]');

    try {
        await page.evaluate(() => { [...document.querySelectorAll('div.fixed button')].find(x => /✕|×/.test(x.textContent))?.click(); });
        await page.waitForTimeout(400);
        await page.evaluate(() => { const c = [...document.querySelectorAll('div.fixed button')].find(x => /^\s*cerrar\s*$/i.test(x.innerText)); c?.click(); });
        await page.waitForTimeout(900);
        if (await page.evaluate(() => [...document.querySelectorAll('div.fixed')].some(d => d.innerText.trim().length > 5))) {
            await page.reload();
            await page.waitForTimeout(3500);
            log.push('recargada por overlay pegado');
        }

        const filas = page.locator('table tbody tr');
        const nf = await filas.count();
        let row = null;
        for (let i = 0; i < nf; i++) {
            if ((await filas.nth(i).locator('td').first().innerText().catch(() => '')).includes(cfg.op)) { row = filas.nth(i); break; }
        }
        if (!row) return 'FILA NO ENCONTRADA: ' + cfg.op;
        log.push('fila: ' + (await row.innerText()).replace(/\n+/g, ' | ').slice(0, 230));
        await row.locator('button[aria-label="Cambiar estado"]').click({ timeout: T });
        await page.waitForTimeout(1800);
        log.push('modal: ' + (await cont().innerText().catch(() => 'NO MODAL')).replace(/\n+/g, ' | ').slice(0, 450));

        const chip = cont().locator('button').filter({ hasText: cfg.destino }).first();
        if (!(await chip.count())) {
            log.push('CHIP NO ENCONTRADO: ' + cfg.destino);
            log.push('modal completo: ' + (await cont().innerText()).replace(/\n+/g, ' | ').slice(0, 1200));
            if (!cfg.permitirSinChip) return log.join('\n');
        } else {
            await chip.click({ timeout: T });
            await page.waitForTimeout(1600);
            log.push('tras elegir: ' + (await cont().innerText().catch(() => '')).replace(/\n+/g, ' | ').slice(0, 700));
        }

        if (cfg.dumpTabla) {
            log.push('cabecera: ' + (await cont().locator('table').first().locator('thead').innerText().catch(() => 'sin thead')).replace(/\n+/g, ' | '));
            const q = await tabla().count();
            log.push('filas: ' + q);
            for (let i = 0; i < Math.min(q, 3); i++) {
                log.push('  fila' + i + ': ' + (await tabla().nth(i).innerText().catch(() => '')).replace(/\n+/g, ' | ')
                    + ' inputs=' + JSON.stringify(await tabla().nth(i).locator('input').evaluateAll(es => es.map(e => e.type + '=' + e.value))));
            }
            log.push('fechas: ' + JSON.stringify(await cont().locator('input[type=date]').evaluateAll(es => es.map(e => (e.closest('div')?.innerText || '').replace(/\n+/g, ' ').slice(0, 45) + '=' + e.value))));
        }

        if (cfg.okTodas) {
            const b = cont().locator('button').filter({ hasText: /a todas/i }).first();
            log.push('boton a todas: ' + await b.count());
            await b.click({ timeout: T });
            await page.waitForTimeout(1500);
        }
        if (cfg.okFilas) {
            for (const i of cfg.okFilas) { await btnFila(i, 'Poner en aprobada los esperados para talla').first().click({ timeout: T }).catch(async () => {
                const alt = tabla().nth(i).locator('button[aria-label^="Poner en aprobada"]');
                await alt.click({ timeout: T });
            }); log.push('OK fila' + i); await page.waitForTimeout(300); }
        }
        if (cfg.restarReparacion) {
            for (const i of cfg.restarReparacion) { await btnFila(i, 'Restar 1 a en reparación').click({ timeout: T }); log.push('restada reparacion fila' + i); await page.waitForTimeout(400); }
        }
        if (cfg.sumarAprobada) {
            for (const i of cfg.sumarAprobada) { await btnFila(i, 'Sumar 1 a aprobada').click({ timeout: T }); log.push('sumada aprobada fila' + i); await page.waitForTimeout(400); }
        }
        if (cfg.restarAprobada) {
            for (const i of cfg.restarAprobada) { await btnFila(i, 'Restar 1 a aprobada').click({ timeout: T }); log.push('restada aprobada fila' + i); await page.waitForTimeout(400); }
        }
        if (cfg.sumarReparacion) {
            for (const i of cfg.sumarReparacion) { await btnFila(i, 'Sumar 1 a en reparación').click({ timeout: T }); log.push('sumada reparacion fila' + i); await page.waitForTimeout(500); }
        }
        if (cfg.sumarNoRecuperable) {
            for (const i of cfg.sumarNoRecuperable) { await btnFila(i, 'Sumar 1 a no recuperable').click({ timeout: T }); log.push('sumado no recuperable fila' + i); await page.waitForTimeout(500); }
        }

        if (cfg.compromiso) {
            const d = cont().locator('input[type=date]');
            const k = await d.count();
            log.push('campos de fecha visibles: ' + k + ' ' + JSON.stringify(await d.evaluateAll(es => es.map(e => (e.closest('div')?.innerText || '').replace(/\n+/g, ' ').slice(0, 45)))));
            if (k > cfg.compromisoIdx) { await d.nth(cfg.compromisoIdx).fill(cfg.compromiso, { timeout: T }); log.push('compromiso puesto en idx' + cfg.compromisoIdx + ' = ' + cfg.compromiso); }
            else log.push('NO HAY campo de compromiso (total fechas: ' + k + ')');
            await page.waitForTimeout(1200);
        }

        if (cfg.volcar) {
            const t = await cont().locator('table').first().innerText();
            log.push('tabla: ' + t.replace(/\s+/g, ' ').slice(-260));
            log.push('inputs del modal: ' + JSON.stringify(await cont().locator('input,select,textarea').evaluateAll(es => es.slice(0, 14).map(e => ({ t: e.type || e.tagName, aria: e.getAttribute('aria-label'), ph: e.getAttribute('placeholder'), v: e.value, cerca: (e.closest('div')?.innerText || '').replace(/\s+/g, ' ').slice(0, 55) })))));
            log.push('avisos: ' + JSON.stringify((await cont().innerText()).replace(/\s+/g, ' ').match(/(No se puede[^.]{0,160}|falta[^.]{0,120}|compromiso[^.]{0,140}\.?)/g)?.slice(0, 6)));
        }

        const save = cont().getByRole('button', { name: /^guardar$/i }).last();
        const av = cont().getByRole('button', { name: /guardar avance/i });
        log.push('GUARDAR presente/disabled: ' + await save.count() + '/' + await save.isDisabled().catch(() => 'n/a'));
        log.push('AVANCE presente/disabled: ' + await av.count() + '/' + await av.isDisabled().catch(() => 'n/a'));

        if (cfg.avance) {
            const resp = page.waitForResponse(r => /recepcion-borrador/.test(r.url()), { timeout: 25000 });
            await av.click({ timeout: T });
            const r = await resp.catch(() => null);
            log.push('avance: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
            await page.waitForTimeout(3000);
        }

        if (cfg.guardar) {
            if (await save.isDisabled().catch(() => true)) {
                log.push('GUARDAR BLOQUEADO POR EL CLIENTE (no se envió petición)');
                log.push('texto del bloqueo: ' + (await cont().innerText()).replace(/\s+/g, ' ').match(/[^.]*?(No se puede|faltan|debe|compromiso)[^.]*\.?/i)?.[0]);
            } else {
                const resp = page.waitForResponse(r => /\/estado/.test(r.url()), { timeout: 25000 });
                await save.click({ timeout: T });
                const r = await resp.catch(() => null);
                log.push('estado: ' + (r ? r.request().method() + ' ' + r.status() : 'SIN_PETICION'));
                await page.waitForTimeout(3200);
                log.push('modal tras guardar: ' + (await cont().innerText().catch(() => 'CERRADO')).replace(/\n+/g, ' | ').slice(0, 200));
            }
        }
        log.push('errores UI: ' + JSON.stringify((await page.locator('[class*=text-red]').allInnerTexts()).filter(t => t && t.length > 4).slice(0, 6)).slice(0, 900));
        log.push('flash: ' + JSON.stringify(await page.locator('[role=status]').allInnerTexts().catch(() => [])).slice(0, 400));
        const filas2 = page.locator('table tbody tr');
        for (let i = 0; i < await filas2.count(); i++) {
            if ((await filas2.nth(i).locator('td').first().innerText().catch(() => '')).includes(cfg.op)) {
                log.push('fila final: ' + (await filas2.nth(i).innerText()).replace(/\n+/g, ' | ').slice(0, 300));
                break;
            }
        }
    } catch (e) {
        log.push('ERROR DRIVER: ' + String(e.message || e).split('\n')[0]);
    }
    return log.join('\n');
}
