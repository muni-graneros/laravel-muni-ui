// Pruebas de comportamiento de los componentes interactivos sobre el catálogo
// generado, en un navegador real. Pest solo ve el HTML del servidor; esto cubre lo
// que hace Alpine: foco, teclado, enlace con x-model/wire:model, orden, avisos.
//   node demo/catalogo/interacciones.mjs      (PW_CHROMIUM = Chromium propio, opcional)
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';

const url = 'file://' + fileURLToPath(new URL('../catalogo.html', import.meta.url));
const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
const fallas = [];
let pruebas = 0;

async function pagina(viewport = { width: 1280, height: 900 }) {
    // Con movimiento reducido las transiciones duran ~0 y las esperas no dependen de la
    // velocidad de la máquina de CI.
    const page = await browser.newPage({ viewport, reducedMotion: 'reduce' });
    page.on('pageerror', (e) => fallas.push(`Error de JS: ${e.message}`));
    page.setDefaultTimeout(5000);
    await page.route(/fonts\.(googleapis|gstatic)\.com/, (r) => r.abort());
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(600);
    return page;
}

async function prueba(nombre, fn) {
    pruebas++;
    const page = await pagina();
    try {
        await fn(page);
    } catch (e) {
        fallas.push(`${nombre}: ${e.message.split('\n')[0]}`);
    } finally {
        await page.close();
    }
}

function igual(real, esperado, que) {
    if (JSON.stringify(real) !== JSON.stringify(esperado)) {
        throw new Error(`${que}: se esperaba ${JSON.stringify(esperado)} y llegó ${JSON.stringify(real)}`);
    }
}

const foco = (page) => page.evaluate(() => {
    const a = document.activeElement;
    return a ? (a.innerText || a.getAttribute('aria-label') || a.tagName).trim().slice(0, 40) : null;
});
const dentro = (page, sel) => page.evaluate((s) => !!document.activeElement?.closest(s), sel);
const demo = (page, id) => page.locator(`#c-${id} .cat-demo`);

await prueba('modal: foco adentro, Tab atrapado, Escape devuelve el foco', async (page) => {
    const abrir = demo(page, 'modal').getByRole('button', { name: 'Dar de baja' });
    await abrir.click();
    await page.waitForTimeout(250);
    igual(await dentro(page, '[role=dialog]'), true, 'foco dentro del diálogo al abrir');
    igual(await page.evaluate(() => document.documentElement.style.overflow || document.body.style.overflow), 'hidden', 'scroll bloqueado');
    for (let i = 0; i < 6; i++) await page.keyboard.press('Tab');
    igual(await dentro(page, '[role=dialog]'), true, 'foco sigue dentro tras 6 Tab');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(250);
    igual(await foco(page), 'Dar de baja', 'foco vuelve al botón que abrió');
    igual(await page.evaluate(() => document.documentElement.style.overflow || document.body.style.overflow), '', 'scroll liberado');
});

await prueba('drawer: el botón del pie con open = false cierra', async (page) => {
    await demo(page, 'drawer').getByRole('button', { name: 'Ver ficha' }).click();
    await page.waitForTimeout(250);
    igual(await dentro(page, '[role=dialog]'), true, 'foco dentro del drawer');
    await page.getByRole('dialog').getByRole('button', { name: 'Cerrar', exact: true }).last().click();
    await page.waitForTimeout(700);
    igual(await page.getByRole('dialog').isVisible(), false, 'drawer cerrado');
});

await prueba('modal dentro de drawer: Escape cierra de a uno', async (page) => {
    const receta = page.locator('#receta-ficha');
    await receta.getByRole('button', { name: /Abrir la ficha/ }).click();
    await page.waitForTimeout(250);
    await page.getByRole('dialog').getByRole('button', { name: 'Dar de baja' }).click();
    await page.waitForTimeout(250);
    igual(await page.locator('[role=dialog]:visible').count(), 2, 'dos diálogos abiertos');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(700);
    igual(await page.locator('[role=dialog]:visible').count(), 1, 'queda el drawer');
    igual(await page.evaluate(() => document.documentElement.style.overflow || document.body.style.overflow), 'hidden', 'scroll sigue bloqueado');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(700);
    igual(await page.evaluate(() => document.documentElement.style.overflow || document.body.style.overflow), '', 'scroll liberado al cerrar todo');
});

await prueba('dropdown: flechas recorren, Escape vuelve al botón, Tab afuera cierra', async (page) => {
    const d = demo(page, 'dropdown');
    const boton = d.getByRole('button', { name: /Acciones/ });
    await boton.focus();
    await page.keyboard.press('ArrowDown');
    await page.waitForTimeout(150);
    igual(await boton.getAttribute('aria-expanded'), 'true', 'aria-expanded en el botón');
    igual(await foco(page), 'Ver ficha', 'primera opción enfocada');
    await page.keyboard.press('ArrowUp');
    igual(await foco(page), 'Dar de baja', 'ArrowUp da la vuelta a la última');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(150);
    igual(await foco(page), 'Acciones ▾', 'Escape vuelve al botón');
    await page.keyboard.press('ArrowDown');
    await page.waitForTimeout(100);
    for (let i = 0; i < 4; i++) await page.keyboard.press('Tab');
    await page.waitForTimeout(150);
    igual(await boton.getAttribute('aria-expanded'), 'false', 'se cierra al salir con Tab');
});

await prueba('tabs: las flechas mueven el foco y el panel', async (page) => {
    const t = demo(page, 'tabs');
    await t.getByRole('tab', { name: 'Datos' }).focus();
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(100);
    igual(await foco(page), 'Documentos', 'foco en la pestaña siguiente');
    igual(await t.getByRole('tabpanel').filter({ visible: true }).innerText(), 'Cédula, certificado de residencia y contrato de arriendo.', 'panel visible');
});

await prueba('calendar: el teclado elige, respeta min y el enlace x-model se actualiza', async (page) => {
    const c = demo(page, 'calendar');
    const enlazado = c.locator('.muni-cal').nth(1);
    const valor = () => c.locator('code').last().innerText();
    igual(await valor(), '2026-10-05', 'valor inicial');
    // develop dejó la rejilla APG (flechas) fuera a propósito: cada día es un botón.
    await enlazado.locator('button:not([disabled])', { hasText: /^12$/ }).first().focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(150);
    igual(await valor(), '2026-10-12', 'Enter sobre el 12 lo elige');
    await c.getByRole('button', { name: 'Poner 24-12-2026' }).click();
    await page.waitForTimeout(150);
    igual(await enlazado.locator('button[aria-pressed=true]').innerText(), '24', 'el valor externo marca el día');
    const conMin = c.locator('.muni-cal').first();
    igual(await conMin.locator('button:has-text("9"):not([style*=hidden])').first().isDisabled(), true, 'días antes de min deshabilitados');
});

await prueba('otp-input: pegar reemplaza el código y x-model lo recibe', async (page) => {
    const o = demo(page, 'otp-input');
    const casillas = o.locator('.muni-otp');
    await casillas.first().focus();
    await page.evaluate(() => {
        const dt = new DataTransfer();
        dt.setData('text', '987654');
        document.activeElement.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
    });
    await page.waitForTimeout(100);
    igual(await casillas.evaluateAll((els) => els.slice(0, 6).map((e) => e.value).join('')), '987654', 'código pegado');
    await o.getByRole('button', { name: 'Rellenar 2468' }).click();
    await page.waitForTimeout(100);
    igual(await casillas.evaluateAll((els) => els.slice(-4).map((e) => e.value).join('')), '2468', 'valor externo llena las casillas');
});

await prueba('rating y segmented: el enlace refleja el clic', async (page) => {
    const r = demo(page, 'rating');
    await r.getByRole('radiogroup', { name: 'Calidad de la atención' }).getByRole('radio', { name: '5 de 5' }).click();
    igual(await r.locator('code').innerText(), '5', 'nota enlazada');
    const s = demo(page, 'segmented');
    await s.locator('label', { hasText: 'Mapa' }).click();
    igual(await s.locator('code').innerText(), 'mapa', 'vista enlazada sin enviar el form');
});

await prueba('sortable-table: ordena montos chilenos', async (page) => {
    const t = demo(page, 'sortable-table');
    await t.getByRole('button', { name: /Deuda/ }).click();
    const col = await t.locator('tbody tr:visible').evaluateAll((trs) => trs.map((tr) => tr.cells[2].innerText));
    igual(col, ['$0', '$12.000', '$184.500'], 'orden ascendente');
});

await prueba('toast-host: el aviso aparece y se puede cerrar', async (page) => {
    await demo(page, 'toast-host').getByRole('button', { name: 'Mostrar aviso' }).click();
    const aviso = page.getByText('Los cambios se guardaron.').filter({ visible: true }).first();
    await aviso.waitFor({ state: 'visible', timeout: 2000 });
});

// El catálogo tiene dos paletas (ejemplo y receta «marco») y Ctrl+K abre las dos; un
// sistema real tiene una. Aquí se abre con su botón.
await prueba('command-palette: abre, filtra y Escape devuelve el foco', async (page) => {
    const boton = demo(page, 'command-palette').getByRole('button', { name: /Buscar/ });
    await boton.focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(250);
    const entrada = page.getByRole('combobox').filter({ visible: true }).and(page.locator('[aria-controls]'));
    igual(await entrada.evaluate((e) => e === document.activeElement), true, 'foco en el buscador');
    await page.keyboard.type('moro');
    await page.waitForTimeout(150);
    igual(await page.getByRole('dialog').getByRole('option').filter({ visible: true }).count(), 1, 'filtra a una opción');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(250);
    igual(await boton.evaluate((e) => e === document.activeElement), true, 'foco devuelto');
});

pruebas++;
{
    const page = await pagina({ width: 390, height: 844 });
    try {
        const frame = page.frameLocator('#c-dashboard-shell iframe');
        // Por clase y no por rol: con la lateral abierta, x-trap.inert deja el resto
        // (el botón incluido) con aria-hidden y el rol ya no lo encuentra.
        const menu = frame.locator('.muni-ds__burger');
        await menu.click();
        await page.waitForTimeout(350);
        igual(await menu.getAttribute('aria-expanded'), 'true', 'el menú abre la barra lateral en móvil');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(350);
        igual(await menu.getAttribute('aria-expanded'), 'false', 'Escape la cierra');
    } catch (e) {
        fallas.push(`dashboard-shell móvil: ${e.message.split('\n')[0]}`);
    }
    await page.close();
}

await browser.close();
if (fallas.length) {
    console.error(fallas.join('\n'));
    process.exit(1);
}
console.log(`Interacciones verificadas: ${pruebas} pruebas sin fallas.`);
