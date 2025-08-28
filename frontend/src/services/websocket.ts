import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { SensorDataUpdateEvent, AlertCreatedEvent } from '../types';

// Configure Pusher
(window as any).Pusher = Pusher;

class WebSocketService {
  private echo: Echo<any> | null = null;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 10;
  private reconnectDelay = 1000;
  private isConnected = false;
  private subscribers: Map<string, Set<Function>> = new Map();
  private organizationId: string | null = null;
  private userId: string | null = null;

  constructor() {
    this.initializeEcho();
  }

  private initializeEcho() {
    try {
      this.echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_APP_KEY || 'app-key',
        wsHost: import.meta.env.VITE_WS_HOST || 'localhost',
        wsPort: parseInt(import.meta.env.VITE_WS_PORT || '8080'),
        wssPort: parseInt(import.meta.env.VITE_WS_PORT || '8080'),
        forceTLS: false,
        encrypted: false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: any) => {
          return {
            authorize: (socketId: string, callback: Function) => {
              // Get auth token from localStorage or state management
              const token = localStorage.getItem('auth_token');
              
              if (!token) {
                callback('Unauthorized', null);
                return;
              }

              fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/broadcasting/auth`, {
                method: 'POST',
                headers: {
                  'Authorization': `Bearer ${token}`,
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                },
                body: JSON.stringify({
                  socket_id: socketId,
                  channel_name: channel.name,
                })
              })
              .then(response => response.json())
              .then(data => {
                callback(null, data);
              })
              .catch(error => {
                console.error('WebSocket auth error:', error);
                callback('Unauthorized', null);
              });
            }
          };
        },
      });

      // Connection event handlers
      this.echo.connector.pusher.connection.bind('connected', () => {
        console.log('✅ WebSocket connected');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.emit('connected', { connected: true });
      });

      this.echo.connector.pusher.connection.bind('disconnected', () => {
        console.log('❌ WebSocket disconnected');
        this.isConnected = false;
        this.emit('disconnected', { connected: false });
        this.attemptReconnect();
      });

      this.echo.connector.pusher.connection.bind('error', (error: any) => {
        console.error('❌ WebSocket error:', error);
        this.emit('error', { error });
      });

    } catch (error) {
      console.error('Failed to initialize WebSocket:', error);
      this.attemptReconnect();
    }
  }

  private attemptReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    console.log(`🔄 Attempting reconnection ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);

    setTimeout(() => {
      this.initializeEcho();
    }, this.reconnectDelay * this.reconnectAttempts);
  }

  // Initialize user-specific channels
  public initializeUser(userId: string, organizationId: string) {
    this.userId = userId;
    this.organizationId = organizationId;

    if (!this.echo) return;

    // Subscribe to organization-wide updates
    this.subscribeToOrganization(organizationId);

    // Subscribe to user-specific notifications
    this.subscribeToUserNotifications(userId);

    // Subscribe to critical alerts
    this.subscribeToCriticalAlerts(organizationId);
  }

  // Subscribe to organization updates
  public subscribeToOrganization(organizationId: string) {
    if (!this.echo) return;

    const channelName = `organization.${organizationId}`;
    
    this.echo.private(channelName)
      .listen('SensorDataUpdated', (event: SensorDataUpdateEvent) => {
        this.emit('sensor-data-updated', event);
      })
      .listen('AlertCreated', (event: AlertCreatedEvent) => {
        this.emit('alert-created', event);
      })
      .listen('EnergyAnalyticsUpdated', (event: any) => {
        this.emit('energy-updated', event);
      });
  }

  // Subscribe to specific room updates
  public subscribeToRoom(roomId: string) {
    if (!this.echo) return;

    const channelName = `room.${roomId}`;
    
    this.echo.private(channelName)
      .listen('SensorDataUpdated', (event: SensorDataUpdateEvent) => {
        this.emit('room-sensor-updated', { ...event, roomId });
      })
      .listen('AlertCreated', (event: AlertCreatedEvent) => {
        this.emit('room-alert-created', { ...event, roomId });
      });
  }

  // Subscribe to specific sensor updates
  public subscribeToSensor(sensorId: string) {
    if (!this.echo) return;

    const channelName = `sensor.${sensorId}`;
    
    this.echo.private(channelName)
      .listen('SensorDataUpdated', (event: SensorDataUpdateEvent) => {
        this.emit('sensor-updated', { ...event, sensorId });
      })
      .listen('SensorAlert', (event: any) => {
        this.emit('sensor-alert', { ...event, sensorId });
      });
  }

  // Subscribe to user notifications
  public subscribeToUserNotifications(userId: string) {
    if (!this.echo) return;

    const channelName = `notifications.${userId}`;
    
    this.echo.private(channelName)
      .listen('NotificationSent', (event: any) => {
        this.emit('notification-received', event);
        this.showPushNotification(event);
      });
  }

  // Subscribe to critical alerts
  public subscribeToCriticalAlerts(organizationId: string) {
    if (!this.echo) return;

    const channelName = `critical-alerts.${organizationId}`;
    
    this.echo.private(channelName)
      .listen('CriticalAlert', (event: any) => {
        this.emit('critical-alert', event);
        this.showCriticalNotification(event);
      });
  }

  // Subscribe to leaderboard updates
  public subscribeToLeaderboard(organizationId: string) {
    if (!this.echo) return;

    const channelName = `leaderboard.${organizationId}`;
    
    this.echo.private(channelName)
      .listen('LeaderboardUpdated', (event: any) => {
        this.emit('leaderboard-updated', event);
      });
  }

  // Event subscription system
  public on(eventType: string, callback: Function) {
    if (!this.subscribers.has(eventType)) {
      this.subscribers.set(eventType, new Set());
    }
    this.subscribers.get(eventType)!.add(callback);

    // Return unsubscribe function
    return () => {
      this.subscribers.get(eventType)?.delete(callback);
    };
  }

  public off(eventType: string, callback?: Function) {
    if (!callback) {
      this.subscribers.delete(eventType);
      return;
    }
    this.subscribers.get(eventType)?.delete(callback);
  }

  public emit(eventType: string, data: any) {
    const callbacks = this.subscribers.get(eventType);
    if (callbacks) {
      callbacks.forEach(callback => callback(data));
    }
  }

  // Push notification support
  private async showPushNotification(event: any) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    const options: NotificationOptions = {
      body: event.message || 'New notification received',
      icon: '/pwa-192x192.png',
      badge: '/pwa-192x192.png',
      tag: `ecocomfort-${event.id}`,
      requireInteraction: event.severity === 'critical',
      // actions: event.actions || [], // Actions not supported in standard Notification API
      data: event
      // timestamp: new Date().getTime() // Not supported in NotificationOptions
    };

    try {
      const notification = new Notification(event.title || 'EcoComfort', options);
      
      notification.onclick = () => {
        window.focus();
        notification.close();
        // Navigate to relevant page if needed
        this.emit('notification-clicked', event);
      };

      // Auto-close non-critical notifications
      if (event.severity !== 'critical') {
        setTimeout(() => notification.close(), 5000);
      }

    } catch (error) {
      console.error('Failed to show push notification:', error);
    }
  }

  private async showCriticalNotification(event: any) {
    // Always show critical notifications as browser notifications
    if ('Notification' in window) {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        this.showPushNotification({
          ...event,
          severity: 'critical',
          title: '🚨 ALERTE CRITIQUE - EcoComfort'
        });
      }
    }

    // Also emit for UI handling
    this.emit('critical-notification', event);
  }

  // Utility methods
  public isConnected_(): boolean {
    return this.isConnected;
  }

  public getConnectionState(): string {
    if (!this.echo) return 'disconnected';
    return this.echo.connector.pusher.connection.state;
  }

  public disconnect() {
    if (this.echo) {
      this.echo.disconnect();
      this.echo = null;
    }
    this.isConnected = false;
    this.subscribers.clear();
  }

  public reconnect() {
    this.disconnect();
    this.reconnectAttempts = 0;
    this.initializeEcho();
    
    // Re-subscribe to channels if user is set
    if (this.userId && this.organizationId) {
      setTimeout(() => {
        this.initializeUser(this.userId!, this.organizationId!);
      }, 1000);
    }
  }

  // Leave specific channels
  public leaveRoom(roomId: string) {
    if (this.echo) {
      this.echo.leave(`room.${roomId}`);
    }
  }

  public leaveSensor(sensorId: string) {
    if (this.echo) {
      this.echo.leave(`sensor.${sensorId}`);
    }
  }

  // Health check
  public async healthCheck(): Promise<boolean> {
    return new Promise((resolve) => {
      if (!this.echo) {
        resolve(false);
        return;
      }

      const timeout = setTimeout(() => {
        resolve(false);
      }, 5000);

      this.echo.connector.pusher.connection.bind('connected', () => {
        clearTimeout(timeout);
        resolve(true);
      });

      this.echo.connector.pusher.connection.bind('error', () => {
        clearTimeout(timeout);
        resolve(false);
      });
    });
  }
}

// Create singleton instance
const webSocketService = new WebSocketService();
export default webSocketService;