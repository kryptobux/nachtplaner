/* Hintergrunddienst des Nachtplaners: macht die App installierbar und offline nutzbar.
   Seiten der App: erst Netz (damit neue Versionen sofort kommen), ohne Netz aus dem Speicher.
   Schriften: aus dem Speicher, nebenbei erneuert. Wetter (Open-Meteo, wetter7.php): immer live, nie gespeichert;
   die App hält ihre letzte Vorhersage selbst im Browserspeicher. */
const VERSION = 'np-7a828b2214';
const KERN = ['./', 'index.html', 'manifest.webmanifest', 'symbol-192.png', 'symbol-512.png', 'symbol-maskierbar-512.png',
  'horizont/', 'horizont/index.html', 'horizont/astronomy.browser.min.js'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(KERN)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== VERSION && k !== VERSION + '-schrift').map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET') return;
  if (u.origin === location.origin) {
    if (u.pathname.endsWith('.php')) return; // Wetter nicht speichern
    e.respondWith(fetch(r).then(a => {
      if (a.ok) { const k = a.clone(); caches.open(VERSION).then(c => c.put(r, k)); }
      return a;
    }).catch(() => caches.match(r, { ignoreSearch: true }).then(a => a || caches.match('index.html'))));
    return;
  }
  if (u.host === 'fonts.googleapis.com' || u.host === 'fonts.gstatic.com') {
    e.respondWith(caches.open(VERSION + '-schrift').then(c => c.match(r).then(alt => {
      const neu = fetch(r).then(a => { if (a.ok || a.type === 'opaque') c.put(r, a.clone()); return a; }).catch(() => alt);
      return alt || neu;
    })));
  }
});
