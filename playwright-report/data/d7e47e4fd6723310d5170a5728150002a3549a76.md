# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: orden-produccion.spec.ts >> Orden de producci�n >> 1. se puede crear una OP
- Location: tests\e2e\orden-produccion.spec.ts:45:5

# Error details

```
Test timeout of 30000ms exceeded while running "beforeEach" hook.
```

```
Error: page.waitForURL: Test timeout of 30000ms exceeded.
=========================== logs ===========================
waiting for navigation until "load"
============================================================
```

# Page snapshot

```yaml
- generic [ref=e5]:
  - generic [ref=e6]:
    - link "Macondas Macondas dev" [ref=e7] [cursor=pointer]:
      - /url: http://127.0.0.1:8011/
      - img "Macondas" [ref=e9]
      - generic [ref=e10]: Macondas dev
    - generic [ref=e11]:
      - heading "Macondas dev" [level=1] [ref=e12]
      - paragraph [ref=e13]: Ingrese a su cuenta
  - generic [ref=e15]:
    - generic [ref=e16]:
      - generic [ref=e17]: Email
      - textbox "Email" [ref=e18]:
        - /placeholder: email@example.com
        - text: e2e@maco.test
    - generic [ref=e19]:
      - generic [ref=e20]:
        - generic [ref=e21]: Contraseña
        - link "¿Olvidaste tu contraseña?" [ref=e22] [cursor=pointer]:
          - /url: http://127.0.0.1:8011/forgot-password
      - textbox "Contraseña" [ref=e23]:
        - /placeholder: Password
        - text: password123
    - generic [ref=e25]:
      - checkbox "Recuerdame" [ref=e26]
      - generic [ref=e27]: Recuerdame
    - button "Ingresar" [disabled]
```

# Test source

```ts
  1   | import { test, expect, Page } from '@playwright/test';
  2   | 
  3   | const USUARIO_EMAIL = 'e2e@maco.test';
  4   | const USUARIO_PASSWORD = 'password123';
  5   | 
  6   | async function login(page: Page) {
  7   |     await page.goto('/login');
  8   |     await page.locator('input[id="email"]').fill(USUARIO_EMAIL);
  9   |     await page.locator('input[id="password"]').fill(USUARIO_PASSWORD);
  10  |     await page.getByRole('button', { name: 'Ingresar' }).click();
> 11  |     await page.waitForURL((url) => !url.pathname.includes('/login'));
      |                ^ Error: page.waitForURL: Test timeout of 30000ms exceeded.
  12  | }
  13  | 
  14  | async function irIndexOp(page: Page) {
  15  |     await page.goto('/OrdenProduccion');
  16  |     // Esperar a que la tabla deje de mostrar esqueletos y cargue datos
  17  |     await page.locator('tbody.op-page, text="Aún no hay órdenes de producción"').first().waitFor();
  18  | }
  19  | 
  20  | async function seleccionarSelect(page: Page, placeholder: string, valor: string) {
  21  |     const input = page.locator(`input[placeholder="${placeholder}"]`);
  22  |     await input.click();
  23  |     await input.fill(valor);
  24  |     const opcion = page.locator('.vs__dropdown-option').filter({ hasText: valor }).first();
  25  |     await opcion.waitFor({ state: 'visible', timeout: 10000 });
  26  |     await opcion.click();
  27  | }
  28  | 
  29  | async function filaOp(page: Page, numeroOp: string) {
  30  |     return page.locator('tr').filter({ hasText: numeroOp });
  31  | }
  32  | 
  33  | async function abrirCambiarEstado(page: Page, numeroOp: string) {
  34  |     const fila = filaOp(page, numeroOp);
  35  |     await fila.getByRole('button', { name: 'Cambiar estado' }).click();
  36  |     await page.getByRole('heading', { name: /Cambiar estado/ }).waitFor();
  37  | }
  38  | 
  39  | test.describe('Orden de producci�n', () => {
  40  |     test.beforeEach(async ({ page }) => {
  41  |         await login(page);
  42  |         await irIndexOp(page);
  43  |     });
  44  | 
  45  |     test('1. se puede crear una OP', async ({ page }) => {
  46  |         await page.getByRole('button', { name: 'Nueva orden' }).click();
  47  | 
  48  |         const modal = page.getByRole('heading', { name: /Nueva orden|Continuar/ }).first();
  49  |         await modal.waitFor();
  50  | 
  51  |         // Tipo: taller (por defecto ya viene seleccionado)
  52  |         await page.getByRole('button', { name: 'Taller' }).click();
  53  | 
  54  |         // Seleccionar taller
  55  |         await seleccionarSelect(page, 'Seleccione un taller...', 'Taller E2E');
  56  | 
  57  |         // Seleccionar producto con ficha
  58  |         await seleccionarSelect(page, 'Buscar y seleccionar productos...', 'Producto E2E');
  59  | 
  60  |         // La distribuci�n se carga sola (talla Única, qty 1)
  61  |         await page.getByText('Única: 1').waitFor({ state: 'visible', timeout: 10000 });
  62  | 
  63  |         // Guardar / Agregar
  64  |         await page.getByRole('button', { name: 'Nuevo' }).click();
  65  | 
  66  |         // La modal se cierra y volvemos al index
  67  |         await expect(page.getByRole('heading', { name: /Nueva orden|Continuar/ }).first()).toBeHidden();
  68  | 
  69  |         // Verificar que apareci� una OP nueva en estado Preparaci�n (numero OP-XXX)
  70  |         const opNueva = page.locator('tr').filter({ hasText: /^OP-\d+$/ }).first();
  71  |         await expect(opNueva).toContainText('Preparaci�n');
  72  |     });
  73  | 
  74  |     test('2. avanzar preparaci�n -> esperando materiales aunque falten materiales', async ({ page }) => {
  75  |         await abrirCambiarEstado(page, 'E2E-OP-PREP');
  76  | 
  77  |         await page.getByRole('button', { name: 'Esperando materiales' }).click();
  78  |         await page.getByRole('button', { name: 'Guardar' }).click();
  79  | 
  80  |         await expect(page.getByRole('heading', { name: /Cambiar estado/ })).toBeHidden();
  81  | 
  82  |         const fila = filaOp(page, 'E2E-OP-PREP');
  83  |         await expect(fila).toContainText('Esperando materiales');
  84  |     });
  85  | 
  86  |     test('3. no avanzar esperando materiales -> lista para taller si faltan materiales', async ({ page }) => {
  87  |         await abrirCambiarEstado(page, 'E2E-OP-ESP');
  88  | 
  89  |         await page.getByRole('button', { name: 'Lista para taller' }).click();
  90  | 
  91  |         // El backend/UI bloquea la transici�n por kit incompleto
  92  |         await expect(
  93  |             page.getByText('No se puede pasar a \u00ABLista para taller\u00BB: faltan materiales.')
  94  |         ).toBeVisible();
  95  | 
  96  |         const fila = filaOp(page, 'E2E-OP-ESP');
  97  |         await expect(fila).toContainText('Esperando materiales');
  98  | 
  99  |         await page.getByRole('button', { name: 'Cerrar' }).click();
  100 |     });
  101 | 
  102 |     test('4. no pasar a cerrado si hay pares en reparaci�n', async ({ page }) => {
  103 |         await abrirCambiarEstado(page, 'E2E-OP-REP');
  104 | 
  105 |         await page.getByRole('button', { name: 'Cerrada' }).click();
  106 | 
  107 |         await expect(
  108 |             page.getByText(/No se puede cerrar la OP: a\u00fan hay \d+ par\(es\) en reparaci\u00f3n\./)
  109 |         ).toBeVisible();
  110 | 
  111 |         const fila = filaOp(page, 'E2E-OP-REP');
```