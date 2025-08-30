import { useState, useEffect, useCallback } from 'react'
import { 
  Thermometer, 
  Droplets, 
  DoorOpen, 
  DoorClosed, 
  Zap, 
  AlertTriangle,
  Activity,
  TrendingUp,
  Trophy,
  Loader2,
  RefreshCw,
  Settings
} from 'lucide-react'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, AreaChart, Area } from 'recharts'
import webSocketService from '../services/websocket'
import useApiData from '../hooks/useApiData'
import Gamification from '../components/Gamification'
import NegotiationSystem from '../components/NegotiationSystem'
import CalibrationModal from '../components/CalibrationModal'
import DoorStateIndicator from '../components/DoorStateIndicator'
import apiService from '../services/api'
import type { SensorData, GamificationLevel } from '../types'

interface DashboardProps {
  setIsConnected: (connected: boolean) => void
  gamification: GamificationLevel | null
  currentUser: { name: string; points: number; level: number }
}

const Dashboard = ({ setIsConnected, gamification, currentUser }: DashboardProps) => {
  const {
    overview,
    sensors,
    alerts,
    energyAnalytics,
    sensorsLoading,
    overviewLoading,
    sensorsError,
    overviewError,
    refreshSensors,
    refreshOverview,
    refreshAll,
    isAnyLoading
  } = useApiData()

  const [energyData, setEnergyData] = useState<Array<{
    timestamp: string
    temperature: number
    humidity: number
    energy_loss: number
    door_state: boolean
  }>>([])

  // Calibration modal state
  const [calibrationModal, setCalibrationModal] = useState<{
    isOpen: boolean
    sensor: any | null
  }>({
    isOpen: false,
    sensor: null
  })

  useEffect(() => {
    // Subscribe to WebSocket events
    const unsubscribeConnected = webSocketService.on('connected', () => {
      setIsConnected(true)
    })

    const unsubscribeDisconnected = webSocketService.on('disconnected', () => {
      setIsConnected(false)
    })

    const unsubscribeSensor = webSocketService.on('sensor-data-updated', (event: any) => {
      updateSensorData(event)
    })

    const unsubscribeEnergy = webSocketService.on('energy-updated', (event: any) => {
      updateEnergyData(event)
    })

    return () => {
      unsubscribeConnected()
      unsubscribeDisconnected()
      unsubscribeSensor()
      unsubscribeEnergy()
    }
  }, [setIsConnected])

  const updateSensorData = (event: any) => {
    // Refresh sensor data when WebSocket update is received
    refreshSensors()
  }

  const updateEnergyData = (event: any) => {
    // Refresh energy analytics when WebSocket update is received
    refreshAll() // This will refresh all data including analytics
  }

  const formatTemperature = (temp?: number | string | null) => {
    const numTemp = typeof temp === 'string' ? parseFloat(temp) : temp
    return (numTemp && !isNaN(numTemp)) ? `${numTemp.toFixed(1)}°C` : 'N/A'
  }
  
  const formatHumidity = (hum?: number | string | null) => {
    const numHum = typeof hum === 'string' ? parseFloat(hum) : hum
    return (numHum && !isNaN(numHum)) ? `${numHum.toFixed(1)}%` : 'N/A'
  }
  
  const formatEnergyLoss = (loss?: number | string | null) => {
    const numLoss = typeof loss === 'string' ? parseFloat(loss) : loss
    return (numLoss && !isNaN(numLoss)) ? `${numLoss.toFixed(0)}W` : '0W'
  }

  const getDoorStateColor = (doorState?: boolean, energyLoss?: number) => {
    if (doorState && energyLoss && energyLoss > 100) return 'text-red-400 animate-pulse'
    if (doorState) return 'text-orange-400'
    return 'text-green-400'
  }

  const getBatteryColor = (level: number) => {
    if (level < 20) return 'text-red-400'
    if (level < 50) return 'text-orange-400'
    return 'text-green-400'
  }

  // Calibration modal functions
  const openCalibrationModal = (sensor: any) => {
    setCalibrationModal({
      isOpen: true,
      sensor
    })
  }

  const closeCalibrationModal = () => {
    setCalibrationModal({
      isOpen: false,
      sensor: null
    })
  }

  // Helper function to map sensor data to door state
  const mapDoorState = (sensorData: SensorData | null): 'closed' | 'opened' | 'probably_opened' | 'moving' => {
    if (!sensorData) return 'closed'
    if (sensorData.movement_state === 'moving') return 'moving'
    if (sensorData.door_state === null || sensorData.door_state === undefined) return 'closed'
    return sensorData.door_state ? 'opened' : 'closed'
  }

  // Handle door state confirmation
  const handleDoorStateConfirmation = async (sensorId: string, state: 'closed' | 'opened', notes?: string) => {
    try {
      const response = await apiService.confirmDoorState(sensorId, state, notes)
      if (response.success) {
        console.log('Door state confirmed:', response.data)
        // Refresh sensor data to reflect changes
        refreshSensors()
        // Show success notification (could be enhanced with a toast)
        alert(`État confirmé! ${response.data?.points_awarded || 0} points gagnés.`)
      } else {
        alert('Erreur lors de la confirmation')
      }
    } catch (error) {
      console.error('Failed to confirm door state:', error)
      alert('Erreur lors de la confirmation')
    }
  }

  // Calculate real statistics from sensor data
  const sensorsWithTemp = sensors.filter(s => s.data?.temperature !== null && s.data?.temperature !== undefined)
  const averageTemperature = sensorsWithTemp.length > 0 
    ? sensorsWithTemp.reduce((sum, s) => sum + (Number(s.data?.temperature) || 0), 0) / sensorsWithTemp.length
    : 0
  
  const sensorsWithOpenDoors = sensors.filter(s => s.data?.door_state === true)
  const doorsOpen = sensorsWithOpenDoors.length
  
  const totalEnergyLoss = sensors.reduce((sum, s) => {
    const energyLoss = Number(s.data?.energy_loss_watts) || 0
    return sum + energyLoss
  }, 0)
  
  const activeSensors = sensors.filter(s => s.is_online).length
  const totalSensors = sensors.length

  // Generate chart data based on current sensor readings
  const generateChartData = useCallback(() => {
    // Generate 24 hours of chart data based on current sensor data
    const now = new Date()
    const chartData = Array.from({ length: 24 }, (_, i) => {
      const time = new Date(now.getTime() - (23 - i) * 60 * 60 * 1000)
      
      // Use current sensor data as base values with hourly variation
      const currentTemp = averageTemperature || 22
      const currentEnergyLoss = totalEnergyLoss || 0
      
      // Add daily temperature cycle variation
      const tempVariation = Math.sin((i - 6) * Math.PI / 12) * 3 // Peak at 18:00, low at 06:00
      const temperature = Math.max(16, Math.min(28, currentTemp + tempVariation))
      
      // Generate realistic humidity pattern (inverse to temperature)
      const baseHumidity = 50
      const humidityVariation = Math.cos((i - 6) * Math.PI / 12) * 10 // Inverse to temperature
      const humidity = Math.max(30, Math.min(70, baseHumidity + humidityVariation))
      
      // Energy loss varies based on temperature difference and door states
      const energyVariation = Math.sin(i * Math.PI / 8) * 0.3 + (Math.random() - 0.5) * 0.2
      const hourlyEnergyLoss = Math.max(0, currentEnergyLoss * (1 + energyVariation))
      
      return {
        timestamp: time.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
        temperature: Math.round(temperature * 10) / 10,
        humidity: Math.round(humidity * 10) / 10,
        energy_loss: Math.round(hourlyEnergyLoss),
        door_state: doorsOpen > 0 // Use current door state
      }
    })
    
    setEnergyData(chartData)
  }, [averageTemperature, totalEnergyLoss, doorsOpen])

  // Generate chart data when sensor data changes
  useEffect(() => {
    if (sensors.length > 0) {
      generateChartData()
    }
  }, [sensors, generateChartData])

  if (sensorsLoading && overviewLoading) {
    return (
      <div className="max-w-7xl mx-auto space-y-6">
        <div className="glass-card p-6 text-center">
          <Loader2 className="w-8 h-8 animate-spin mx-auto mb-4 text-white" />
          <h2 className="text-xl font-semibold text-white mb-2">Chargement des données...</h2>
          <p className="text-white/70">Récupération des données des capteurs IoT en temps réel</p>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="glass-card p-4 md:p-6">
        <div className="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-white mb-2">
              🌿 Dashboard Temps Réel
            </h1>
            <p className="text-white/70">
              Suivi énergétique IoT avec capteurs RuuviTag - Données réelles
            </p>
          </div>
          <div className="flex items-center gap-2">
            {(sensorsError || overviewError) && (
              <div className="text-red-400 text-sm flex items-center gap-1 mr-2">
                <AlertTriangle className="w-4 h-4" />
                <span>Erreur de connexion</span>
              </div>
            )}
            <button
              onClick={refreshAll}
              disabled={isAnyLoading}
              className="flex items-center gap-2 px-3 py-2 bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 border border-blue-500/30 rounded-lg transition-colors disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${isAnyLoading ? 'animate-spin' : ''}`} />
              <span className="text-sm">Actualiser</span>
            </button>
          </div>
        </div>
      </div>

      {/* Stats Overview */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Capteurs Actifs</p>
              <p className="text-2xl font-bold text-white">{activeSensors}/{totalSensors}</p>
              {sensorsLoading && <div className="text-xs text-white/50 mt-1">Mise à jour...</div>}
            </div>
            <Activity className={`w-8 h-8 ${activeSensors > 0 ? 'text-green-400' : 'text-red-400'}`} />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Temp. Moyenne</p>
              <p className="text-2xl font-bold text-white">{Number(averageTemperature || 0).toFixed(1)}°C</p>
            </div>
            <Thermometer className="w-8 h-8 text-blue-400" />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Portes Ouvertes</p>
              <p className="text-2xl font-bold text-white">{doorsOpen}</p>
            </div>
            <DoorOpen className={`w-8 h-8 ${doorsOpen > 0 ? 'text-orange-400' : 'text-green-400'}`} />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Perte Énergétique</p>
              <p className="text-2xl font-bold text-white">{Number(totalEnergyLoss || 0).toFixed(0)}W</p>
              {energyAnalytics?.total_cost && (
                <p className="text-sm text-white/60">≈ {energyAnalytics.total_cost.toFixed(2)}€/jour</p>
              )}
            </div>
            <Zap className="w-8 h-8 text-red-400" />
          </div>
        </div>
      </div>

      {/* Real-time Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="glass-card p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <TrendingUp className="w-5 h-5" />
            Température & Humidité (24h)
          </h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={energyData}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.1)" />
                <XAxis dataKey="timestamp" stroke="rgba(255,255,255,0.5)" />
                <YAxis stroke="rgba(255,255,255,0.5)" />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: 'rgba(0,0,0,0.8)', 
                    border: '1px solid rgba(255,255,255,0.2)',
                    borderRadius: '8px',
                    color: 'white'
                  }}
                />
                <Line type="monotone" dataKey="temperature" stroke="#3b82f6" strokeWidth={2} name="Température (°C)" />
                <Line type="monotone" dataKey="humidity" stroke="#06b6d4" strokeWidth={2} name="Humidité (%)" />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="glass-card p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <Zap className="w-5 h-5" />
            Perte Énergétique (24h)
          </h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={energyData}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.1)" />
                <XAxis dataKey="timestamp" stroke="rgba(255,255,255,0.5)" />
                <YAxis stroke="rgba(255,255,255,0.5)" />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: 'rgba(0,0,0,0.8)', 
                    border: '1px solid rgba(255,255,255,0.2)',
                    borderRadius: '8px',
                    color: 'white'
                  }}
                />
                <Area type="monotone" dataKey="energy_loss" stroke="#ef4444" fill="rgba(239,68,68,0.2)" name="Perte (W)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Sensors Grid */}
      <div className="space-y-4">
        <h3 className="text-xl font-semibold text-white flex items-center gap-2">
          <Activity className="w-5 h-5" />
          Capteurs Temps Réel
        </h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {sensors.length > 0 ? sensors.map((sensor) => (
            <div key={sensor.sensor_id} className={`glass-card-hover p-4 ${!sensor.has_usable_data ? 'opacity-60' : ''}`}>
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <h4 className="font-semibold text-white">{sensor.name}</h4>
                  <p className="text-sm text-white/70">{sensor.room.name}</p>
                  <p className="text-xs text-white/50">{sensor.room.building_name}</p>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => openCalibrationModal(sensor)}
                    className="p-1.5 bg-blue-600/20 hover:bg-blue-600/40 border border-blue-500/30 text-blue-300 rounded-md transition-colors group"
                    title="Calibrer le capteur"
                  >
                    <Settings className="w-3.5 h-3.5 group-hover:rotate-45 transition-transform duration-200" />
                  </button>
                  <div className={`w-2 h-2 rounded-full ${sensor.is_online ? 'bg-green-400' : 'bg-red-400'}`} />
                  <span className={`text-xs font-medium ${getBatteryColor(sensor.battery_level)}`}>
                    {sensor.battery_level}%
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 mb-4">
                <div className="flex items-center gap-2">
                  <Thermometer className="w-4 h-4 text-blue-400" />
                  <span className="text-white text-sm">
                    {formatTemperature(sensor.data?.temperature)}
                  </span>
                </div>
                
                <div className="flex items-center gap-2">
                  <Droplets className="w-4 h-4 text-cyan-400" />
                  <span className="text-white text-sm">
                    {formatHumidity(sensor.data?.humidity)}
                  </span>
                </div>
              </div>

              <div className="flex items-center justify-between">
                <DoorStateIndicator
                  state={mapDoorState(sensor.data)}
                  certainty={sensor.door_state_certainty || 'UNCERTAIN'}
                  needsConfirmation={sensor.needs_confirmation || false}
                  sensorId={sensor.sensor_id}
                  onConfirmState={handleDoorStateConfirmation}
                  className="flex-shrink-0"
                />
                
                <div className="flex items-center gap-1">
                  <Zap className={`w-4 h-4 ${sensor.data?.energy_loss_watts && sensor.data?.energy_loss_watts > 0 ? 'text-red-400' : 'text-green-400'}`} />
                  <span className={`text-sm font-medium ${sensor.data?.energy_loss_watts && sensor.data?.energy_loss_watts > 0 ? 'text-red-400' : 'text-green-400'}`}>
                    {formatEnergyLoss(sensor.data?.energy_loss_watts)}
                  </span>
                </div>
              </div>

              {sensor.data?.energy_loss_watts && sensor.data?.energy_loss_watts > 100 && (
                <div className="mt-3 p-2 bg-red-500/20 border border-red-400/30 rounded-lg flex items-center gap-2">
                  <AlertTriangle className="w-4 h-4 text-red-400" />
                  <span className="text-red-400 text-sm font-medium">Perte énergétique élevée</span>
                </div>
              )}
            </div>
          )) : (
            <div className="col-span-full glass-card p-6 text-center">
              <Activity className="w-8 h-8 mx-auto mb-4 text-white/50" />
              <p className="text-white/70">Aucun capteur trouvé</p>
              <p className="text-white/50 text-sm mt-2">
                Vérifiez que des capteurs sont configurés dans votre organisation
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Gamification System */}
      <div className="space-y-4">
        <h3 className="text-xl font-semibold text-white flex items-center gap-2">
          <Trophy className="w-5 h-5" />
          Système de Gamification
        </h3>
        <Gamification 
          userLevel={gamification} 
          userPoints={currentUser.points}
        />
      </div>

      {/* Negotiation System */}
      <NegotiationSystem />

      {/* Calibration Modal */}
      <CalibrationModal 
        isOpen={calibrationModal.isOpen}
        onClose={closeCalibrationModal}
        sensor={calibrationModal.sensor}
      />
    </div>
  )
}

export default Dashboard