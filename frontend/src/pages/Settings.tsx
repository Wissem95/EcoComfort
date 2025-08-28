import { useState, useEffect } from 'react'
import { 
  Settings as SettingsIcon, 
  Bell, 
  BellOff,
  Volume2, 
  VolumeX,
  Moon, 
  Sun,
  Globe,
  Thermometer,
  Euro,
  Shield,
  Database,
  Trash2,
  Download,
  Upload,
  Smartphone,
  Monitor,
  Wifi,
  WifiOff,
  Palette,
  Zap,
  Target,
  Users,
  Clock,
  Save,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
  Info
} from 'lucide-react'
import type { UserSettings } from '../types'

const Settings = () => {
  const [settings, setSettings] = useState<UserSettings>({
    notifications: {
      push_enabled: true,
      email_enabled: true,
      critical_only: false,
      quiet_hours: {
        enabled: false,
        start: '22:00',
        end: '07:00'
      }
    },
    display: {
      theme: 'dark',
      temperature_unit: 'celsius',
      currency: 'EUR',
      language: 'fr'
    },
    gamification: {
      enabled: true,
      show_leaderboard: true,
      show_notifications: true
    }
  })
  
  const [isDirty, setIsDirty] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [saveStatus, setSaveStatus] = useState<'idle' | 'success' | 'error'>('idle')
  const [showResetConfirm, setShowResetConfirm] = useState(false)
  const [activeTab, setActiveTab] = useState<'general' | 'notifications' | 'privacy' | 'advanced'>('general')

  useEffect(() => {
    // Load settings from localStorage
    const savedSettings = localStorage.getItem('ecocomfort-settings')
    if (savedSettings) {
      try {
        const parsed = JSON.parse(savedSettings)
        setSettings(parsed)
      } catch (error) {
        console.error('Failed to load settings:', error)
      }
    }
  }, [])

  const updateSettings = (path: string, value: any) => {
    setSettings(prev => {
      const keys = path.split('.')
      const updated = { ...prev }
      let current: any = updated
      
      for (let i = 0; i < keys.length - 1; i++) {
        current[keys[i]] = { ...current[keys[i]] }
        current = current[keys[i]]
      }
      
      current[keys[keys.length - 1]] = value
      return updated
    })
    setIsDirty(true)
  }

  const saveSettings = async () => {
    setIsSaving(true)
    setSaveStatus('idle')
    
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      localStorage.setItem('ecocomfort-settings', JSON.stringify(settings))
      
      // Apply theme change
      document.documentElement.classList.toggle('dark', settings.display.theme === 'dark')
      
      setIsDirty(false)
      setSaveStatus('success')
      
      setTimeout(() => setSaveStatus('idle'), 3000)
    } catch (error) {
      setSaveStatus('error')
      console.error('Failed to save settings:', error)
    } finally {
      setIsSaving(false)
    }
  }

  const resetSettings = () => {
    const defaultSettings: UserSettings = {
      notifications: {
        push_enabled: true,
        email_enabled: true,
        critical_only: false,
        quiet_hours: {
          enabled: false,
          start: '22:00',
          end: '07:00'
        }
      },
      display: {
        theme: 'dark',
        temperature_unit: 'celsius',
        currency: 'EUR',
        language: 'fr'
      },
      gamification: {
        enabled: true,
        show_leaderboard: true,
        show_notifications: true
      }
    }
    
    setSettings(defaultSettings)
    setIsDirty(true)
    setShowResetConfirm(false)
  }

  const exportSettings = () => {
    const dataStr = JSON.stringify(settings, null, 2)
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr)
    
    const exportFileDefaultName = `ecocomfort-settings-${new Date().toISOString().split('T')[0]}.json`
    
    const linkElement = document.createElement('a')
    linkElement.setAttribute('href', dataUri)
    linkElement.setAttribute('download', exportFileDefaultName)
    linkElement.click()
  }

  const importSettings = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return
    
    const reader = new FileReader()
    reader.onload = (e) => {
      try {
        const imported = JSON.parse(e.target?.result as string)
        setSettings(imported)
        setIsDirty(true)
      } catch (error) {
        console.error('Failed to import settings:', error)
        alert('Fichier de paramètres invalide')
      }
    }
    reader.readAsText(file)
  }

  const requestNotificationPermission = async () => {
    if ('Notification' in window) {
      const permission = await Notification.requestPermission()
      if (permission === 'granted') {
        updateSettings('notifications.push_enabled', true)
      }
    }
  }

  const testNotification = () => {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('🌿 EcoComfort Test', {
        body: 'Les notifications fonctionnent correctement !',
        icon: '/pwa-192x192.png',
        tag: 'test-notification'
      })
    }
  }

  const tabs = [
    { id: 'general', label: 'Général', icon: SettingsIcon },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'privacy', label: 'Confidentialité', icon: Shield },
    { id: 'advanced', label: 'Avancé', icon: Database }
  ] as const

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="glass-card p-4 md:p-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-white mb-2 flex items-center gap-3">
              <SettingsIcon className="w-8 h-8 text-blue-400" />
              Paramètres
            </h1>
            <p className="text-white/70">
              Personnalisez votre expérience EcoComfort
            </p>
          </div>
          
          <div className="flex gap-2">
            {saveStatus === 'success' && (
              <div className="flex items-center gap-2 text-green-400 text-sm">
                <CheckCircle className="w-4 h-4" />
                Sauvegardé
              </div>
            )}
            {saveStatus === 'error' && (
              <div className="flex items-center gap-2 text-red-400 text-sm">
                <AlertTriangle className="w-4 h-4" />
                Erreur
              </div>
            )}
            {isDirty && (
              <button
                onClick={saveSettings}
                disabled={isSaving}
                className="glass-button-primary flex items-center gap-2"
              >
                {isSaving ? (
                  <RefreshCw className="w-4 h-4 animate-spin" />
                ) : (
                  <Save className="w-4 h-4" />
                )}
                Sauvegarder
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="glass-card p-2">
        <div className="flex gap-1 overflow-x-auto">
          {tabs.map((tab) => {
            const Icon = tab.icon
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap ${
                  activeTab === tab.id
                    ? 'bg-primary-500/20 text-primary-300 border border-primary-400/30'
                    : 'text-white/70 hover:text-white hover:bg-white/5'
                }`}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
              </button>
            )
          })}
        </div>
      </div>

      {/* General Settings */}
      {activeTab === 'general' && (
        <div className="space-y-6">
          {/* Display Settings */}
          <div className="glass-card p-6">
            <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
              <Palette className="w-5 h-5" />
              Affichage
            </h3>
            
            <div className="space-y-4">
              {/* Theme */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  {settings.display.theme === 'dark' ? (
                    <Moon className="w-5 h-5 text-blue-400" />
                  ) : (
                    <Sun className="w-5 h-5 text-yellow-400" />
                  )}
                  <div>
                    <p className="text-white font-medium">Thème</p>
                    <p className="text-white/60 text-sm">Mode sombre ou clair</p>
                  </div>
                </div>
                <select
                  value={settings.display.theme}
                  onChange={(e) => updateSettings('display.theme', e.target.value)}
                  className="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                >
                  <option value="light">Clair</option>
                  <option value="dark">Sombre</option>
                  <option value="auto">Automatique</option>
                </select>
              </div>

              {/* Language */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Globe className="w-5 h-5 text-green-400" />
                  <div>
                    <p className="text-white font-medium">Langue</p>
                    <p className="text-white/60 text-sm">Langue de l'interface</p>
                  </div>
                </div>
                <select
                  value={settings.display.language}
                  onChange={(e) => updateSettings('display.language', e.target.value)}
                  className="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                >
                  <option value="fr">Français</option>
                  <option value="en">English</option>
                </select>
              </div>

              {/* Temperature Unit */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Thermometer className="w-5 h-5 text-red-400" />
                  <div>
                    <p className="text-white font-medium">Unité de température</p>
                    <p className="text-white/60 text-sm">Celsius ou Fahrenheit</p>
                  </div>
                </div>
                <select
                  value={settings.display.temperature_unit}
                  onChange={(e) => updateSettings('display.temperature_unit', e.target.value)}
                  className="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                >
                  <option value="celsius">Celsius (°C)</option>
                  <option value="fahrenheit">Fahrenheit (°F)</option>
                </select>
              </div>

              {/* Currency */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Euro className="w-5 h-5 text-yellow-400" />
                  <div>
                    <p className="text-white font-medium">Devise</p>
                    <p className="text-white/60 text-sm">Monnaie pour les coûts</p>
                  </div>
                </div>
                <select
                  value={settings.display.currency}
                  onChange={(e) => updateSettings('display.currency', e.target.value)}
                  className="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                >
                  <option value="EUR">Euro (€)</option>
                  <option value="USD">Dollar ($)</option>
                </select>
              </div>
            </div>
          </div>

          {/* Gamification Settings */}
          <div className="glass-card p-6">
            <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
              <Target className="w-5 h-5" />
              Gamification
            </h3>
            
            <div className="space-y-4">
              {/* Enable Gamification */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Zap className="w-5 h-5 text-purple-400" />
                  <div>
                    <p className="text-white font-medium">Activer la gamification</p>
                    <p className="text-white/60 text-sm">Points, niveaux, et récompenses</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.gamification.enabled}
                    onChange={(e) => updateSettings('gamification.enabled', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              {/* Show Leaderboard */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Users className="w-5 h-5 text-blue-400" />
                  <div>
                    <p className="text-white font-medium">Afficher le classement</p>
                    <p className="text-white/60 text-sm">Comparaison avec les collègues</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.gamification.show_leaderboard}
                    onChange={(e) => updateSettings('gamification.show_leaderboard', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              {/* Gamification Notifications */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Bell className="w-5 h-5 text-green-400" />
                  <div>
                    <p className="text-white font-medium">Notifications de progression</p>
                    <p className="text-white/60 text-sm">Nouveaux niveaux et réalisations</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.gamification.show_notifications}
                    onChange={(e) => updateSettings('gamification.show_notifications', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Notifications Settings */}
      {activeTab === 'notifications' && (
        <div className="space-y-6">
          <div className="glass-card p-6">
            <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
              <Bell className="w-5 h-5" />
              Notifications
            </h3>
            
            <div className="space-y-4">
              {/* Push Notifications */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Smartphone className="w-5 h-5 text-blue-400" />
                  <div>
                    <p className="text-white font-medium">Notifications push</p>
                    <p className="text-white/60 text-sm">Alertes temps réel sur votre appareil</p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={testNotification}
                    className="text-xs px-2 py-1 bg-blue-500/20 text-blue-400 rounded hover:bg-blue-500/30 transition-colors"
                  >
                    Test
                  </button>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={settings.notifications.push_enabled}
                      onChange={(e) => updateSettings('notifications.push_enabled', e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                  </label>
                </div>
              </div>

              {/* Email Notifications */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Monitor className="w-5 h-5 text-green-400" />
                  <div>
                    <p className="text-white font-medium">Notifications email</p>
                    <p className="text-white/60 text-sm">Résumés quotidiens et alertes importantes</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.notifications.email_enabled}
                    onChange={(e) => updateSettings('notifications.email_enabled', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              {/* Critical Only */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <AlertTriangle className="w-5 h-5 text-red-400" />
                  <div>
                    <p className="text-white font-medium">Alertes critiques uniquement</p>
                    <p className="text-white/60 text-sm">Ne recevoir que les notifications urgentes</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.notifications.critical_only}
                    onChange={(e) => updateSettings('notifications.critical_only', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              {/* Quiet Hours */}
              <div className="border-t border-white/10 pt-4">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <Clock className="w-5 h-5 text-purple-400" />
                    <div>
                      <p className="text-white font-medium">Heures de silence</p>
                      <p className="text-white/60 text-sm">Suspendre les notifications pendant certaines heures</p>
                    </div>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={settings.notifications.quiet_hours.enabled}
                      onChange={(e) => updateSettings('notifications.quiet_hours.enabled', e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                  </label>
                </div>

                {settings.notifications.quiet_hours.enabled && (
                  <div className="grid grid-cols-2 gap-4 ml-8">
                    <div>
                      <label className="block text-white/70 text-sm mb-2">Début</label>
                      <input
                        type="time"
                        value={settings.notifications.quiet_hours.start}
                        onChange={(e) => updateSettings('notifications.quiet_hours.start', e.target.value)}
                        className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                      />
                    </div>
                    <div>
                      <label className="block text-white/70 text-sm mb-2">Fin</label>
                      <input
                        type="time"
                        value={settings.notifications.quiet_hours.end}
                        onChange={(e) => updateSettings('notifications.quiet_hours.end', e.target.value)}
                        className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* Permission Request */}
              {!settings.notifications.push_enabled && 'Notification' in window && Notification.permission === 'default' && (
                <div className="p-4 bg-blue-500/20 border border-blue-400/30 rounded-lg">
                  <div className="flex items-center gap-3 mb-3">
                    <Info className="w-5 h-5 text-blue-400" />
                    <h4 className="text-blue-300 font-medium">Autoriser les notifications</h4>
                  </div>
                  <p className="text-blue-200 text-sm mb-3">
                    Autorisez les notifications pour recevoir des alertes énergétiques importantes en temps réel.
                  </p>
                  <button
                    onClick={requestNotificationPermission}
                    className="glass-button-primary text-sm"
                  >
                    Autoriser les notifications
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Privacy Settings */}
      {activeTab === 'privacy' && (
        <div className="glass-card p-6">
          <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <Shield className="w-5 h-5" />
            Confidentialité & Sécurité
          </h3>
          
          <div className="space-y-6">
            <div className="p-4 bg-green-500/10 border border-green-400/30 rounded-lg">
              <div className="flex items-center gap-3 mb-3">
                <CheckCircle className="w-5 h-5 text-green-400" />
                <h4 className="text-green-300 font-medium">Vos données sont protégées</h4>
              </div>
              <div className="text-green-200 text-sm space-y-2">
                <p>• Toutes les communications sont chiffrées (HTTPS/WSS)</p>
                <p>• Les données sensorielles sont anonymisées</p>
                <p>• Aucune donnée personnelle n'est vendue à des tiers</p>
                <p>• Vous contrôlez entièrement vos données</p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="p-4 bg-white/5 rounded-lg">
                <h4 className="text-white font-medium mb-2">Données collectées</h4>
                <ul className="text-white/70 text-sm space-y-1">
                  <li>• Données des capteurs (température, humidité)</li>
                  <li>• Actions de gamification</li>
                  <li>• Préférences utilisateur</li>
                  <li>• Logs d'activité système</li>
                </ul>
              </div>

              <div className="p-4 bg-white/5 rounded-lg">
                <h4 className="text-white font-medium mb-2">Utilisations</h4>
                <ul className="text-white/70 text-sm space-y-1">
                  <li>• Optimisation énergétique</li>
                  <li>• Gamification et récompenses</li>
                  <li>• Analytics et rapports</li>
                  <li>• Amélioration de l'expérience</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Advanced Settings */}
      {activeTab === 'advanced' && (
        <div className="space-y-6">
          {/* Data Management */}
          <div className="glass-card p-6">
            <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
              <Database className="w-5 h-5" />
              Gestion des Données
            </h3>
            
            <div className="space-y-4">
              <div className="flex flex-col sm:flex-row gap-4">
                <button
                  onClick={exportSettings}
                  className="flex-1 glass-button flex items-center justify-center gap-2"
                >
                  <Download className="w-4 h-4" />
                  Exporter les paramètres
                </button>
                
                <label className="flex-1 glass-button flex items-center justify-center gap-2 cursor-pointer">
                  <Upload className="w-4 h-4" />
                  Importer les paramètres
                  <input
                    type="file"
                    accept=".json"
                    onChange={importSettings}
                    className="hidden"
                  />
                </label>
              </div>

              <div className="border-t border-white/10 pt-4">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h4 className="text-white font-medium">Réinitialiser les paramètres</h4>
                    <p className="text-white/60 text-sm">Restaurer tous les paramètres par défaut</p>
                  </div>
                  
                  {!showResetConfirm ? (
                    <button
                      onClick={() => setShowResetConfirm(true)}
                      className="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors flex items-center gap-2"
                    >
                      <RefreshCw className="w-4 h-4" />
                      Réinitialiser
                    </button>
                  ) : (
                    <div className="flex gap-2">
                      <button
                        onClick={() => setShowResetConfirm(false)}
                        className="px-3 py-2 bg-white/10 text-white/70 rounded-lg hover:bg-white/20 transition-colors text-sm"
                      >
                        Annuler
                      </button>
                      <button
                        onClick={resetSettings}
                        className="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm flex items-center gap-1"
                      >
                        <Trash2 className="w-3 h-3" />
                        Confirmer
                      </button>
                    </div>
                  )}
                </div>

                {showResetConfirm && (
                  <div className="p-3 bg-red-500/10 border border-red-400/30 rounded-lg">
                    <div className="flex items-center gap-2 text-red-400 text-sm">
                      <AlertTriangle className="w-4 h-4" />
                      Cette action est irréversible. Tous vos paramètres personnalisés seront perdus.
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* System Information */}
          <div className="glass-card p-6">
            <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
              <Info className="w-5 h-5" />
              Informations Système
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-white/70">Version</span>
                  <span className="text-white">v1.0.0</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/70">Build</span>
                  <span className="text-white">2024.01.15</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/70">Service Worker</span>
                  <span className="text-green-400">✓ Actif</span>
                </div>
              </div>
              
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-white/70">Stockage utilisé</span>
                  <span className="text-white">2.3 MB</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/70">Cache</span>
                  <span className="text-white">12.7 MB</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/70">Connexion</span>
                  <span className="text-green-400 flex items-center gap-1">
                    <Wifi className="w-3 h-3" />
                    En ligne
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Save indicator */}
      {isDirty && (
        <div className="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50">
          <div className="glass-card px-6 py-3 flex items-center gap-3 shadow-2xl">
            <div className="w-2 h-2 bg-orange-400 rounded-full animate-pulse" />
            <span className="text-white font-medium">Modifications non sauvegardées</span>
            <button
              onClick={saveSettings}
              disabled={isSaving}
              className="glass-button-primary ml-2"
            >
              {isSaving ? (
                <RefreshCw className="w-4 h-4 animate-spin" />
              ) : (
                'Sauvegarder'
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default Settings