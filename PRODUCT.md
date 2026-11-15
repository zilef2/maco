# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Personal operativo de una fábrica de calzado (MacO): planeación/compras, bodega y jefes de producción. Trabajan la pantalla durante la jornada, con datos reales de producción en curso, en español, y vuelven a la misma vista varias veces al día. No son diseñadores ni técnicos de software: esperan formularios, tablas y botones que hagan lo que dicen.

Aud secundario: desarrollo interno (superadmin) que importa Excel masivos y revisa bitácora de actividad.

## Product Purpose

Un ERP interno de producción de calzado: materiales e inventario, productos y fichas técnicas, órdenes de producción (OP) con su kit, proveedores y terceros, procesos externos, costos y compras. Existe para que la fábrica sepa en cada momento qué tiene, qué le hace falta para las OP abiertas y cuánto le cuesta. Éxito = que la planeación y la compra se decidan con datos de la app y no con cuaderno.

## Positioning

La carencia se calcula desde el trabajo real: el kit de cada OP abierta contra la existencia actual del material, no contra una previsión de días ni contra una foto vieja del stock. La app sabe qué OP concreta exige cada faltante y en qué cantidad, y solo las OP que aún no llegaron a taller generan necesidad de compra.

## Operating Context

- Laravel + Inertia + Vue 3 (`<script setup lang="ts">`) + Tailwind, heroicons, shadcn-vue (`@/components/ui`), vue-select; todo el texto de interfaz en español.
- Flujo de la OP por estados (`estado_operacion`): desde «Esperando materiales» / «Lista para taller» hasta «En taller», «Recibiendo», «Terminada», «Cerrada». Los estados desde «En taller» ya descuentan stock y por eso quedan fuera del cálculo de faltantes.
- El material genérico (`generico`, código `CATEGORIA-BASE-0`) es el único que entra a la ficha técnica; uno por par categoría-base.
- Carga masiva desde plantillas Excel por entidad (Materiales, Productos, Ficha de producto, etc.) con validación previa que rechaza el archivo completo.
- Barra superior fija con la alerta «Comprar faltantes» y las notificaciones; sidebar con grupos Inventario / Orden Producción / terceros / fichas.
- Precios de material en histórico (`material_precios`: precio, fecha); el vigente es el de la compra más reciente.

## Capabilities and Constraints

- Permisos por ability (`update Material`, `read Material`, `isadmin`, `issuperadmin`); la alerta de faltantes solo se comparte si el usuario puede `update Material`.
- Escrita de compras: sube `existencia_actual`, registra movimiento de inventario, crea el precio del día y promueve OPs de la cola FIFO cuando el kit se completa.
- El precio vigente solo cambia creando una fila nueva de histórico con fecha del día; nunca se sobreescribe el precio anterior.
- Cachés con versión (`op_index_version`, clave del listado de materiales) que toda escritura debe invalidar.
- Copy de interfaz en español, tono directo y operativo; cifras con formato `zilef_nuber_format` y fechas con `formatDate`.
- Sin sistema de diseño documentado (no existe DESIGN.md): la autoridad visual es el código existente — todas las páginas de listado replican el mismo estilo y los modales reaprovechan `Modal.vue`, `PrimaryButton`, `SecondaryButton`, `TextInput`, `InputError`.
- Decisiones abiertas: nada de exportación/PDF de la orden de compra está decidido; hoy la orden de compra es una pantalla de lectura y ajuste de precios.

## Brand Commitments

- Nombre MacO; idioma de la app español siempre (se ignora cualquier `locale` de sesión).
- Ayuda en línea dentro de la propia pantalla: los importadores explican sus reglas en texto plano y en la página «Explicaciones» (`data/explicaciones.ts`).

## Evidence on Hand

- Datos reales de la fábrica en las tablas del proyecto (materiales, OPs, kits, precios, movimientos).
- Ausencias que no se deben inventar: logotipos nuevos, fotografías de producto, testimonios, precios de referencia de proveedores, benchmarks, capturas de marketing.

## Product Principles

1. El número que se muestra tiene que ser trazable: de la carencia se debe poder llegar a la OP que la exige.
2. No inventar decisiones del usuario: la app calcula y sugiere, la persona escribe el precio o la cantidad.
3. Todo lo que se guarda deja histórico (movimientos, precios, bitácora de actividad).
4. Una pantalla, una tarea: los listados filtran y ordenan; las acciones pesivas viven en su propio sitio.
5. Español operativo y cifras con separadores, nunca texto de relleno ni en inglés.

## Accessibility & Inclusion

Uso diario en escritorio con mouse y teclado; iconos con `title`/`aria-label`, foco visible con `ring`, cifras alineadas (`tabular-nums`) y estados anunciados (`role="status"`). Sin requisito de certificación documentado.
