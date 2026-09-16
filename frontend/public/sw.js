const CACHE_NAME = 'kitu-analytics-v1';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/static/js/main.chunk.js',
  '/static/js/bundle.js',
  '/static/css/main.chunk.css',
  '/offline.html',
];

// ── Install: cache static assets ─────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {
        // Some assets may not exist yet — that's fine
      });
    })
  );
  self.skipWaiting();
});

// ── Activate: clean old caches ────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch: network-first for API, cache-first for assets ─────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') return;

  // Skip cross-origin requests except our API
  if (url.origin !== location.origin && !url.href.includes('localhost:8000')) return;

  // API requests: network first, fall back to cache
  if (url.href.includes('/api/v1/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache successful API responses
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => {
          // Offline — return cached API response if available
          return caches.match(request).then((cached) => {
            if (cached) return cached;
            // Return empty offline response for API calls
            return new Response(
              JSON.stringify({ offline: true, message: 'Huna mtandao. Data ya zamani inaonyeshwa.' }),
              { headers: { 'Content-Type': 'application/json' } }
            );
          });
        })
    );
    return;
  }

  // Static assets: cache first, fall back to network
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request)
        .then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => {
          // If HTML page requested and offline, show offline page
          if (request.headers.get('accept')?.includes('text/html')) {
            return caches.match('/offline.html');
          }
        });
    })
  );
});

// ── Background sync: process queued transactions ──────────────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-transactions') {
    event.waitUntil(syncQueuedTransactions());
  }
});

async function syncQueuedTransactions() {
  const queue = await getQueue();
  if (!queue.length) return;

  for (const item of queue) {
    try {
      const response = await fetch(item.url, {
        method: 'POST',
        headers: item.headers,
        body: item.body,
      });

      if (response.ok) {
        await removeFromQueue(item.id);
      }
    } catch {
      // Still offline — will retry next sync
    }
  }
}

// Simple IndexedDB queue for offline transactions
async function getQueue() {
  return new Promise((resolve) => {
    const request = indexedDB.open('kitu-offline', 1);
    request.onupgradeneeded = (e) => {
      e.target.result.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
    };
    request.onsuccess = (e) => {
      const db = e.target.result;
      const tx = db.transaction('queue', 'readonly');
      const store = tx.objectStore('queue');
      const all = store.getAll();
      all.onsuccess = () => resolve(all.result);
      all.onerror = () => resolve([]);
    };
    request.onerror = () => resolve([]);
  });
}

async function removeFromQueue(id) {
  return new Promise((resolve) => {
    const request = indexedDB.open('kitu-offline', 1);
    request.onsuccess = (e) => {
      const db = e.target.result;
      const tx = db.transaction('queue', 'readwrite');
      tx.objectStore('queue').delete(id);
      tx.oncomplete = resolve;
    };
    request.onerror = resolve;
  });
}