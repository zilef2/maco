# Cómo actualizar la suite de tests tras el cambio de materiales

Documento de trabajo. **No** hay que ejecutar nada de esto para cerrar el cambio de código: es la
guía para quien revise la suite después. Reglas y motivación están en
[docs/material-base-reglas.md](file:///C:/laragon/www/maco/docs/material-base-reglas.md).

## 0. La regla que hay que buscar en los tests

Antes el rol se deducía de la **forma del código** (`CAT-BAS-X` = genérico, `CAT-BAS-X-Y` = hijo) y
el Excel no llevaba ninguna columna para decirlo. Ahora el rol es el booleano `materials.generico`:

- En fixtures: `Material::create([... 'generico' => true|false ...])`.
- En archivos de Excel: columna **«Base si o no»** (obligatoria; sin ella `ExcelMaterialesService`
  lanza `RuntimeException` y no se importa nada).
- En peticiones HTTP: campo `generico` en el payload de `Material.store` / `Material.update`.

Un fixture que **no** declare `generico` es ahora un material huérfano: se puede anotar en una
ficha, se reserva y se gasta de su propia `existencia_actual`. Eso es exactamente lo que usan hoy
`ContextoMateriales::materialBase()` y `SeedsOrdenesProduccion::material()`, así que la mayoría de
los tests de OP no cambian.

Antes de tocar nada, ejecutar la suite y anclar los fallos reales:

```bash
php artisan test --filter=Material          # Unit + Feature de materiales
php artisan test --filter=Excel              # importaciones (MPP, ficha, proveedores)
php artisan test --filter=FichaTecnica
php artisan test --filter=OrdenProduccion
```

Detalles de entorno que ya causaron falsos positivos: `queue:work` sigue corriendo código viejo
(reiniciarlo antes de probar el MPP) y `bootstrap/cache/config.php` está cacheado.

## 1. tests/Unit/ExcelMaterialesImportacionColumnasTest.php

`tests/Unit/ExcelMaterialesImportacionColumnasTest.php:20-51` arma la fila de títulos con 13
columnas y **sin «Base si o no»** → `registrosDesdeFilas()` revienta por columna requerida.

1. Agregar `'Base si o no'` a la cabecera y `'no'` en la fila de datos (orden libre: ya no se
   valida que `Procesado` vaya pegada a `Costo`).
2. `tests/Unit/ExcelMaterialesImportacionColumnasTest.php:66` busca `TEX-LFI-1`; el modelo rellena
   el número, así que el código guardado es `TEX-LFI-001`. Cambiar la aserción.
3. Caso nuevo que falta: una fila con `'Base si o no' => 'si'` y celdas de `Color`, `Talla / tamaño`,
   `Modelo / diseño / acabado`, `Proveedor habitual`, `Costo` e `Inventario Fisico` escritas. Se
   espera que `datos` no traiga `existencia_actual`, que `color`/`talla_tamano`/`modelo_diseno_acabado`
   queden `null`, que `colores` y `variantes` vengan vacíos y que `precio` sea `null`
   (`ExcelMaterialesService` descarta esas celdas en una fila base).
4. Caso nuevo: `'Base si o no' => 'quizas'` ⇒ error de fila con el texto
   `"Base si o no" debe ser si o no`.
5. Caso nuevo: el alias `Base`, `Es base`, `Material base` y `Generico` también resuelven a la
   columna `generico`.

## 2. tests/Feature/MaterialVariantesTest.php

Dos uploads de Excel con cabeceras incompletas (`tests/Feature/MaterialVariantesTest.php:121-128` y
`tests/Feature/MaterialVariantesTest.php:153-158`):

1. Añadir `'base si o no'` a la fila de títulos y `'no'` a cada fila de datos.
2. `test_importar_excel_rechaza_una_fila_con_colores_y_variantes`
   (`tests/Feature/MaterialVariantesTest.php:149`) describe una regla que **ya no existe**: un
   material sí puede tener colores y otras variantes a la vez. Reemplazar la aserción de rechazo por
   la verificación de que las dos listas se guardan independientes (mismo material, `tipo`
   `color` y `variante`).
3. Los helpers `materialBase()`/`materialProcesado()` de `tests/Feature/Concerns/ContextoMateriales.php:34-63`
   crean huérfanos. Si un test necesita una base, pasar el atributo explícitamente:
   `$this->materialBase('COM-CAP-055', null, ['generico' => true])`.

## 3. tests/Feature/Concerns/ContextoMateriales.php

`materialBase()` tiene nombre equívoco: **no** crea un material base (`generico`), crea un material
normal. Dos opciones, en orden de preferencia:

- Renombrar el método a `materialNormal()` (o `materialHuerfano()`) y actualizar sus usos, o
- Dejar el nombre y agregar un parámetro `bool $base = false` que escriba `generico`.

En cualquier caso conviene exponer un helper de familia para las pruebas nuevas:

```php
protected function familia(string $prefijo, int $numero): Material   // generico => true
protected function hijoDe(string $prefijo, int $numero, int $consecutivo, array $atributos = []): Material
```

El hijo solo es hijo si la base existe con `generico = true`; si no, es huérfano y los tests de OP
no preguntarían el concreto.

## 4. tests/Feature/FichaTecnicaMaterialesTest.php y tests/Feature/MaterialFichaTest.php

La ficha dejó de ser «solo genéricos»: ahora admite bases **y huérfanos**, y rechaza únicamente los
hijos de una base.

1. `FichaTecnicaMaterialesTest.php:15-186` y `MaterialFichaTest.php` usan `materialProcesado()` /
   `materialBase()` sin `generico` ⇒ son huérfanos y pasan a ser **válidos** en la ficha. Verificar
   que ningún test asuma lo contrario (mensajes tipo «solo admite genéricos»).
2. Caso nuevo: anotar en una ficha un material cuyo prefijo apunta a una base marcada
   (`COM-CAP-055` con `generico = true`, y la línea `COM-CAP-055-34`) ⇒ `FichaTecnicaService::guardar()`
   lanza `ValidationException` en la clave `materiales` con el texto
   «La ficha va sobre la base o sobre el material que no tiene base arriba: … Quite el hijo y anote
   su base.»
3. Caso nuevo sobre el servicio de Excel (`ExcelFichasProductoService`): la misma celda hijo
   **no** se rechaza, se reanuda a su base (`specialfunctions_fichaproducto::materialDesdeCodigo`
   devuelve el id de la base). Y un código inexistente ahora produce el aviso «no existe en la app»
   sin crear ningún material.
4. Caso nuevo: dos materiales con el mismo nombre ⇒ `materialDesdeNombre()` devuelve `AMBIGUO` y el
   aviso dice «coincide con varios registros; escriba el código».
5. Ojo con el orden del MPP: la hoja `ficha` corre después de la hoja `material` dentro de la misma
   transacción y `specialfunctions_fichaproducto::recargar()` se llama al empezar la ficha. Un test
   que cree el material y la ficha en el mismo archivo depende de esa recarga; si se prueba la ficha
   por separado (`importarRegistros($lectura)`) hay que llamar `recargar()` a mano o los índices
   memoizados no ven el material recién creado.
6. `importar()` y `importarRegistros()` **perdieron el parámetro `$usuarioId`**. Cualquier llamada con
   dos argumentos sigue funcionando (PHP tolera el extra), pero conviene limpiarlas.

## 5. tests/Feature/MaterialPrevisionTest.php y tests/Feature/MaterialCompraTest.php

- `MaterialPrevisionTest.php:22` y `:60` usan `COM-CAP-777` / `COM-CAP-778` con
  `materialProcesadoEnPrevision()` (`:102`). Son huérfanos ⇒ entran en la previsión. Si el test
  quiere una base excluida, poner `generico => true` y confirmar que la fila **no** aparece.
- `MaterialCompraTest.php:469-479` (aviso de compra sobre un genérico): el mensaje vigente es
  «Las bases no reciben compra: … Compre el material concreto (con color o talla) que cuelga de esa
  base.» Actualizar la aserción del texto y crear la base con `'generico' => true`, no por forma de
  código.

## 6. tests/Feature/OrdenProduccion/**

`SeedsOrdenesProduccion.php:119` sigue anunciando en su comentario «Material genérico/concreto»;
actualizar el comentario: los seeds crean huérfanos.

- `OrdenProduccionE2ETest.php:42-77`, `:93-125`, `:159-167`: como las fichas llevan huérfanos, la
  tarjeta «Material concreto de la orden» no debe exigir ninguna elección. Sirven para el caso
  contrario: agregar un escenario base + dos hijos y verificar que **solo** la línea base pide
  selector, que la huérfana se descuenta de su propia existencia y que
  `MaterialConcretoOpService::resolver()` devuelve el material de ficha sin elección.
- Si algún helper armaba el payload de `materiales_concretos` para líneas que ya no se preguntan,
  sobra: `validar()` solo exige las líneas con `generico = true`.

## 7. tests/Unit/ImportacionExcelNotificadorTest.php, ImportacionFalloMailableTest.php, Feature/ExcelImportacionMetricasTest.php

No tocan la regla del rol, pero sí los formatos que cambiaron:

- `ImportacionExcelNotificadorTest.php:60-67` y `ImportacionFalloMailableTest.php:48-60`: si
  fabrican filas de materiales, añadir la columna «Base si o no»; si solo prueban el aviso por
  `RuntimeException`, revisar los textos esperados («no existe en la app», «coincide con varios
  registros»).
- `ExcelImportacionMetricasTest.php:95`: la hoja de materiales del archivo de prueba necesita la
  columna obligatoria.

## 8. tests/Unit/MaterialRepositoryTest.php y MaterialServiceTest.php

Siguen sirviendo tal cual (los `TEX-LFI-1` son huérfanos y conservan color/talla). Dos mejoras
menores:

- Escribir `'generico' => false` en los payloads para que la intención no dependa del default.
- Agregar un test de `Material::create` con `'codigo_material' => 'COM-CAP-000'` ⇒
  `ValidationException` («Ningún número del código puede valer 0»), y otro con dos materiales del
  mismo código ⇒ rechazo por código tomado.

## 9. Cobertura nueva que vale la pena pedir

| Regla | Dónde probarla |
| --- | --- |
| `Material::basesDeHijos()` ignora familias sin `generico = true` | Unit, con tres registros: base, hijo, huérfano del mismo prefijo |
| `Material::familiaDeCodigo()` solo es vínculo: `COM-CAP-011` puede ser base | Unit |
| `saving` de una base limpia variantes, `maneja_*`, `color`, `talla_tamano`, `existencia_actual` y `gasto_diario` | Unit sobre un material existente con variantes |
| `nombre_mostrar` = `nombre_material ?: nombre_base` | Unit + Feature (listado de Materiales muestra el nombre del material) |
| Un código de cuatro tramos **no** es hijo si su familia no está marcada como base | Feature de ficha: la línea se acepta tal cual |
| El Excel de una fila base no escribe costo ni inventario | Unit de `ExcelMaterialesService::importar()` + `material_prices` vacío |
| La OP no pregunta materiales huérfanos | Feature de `OrdenProduccionController::store` sin `materiales_concretos` |

## 10. Lo que NO hay que reimplantar

- `asegurarGenericos()` / `MaterialesGenericosService`: reemplazados a propósito por la columna
  explícita. Si un test los busca, el test se borra, no el servicio.
- La creación automática del genérico que faltaba a partir del hijo citado en la ficha de producto.
  Hoy el archivo de fichas **no crea materiales**: rechaza y avisa.
- La validación de posición de la columna `Procesado`.
