import { useState, useEffect } from 'react'
import { 
  Calendar, 
  Clock, 
  Filter,
  Download,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Info,
  Zap,
  DoorOpen,
  Thermometer,
  Droplets,
  TrendingUp,
  BarChart3,
  LineChart as LineChartIcon,
  Search
} from 'lucide-react'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, AreaChart, Area } from 'recharts'
import type { Event } from '../types'

const History = () => {
  const [timeRange, setTimeRange] = useState<'24h' | '7d' | '30d' | '90d'>('7d')
  const [eventType, setEventType] = useState<'all' | 'door_open' | 'temperature_high' | 'energy_loss' | 'battery_low'>('all')
  const [severity, setSeverity] = useState<'all' | 'info' | 'warning' | 'critical'>('all')
  const [searchQuery, setSearchQuery] = useState('')
  const [chartView, setChartView] = useState<'line' | 'bar' | 'area'>('line')
  const [events, setEvents] = useState<Event[]>([])
  const [historicalData, setHistoricalData] = useState<Array<{
    timestamp: string
    date: string
    temperature: number
    humidity: number
    energy_loss: number
    events_count: number
    doors_opened: number
    cost_impact: number
  }>>([])

  useEffect(() => {
    generateMockHistoricalData()
  }, [timeRange])

  const generateMockHistoricalData = () => {
    const days = timeRange === '24h' ? 1 : timeRange === '7d' ? 7 : timeRange === '30d' ? 30 : 90
    const intervals = timeRange === '24h' ? 24 : days
    
    const historicalEvents: Event[] = []
    const chartData: typeof historicalData = []
    
    for (let i = intervals - 1; i >= 0; i--) {
      const date = new Date()
      if (timeRange === '24h') {
        date.setHours(date.getHours() - i)
      } else {
        date.setDate(date.getDate() - i)
      }
      
      const eventsForPeriod = Math.floor(Math.random() * 5)
      const temperature = 20 + Math.random() * 8
      const humidity = 35 + Math.random() * 30
      const energyLoss = Math.random() * 300
      const doorsOpened = Math.floor(Math.random() * 3)
      
      // Generate events for this period
      for (let j = 0; j < eventsForPeriod; j++) {
        const eventId = `event-${i}-${j}`
        const eventTypes = ['door_open', 'temperature_high', 'energy_loss', 'battery_low'] as const
        const severities = ['info', 'warning', 'critical'] as const
        const rooms = ['Bureau Principal', 'Salle de Réunion A', 'Couloir Est', 'Espace Détente']
        
        const type = eventTypes[Math.floor(Math.random() * eventTypes.length)]
        const sev = severities[Math.floor(Math.random() * severities.length)]
        const room = rooms[Math.floor(Math.random() * rooms.length)]
        
        const eventDate = new Date(date.getTime() + Math.random() * (timeRange === '24h' ? 3600000 : 86400000))
        
        historicalEvents.push({
          id: eventId,
          type,
          severity: sev,
          message: generateEventMessage(type, room),
          cost_impact: Math.random() * 50,
          acknowledged: Math.random() > 0.3,
          acknowledged_at: Math.random() > 0.5 ? eventDate.toISOString() : undefined,
          data: { room_name: room, temperature, humidity },
          sensor: {
            id: `sensor-${Math.floor(Math.random() * 3) + 1}`,
            name: `RuuviTag-${112 + Math.floor(Math.random() * 3) * 2}`,
            mac_address: 'AA:BB:CC:DD:EE:FF',
            position: 'door',
            battery_level: 50 + Math.random() * 50,
            is_active: true,
            is_online: true,
            room: {
              id: 'room-1',
              name: room,
              type: 'office',
              floor: 1,
              surface_m2: 25,
              target_temperature: 23,
              target_humidity: 50,
              building: {
                id: 'building-1',
                name: 'Bâtiment Principal',
                address: '123 Rue de l\'Énergie',
                floors_count: 3
              }
            }
          },
          room: {
            id: 'room-1',
            name: room,
            type: 'office',
            floor: 1,
            surface_m2: 25,
            target_temperature: 23,
            target_humidity: 50,
            building: {
              id: 'building-1',
              name: 'Bâtiment Principal',
              address: '123 Rue de l\'Énergie',
              floors_count: 3
            }
          },
          created_at: eventDate.toISOString()
        })
      }
      
      chartData.push({
        timestamp: timeRange === '24h' 
          ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
          : date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' }),
        date: date.toISOString(),
        temperature,
        humidity,
        energy_loss: energyLoss,
        events_count: eventsForPeriod,
        doors_opened: doorsOpened,
        cost_impact: eventsForPeriod * 12.5 + energyLoss * 0.15
      })
    }
    
    setEvents(historicalEvents.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()))
    setHistoricalData(chartData)
  }

  const generateEventMessage = (type: string, room: string): string => {
    const messages = {
      door_open: `Porte ouverte détectée dans ${room}`,
      temperature_high: `Température élevée dans ${room}`,
      energy_loss: `Perte énergétique détectée dans ${room}`,
      battery_low: `Niveau de batterie faible - capteur ${room}`
    }
    return messages[type as keyof typeof messages] || `Événement dans ${room}`
  }

  const getEventIcon = (type: string) => {
    switch (type) {
      case 'door_open': return <DoorOpen className="w-4 h-4" />
      case 'temperature_high': return <Thermometer className="w-4 h-4" />
      case 'energy_loss': return <Zap className="w-4 h-4" />
      case 'battery_low': return <AlertTriangle className="w-4 h-4" />
      default: return <Info className="w-4 h-4" />
    }
  }

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical': return 'text-red-400'
      case 'warning': return 'text-orange-400'
      case 'info': return 'text-blue-400'
      default: return 'text-white/60'
    }
  }

  const getSeverityBg = (severity: string) => {
    switch (severity) {
      case 'critical': return 'bg-red-500/20 border-red-400/30'
      case 'warning': return 'bg-orange-500/20 border-orange-400/30'
      case 'info': return 'bg-blue-500/20 border-blue-400/30'
      default: return 'bg-white/5 border-white/10'
    }
  }

  const filteredEvents = events.filter(event => {
    const matchesType = eventType === 'all' || event.type === eventType
    const matchesSeverity = severity === 'all' || event.severity === severity
    const matchesSearch = searchQuery === '' || 
      event.message.toLowerCase().includes(searchQuery.toLowerCase()) ||
      event.room.name.toLowerCase().includes(searchQuery.toLowerCase())
    
    return matchesType && matchesSeverity && matchesSearch
  })

  const totalEvents = filteredEvents.length
  const acknowledgedEvents = filteredEvents.filter(e => e.acknowledged).length
  const totalCostImpact = filteredEvents.reduce((sum, e) => sum + (e.cost_impact || 0), 0)
  const avgEnergyLoss = historicalData.reduce((sum, d) => sum + d.energy_loss, 0) / historicalData.length

  const exportData = () => {
    const csvContent = [
      ['Date', 'Type', 'Gravité', 'Message', 'Salle', 'Coût Impact', 'Accusé Réception'].join(','),
      ...filteredEvents.map(event => [
        new Date(event.created_at).toLocaleString('fr-FR'),
        event.type,
        event.severity,
        `"${event.message}"`,
        event.room.name,
        event.cost_impact?.toFixed(2) || '0',
        event.acknowledged ? 'Oui' : 'Non'
      ].join(','))
    ].join('\n')

    const blob = new Blob([csvContent], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `ecocomfort-historique-${timeRange}-${new Date().toISOString().split('T')[0]}.csv`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
  }

  const renderChart = () => {
    const ChartComponent = chartView === 'line' ? LineChart : chartView === 'bar' ? BarChart : AreaChart
    
    return (
      <ResponsiveContainer width="100%" height={300}>
        <ChartComponent data={historicalData}>
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
          {chartView === 'line' && (
            <>
              <Line type="monotone" dataKey="temperature" stroke="#3b82f6" strokeWidth={2} name="Température (°C)" />
              <Line type="monotone" dataKey="humidity" stroke="#06b6d4" strokeWidth={2} name="Humidité (%)" />
              <Line type="monotone" dataKey="energy_loss" stroke="#ef4444" strokeWidth={2} name="Perte Énergétique (W)" />
            </>
          )}
          {chartView === 'bar' && (
            <>
              <Bar dataKey="events_count" fill="#8b5cf6" name="Nombre d'événements" />
              <Bar dataKey="doors_opened" fill="#f59e0b" name="Portes ouvertes" />
            </>
          )}
          {chartView === 'area' && (
            <>
              <Area type="monotone" dataKey="cost_impact" stroke="#ef4444" fill="rgba(239,68,68,0.2)" name="Impact Coût (€)" />
              <Area type="monotone" dataKey="energy_loss" stroke="#f59e0b" fill="rgba(245,158,11,0.2)" name="Perte Énergétique (W)" />
            </>
          )}
        </ChartComponent>
      </ResponsiveContainer>
    )
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="glass-card p-4 md:p-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-white mb-2 flex items-center gap-3">
              <Calendar className="w-8 h-8 text-blue-400" />
              Historique & Analytics
            </h1>
            <p className="text-white/70">
              Analyse complète des données énergétiques et événements
            </p>
          </div>
          
          <button
            onClick={exportData}
            className="glass-button-primary flex items-center gap-2"
          >
            <Download className="w-4 h-4" />
            Exporter CSV
          </button>
        </div>
      </div>

      {/* Stats Overview */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Total Événements</p>
              <p className="text-2xl font-bold text-white">{totalEvents}</p>
            </div>
            <AlertTriangle className="w-8 h-8 text-orange-400" />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Traités</p>
              <p className="text-2xl font-bold text-white">{acknowledgedEvents}</p>
            </div>
            <CheckCircle className="w-8 h-8 text-green-400" />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Impact Coût</p>
              <p className="text-2xl font-bold text-white">{totalCostImpact.toFixed(0)}€</p>
            </div>
            <TrendingUp className="w-8 h-8 text-red-400" />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Perte Moy.</p>
              <p className="text-2xl font-bold text-white">{avgEnergyLoss.toFixed(0)}W</p>
            </div>
            <Zap className="w-8 h-8 text-yellow-400" />
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="glass-card p-4">
        <div className="flex flex-wrap gap-4">
          {/* Time Range */}
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-white/60" />
            <select 
              value={timeRange} 
              onChange={(e) => setTimeRange(e.target.value as any)}
              className="bg-white/10 border border-white/20 rounded px-3 py-1 text-white text-sm"
            >
              <option value="24h">Dernières 24h</option>
              <option value="7d">7 derniers jours</option>
              <option value="30d">30 derniers jours</option>
              <option value="90d">90 derniers jours</option>
            </select>
          </div>

          {/* Event Type */}
          <div className="flex items-center gap-2">
            <Filter className="w-4 h-4 text-white/60" />
            <select 
              value={eventType} 
              onChange={(e) => setEventType(e.target.value as any)}
              className="bg-white/10 border border-white/20 rounded px-3 py-1 text-white text-sm"
            >
              <option value="all">Tous les types</option>
              <option value="door_open">Portes ouvertes</option>
              <option value="temperature_high">Température élevée</option>
              <option value="energy_loss">Perte énergétique</option>
              <option value="battery_low">Batterie faible</option>
            </select>
          </div>

          {/* Severity */}
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-white/60" />
            <select 
              value={severity} 
              onChange={(e) => setSeverity(e.target.value as any)}
              className="bg-white/10 border border-white/20 rounded px-3 py-1 text-white text-sm"
            >
              <option value="all">Toutes gravités</option>
              <option value="info">Information</option>
              <option value="warning">Avertissement</option>
              <option value="critical">Critique</option>
            </select>
          </div>

          {/* Search */}
          <div className="flex items-center gap-2 flex-1 min-w-[200px]">
            <Search className="w-4 h-4 text-white/60" />
            <input
              type="text"
              placeholder="Rechercher..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="flex-1 bg-white/10 border border-white/20 rounded px-3 py-1 text-white text-sm placeholder:text-white/50"
            />
          </div>
        </div>
      </div>

      {/* Historical Charts */}
      <div className="glass-card p-6">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-xl font-semibold text-white">Tendances Historiques</h3>
          <div className="flex gap-2">
            <button
              onClick={() => setChartView('line')}
              className={`p-2 rounded-lg transition-colors ${
                chartView === 'line' ? 'bg-primary-500/20 text-primary-300' : 'text-white/60 hover:text-white hover:bg-white/5'
              }`}
            >
              <LineChartIcon className="w-4 h-4" />
            </button>
            <button
              onClick={() => setChartView('bar')}
              className={`p-2 rounded-lg transition-colors ${
                chartView === 'bar' ? 'bg-primary-500/20 text-primary-300' : 'text-white/60 hover:text-white hover:bg-white/5'
              }`}
            >
              <BarChart3 className="w-4 h-4" />
            </button>
            <button
              onClick={() => setChartView('area')}
              className={`p-2 rounded-lg transition-colors ${
                chartView === 'area' ? 'bg-primary-500/20 text-primary-300' : 'text-white/60 hover:text-white hover:bg-white/5'
              }`}
            >
              <TrendingUp className="w-4 h-4" />
            </button>
          </div>
        </div>
        
        <div className="h-80">
          {renderChart()}
        </div>
      </div>

      {/* Events List */}
      <div className="glass-card p-6">
        <h3 className="text-xl font-semibold text-white mb-6">Événements Détaillés</h3>
        
        {filteredEvents.length === 0 ? (
          <div className="text-center py-8 text-white/60">
            <AlertTriangle className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>Aucun événement trouvé pour les filtres sélectionnés</p>
          </div>
        ) : (
          <div className="space-y-3">
            {filteredEvents.slice(0, 50).map((event) => (
              <div 
                key={event.id} 
                className={`p-4 rounded-lg border ${getSeverityBg(event.severity)}`}
              >
                <div className="flex items-start gap-3">
                  <div className={`p-2 rounded-lg ${getSeverityColor(event.severity)} bg-current/20`}>
                    {getEventIcon(event.type)}
                  </div>
                  
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-1">
                      <h4 className="text-white font-medium">{event.message}</h4>
                      <div className="flex items-center gap-2">
                        {event.acknowledged ? (
                          <CheckCircle className="w-4 h-4 text-green-400" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-400" />
                        )}
                        <span className="text-white/60 text-sm">
                          {new Date(event.created_at).toLocaleString('fr-FR')}
                        </span>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-4 text-sm text-white/70">
                      <span>📍 {event.room.name}</span>
                      <span>🏷️ {event.type.replace('_', ' ')}</span>
                      <span className={`px-2 py-1 rounded ${getSeverityBg(event.severity)} ${getSeverityColor(event.severity)}`}>
                        {event.severity}
                      </span>
                      {event.cost_impact && (
                        <span>💰 {event.cost_impact.toFixed(2)}€</span>
                      )}
                    </div>
                    
                    {event.acknowledged_at && (
                      <div className="mt-2 text-xs text-green-400">
                        ✅ Traité le {new Date(event.acknowledged_at).toLocaleString('fr-FR')}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
            
            {filteredEvents.length > 50 && (
              <div className="text-center p-4 text-white/60">
                Affichage des 50 premiers événements sur {filteredEvents.length} total
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

export default History