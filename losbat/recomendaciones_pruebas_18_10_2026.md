# Recomendaciones de pruebas — maco

Dos bloques: (A) cómo hacer las pruebas más sencillas de mantener y (B) qué
pruebas faltan (máximo 2, las de mayor valor).

---

## A. Hacer las pruebas más sencillas

1. **Blindar el entorno de test contra la caché de config.**
   El fallo más caro (15 tests con HTTP 419) venía de `bootstrap/cache/config.php`
   cacheado con `APP_ENV=test` + `DB_DATABASE=maco`. Recomendaciones:
   - Dejar **`.env.testing`** versionado (ya creado: `APP_ENV=testing`,
     `DB_DATABASE=maco_tests`) para que el entorno nunca dependa del `.env` real.
   - Añadir a `phpunit.xml` un paso o un script de composer que ejecute
     `php artisan config:clear` antes de `php artisan test`, o documentar que
     **jamás** se debe correr la suite con la config cacheada.

2. **Base de datos aislada obligatoria.** `phpunit.xml` ya apunta a MySQL
   `maco_tests` (antes `sqlite :memory:`, que rechinaba con varias migraciones).
   Mantener `RefreshDatabase` (migra + transacción por test) como estándar en
   todos los tests, incluso los de `tests/Unit` que hoy no lo usan.

3. **Factories para no repetir columnas `NOT NULL`.** El test de distribución de
   tallas falló porque hay que rellenar a mano `ID_producto_Shopify`,
   `Categoria_original`, etc. Con una `ProductoFactory` (y `MaterialFactory`,
   `EstadoOperacionFactory`, `OrdenProduccionFactory`) los tests quedan de una
   línea (`Producto::factory()->create()`) y no se rompen cuando cambia el
   schema. Esto elimina la mayor fuente de fragilidad de la suite.

4. **Trait de ayuda para requests autenticadas.** Muchos tests_feature repiten
   `User::factory()->create()` + `actingAs`. Un `use CreatesAuthenticatedUser;`
   con un usuario que ya tenga los roles/permisos (`HasRoles`) reduce plantilla y
   permite probar endpoints protegidos sin re-implementar el setup cada vez.

5. **Sacar los comandos destructivos de la suite.** `copy:attr` reescribe
   migraciones/modelos/Vue/lang y llama `optimize:clear`; no es testeable de forma
   segura. Dejar solo tests del allow-list (que no tocan disco) como se hizo, y
   documentar que el "camino feliz" se valida a mano, no en CI.

6. **Limpiar código muerto que ensucia las pruebas.** `PresupuestoService` /
   `PresupuestoRepository` referencian `App\Models\Presupuesto` (inexistente), que
   además rompe `php artisan route:list`. O se crea la feature completa (modelo +
   migración + factory) o se borran ambas clases; así el test que se eliminó
   podría volver con sentido.

---

## B. Pruebas que faltan (máximo 2)

Las features recientes de producción no tienen **ninguna** cobertura automatizada.
Las dos de mayor valor y riesgo de regresión:

1. **Flujo de estados de la Orden de Producción (`Borrador ↔ Preparación`).**
   Cubre lo pedido en este sprint y es la puerta de entrada de todo el módulo OP:
   - `POST /OrdenProduccion/borrador` (guardarBorrador): persiste una OP con
     estado **Borrador** aunque falten `taller_id`/`user_id`/productos (ahora
     nullable) y autogenera `numero_op` `OP-{n}` solo cuando es nueva y está vacía.
   - Volver a guardar con el mismo `id` **no duplica** la OP.
   - Al actualizar desde un Borrador (create "Guardar"), el estado salta
     automáticamente a **Preparación** (`eraBorrador → forceFill Preparación`).
   - `GET /OrdenProduccion` (index) expone `borradorUltimo` = última OP en
     Borrador, para el botón "Continuar última OP".
   - Afirmaciones clave: `assertDatabaseHas('orden_produccions', ['estado_op_id'
     => <Borrador|Preparación id>])` y que `borradorUltimo` apunta a la OP correcta.

2. **Salida de materiales al taller + descuento de inventario.**
   Es la lógica con dinero/stock involucrada, la que más conviene fijar con tests:
   - `materialesOp($orden)`: el requerimiento por material es
     `consumo_estandar × cantidad_planificada` agregado desde la ficha técnica,
     y calcula el `faltante`.
   - `salidaOp`: **solo** descuenta inventario si alcanza el `existencia_actual`;
     con stock insuficiente debe **rollback** (no crea ningún `ProcesoExterno` y
     ningún `material_movimiento`), sin stock suficiente crea **un
     `ProcesoExterno` por material** y registra el movimiento de salida.
   - Afirmaciones: `existencia_actual` baja exactamente lo consumido,
     `assertDatabaseCount('proceso_externos', N)` por material, y que un caso de
     faltante deja el inventario **igual** (transacción revertida).

> Estas dos pruebas cubrirían el mayor volumen de código nuevo y de negocio del
> proyecto. Recomendadas en ese orden: (1) valida el workflow de estados,
> (2) protege el inventario.

