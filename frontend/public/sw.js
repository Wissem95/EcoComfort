const CACHE_NAME = 'ecocomfort-v1'
const API_CACHE_NAME = 'ecocomfort-api-v1'
const STATIC_CACHE_NAME = 'ecocomfort-static-v1'

// App shell files to cache
const STATIC_RESOURCES = [
  '/',
  '/index.html',
  '/manifest.json',
  '/pwa-192x192.png',
  '/pwa-512x512.png'
]

// API endpoints to cache with different strategies
const API_PATTERNS = [
  /^http:\/\/localhost:8000\/api\/dashboard$/,
  /^http:\/\/localhost:8000\/api\/sensors$/,
  /^http:\/\/localhost:8000\/api\/rooms$/,
  /^http:\/\/localhost:8000\/api\/analytics$/
]

// Install event - cache static resources
self.addEventListener('install', (event) => {
  console.log('🔧 Service Worker: Installing...')
  
  event.waitUntil(
    Promise.all([
      // Cache static resources
      caches.open(STATIC_CACHE_NAME).then((cache) => {
        console.log('📦 Caching static resources')
        return cache.addAll(STATIC_RESOURCES).catch((err) => {
          console.error('Failed to cache some static resources:', err)
          // Cache available resources individually
          return Promise.allSettled(
            STATIC_RESOURCES.map(resource => cache.add(resource))
          )
        })
      }),
      
      // Initialize API cache
      caches.open(API_CACHE_NAME),
      caches.open(CACHE_NAME)
    ])
  )
  
  // Force activation of new service worker
  self.skipWaiting()
})

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('✅ Service Worker: Activated')
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME && 
                cacheName !== API_CACHE_NAME && 
                cacheName !== STATIC_CACHE_NAME) {
              console.log('🗑️ Deleting old cache:', cacheName)
              return caches.delete(cacheName)
            }
          })
        )
      }),
      
      // Claim all clients
      self.clients.claim()
    ])
  )
})

// Fetch event - implement caching strategies
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)
  
  // Skip non-HTTP requests
  if (!request.url.startsWith('http')) {
    return
  }
  
  // Handle API requests
  if (isApiRequest(request.url)) {
    event.respondWith(handleApiRequest(request))
    return
  }
  
  // Handle static resources
  if (isStaticResource(request.url)) {
    event.respondWith(handleStaticRequest(request))
    return
  }
  
  // Handle navigation requests
  if (request.mode === 'navigate') {
    event.respondWith(handleNavigationRequest(request))
    return
  }
  
  // Default: network first, cache fallback
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Cache successful responses
        if (response.status === 200) {
          const responseClone = response.clone()
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseClone)
          })
        }
        return response
      })
      .catch(() => {
        return caches.match(request)
      })
  )
})

// Handle API requests with Network First strategy
async function handleApiRequest(request) {
  const cache = await caches.open(API_CACHE_NAME)
  
  try {
    // Try network first
    const networkResponse = await fetch(request)
    
    if (networkResponse.ok) {
      // Cache successful responses with TTL
      const responseClone = networkResponse.clone()
      const responseWithTimestamp = await addTimestampToResponse(responseClone)
      cache.put(request, responseWithTimestamp)
      
      return networkResponse
    }
    
    throw new Error('Network response not ok')
    
  } catch (error) {
    console.warn('📡 Network failed, trying cache for:', request.url)
    
    // Try cache
    const cachedResponse = await cache.match(request)
    
    if (cachedResponse) {
      // Check if cache is still valid (5 minutes TTL)
      const cacheTimestamp = cachedResponse.headers.get('sw-cache-timestamp')
      const now = Date.now()
      const fiveMinutes = 5 * 60 * 1000
      
      if (cacheTimestamp && (now - parseInt(cacheTimestamp)) < fiveMinutes) {
        console.log('📦 Serving from cache:', request.url)
        return cachedResponse
      }
    }
    
    // Return offline fallback for critical API endpoints
    if (request.url.includes('/api/dashboard')) {
      return new Response(JSON.stringify({
        data: {
          organization: { name: 'EcoComfort (Hors ligne)' },
          infrastructure: {
            total_buildings: 0,
            total_rooms: 0,
            total_sensors: 0,
            active_sensors: 0,
            sensor_uptime: 0
          },
          energy: {
            total_energy_loss_kwh: 0,
            total_cost: 0,
            rooms_with_open_doors: 0
          },
          alerts: {
            total_alerts: 0,
            total_cost: 0,
            critical_count: 0,
            warning_count: 0,
            info_count: 0,
            unacknowledged: 0
          }
        },
        message: 'Mode hors ligne - Données limitées'
      }), {
        status: 200,
        headers: {
          'Content-Type': 'application/json',
          'X-Offline-Response': 'true'
        }
      })
    }
    
    // Return error response for non-critical endpoints
    return new Response(JSON.stringify({
      error: 'Service temporairement indisponible',
      offline: true
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    })
  }
}

// Handle static resources with Cache First strategy
async function handleStaticRequest(request) {
  const cache = await caches.open(STATIC_CACHE_NAME)
  const cachedResponse = await cache.match(request)
  
  if (cachedResponse) {
    return cachedResponse
  }
  
  try {
    const networkResponse = await fetch(request)
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone())
      return networkResponse
    }
  } catch (error) {
    console.warn('Failed to fetch static resource:', request.url)
  }
  
  // Return offline fallback
  if (request.url.includes('.png') || request.url.includes('.jpg')) {
    return new Response('', { status: 200 })
  }
  
  return new Response('Resource unavailable offline', { status: 503 })
}

// Handle navigation requests
async function handleNavigationRequest(request) {
  try {
    const networkResponse = await fetch(request)
    return networkResponse
  } catch (error) {
    // Return cached index.html for SPA routing
    const cache = await caches.open(STATIC_CACHE_NAME)
    const cachedResponse = await cache.match('/index.html')
    
    if (cachedResponse) {
      return cachedResponse
    }
    
    // Fallback offline page
    return new Response(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>EcoComfort - Hors ligne</title>
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
            body {
              font-family: system-ui, -apple-system, sans-serif;
              background: linear-gradient(135deg, #0f172a, #581c87, #0f172a);
              color: white;
              margin: 0;
              padding: 2rem;
              min-height: 100vh;
              display: flex;
              align-items: center;
              justify-content: center;
              text-align: center;
            }
            .container {
              background: rgba(255, 255, 255, 0.1);
              backdrop-filter: blur(10px);
              border: 1px solid rgba(255, 255, 255, 0.2);
              border-radius: 1rem;
              padding: 2rem;
              max-width: 400px;
            }
            .icon { font-size: 3rem; margin-bottom: 1rem; }
            h1 { margin-bottom: 1rem; color: #22c55e; }
            button {
              background: rgba(34, 197, 94, 0.2);
              border: 1px solid rgba(34, 197, 94, 0.3);
              color: #22c55e;
              padding: 0.75rem 1.5rem;
              border-radius: 0.5rem;
              cursor: pointer;
              font-size: 1rem;
              margin-top: 1rem;
            }
            button:hover {
              background: rgba(34, 197, 94, 0.3);
            }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="icon">🌿</div>
            <h1>EcoComfort</h1>
            <p>Application temporairement hors ligne</p>
            <p>Vérifiez votre connexion internet et réessayez</p>
            <button onclick="window.location.reload()">
              🔄 Réessayer
            </button>
          </div>
        </body>
      </html>
    `, {
      status: 200,
      headers: { 'Content-Type': 'text/html' }
    })
  }
}

// Utility functions
function isApiRequest(url) {
  return API_PATTERNS.some(pattern => pattern.test(url))
}

function isStaticResource(url) {
  return url.includes('.js') || 
         url.includes('.css') || 
         url.includes('.png') || 
         url.includes('.jpg') || 
         url.includes('.svg') ||
         url.includes('.ico') ||
         url.includes('.woff2')
}

async function addTimestampToResponse(response) {
  const headers = new Headers(response.headers)
  headers.set('sw-cache-timestamp', Date.now().toString())
  
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers: headers
  })
}

// Background sync for offline actions
self.addEventListener('sync', (event) => {
  console.log('🔄 Background sync:', event.tag)
  
  if (event.tag === 'sync-sensor-data') {
    event.waitUntil(syncSensorData())
  }
  
  if (event.tag === 'sync-user-actions') {
    event.waitUntil(syncUserActions())
  }
})

async function syncSensorData() {
  try {
    // Get pending sensor data from IndexedDB
    const pendingData = await getFromIndexedDB('pendingSensorData')
    
    if (pendingData && pendingData.length > 0) {
      for (const data of pendingData) {
        try {
          await fetch('/api/sensors/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          })
          
          // Remove synced data
          await removeFromIndexedDB('pendingSensorData', data.id)
        } catch (error) {
          console.error('Failed to sync sensor data:', error)
        }
      }
    }
  } catch (error) {
    console.error('Background sync failed:', error)
  }
}

async function syncUserActions() {
  try {
    // Sync pending user actions (gamification points, etc.)
    const pendingActions = await getFromIndexedDB('pendingUserActions')
    
    if (pendingActions && pendingActions.length > 0) {
      for (const action of pendingActions) {
        try {
          await fetch('/api/user/actions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(action)
          })
          
          await removeFromIndexedDB('pendingUserActions', action.id)
        } catch (error) {
          console.error('Failed to sync user action:', error)
        }
      }
    }
  } catch (error) {
    console.error('User actions sync failed:', error)
  }
}

// Push notification handling
self.addEventListener('push', (event) => {
  console.log('🔔 Push notification received')
  
  let notificationData = {}
  
  if (event.data) {
    try {
      notificationData = event.data.json()
    } catch (error) {
      notificationData = {
        title: 'EcoComfort',
        body: event.data.text() || 'Nouvelle notification',
        icon: '/pwa-192x192.png'
      }
    }
  }
  
  const {
    title = 'EcoComfort',
    body = 'Nouvelle notification',
    icon = '/pwa-192x192.png',
    badge = '/pwa-192x192.png',
    tag = 'ecocomfort-notification',
    requireInteraction = false,
    actions = [],
    data = {}
  } = notificationData
  
  const notificationOptions = {
    body,
    icon,
    badge,
    tag,
    requireInteraction,
    actions,
    data,
    timestamp: Date.now(),
    vibrate: [200, 100, 200]
  }
  
  // Show notification based on severity
  if (data.severity === 'critical') {
    notificationOptions.requireInteraction = true
    notificationOptions.vibrate = [300, 100, 300, 100, 300]
    notificationOptions.actions = [
      { action: 'acknowledge', title: '✅ Accusé réception' },
      { action: 'view', title: '👁️ Voir détails' }
    ]
  }
  
  event.waitUntil(
    self.registration.showNotification(title, notificationOptions)
  )
})

// Notification click handling
self.addEventListener('notificationclick', (event) => {
  console.log('🔔 Notification clicked:', event.action)
  
  event.notification.close()
  
  const { action, notification } = event
  const { data } = notification
  
  let urlToOpen = '/'
  
  if (action === 'view' || !action) {
    if (data.room_id) {
      urlToOpen = `/rooms/${data.room_id}`
    } else if (data.sensor_id) {
      urlToOpen = `/sensors/${data.sensor_id}`
    } else {
      urlToOpen = '/dashboard'
    }
  }
  
  if (action === 'acknowledge') {
    // Send acknowledgment to API
    event.waitUntil(
      fetch('/api/notifications/acknowledge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: data.id })
      }).catch((error) => {
        console.error('Failed to acknowledge notification:', error)
        // Store for later sync
        storeInIndexedDB('pendingAcknowledgments', {
          id: Date.now(),
          notification_id: data.id,
          timestamp: Date.now()
        })
      })
    )
    return
  }
  
  // Open URL in existing tab or new tab
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        // Check if there's already a tab open
        for (const client of clients) {
          if (client.url.includes(self.location.origin)) {
            client.focus()
            client.postMessage({ type: 'navigate', url: urlToOpen })
            return
          }
        }
        
        // Open new tab
        return self.clients.openWindow(urlToOpen)
      })
  )
})

// IndexedDB helper functions
async function getFromIndexedDB(storeName) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('EcoComfortDB', 1)
    
    request.onerror = () => reject(request.error)
    request.onsuccess = () => {
      const db = request.result
      const transaction = db.transaction([storeName], 'readonly')
      const store = transaction.objectStore(storeName)
      const getRequest = store.getAll()
      
      getRequest.onsuccess = () => resolve(getRequest.result)
      getRequest.onerror = () => reject(getRequest.error)
    }
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result
      if (!db.objectStoreNames.contains(storeName)) {
        db.createObjectStore(storeName, { keyPath: 'id' })
      }
    }
  })
}

async function storeInIndexedDB(storeName, data) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('EcoComfortDB', 1)
    
    request.onerror = () => reject(request.error)
    request.onsuccess = () => {
      const db = request.result
      const transaction = db.transaction([storeName], 'readwrite')
      const store = transaction.objectStore(storeName)
      const addRequest = store.add(data)
      
      addRequest.onsuccess = () => resolve(addRequest.result)
      addRequest.onerror = () => reject(addRequest.error)
    }
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result
      if (!db.objectStoreNames.contains(storeName)) {
        db.createObjectStore(storeName, { keyPath: 'id' })
      }
    }
  })
}

async function removeFromIndexedDB(storeName, id) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('EcoComfortDB', 1)
    
    request.onerror = () => reject(request.error)
    request.onsuccess = () => {
      const db = request.result
      const transaction = db.transaction([storeName], 'readwrite')
      const store = transaction.objectStore(storeName)
      const deleteRequest = store.delete(id)
      
      deleteRequest.onsuccess = () => resolve()
      deleteRequest.onerror = () => reject(deleteRequest.error)
    }
  })
}

console.log('🚀 EcoComfort Service Worker loaded')