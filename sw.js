const CACHE_NAME = 'vaxibloom-v24';
const ASSETS_TO_CACHE = [
  '/',
  'img/logo.jpg',
  'css/user/home.css',
  'manifest.json',
];

// Install event: Cache assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Cache opened');
        return cache.addAll(ASSETS_TO_CACHE)
          .catch(err => {
            console.warn('[Service Worker] Cache addAll error:', err);
            // Fallback to individual caching if addAll fails
            return Promise.all(
              ASSETS_TO_CACHE.map(url => {
                return fetch(new Request(url, { cache: 'reload', credentials: 'include' }))
                  .then(response => {
                    if (!response.ok) throw new Error(`Failed to fetch ${url}`);
                    return cache.put(url, response);
                  })
                  .catch(err => {
                    console.warn(`[Service Worker] Could not cache ${url}:`, err);
                    return Promise.resolve();
                  });
              })
            );
          });
      })
  );
  self.skipWaiting();
});

// Activate event: Clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event: Serve assets from cache or network
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || event.request.url.startsWith('chrome-extension://')) return;

  const isDynamic = 
    event.request.url.includes('.php') ||
    event.request.url.includes('/api/') ||
    event.request.url.includes('auth') || 
    event.request.url.includes('login') ||
    event.request.url.includes('logout') ||
    event.request.url.includes('register') ||
    event.request.url.includes('session');

  if (isDynamic) {
    // Always fetch fresh for dynamic pages
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          if (!response.ok || response.type !== 'basic') return response;

          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
          return response;
        }).catch((err) => {
          console.error('[Service Worker] Fetch failed:', err);
          // Optionally, you could return a fallback resource here, e.g., an offline page
        });
      })
  );
});

// Push event: Show notification
self.addEventListener('push', (event) => {
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/img/logo.jpg',
    badge: '/img/logo.jpg',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click event: Handle notification click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' })
      .then(clientList => {
        // Look for an open client with the same URL and focus it
        for (const client of clientList) {
          if (client.url === event.notification.data.url && 'focus' in client) {
            return client.focus();
          }
        }
        // Otherwise, open a new window
        if (clients.openWindow) {
          return clients.openWindow(event.notification.data.url);
        }
      })
  );
});
