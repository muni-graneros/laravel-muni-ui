// Expone Leaflet como window.L ANTES de que se importen los plugins (markercluster,
// heat), que esperan el global L. Los imports ESM se ejecutan en orden, así que
// importar este módulo primero garantiza que window.L exista para los plugins.
import L from 'leaflet';

window.L = L;

export default L;
