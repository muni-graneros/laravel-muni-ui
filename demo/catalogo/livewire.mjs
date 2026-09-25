// Prueba de punta a punta con Livewire real: cada control enlazable del paquete,
// usado con wire:model en un componente Livewire (workbench/), tiene que llevar su
// valor al servidor y reflejar lo que el servidor le devuelve.
//   vendor/bin/testbench serve --port=8765 &   (desde la raíz del repo)
//   node demo/catalogo/livewire.mjs            (LIVEWIRE_URL y PW_CHROMIUM opcionales)
import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const url = process.env.LIVEWIRE_URL || 'http://127.0.0.1:8765/formulario';
const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 1100, height: 900 }, reducedMotion: 'reduce' });
page.setDefaultTimeout(5000);
const fallas = [];
page.on('pageerror', (e) => fallas.push(`Error de JS: ${e.message}`));
await page.goto(url, { waitUntil: 'networkidle' });

const servidor = async () => JSON.parse(await page.locator('#servidor').innerText());

// Espera a que el servidor tenga el valor (Livewire responde de forma asíncrona).
async function llega(campo, esperado, que) {
    try {
        await page.waitForFunction(
            ([c, e]) => JSON.parse(document.querySelector('#servidor').innerText)[c] === e,
            [campo, esperado],
            { timeout: 5000 },
        );
    } catch {
        fallas.push(`${que}: el servidor tiene ${campo}=${JSON.stringify((await servidor())[campo])}, se esperaba ${JSON.stringify(esperado)}`);
    }
}

async function paso(que, fn) {
    try {
        await fn();
    } catch (e) {
        fallas.push(`${que}: ${e.message.split('\n')[0]}`);
    }
}

await paso('spinner oculto sin petición', async () => {
    if (await page.locator('#cargando').isVisible()) fallas.push('spinner con wire:loading visible sin ninguna petición en curso');
});

// Del componente al servidor
await paso('calendar', async () => {
    await page.locator('.muni-cal button[aria-label$="12 de octubre de 2026"]').click();
    await llega('fecha', '2026-10-12', 'calendar → servidor');
});
await paso('otp-input', async () => {
    const casillas = page.locator('.muni-otp');
    for (const [i, d] of ['4', '2', '8', '6'].entries()) await casillas.nth(i).fill(d);
    await llega('codigo', '4286', 'otp-input → servidor');
});
await paso('rating', async () => {
    await page.getByRole('radio', { name: '5 de 5' }).click();
    await llega('nota', 5, 'rating → servidor');
});
await paso('segmented', async () => {
    await page.locator('label', { hasText: 'Mapa' }).click();
    await llega('vista', 'mapa', 'segmented → servidor');
});
await paso('radio-group', async () => {
    await page.getByRole('radio', { name: 'Oposición' }).check();
    await llega('tipo', 'oposicion', 'radio-group → servidor');
});
await paso('checkbox', async () => {
    await page.getByRole('checkbox', { name: 'Acepto' }).check();
    await llega('acepta', true, 'checkbox → servidor');
});
await paso('switch', async () => {
    await page.locator('label', { hasText: 'Notificar' }).click();
    await llega('notificar', false, 'switch → servidor');
});
await paso('textarea', async () => {
    await page.locator('textarea[name=obs]').fill('Sin observaciones');
    await llega('obs', 'Sin observaciones', 'textarea → servidor');
});
await paso('input', async () => {
    await page.getByLabel('RUN').fill('12.345.678-9');
    await llega('run', '12.345.678-9', 'input → servidor');
});
await paso('file-dropzone (soltar un archivo)', async () => {
    const ruta = join(tmpdir(), 'cedula-prueba.pdf');
    writeFileSync(ruta, '%PDF-1.4 prueba');
    await page.locator('.muni-dz').evaluate(async (zona) => {
        const dt = new DataTransfer();
        dt.items.add(new File(['%PDF-1.4 prueba'], 'cedula.pdf', { type: 'application/pdf' }));
        zona.closest('[x-data]').dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
    });
    await llega('archivo', 'cedula.pdf', 'file-dropzone (arrastrado) → servidor');
});

await paso('data-table (orden por wireSort)', async () => {
    const deuda = page.getByRole('columnheader', { name: /Deuda/ });
    await deuda.getByRole('button').click();
    await llega('orden', 'deuda asc', 'data-table → servidor');
    await page.waitForFunction(() => [...document.querySelectorAll('th')].some((th) => th.textContent.includes('Deuda') && th.getAttribute('aria-sort') === 'ascending'));
    await deuda.getByRole('button').click();
    await llega('orden', 'deuda desc', 'data-table invierte la misma columna');
});

// Del servidor al componente
await paso('servidor → componentes', async () => {
    await page.getByRole('button', { name: 'Valores desde el servidor' }).click();
    await llega('fecha', '2026-12-24', 'reinicio en el servidor');
    await page.waitForTimeout(200);
    const cal = await page.locator('.muni-cal [aria-pressed=true]').getAttribute('aria-label');
    if (!cal?.endsWith('24 de diciembre de 2026')) fallas.push(`servidor → calendar: marca «${cal}»`);
    const otp = await page.locator('.muni-otp').evaluateAll((els) => els.map((e) => e.value).join(''));
    if (otp !== '1357') fallas.push(`servidor → otp-input: muestra «${otp}»`);
    const nota = await page.locator('[role=radiogroup][aria-label=Nota] [aria-checked=true]').getAttribute('aria-label');
    if (nota !== '4 de 5') fallas.push(`servidor → rating: marca «${nota}»`);
    if (!(await page.locator('input[name=vista][value=mapa]').isChecked())) fallas.push('servidor → segmented: «mapa» no quedó marcado');
    if (!(await page.getByRole('radio', { name: 'Oposición' }).isChecked())) fallas.push('servidor → radio-group: «Oposición» no quedó marcado');
    // «Del servidor» son 12 caracteres: quedan 38 de 50.
    const contador = await page.locator('.muni-textarea__contador').innerText();
    if (contador !== '38 de 50 caracteres disponibles') fallas.push(`servidor → textarea: el contador dice «${contador}», se esperaba «38 de 50 caracteres disponibles»`);
});

await browser.close();
if (fallas.length) {
    console.error(fallas.join('\n'));
    process.exit(1);
}
console.log('Livewire de punta a punta: 12 controles llevan su valor al servidor y reflejan lo que devuelve.');
