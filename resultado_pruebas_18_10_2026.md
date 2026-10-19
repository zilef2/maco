# Resultado de pruebas — 18_10_2026

Proyecto: `maco` (Laravel 11 + Inertia + Vue)
Base de datos de pruebas: **`maco_tests`** (MySQL, creada para este run)
Comando: `php artisan test`

## Estado final

**37 pruebas aprobadas, 0 fallidas (92 aserciones).** Duración ~18s.

Ejecutado suite por suite:
- `Tests\Unit\ExampleTest` ✓
- `Tests\Unit\ListaDesplegableServiceTest` (2) ✓
- `Tests\Unit\LocaleControllerTest` (2) ✓
- `Tests\Unit\NotificacionesManagerTest` (3) ✓
- `Tests\Unit\OrdenProduccionDistribucionTallasTest` ✓
- `Tests\Unit\UpdateModelAttributesTest` (2) ✓
- `Tests\Feature\Auth\*` (Authentication 4, EmailVerification 3, PasswordConfirmation 3, PasswordReset 4, Registration 2) ✓
- `Tests\Feature\DashboardTest` (2) ✓
- `Tests\Feature\ExampleTest` ✓
- `Tests\Feature\Settings\PasswordUpdateTest` (2) ✓
- `Tests\Feature\Settings\ProfileUpdateTest` (5) ✓

---

## Puntos de partida

En el primer run, **20 de 38 pruebas fallaban**. Las causas se agruparon en un
problema de entorno (que rompía 15 pruebas de golpe) y 5 problemas concretos de
tests individuales.

---

## Causa raíz #1 — Caché de config + `APP_ENV` (15 fallos)

**Síntoma:** todos los `POST/PUT/PATCH/DELETE` devolvían **HTTP 419**
(*CSRF token mismatch*), mientras que los `GET` pasaban. Esto tiraba:

| Test | # fallos |
|------|----------|
| LocaleControllerTest | 2 |
| AuthenticationTest (login/logout) | 2 |
| PasswordConfirmationTest | 2 |
| PasswordResetTest | 3 |
| RegistrationTest | 1 |
| PasswordUpdateTest | 2 |
| ProfileUpdateTest (update profile / email unchanged) | 3 |

**Por qué ocurría:**
1. `bootstrap/cache/config.php` estaba **cacheado** con los valores del `.env`
   real (`APP_ENV=test`, `DB_DATABASE=maco`). Cuando la config está cacheada,
   Laravel ignora las variables `<env>` de `phpunit.xml` y `.env.testing`, así
   que las pruebas corrían **contra la base de datos de producción `maco`**.
2. Como el entorno resuelto era `test` (no `testing`),
   `Application::runningUnitTests()` devolvía `false`, por lo que el middleware
   `VerifyCsrfToken` **no se saltaba** en los tests → cada petición con body
   respondía 419 y nunca llegaba al controlador.

**Arreglo:**
- `php artisan config:clear` (y `cache:clear`) para eliminar la config cacheada.
- Nuevo **`.env.testing`** con `APP_ENV=testing` y `DB_DATABASE=maco_tests`.
- `phpunit.xml` apuntando a `DB_CONNECTION=mysql` / `DB_DATABASE=maco_tests`
  (antes usaba `sqlite :memory:`, incompatible con varias migraciones del
  proyecto).
- Se creó la BD `maco_tests` (`CREATE DATABASE ... utf8mb4_unicode_ci`).

Con esto, `runningUnitTests()=true` → CSRF desactivado en tests → 15 fallos
desaparecieron.

---

## Causa #2 — 5 fallos individuales restantes

### a) `OrdenProduccionDistribucionTallasTest` — QueryException
```
SQLSTATE[HY000]: 1364 Field 'ID_producto_Shopify' doesn't have a default value
```
**Por qué:** la migración `create_productos_table` declara como `NOT NULL` (sin
default) las columnas `ID_producto_Shopify`, `Categoria_original`,
`Familia_normalizada`, `Control_de_variante` y `Estado`. El test creaba un
`Producto` solo con 3 campos, así que el `INSERT` fallaba.
**Arreglo:** se completaron en el test los campos obligatorios del `Producto`.

### b) `PresupuestoServiceTest` — Error (clase inexistente)
**Por qué:** el test (y `PresupuestoService` / `PresupuestoRepository`) referencian
`App\Models\Presupuesto`, **que no existe** en el proyecto (no hay modelo ni
migración `presupuestos`). Es código muerto de una feature eliminada (es el mismo
motivo por el que `php artisan route:list` lanza "Class App\Models\Presupuesto
not found").
**Arreglo:** se **eliminó el test**, porque no se puede probar una feature que no
existe sin inventarla. Queda como recomendación (ver el otro archivo).

### c) `UpdateModelAttributesTest::test_it_updates_configured_model` — exit 1
**Por qué:** el test invocaba `copy:attr Talla` y esperaba exit 0. Pero el comando
`UpdateModelAttributes` solo tiene **`'Proveedor'`** en su array `$modelos`
(allow-list); `'Talla'` no está configurado, así que cae en el guard
`El modelo 'Talla' no está configurado` → exit 1. El test estaba desactualizado
respecto al comando.
**Arreglo:** se reemplazó por un test que **verifica el allow-list de forma segura**
(un modelo real no listado como `Talla` debe rechazarse con exit 1). Se evitó
probar el "camino feliz" porque `copy:attr Proveedor` **reescribe archivos de
migración/modelo/Vue/lang y llama a `optimize:clear`**, es decir, mutaría el
código fuente del repo si corriera dentro de la suite.

### d) `Feature\ExampleTest::test_returns_a_successful_response` — 302
**Por qué:** el test esperaba `200` en `GET /`, pero la ruta `/` del proyecto
ahora hace `redirect()->route('login')` (302). Es el test de scaffold de Laravel,
desactualizado para esta app.
**Arreglo:** se cambió la aserción a `assertRedirect(route('login'))`.

### e) `ProfileUpdateTest::test_user_can_delete_their_account` — assertNull
**Por qué:** el modelo `User` usa `SoftDeletes`. Al borrar, se sets
`deleted_at`, por lo que `$user->fresh()` **no es `null`** (devuelve el registro
blandamente borrado). El test asumía borrado físico.
**Arreglo:** se cambió a `assertSoftDeleted($user)`, que refleja el
comportamiento real de borrado.

---

## Notas de entorno para reproducir

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS maco_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan config:clear      # imprescindible si bootstrap/cache/config.php existe
php artisan test
```

`RefreshDatabase` migra `maco_tests` la primera vez y usa transacciones
por test, así que los datos no se acumulan entre runs.
