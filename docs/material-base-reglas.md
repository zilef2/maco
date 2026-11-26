# Materiales: el rol lo declara el archivo, no el código

Resumen de lo que cambió en el sistema de materiales (2026-11-26) y de lo que sigue siendo
obligatorio en los formularios `Material/Create.vue` y `Material/Edit.vue`.

## 1. La regla que se eliminó

Antes, la **forma del código** decidía qué era el registro:

| Escritura | Rol antiguo |
| --- | --- |
| `CAT-BAS-X` (tres tramos) | genérico / familia |
| `CAT-BAS-X-Y` (cuatro tramos) | hijo concreto |

Esa regla ya **no aplica**. Un código `XXX-YYY-011` puede ser base, hijo o huérfano: lo decide
únicamente el booleano `materials.generico`, que se escribe desde

- la columna **«Base si o no»** del Excel de materiales / MPP, y
- el interruptor **«Material base»** de `Material/Create.vue` y `Material/Edit.vue`.

## 2. Los tres roles ahora

| Rol | Cómo se declara | Qué hace |
| --- | --- | --- |
| **Base** (antes «genérico») | `generico = true` | Referente de la familia. Es la línea que se anota en la ficha técnica. No almacena existencia, no declara variantes, no tiene proveedor habitual ni precio de compra. |
| **Hijo** | `generico = false` **y** el código sin su último tramo existe y está marcado como base | Se gasta y se compra. La OP obliga a elegir uno por cada línea base de la ficha. |
| **Huérfano** | `generico = false` y **no** hay ninguna base marcada sobre su prefijo | Se anota en la ficha tal como está, se reserva y se consume de su propia `existencia_actual`. La OP **no** lo pregunta. |

El vínculo sigue siendo el prefijo: `Material::familiaDeCodigo()` devuelve el código sin el último
tramo y `Material::basesDeHijos()` arma el mapa hijo → base solo para los registros cuya familia
existe **con `generico = true`**. Un `COM-CAP-055-34` sin `COM-CAP-055` base arriba es huérfano.

## 3. Qué se llevó puesto el cambio

| Punto | Antes | Ahora |
| --- | --- | --- |
| `app/Models/Material.php` | `esCodigoFamilia`, `esCodigoHijo`, `tieneNumeroEnCero`, `familiaMasAltaDelPar`, `siguienteNumeroFamilia`, `siguienteNumeroHijo` | Solo `normalizarCodigo`, `normalizarFragmento`, `abreviar`, `familiaDeCodigo`, `basesDeHijos`, `siguienteCodigoDelPar`, `rechazarNumeroEnCero`. |
| Alta de un concreto sin familia | el modelo la creaba o rechazaba («primero registre su familia») | no se crea ni se rechaza nada: sale huérfano |
| Columna del Excel | no existía (el rol se deducía del código) | **«Base si o no» es obligatoria**; sin ella no se importa nada |
| Columna `Nombre del material` | no existía | nueva columna → `materials.nombre_material`; en blanco se muestra `nombre_base` (`Material::nombre_mostrar`) |
| Columna `Inventario Fisico` | `Cantidad` | alias nuevo; sigue escribiendo `existencia_actual` |
| Orden de columnas del Excel | `Procesado` tenía que ir pegada a `Costo`/`Cantidad` | **no importa el orden**: la hoja se lee por rótulos y alias |
| Fila de una base en el Excel | se guardaban color, talla, costo, proveedor e inventario si venían escritos | esas celdas **se ignoran** (igual que en el formulario) |
| Ficha técnica (pantalla y Excel) | solo admitía genéricos | admite **bases y huérfanos**; un hijo citado en el Excel se reanuda a su base y el formulario rechaza la línea hijo |
| Ficha desde Excel | creaba el genérico faltante a partir del hijo | **ya no crea materiales**: todo lo citado tiene que existir en la app o venir en la hoja `material` del mismo archivo |
| OP | preguntaba el concreto de **todas** las líneas de la ficha | pregunta solo las líneas **base**; las huérfanas se consumen tal cual, sin selector |
| Registro de compra | — | sigue rechazando las bases: se compra el concreto de su familia |

## 4. Formularios de material: obligatorio / opcional

Los campos del formulario los genera `Material::getFillableWithTypes()` (las columnas reales de la
tabla ∩ `$fillable`), así que cualquier columna nueva aparece sola. Los textos van en
`lang/es/app.php`.

### `Material/Create.vue`

| Campo | Base (`generico = true`) | No base |
| --- | --- | --- |
| **Categoría** | obligatorio | obligatorio |
| **Nombre base** | obligatorio | obligatorio |
| **Unidad inventario** | obligatorio | obligatorio |
| **Estado** | obligatorio (toggle Activo/Inactivo) | obligatorio |
| **Precio de última compra** + fecha | **no se pide**: la base no recibe compra | obligatorio (`last_purchase_price ≥ 0`) |
| **Código material** | nunca se digita: se arma con el par y el primer número libre del prefijo | igual |
| **Código categoría** / **Código base** | opcionales; si quedan vacíos se abrevia Categoría / Nombre base (hasta 3 letras) | igual |
| **Nombre del material** | **opcional** (está en `camposIgnorados`); en blanco se muestra el Nombre base | igual |
| **Modelo / diseño / acabado**, **Color**, **Talla / tamaño**, **Variante sugerida** | ocultos, limpiados y **no se envían** | opcionales |
| **Proveedor habitual** | oculto y **no se envía** (el formulario ni siquiera exige que existan proveedores para crear una base) | **opcional** en la interfaz, obligatorio solo si hay proveedores cargados |
| **Existencia actual** | oculto y forzado a 0 (el modelo lo deja en `null`) | opcional, arranca en 0 |
| **Maneja color / otra variante / talla** | el toggle de talla se oculta y los tres se fuerzan a falso | opcionales |
| **Procesado** | opcional (0/1) | opcional |
| **Observaciones** | opcional | opcional |
| **Ficha de materiales** (`EditorMateriales`) | opcional | opcional |

Servidor (`MaterialController::store`): `last_purchase_price` / `last_purchase_date` son
`nullable` cuando `generico` es verdadero y `required` en caso contrario; `codigo_categoria` y
`codigo_base` admiten solo letras; `codigo_material` **se descarta del request** y lo forma el
modelo. Además `Material::aplicarReglaDelCodigo()` rechaza un número 0 en el código y un código ya
usado por otro material.

### `Material/Edit.vue`

Mismos campos, con tres diferencias:

- `codigo_material` y `existencia_actual` están **bloqueados** (la existencia se mueve con
  «Registrar compra» o con el Excel).
- `last_purchase_price` y `last_purchase_date` **nunca** se envían desde este formulario: los
  escribe solo el registro de compra.
- Cuando el registro es base se suman a la lista de no-enviados `existencia_actual`,
  `modelo_diseno_acabado`, `color`, `talla_tamano`, `variante_atributo_sugerido` y
  `proveedor_habitual`, para no pisar lo que ya tenían.

Para pasar un material a base hay que desocupar primero sus variantes: el `saving` del modelo
borra `material_variants` del registro cuando `generico = true`.

## 5. Excel de materiales (17 columnas de la plantilla)

`Código material · Código categoría · Categoría · Código base · Nombre base ·
Modelo / diseño / acabado · Color · Talla / tamaño · Base si o no · Nombre del material ·
Unidad inventario · Proveedor habitual · Estado · Costo · Procesado · Inventario Fisico ·
Observaciones`

Obligatorias: **Código material, Nombre base, Base si o no, Estado, Procesado**.
Todo lo demás se puede dejar fuera del archivo o en blanco.

Detalles que vale la pena tener presentes:

- Los rótulos se normalizan (sin mayúsculas, tildes ni puntuación) y se aceptan alias:
  `Inventario` / `Existencia` / `Cantidad` → existencia actual; `Base` / `Es base` / `Material base` /
  `Generico` → Base si o no; `Nombre` / `Nombre comercial` → según el caso.
- Sí/no admite `si, sí, s, 1, true, verdadero, v, x` y `no, n, 0, false, falso`; vacío = no.
  Cualquier otro texto cancela la subida avisando la fila exacta.
- Una fila base no escribe Color, Talla, Modelo, Proveedor, Costo ni Inventario Fisico; el
  proveedor de una fila base ni siquiera se valida, así que un nombre inexistente no cancela
  la subida por esa fila.
- `Costo` es el **Precio** de la app (va a `material_prices` con la fecha de hoy), no un campo del
  material.
- Volver a subir el mismo archivo no duplica: las filas se emparejan por `codigo_material`
  normalizado.

## 6. Dónde vive cada regla

| Regla | Archivo |
| --- | --- |
| Forma del código, relleno, rechazo del 0 y del código tomado | [app/Models/Material.php](file:///C:/laragon/www/maco/app/Models/Material.php) |
| Limpieza de variantes/inventario de una base | `Material::limpiarVariantesPorGenerico()` (evento `saving`) |
| Lectura del Excel de materiales | [app/Services/ExcelMaterialesService.php](file:///C:/laragon/www/maco/app/Services/ExcelMaterialesService.php) |
| Plantilla descargable y textos de columna | [app/Services/PlantillasExcelService.php](file:///C:/laragon/www/maco/app/Services/PlantillasExcelService.php) |
| Ficha en pantalla (select y guardado) | [app/Services/FichaTecnicaService.php](file:///C:/laragon/www/maco/app/Services/FichaTecnicaService.php) |
| Ficha desde Excel (reapuntar hijo a base, no crear nada) | [app/Services/ExcelFichasProductoService.php](file:///C:/laragon/www/maco/app/Services/ExcelFichasProductoService.php) y [app/Services/specialfunctions_fichaproducto.php](file:///C:/laragon/www/maco/app/Services/specialfunctions_fichaproducto.php) |
| Elección del concreto en la OP | [app/Services/MaterialConcretoOpService.php](file:///C:/laragon/www/maco/app/Services/MaterialConcretoOpService.php) y [resources/js/composables/materialesConcretosOp.ts](file:///C:/laragon/www/maco/resources/js/composables/materialesConcretosOp.ts) |
| Textos de ayuda de la pantalla | [resources/js/pages/Material/Create.vue](file:///C:/laragon/www/maco/resources/js/pages/Material/Create.vue), [Edit.vue](file:///C:/laragon/www/maco/resources/js/pages/Material/Edit.vue), [Index.vue](file:///C:/laragon/www/maco/resources/js/pages/Material/Index.vue), [ImportarMaterialModal.vue](file:///C:/laragon/www/maco/resources/js/pages/Material/ImportarMaterialModal.vue) |
| Reglas documentadas en la app | [resources/js/data/explicaciones.ts](file:///C:/laragon/www/maco/resources/js/data/explicaciones.ts) (temas `material-codigo-del-material`, `material-generico`, `material-columna-procesado-del-excel`) |
