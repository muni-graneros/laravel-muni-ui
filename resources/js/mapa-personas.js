// Mapa de personas (página Filament): Leaflet + MarkerCluster + Heat, antes por
// CDN (unpkg). El orden importa: _leaflet-global setea window.L primero, luego
// los plugins lo extienden (L.markerClusterGroup, L.heatLayer).
import './_leaflet-global';
import 'leaflet.markercluster';
import 'leaflet.heat';

import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
