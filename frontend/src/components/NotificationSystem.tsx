import { useState, useEffect } from 'react'
import { 
  AlertTriangle, 
  Info, 
  CheckCircle, 
  XCircle, 
  X, 
  Clock,
  Zap,
  DoorOpen,
  Thermometer,
  Bell,
  BellOff,
  Volume2,
  VolumeX
} from 'lucide-react'
import webSocketService from '../services/websocket'
import type { Notification, NotificationState, NotificationAction } from '../types'

interface NotificationSystemProps {
  className?: string
}

interface InAppNotification extends Notification {
  id: string
  isVisible: boolean
  progress?: number
  remainingTime?: number
}

const NotificationSystem = ({ className = '' }: NotificationSystemProps) => {
  const [notifications, setNotifications] = useState<InAppNotification[]>([])
  const [soundEnabled, setSoundEnabled] = useState(true)
  const [isMinimized, setIsMinimized] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)

  useEffect(() => {
    // Subscribe to WebSocket notifications
    const unsubscribeAlert = webSocketService.on('alert-created', handleNewAlert)
    const unsubscribeCritical = webSocketService.on('critical-alert', handleCriticalAlert)
    const unsubscribeNotification = webSocketService.on('notification-received', handleNotificationReceived)
    const unsubscribeEnergy = webSocketService.on('energy-updated', handleEnergyUpdate)

    // Load sound preference from localStorage
    const soundPref = localStorage.getItem('ecocomfort-sound-enabled')
    if (soundPref !== null) {
      setSoundEnabled(JSON.parse(soundPref))
    }

    return () => {
      unsubscribeAlert()
      unsubscribeCritical()
      unsubscribeNotification()
      unsubscribeEnergy()
    }
  }, [])

  const handleNewAlert = (event: any) => {
    const notification: InAppNotification = {
      id: `alert-${event.data.event_id}`,
      title: `⚠️ Alerte - ${event.data.room_name}`,
      message: event.data.message,
      type: getNotificationTypeFromSeverity(event.data.severity),
      timestamp: new Date().toISOString(),
      isVisible: true,
      actions: event.data.severity === 'critical' ? [
        {
          id: 'acknowledge',
          label: 'Accusé réception',
          type: 'accept',
          reward: { points: 10, description: 'Réaction rapide' }
        },
        {
          id: 'view-room',
          label: 'Voir la salle',
          type: 'accept'
        }
      ] : [
        {
          id: 'acknowledge',
          label: 'OK',
          type: 'accept'
        }
      ],
      auto_resolve: event.data.severity !== 'critical',
      resolve_timeout: event.data.severity === 'critical' ? 300000 : 30000 // 5min for critical, 30s for others
    }

    addNotification(notification)
    playNotificationSound(notification.type)
  }

  const handleCriticalAlert = (event: any) => {
    const notification: InAppNotification = {
      id: `critical-${Date.now()}`,
      title: '🚨 ALERTE CRITIQUE',
      message: event.message || 'Situation critique détectée',
      type: 'alerte_critical',
      timestamp: new Date().toISOString(),
      isVisible: true,
      actions: [
        {
          id: 'acknowledge',
          label: '✅ J\'interviens',
          type: 'accept',
          reward: { points: 50, description: 'Intervention critique' }
        },
        {
          id: 'snooze',
          label: '⏰ Reporter (5min)',
          type: 'snooze'
        }
      ],
      auto_resolve: false
    }

    addNotification(notification)
    playNotificationSound('alerte_critical')
    
    // Vibrate if supported
    if ('navigator' in window && 'vibrate' in navigator) {
      navigator.vibrate([300, 100, 300, 100, 300])
    }
  }

  const handleNotificationReceived = (event: any) => {
    const notification: InAppNotification = {
      id: `notif-${event.id}`,
      title: event.title,
      message: event.message,
      type: event.type || 'normal',
      timestamp: event.timestamp,
      isVisible: true,
      actions: event.actions,
      auto_resolve: event.auto_resolve !== false,
      resolve_timeout: event.resolve_timeout || 10000
    }

    addNotification(notification)
    playNotificationSound(notification.type)
  }

  const handleEnergyUpdate = (event: any) => {
    if (event.data.high_loss_detected) {
      const notification: InAppNotification = {
        id: `energy-loss-${Date.now()}`,
        title: '⚡ Perte Énergétique Élevée',
        message: `${event.data.loss_watts}W détectés - ${event.data.room_name}`,
        type: 'alerte_warning',
        timestamp: new Date().toISOString(),
        isVisible: true,
        actions: [
          {
            id: 'check-room',
            label: 'Vérifier la salle',
            type: 'accept',
            reward: { points: 20, description: 'Vérification énergétique' }
          }
        ],
        auto_resolve: true,
        resolve_timeout: 60000
      }

      addNotification(notification)
      playNotificationSound('alerte_warning')
    }
  }

  const addNotification = (notification: InAppNotification) => {
    setNotifications(prev => [notification, ...prev])
    setUnreadCount(prev => prev + 1)

    // Auto-resolve if configured
    if (notification.auto_resolve && notification.resolve_timeout) {
      const startTime = Date.now()
      const interval = setInterval(() => {
        const elapsed = Date.now() - startTime
        const remaining = notification.resolve_timeout! - elapsed
        const progress = (elapsed / notification.resolve_timeout!) * 100

        setNotifications(prev => prev.map(n => 
          n.id === notification.id 
            ? { ...n, progress, remainingTime: Math.max(0, remaining) }
            : n
        ))

        if (elapsed >= notification.resolve_timeout!) {
          clearInterval(interval)
          removeNotification(notification.id)
        }
      }, 100)
    }
  }

  const removeNotification = (id: string) => {
    setNotifications(prev => prev.filter(n => n.id !== id))
  }

  const handleAction = async (notificationId: string, action: NotificationAction) => {
    const notification = notifications.find(n => n.id === notificationId)
    if (!notification) return

    try {
      // Handle different action types
      switch (action.type) {
        case 'accept':
          if (action.id === 'acknowledge') {
            // Send acknowledgment to server
            await fetch('/api/notifications/acknowledge', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ notification_id: notificationId })
            })
          }
          
          // Award points if action has reward
          if (action.reward) {
            showRewardNotification(action.reward)
          }
          
          removeNotification(notificationId)
          break

        case 'reject':
          removeNotification(notificationId)
          break

        case 'snooze':
          // Remove temporarily and re-add after delay
          removeNotification(notificationId)
          setTimeout(() => {
            addNotification({ ...notification, id: `${notificationId}-snoozed` })
          }, 300000) // 5 minutes
          break
      }
    } catch (error) {
      console.error('Failed to handle notification action:', error)
    }
  }

  const showRewardNotification = (reward: { points: number; description: string }) => {
    const rewardNotif: InAppNotification = {
      id: `reward-${Date.now()}`,
      title: '🎉 Points Gagnés!',
      message: `+${reward.points} points - ${reward.description}`,
      type: 'normal',
      timestamp: new Date().toISOString(),
      isVisible: true,
      auto_resolve: true,
      resolve_timeout: 5000
    }
    addNotification(rewardNotif)
    playNotificationSound('normal')
  }

  const playNotificationSound = async (type: NotificationState) => {
    if (!soundEnabled) return

    try {
      // Create AudioContext only when needed and handle user interaction requirement
      const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)()
      
      // Resume AudioContext if suspended (required by browsers)
      if (audioContext.state === 'suspended') {
        await audioContext.resume()
      }
      
      const oscillator = audioContext.createOscillator()
      const gainNode = audioContext.createGain()

      oscillator.connect(gainNode)
      gainNode.connect(audioContext.destination)

      // Different frequencies for different notification types
      const frequencies = {
        normal: 440,
        alerte_info: 523,
        alerte_warning: 659,
        alerte_critical: 880,
        action_auto: 349
      }

      oscillator.frequency.setValueAtTime(frequencies[type] || 440, audioContext.currentTime)
      oscillator.type = 'sine'

      gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
      gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5)

      oscillator.start(audioContext.currentTime)
      oscillator.stop(audioContext.currentTime + 0.5)
      
      // Close AudioContext after use to free resources
      setTimeout(() => {
        audioContext.close()
      }, 600)
    } catch (error) {
      console.warn('Could not play notification sound:', error)
    }
  }

  const getNotificationTypeFromSeverity = (severity: string): NotificationState => {
    switch (severity) {
      case 'critical': return 'alerte_critical'
      case 'warning': return 'alerte_warning'
      case 'info': return 'alerte_info'
      default: return 'normal'
    }
  }

  const getNotificationIcon = (type: NotificationState) => {
    switch (type) {
      case 'alerte_critical': return <XCircle className="w-5 h-5 text-red-400" />
      case 'alerte_warning': return <AlertTriangle className="w-5 h-5 text-orange-400" />
      case 'alerte_info': return <Info className="w-5 h-5 text-blue-400" />
      case 'action_auto': return <Zap className="w-5 h-5 text-purple-400" />
      default: return <CheckCircle className="w-5 h-5 text-green-400" />
    }
  }

  const getNotificationStyles = (type: NotificationState) => {
    const baseStyles = "glass-card p-4 mb-3 border-l-4 transition-all duration-300"
    
    switch (type) {
      case 'alerte_critical':
        return `${baseStyles} border-l-red-400 bg-red-500/10 animate-glow`
      case 'alerte_warning':
        return `${baseStyles} border-l-orange-400 bg-orange-500/10`
      case 'alerte_info':
        return `${baseStyles} border-l-blue-400 bg-blue-500/10`
      case 'action_auto':
        return `${baseStyles} border-l-purple-400 bg-purple-500/10`
      default:
        return `${baseStyles} border-l-green-400 bg-green-500/10`
    }
  }

  const toggleSound = () => {
    const newState = !soundEnabled
    setSoundEnabled(newState)
    localStorage.setItem('ecocomfort-sound-enabled', JSON.stringify(newState))
  }

  const clearAll = () => {
    setNotifications([])
    setUnreadCount(0)
  }

  const markAllAsRead = () => {
    setUnreadCount(0)
  }

  if (isMinimized) {
    return (
      <div className={`fixed top-4 right-4 z-50 ${className}`}>
        <button
          onClick={() => {
            setIsMinimized(false)
            markAllAsRead()
          }}
          className="glass-card-hover p-3 flex items-center gap-2 text-white"
        >
          <Bell className="w-5 h-5" />
          {unreadCount > 0 && (
            <span className="bg-red-500 text-white text-xs px-2 py-1 rounded-full min-w-[20px] h-5 flex items-center justify-center">
              {unreadCount}
            </span>
          )}
        </button>
      </div>
    )
  }

  return (
    <div className={`fixed top-4 right-4 z-50 max-w-md w-full ${className}`}>
      {/* Header */}
      <div className="glass-card p-3 mb-2 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Bell className="w-5 h-5 text-white" />
          <span className="text-white font-medium">Notifications</span>
          {unreadCount > 0 && (
            <span className="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
              {unreadCount}
            </span>
          )}
        </div>
        
        <div className="flex items-center gap-2">
          <button
            onClick={toggleSound}
            className="p-1 text-white/70 hover:text-white transition-colors"
            title={soundEnabled ? 'Désactiver les sons' : 'Activer les sons'}
          >
            {soundEnabled ? <Volume2 className="w-4 h-4" /> : <VolumeX className="w-4 h-4" />}
          </button>
          
          {notifications.length > 0 && (
            <button
              onClick={clearAll}
              className="p-1 text-white/70 hover:text-white transition-colors text-sm"
              title="Tout effacer"
            >
              Effacer
            </button>
          )}
          
          <button
            onClick={() => setIsMinimized(true)}
            className="p-1 text-white/70 hover:text-white transition-colors"
            title="Réduire"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Notifications List */}
      <div className="max-h-96 overflow-y-auto custom-scrollbar space-y-2">
        {notifications.length === 0 ? (
          <div className="glass-card p-4 text-center text-white/70">
            <BellOff className="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p>Aucune notification</p>
          </div>
        ) : (
          notifications.map((notification) => (
            <div
              key={notification.id}
              className={getNotificationStyles(notification.type)}
            >
              <div className="flex items-start gap-3">
                {getNotificationIcon(notification.type)}
                
                <div className="flex-1 min-w-0">
                  <h4 className="text-white font-medium text-sm mb-1">
                    {notification.title}
                  </h4>
                  <p className="text-white/80 text-sm mb-2">
                    {notification.message}
                  </p>
                  
                  {notification.progress !== undefined && (
                    <div className="mb-2">
                      <div className="w-full bg-white/20 rounded-full h-1">
                        <div 
                          className="bg-white/60 h-1 rounded-full transition-all duration-100"
                          style={{ width: `${notification.progress}%` }}
                        />
                      </div>
                      {notification.remainingTime && (
                        <div className="flex items-center gap-1 mt-1">
                          <Clock className="w-3 h-3 text-white/50" />
                          <span className="text-xs text-white/50">
                            {Math.round(notification.remainingTime / 1000)}s
                          </span>
                        </div>
                      )}
                    </div>
                  )}
                  
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-white/50">
                      {new Date(notification.timestamp).toLocaleTimeString('fr-FR')}
                    </span>
                    
                    {notification.actions && notification.actions.length > 0 && (
                      <div className="flex gap-2">
                        {notification.actions.map((action) => (
                          <button
                            key={action.id}
                            onClick={() => handleAction(notification.id, action)}
                            className={`px-3 py-1 rounded text-xs font-medium transition-colors ${
                              action.type === 'accept' 
                                ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                                : action.type === 'reject'
                                ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                                : 'bg-orange-500/20 text-orange-400 hover:bg-orange-500/30'
                            }`}
                          >
                            {action.label}
                            {action.reward && (
                              <span className="ml-1">+{action.reward.points}</span>
                            )}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
                
                <button
                  onClick={() => removeNotification(notification.id)}
                  className="p-1 text-white/50 hover:text-white/80 transition-colors"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default NotificationSystem