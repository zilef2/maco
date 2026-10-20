# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Administración de Macondas: el dueño/administración centraliza en el sistema lo que el resto de la operación reporta (talleres externos, calidad, bodega). Necesita ver el estado de toda la producción sin reconstruirlo por WhatsApp, Excel o papel.

El repositorio ya define roles `trabajador`, `admin` y `superadmin` con permisos por módulo; el uso confirmado hoy es centralizado, no de autoservicio para cada área.

## Product Purpose

Macondas fabrica calzado con talleres satélite y procesos externos (sublimación, corte de correas, pedrería, embarretado, ensamble). El sistema es el registro único de una orden de producción (OP): qué referencia se fabrica, con qué materiales, qué taller la ensambla, qué sale a proceso externo, qué se recibe de calidad y cuándo se cierra.

Éxito: saber en cualquier momento en qué estado está cada OP y qué le falta para entregarse.

## Positioning

El estado de una OP y lo que le falta para entregarse se leen en un solo registro; no se reconstruyen preguntando ni cruzando hojas sueltas de materiales, taller y calidad.

## Operating Context

- Producción por referencia y por talla: la cantidad se planifica y se distribuye por tallas.
- Los talleres externos reciben kits y devuelven pares; el material sigue siendo propiedad de Macondas.
- Procesos satélite: sublimación, corte de correas, pedrería, embarretado, entre otros.
- Calidad recibe pares aprobados, pendientes y bajas por reproceso.
- Los productos se identifican con código interno y datos de Shopify (ID, título, variantes); hoy se cargan o importan, no hay integración en vivo.
- Todo el uso es en español.

## Capabilities and Constraints

- Ciclo de la OP con estados: Borrador, Preparación, Esperando materiales, Procesos externos, Lista para taller, En taller, Recibiendo, Pendiente, Cerrada.
- Materiales con inventario, movimientos y costo teórico; ficha técnica con consumo estándar por material (una ficha por producto).
- Kit de taller, recepción de calidad, cierre de OP, pendientes de producción.
- Presupuesto, desarrollo y pagos; usuarios, roles y permisos por módulo.
- Notificaciones internas (paquete propio) e IMAP configurado.
- El producto se usa y se escribe en español; no se traduce la interfaz.
- El costo asociado real no se captura ni se muestra por ahora; solo el teórico.
- La ficha técnica de materiales se registra al crear el producto, no desde su propio listado.

## Brand Commitments

- Nombre: Macondas. Logotipo en `public/macondas_741x.webp`.
- Tono operativo y directo, en español.

## Evidence on Hand

- `docs/architecture/erd-maco.html` y `maco-db.erd.json`: modelo de datos real.
- `docs/flujo_general/flujo_produccion.html` (y sus JSON): flujo operativo documentado de la OP.
- `public/macondas cronograma.png`: cronograma del proyecto (sep 2026 – feb 2027).
- `database/seeders`: estados de OP y de material, roles, permisos y catálogos reales.
- No hay testimonios, casos de éxito ni métricas de negocio en el repositorio.

## Product Principles

1. Una OP, una historia: cada movimiento de material, taller o calidad queda en la misma orden.
2. El estado se ve sin preguntar: la pantalla debe responder "¿qué falta para entregar?".
3. Registrar cuesta poco: crear una OP o una ficha no puede sentirse como llenar un formato.
4. El taller es externo, el material no: el sistema vigila lo que sale y lo que vuelve.
5. Nada inventado: costos, fechas y cantidades vienen de datos reales o quedan vacíos.

## Accessibility & Inclusion

Sin requisito específico confirmado todavía. La legibilidad y el contraste se mantienen como piso en las tablas densas de operación.
