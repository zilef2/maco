---
version: 1
slug: "resources-js-pages-material-ordencompra-vue"
primary_target: "resources/js/pages/Material/OrdenCompra.vue"
related_targets: ["app/Services/FaltantesOpService.php"]
---

# Surface brief — Material / Orden de compra

Route: `/Material/orden-compra` → `resources/js/pages/Material/OrdenCompra.vue`
Visitor mode: **Operate** (code-led build; `buildPath` is not recorded in `.impeccable/config.json`, so nothing was stored).

## Scope

Pantalla full-screen que responde una sola pregunta: cuánto hay que comprar de cada material para cubrir las OPs abiertas. Nace del botón «Orden de compra» de la barra superior, gemelo del de «Comprar faltantes». Confirmado por el usuario: no registra compra (no toca existencia), solo permite actualizar el precio unitario, y ese cambio crea una fila de `material_precios` con la fecha de hoy. La lista se organiza por material; las OPs que lo exigen quedan como detalle.

## Direction contract

THESIS: una hoja de compra viva — el faltante es el protagonista y cada peso se puede rastrear hasta la OP que lo pide. Rechaza el patrón de tabla de administración con 12 columnas y filtros pesados que usa el resto del listado.

OWN-WORLD: mismo lenguaje del app Tailwind (`bg-card`, `border-border`, `text-muted-foreground`, `ring-ring`, redondeles `rounded-md`/`rounded-full`, heroicons solid, `tabular-nums`), con una jerarquía propia: cifras grandes de faltante en `text-2xl semibold`, etiquetas `text-[10px] uppercase tracking` para campos, y el color solo haciendo trabajo (ámbar = falta precio, verde = cubierto).

STORY: el usuario entra, ve en segundos el total a comprar, cuántos materiales y cuántas OPs; recorre fila por fila con el precio ya puesto (o marcado como pendiente), corrige los que cambiaron, guarda, y expande una fila para ver qué OPs lo piden.

FIRST VIEWPORT: cabecera con título y una línea de criterio de cálculo; franja de tres cifras (total estimado / materiales faltantes / OPs abiertas); toolbar compacta de una fila (buscador + «sin precio» + recargar + guardar cambios); debajo, la lista de materiales: cada fila = código — nombre, unidad, existencia → requerido, **faltante** en grande, importe, input de precio; el detalle de OPs aparece al expandir. Barra de acción inferior fija cuando hay cambios sin guardar.

FORM: fila expandible agrupada por material, ordenada por falta de precio y luego por faltante descendente; orden de llegada de las OPs como chips dentro del detalle.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.
