// sw.js - Service Worker Básico
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Deja pasar todas las peticiones de red normalmente
    event.respondWith(fetch(event.request));
});
//<link rel="manifest" href="manifest.json">  esta es la linea de codigo ue debo de poner debajo
// de cada head en las herramientas para que al hacer el instalar los mande a index