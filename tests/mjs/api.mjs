async page => {
    const cfg = {
        origen: 'http://127.0.0.1:8011/OrdenProduccion',
        llamadas: [
            { m: 'PUT', u: '/OrdenProduccion/5/estado', d: { estado_op_id: 2 }, nota: 'Cerrada -> Preparacion (transicion invalida en OP cerrada)' },
            { m: 'PUT', u: '/OrdenProduccion/4/estado', d: { estado_op_id: 5 }, nota: 'OP-103 Recibiendo -> En taller (no esta en puede_pasar_a)' },
            { m: 'PUT', u: '/OrdenProduccion/4/estado', d: { estado_op_id: 7 }, nota: 'OP-103 Recibiendo -> Cerrada manual' },
            { m: 'PUT', u: '/OrdenProduccion/5/recepcion-borrador', d: { recepciones: [] }, nota: 'recepcion-borrador sobre OP cerrada' },
            { m: 'POST', u: '/OrdenProduccion/5', d: { _method: 'PUT', observaciones: 'intento de edicion en cerrada' }, nota: 'update sobre OP cerrada' },
        ],
    };
    const log = [];
    const token = await page.evaluate(() => document.querySelector('meta[name=csrf-token]')?.content || '');
    log.push('csrf: ' + (token ? 'ok' : 'NO ENCONTRADO'));
    for (const l of cfg.llamadas) {
        try {
            const res = await page.evaluate(async a => {
                const r = await fetch(a.url, {
                    method: a.metodo,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/html',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Referer': a.origen,
                    },
                    body: a.metodo === 'GET' ? undefined : JSON.stringify(a.datos),
                });
                const txt = await r.text();
                return { status: r.status, finalUrl: r.url, len: txt.length, tail: txt };
            }, { url: cfg.origen.replace(/\/$/, '') + l.u, metodo: l.m, datos: l.d, origen: cfg.origen });
            const hits = res.tail.match(/(No se permite[^"<]{0,160}|Transici[oó]n no permitida[^"<]{0,120}|kit est[aá] incompleto[^"<]{0,200}|supera la cantidad esperada[^"<]{0,120}|fecha de compromiso[^"<]{0,140}|Falta registrar[^"<]{0,120}|no pertenece a esta orden[^"<]{0,80}|The .{0,60} field is required[^"<]{0,40})/g) || [];
            log.push(l.nota + ' => ' + l.m + ' ' + l.u + ' : HTTP ' + res.status + ' | errores: ' + JSON.stringify([...new Set(hits.map(h => h.replace(/\s+/g, ' ').slice(0, 170)))].slice(0, 3)));
        } catch (e) {
            log.push(l.nota + ' => EXCEPCION: ' + String(e.message || e).split('\n')[0]);
        }
    }
    return log.join('\n');
}
