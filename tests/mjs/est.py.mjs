async page => {
    const row = page.locator('table tbody tr').filter({ hasText: 'OP-104' });
    await row.locator('button[aria-label="Cambiar estado"]').click({ timeout: 8000 });
    await page.waitForTimeout(2000);
    const body = await page.evaluate(() => {
        const d = [...document.querySelectorAll('[role=dialog], .fixed')].find(x => /Cambiar estado|estado de la orden|Estado/i.test(x.innerText) && x.querySelector('button'));
        if (!d) return 'NO MODAL';
        const btns = [...d.querySelectorAll('button')].map(b => b.textContent.trim().replace(/\s+/g, ' ').slice(0, 46)).filter(Boolean);
        return JSON.stringify({ texto: d.innerText.replace(/\n+/g, ' | ').slice(0, 900), botones: btns.slice(0, 22) });
    });
    return body;
}
