import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.getAttribute('content') || '';
const csrf = meta('csrf-token');
const isHttps = window.location.protocol === 'https:';

// Config en RUNTIME (meta tags desde .env) con fallback a window.location. Así el
// tiempo real funciona en dev (puerto directo de Reverb) y en prod tras el ingress/
// túnel (mismo origen, /app* → Reverb) SIN rehornear la imagen ni puertos fijos.
const host = meta('reverb-host') || window.location.hostname;
const scheme = meta('reverb-scheme') || window.location.protocol.replace(':', '');
const tls = scheme === 'https';
const puerto = meta('reverb-port')
    ? Number(meta('reverb-port'))
    : (window.location.port ? Number(window.location.port) : (isHttps ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: puerto,
    wssPort: puerto,
    forceTLS: tls,
    enabledTransports: ['ws', 'wss'],
    auth: { headers: { 'X-CSRF-TOKEN': csrf } },
});
