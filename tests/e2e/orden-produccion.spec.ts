import { test, expect, Page } from '@playwright/test';

const USUARIO_EMAIL = 'e2e@maco.test';
const USUARIO_PASSWORD = 'password123';

async function login(page: Page) {
    await page.goto('/login');
    await page.locator('input[id="email"]').fill(USUARIO_EMAIL);
    await page.locator('input[id="password"]').fill(USUARIO_PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

async function irIndexOp(page: Page) {
    await page.goto('/OrdenProduccion');
    // Esperar a que la tabla deje de mostrar esqueletos y cargue datos
    await page.locator('tbody.op-page, text="Aún no hay órdenes de producción"').first().waitFor();
}

async function seleccionarSelect(page: Page, placeholder: string, valor: string) {
    const input = page.locator(`input[placeholder="${placeholder}"]`);
    await input.click();
    await input.fill(valor);
    const opcion = page.locator('.vs__dropdown-option').filter({ hasText: valor }).first();
    await opcion.waitFor({ state: 'visible', timeout: 10000 });
    await opcion.click();
}

async function filaOp(page: Page, numeroOp: string) {
    return page.locator('tr').filter({ hasText: numeroOp });
}

async function abrirCambiarEstado(page: Page, numeroOp: string) {
    const fila = filaOp(page, numeroOp);
    await fila.getByRole('button', { name: 'Cambiar estado' }).click();
    await page.getByRole('heading', { name: /Cambiar estado/ }).waitFor();
}

test.describe('Orden de producci�n', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await irIndexOp(page);
    });

    test('1. se puede crear una OP', async ({ page }) => {
        await page.getByRole('button', { name: 'Nueva orden' }).click();

        const modal = page.getByRole('heading', { name: /Nueva orden|Continuar/ }).first();
        await modal.waitFor();

        // Tipo: taller (por defecto ya viene seleccionado)
        await page.getByRole('button', { name: 'Taller' }).click();

        // Seleccionar taller
        await seleccionarSelect(page, 'Seleccione un taller...', 'Taller E2E');

        // Seleccionar producto con ficha
        await seleccionarSelect(page, 'Buscar y seleccionar productos...', 'Producto E2E');

        // La distribuci�n se carga sola (talla Única, qty 1)
        await page.getByText('Única: 1').waitFor({ state: 'visible', timeout: 10000 });

        // Guardar / Agregar
        await page.getByRole('button', { name: 'Nuevo' }).click();

        // La modal se cierra y volvemos al index
        await expect(page.getByRole('heading', { name: /Nueva orden|Continuar/ }).first()).toBeHidden();

        // Verificar que apareci� una OP nueva en estado Preparaci�n (numero OP-XXX)
        const opNueva = page.locator('tr').filter({ hasText: /^OP-\d+$/ }).first();
        await expect(opNueva).toContainText('Preparaci�n');
    });

    test('2. avanzar preparaci�n -> esperando materiales aunque falten materiales', async ({ page }) => {
        await abrirCambiarEstado(page, 'E2E-OP-PREP');

        await page.getByRole('button', { name: 'Esperando materiales' }).click();
        await page.getByRole('button', { name: 'Guardar' }).click();

        await expect(page.getByRole('heading', { name: /Cambiar estado/ })).toBeHidden();

        const fila = filaOp(page, 'E2E-OP-PREP');
        await expect(fila).toContainText('Esperando materiales');
    });

    test('3. no avanzar esperando materiales -> lista para taller si faltan materiales', async ({ page }) => {
        await abrirCambiarEstado(page, 'E2E-OP-ESP');

        await page.getByRole('button', { name: 'Lista para taller' }).click();

        // El backend/UI bloquea la transici�n por kit incompleto
        await expect(
            page.getByText('No se puede pasar a \u00ABLista para taller\u00BB: faltan materiales.')
        ).toBeVisible();

        const fila = filaOp(page, 'E2E-OP-ESP');
        await expect(fila).toContainText('Esperando materiales');

        await page.getByRole('button', { name: 'Cerrar' }).click();
    });

    test('4. no pasar a cerrado si hay pares en reparaci�n', async ({ page }) => {
        await abrirCambiarEstado(page, 'E2E-OP-REP');

        await page.getByRole('button', { name: 'Cerrada' }).click();

        await expect(
            page.getByText(/No se puede cerrar la OP: a\u00fan hay \d+ par\(es\) en reparaci\u00f3n\./)
        ).toBeVisible();

        const fila = filaOp(page, 'E2E-OP-REP');
        await expect(fila).toContainText('En taller');

        await page.getByRole('button', { name: 'Cerrar' }).click();
    });
});
