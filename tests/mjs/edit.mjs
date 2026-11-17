async page => {
    const log = [];
    await page.waitForTimeout(2500);
    const campos = await page.evaluate(() => [...document.querySelectorAll('input,select,textarea')].slice(0, 40).map(e => ({
        t: e.type || e.tagName.toLowerCase(),
        ph: e.getAttribute('placeholder') || '',
        name: e.name || '',
        v: (e.value || '').slice(0, 24),
        dis: !!e.disabled || e.readOnly || !!e.closest('[disabled]'),
        label: (e.closest('div')?.previousElementSibling?.innerText || e.previousElementSibling?.innerText || '').slice(0, 28),
    })));
    log.push('campos: ' + JSON.stringify(campos));
    const avisos = await page.locator('[class*=amber], [class*=yellow], [class*=text-red], [class*=italic]').allInnerTexts();
    log.push('avisos: ' + JSON.stringify(avisos.filter(t => t && t.length > 5).slice(0, 8)).slice(0, 700));
    log.push('texto pagina: ' + (await page.locator('main, #app').first().innerText()).replace(/\n+/g, ' | ').slice(0, 900));
    return log.join('\n');
}
