async page => {
    const out = [];
    await page.evaluate(() => document.querySelectorAll('button').forEach(b => { if (b.textContent.trim() === 'Nueva orden') b.click(); }));
    await page.waitForTimeout(1800);
    const f = page.locator('form');
    await f.getByRole('button', { name: 'Taller', exact: true }).click({ timeout: 8000 });
    await page.waitForTimeout(600);
    await f.locator('[aria-label="Search for option"]').first().click({ timeout: 8000 });
    await page.waitForTimeout(700);
    await page.locator('[role=option]').first().click({ timeout: 8000 });
    await page.waitForTimeout(600);
    await f.getByRole('button', { name: /Productos terminados/i }).click({ timeout: 8000 });
    await page.waitForTimeout(900);

    const combos = f.locator('[aria-label="Search for option"]');
    const n = await combos.count();
    out.push('combos: ' + n);
    for (let i = 0; i < n; i++) {
        const ctx = await combos.nth(i).evaluate(e => {
            const tr = e.closest('tr');
            return tr ? tr.innerText.replace(/\n/g, '|').slice(0, 40) : (e.closest('table') ? 'tabla:' + e.closest('table').querySelector('thead').innerText.replace(/\n/g, '|').slice(0, 30) : 'suelto');
        });
        out.push(`combo${i} -> ${ctx}`);
    }
    await combos.first().click({ timeout: 8000 });
    await page.waitForTimeout(800);
    out.push('opciones: ' + JSON.stringify((await page.locator('[role=option]').allInnerTexts()).slice(0, 8)));
    return out.join('\n');
}
