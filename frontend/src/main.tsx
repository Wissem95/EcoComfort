import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

// PWA Registration and Setup
async function initializePWA() {
  // Register service worker
  if ('serviceWorker' in navigator) {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      })
      
      console.log('🚀 Service Worker registered successfully:', registration.scope)
      
      // Handle service worker updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing
        if (newWorker) {
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              // New version available
              console.log('📦 New version available - Please reload')
              
              // Show update notification to user
              if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('EcoComfort - Mise à jour disponible', {
                  body: 'Une nouvelle version est disponible. Rechargez la page.',
                  icon: '/pwa-192x192.png',
                  tag: 'app-update',
                  // actions: [  // Actions not supported in standard Notification API
                  //   { action: 'reload', title: '🔄 Recharger' }
                  // ]
                })
              }
            }
          })
        }
      })
      
    } catch (error) {
      console.error('❌ Service Worker registration failed:', error)
    }
  }
  
  // Log notification permission status (don't request automatically)
  if ('Notification' in window) {
    console.log('🔔 Notification permission:', Notification.permission)
    
    // Only request permission when user interacts (will be handled by components)
    if (Notification.permission === 'default') {
      console.log('ℹ️ Notification permission will be requested on user interaction')
    }
  }
  
  // Handle PWA install prompt
  let deferredPrompt: any = null
  
  window.addEventListener('beforeinstallprompt', (event) => {
    // Prevent Chrome 67 and earlier from automatically showing the prompt
    event.preventDefault()
    deferredPrompt = event
    
    console.log('💾 PWA install prompt available')
    
    // Show custom install button/banner after a delay
    setTimeout(() => {
      if (deferredPrompt && !window.matchMedia('(display-mode: standalone)').matches) {
        showInstallBanner()
      }
    }, 5000)
  })
  
  // Handle app installed
  window.addEventListener('appinstalled', (_event) => {
    console.log('✅ PWA was installed successfully')
    deferredPrompt = null
    
    // Track install event
    if ('gtag' in window) {
      // @ts-ignore
      gtag('event', 'pwa_install', {
        event_category: 'PWA',
        event_label: 'App Installed'
      })
    }
  })
  
  function showInstallBanner() {
    // Create install banner
    const banner = document.createElement('div')
    banner.id = 'pwa-install-banner'
    banner.innerHTML = `
      <div style="
        position: fixed;
        bottom: 20px;
        left: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        color: white;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        z-index: 1000;
        max-width: 400px;
        margin: 0 auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      ">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="font-size: 24px;">🌿</div>
          <div style="flex: 1;">
            <div style="font-weight: 600; margin-bottom: 4px;">Installer EcoComfort</div>
            <div style="font-size: 14px; opacity: 0.8;">Accès rapide depuis votre écran d'accueil</div>
          </div>
          <button id="install-pwa-btn" style="
            background: #22c55e;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
          ">Installer</button>
          <button id="dismiss-pwa-btn" style="
            background: transparent;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
            opacity: 0.7;
          ">✕</button>
        </div>
      </div>
    `
    
    document.body.appendChild(banner)
    
    // Handle install button click
    document.getElementById('install-pwa-btn')?.addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt()
        const { outcome } = await deferredPrompt.userChoice
        console.log('PWA install choice:', outcome)
        
        deferredPrompt = null
        banner.remove()
      }
    })
    
    // Handle dismiss button click
    document.getElementById('dismiss-pwa-btn')?.addEventListener('click', () => {
      banner.remove()
    })
    
    // Auto-dismiss after 10 seconds
    setTimeout(() => {
      if (banner.parentNode) {
        banner.remove()
      }
    }, 10000)
  }
}

// Handle notification clicks from service worker
navigator.serviceWorker?.addEventListener('message', (event) => {
  if (event.data?.type === 'navigate') {
    // Handle navigation from service worker notifications
    window.history.pushState({}, '', event.data.url)
    window.dispatchEvent(new PopStateEvent('popstate'))
  }
})

// Handle online/offline status
window.addEventListener('online', () => {
  console.log('🟢 Back online')
  // Trigger background sync if service worker is available
  if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
    navigator.serviceWorker.ready.then((registration) => {
      return (registration as any).sync.register('sync-sensor-data')
    }).catch((error) => {
      console.error('Background sync registration failed:', error)
    })
  }
})

window.addEventListener('offline', () => {
  console.log('🔴 Gone offline')
})

// Initialize PWA features
initializePWA()

// Fix viewport height on mobile devices
const setViewportHeight = () => {
  const vh = window.innerHeight * 0.01
  document.documentElement.style.setProperty('--vh', `${vh}px`)
}

// Set initially and on resize
setViewportHeight()
window.addEventListener('resize', setViewportHeight)
window.addEventListener('orientationchange', () => {
  // Delay to ensure proper measurement after orientation change
  setTimeout(setViewportHeight, 100)
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
