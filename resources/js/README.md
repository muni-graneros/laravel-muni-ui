# JS de infraestructura compartido del ecosistema

Módulos de infraestructura que estaban **byte-idénticos** en varios sistemas del
ecosistema. Se distribuyen dentro de este paquete Composer (no como paquete npm) y se
importan desde `vendor/` en el pipeline de Vite del sistema — las dependencias de npm
(`laravel-echo`, `pusher-js`, `leaflet`, `leaflet.markercluster`, `leaflet.heat`) las
resuelve el `node_modules` del propio proyecto, que ya las tiene.

> Solo se comparte lo que es genuinamente común. `tour.js` y `calendario-global.js`
> **no** están aquí a propósito: divergen entre sistemas por diseño (pasos del tour y
> configuración de calendario propios). `echo.js` de licencias tampoco: usa un proxy
> Caddy en vez de meta-tags de Reverb, otra topología.

## Módulos

| Archivo | Qué hace |
|---|---|
| `echo.js` | Configura Laravel Echo (Reverb) leyendo host/puerto/scheme de meta-tags, con fallback a `window.location`. Para sistemas que exponen Reverb por meta-tags (feria, discapacidad). |
| `_leaflet-global.js` | Expone `window.L` antes de que carguen los plugins de Leaflet (markercluster, heat), que esperan el global. |
| `mapa-personas.js` | Mapa de personas (página Filament): Leaflet + MarkerCluster + Heat, en el orden correcto. |

## Uso

En `resources/js/app.js` del sistema:

```js
// Tiempo real
import '../../vendor/muni-graneros/laravel-muni-ui/resources/js/echo.js';

// Mapa de personas (donde aplique)
import '../../vendor/muni-graneros/laravel-muni-ui/resources/js/mapa-personas.js';
```

Vite resuelve la ruta relativa a `vendor/` y bundlea el módulo; sus `import` de librerías
se resuelven contra el `node_modules` del proyecto. Un cambio en la infraestructura de
tiempo real o de mapas se hace **una vez aquí** en vez de en cada repo.
