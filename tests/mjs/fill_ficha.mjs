async page => {
    const out = [];
    const short = { timeout: 8000 };

    if (!(await page.locator('form').count())) {
        await page.getByRole('button', { name: 'Editar PT-018' }).click(short);
        await page.waitForTimeout(1500);
    }
    out.push('modal abierto: ' + (await page.locator('form').count()));

    const f = page.locator('form');
    const matTable = f.locator('table').filter({ has: page.locator('th', { hasText: /unidad/i }) }).last();

    for (const t of ['XS', 'S', 'M']) {
        await f.getByRole('button', { name: t, exact: true }).click(short);
        out.push('talla ' + t);
    }

    const pick = async (input, texto) => {
        for (let i = 0; i < 8; i++) {
            await input.fill(texto, short);
            await page.waitForTimeout(450);
            const opt = page.locator('[role=option]').filter({ hasText: texto });
            if (await opt.count()) { await opt.first().click(short); return true; }
        }
        return false;
    };

    async function addMaterial(buscar, cantidad) {
        await f.getByRole('button', { name: 'Agregar material' }).click(short);
        await page.waitForTimeout(700);
        const row = matTable.locator('tbody tr').filter({ has: page.locator('input') }).last();
        if (!(await pick(row.locator('input').first(), buscar))) { out.push('FALLO ' + buscar); return; }
        await row.locator('input[type=number]').first().fill(String(cantidad), short);
        out.push(buscar + ' x' + cantidad);
    }

    await addMaterial('Capellada (Genérico)', 3);
    await addMaterial('Cordones (Genérico)', 2);

    out.push('modal sigue abierto: ' + (await page.locator('form').count()));

    const resp = page.waitForResponse((r) => /Producto\/\d+/.test(r.url()), { timeout: 25000 });
    await f.getByRole('button', { name: 'Guardar' }).click(short);
    const r = await resp.catch(() => null);
    out.push(r ? `${r.request().method()} -> ${r.status()}` : 'SIN PETICION');
    await page.waitForTimeout(2500);
    const errs = await page.locator('[class*=text-red]').allInnerTexts();
    out.push('errores: ' + JSON.stringify(errs.filter(Boolean).slice(0, 8)));
    return out.join('\n');
}
