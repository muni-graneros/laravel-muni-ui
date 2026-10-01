// Verifica el catálogo generado en un navegador real. Lo corre CI (job «Catálogo»)
// y se puede correr a mano: node demo/catalogo/verificar.mjs
//   - sin errores de JS en la página ni en las plantillas (iframes)
//   - sin scroll horizontal a 390 px (teléfono)
//   - axe-core sin violaciones serias o críticas en los componentes, en ambos temas
// PW_CHROMIUM permite usar un Chromium ya instalado en vez del de Playwright.
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const axe = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const url = 'file://' + fileURLToPath(new URL('../catalogo.html', import.meta.url));
const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
const fallas = [];

const abrir = async (viewport) => {
    const page = await browser.newPage({ viewport });
    page.on('pageerror', (e) => fallas.push(`Error de JS: ${e.message}`));
    // Las fuentes vienen de Google Fonts; sin red no son un error del catálogo.
    await page.route(/fonts\.(googleapis|gstatic)\.com/, (r) => r.abort());
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(800);
    return page;
};

const movil = await abrir({ width: 390, height: 844 });
const ancho = await movil.evaluate(() => document.documentElement.scrollWidth);
if (ancho > 390) fallas.push(`Scroll horizontal en móvil: el documento mide ${ancho}px de ancho a 390px`);

const page = await abrir({ width: 1280, height: 900 });
for (const tema of ['light', 'dark']) {
    await page.evaluate((t) => {
        document.documentElement._x_dataStack[0].tema = t;
    }, tema);
    await page.waitForTimeout(300);
    const frames = [page.mainFrame(), ...page.frames().filter((f) => f !== page.mainFrame())];
    for (const frame of frames) {
        await frame.evaluate(axe);
        // En la página se miran solo los componentes (.cat-demo), no el cromo del catálogo;
        // en cada plantilla, el documento entero.
        const soloDemos = frame === page.mainFrame();
        const r = await frame.evaluate(async (soloDemos) => {
            // eslint-disable-next-line no-undef
            const res = await axe.run(soloDemos ? { include: [['.cat-demo']] } : document, {
                runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'],
                resultTypes: ['violations'],
            });
            return res.violations
                .filter((v) => v.impact === 'serious' || v.impact === 'critical')
                .map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`);
        }, soloDemos);
        for (const v of r) fallas.push(`[${tema}] ${soloDemos ? 'componentes' : 'plantilla'} ${v}`);
    }
}

await browser.close();
if (fallas.length) {
    console.error(fallas.join('\n'));
    process.exit(1);
}
console.log('Catálogo verificado: sin errores de JS, sin scroll horizontal en móvil y sin violaciones serias de axe en ambos temas.');
