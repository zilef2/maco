async page => {
    const out = [];
    const T = 8000;
    const cb = i => page.locator('form [aria-label="Search for option"]').nth(i);

    await page.evaluate(() => { document.querySelectorAll('form button').forEach(b => { if (b.textContent.trim() === 'Cerrar') b.click(); }); });
    await page.waitForTimeout(800);
    await page.evaluate(() => { [...document.querySelectorAll('button')].find(b => b.textContent.trim() === 'Nueva orden').click(); });
    await page.waitForTimeout(1800);
    const f = page.locator('form');

    await f.getByRole('button', { name: 'Taller', exact: true }).click({ timeout: T });
    await page.waitForTimeout(500);

    await cb(0).click({ timeout: T });
    await page.waitForTimeout(700);
    await page.locator('[role=option]').first().click({ timeout: T });
    await page.waitForTimeout(500);
    out.push('taller=' + (await cb(0).innerText()).replace(/\n/g, ' ').slice(0, 30));

    await f.getByRole('button', { name: /Productos terminados/i }).click({ timeout: T });
    await page.waitForTimeout(900);

    // limpiar productos preseleccionados
    let des = f.locator('[aria-label="Search for option"]').nth(1).locator('.vs__deselect');
    for (let i = 0; i < 5 && await des.count(); i++) { await des.first().click(); await page.waitForTimeout(500); }

    await cb(1).click({ timeout: T });
    await page.waitForTimeout(800);
    await page.locator('[role=option]').filter({ hasText: 'bolsito' }).first().click({ timeout: T });
    await page.waitForTimeout(1800);
    out.push('producto=' + (await cb(1).innerText()).replace(/\n/g, ' ').slice(0, 40));

    const kits = f.locator('input.vs__search[placeholder="Elegir material..."]');
    const nKit = await kits.count();
    out.push('selects de material en kit: ' + nKit);
    for (let i = 0; i < nKit; i++) {
        const inp = kits.nth(i);
        await inp.click({ timeout: T });
        await page.waitForTimeout(800);
        const opts = page.locator('[role=option]');
        const c = await opts.count();
        if (c === 0) { out.push(`fila ${i}: SIN OPCIONES`); continue; }
        const first = await opts.first().innerText();
        await opts.first().click({ timeout: T });
        await page.waitForTimeout(600);
        out.push(`fila ${i} -> ${first.replace(/\n/g, ' ')}`);
    }
    return out.join('\n');
}
