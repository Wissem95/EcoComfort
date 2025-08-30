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
  Search,
  Loader2
} from 'lucide-react'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, AreaChart, Area } from 'recharts'
import apiService, { type EventData, type EventsResponse } from '../services/api'

const History = () => {
  const [timeRange, setTimeRange] = useState<'24h' | '7d' | '30d' | '90d'>('7d')
  const [eventType, setEventType] = useState<'all' | 'door_open' | 'temperature_high' | 'energy_loss' | 'battery_low'>('all')
  const [severity, setSeverity] = useState<'all' | 'info' | 'warning' | 'critical'>('all')
  const [searchQuery, setSearchQuery] = useState('')
  const [chartView, setChartView] = useState<'line' | 'bar' | 'area'>('line')
  const [events, setEvents] = useState<EventData[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [statistics, setStatistics] = useState<{
    total_events: number
    unacknowledged_events: number
    critical_events: number
    total_cost_impact: number
  } | null>(null)

  useEffect(() => {
    fetchEvents()
  }, [timeRange, eventType, severity])

  const fetchEvents = async () => {
    try {
      setLoading(true)
      setError(null)
      
      const endDate = new Date()
      const startDate = new Date()
      
      // Calculate date range based on timeRange
      switch (timeRange) {
        case '24h':
          startDate.setHours(startDate.getHours() - 24)
          break
        case '7d':
          startDate.setDate(startDate.getDate() - 7)
          break
        case '30d':
          startDate.setDate(startDate.getDate() - 30)
          break
        case '90d':
          startDate.setDate(startDate.getDate() - 90)
          break
      }
      
      const params: {
        start_date: string;
        end_date: string;
        limit: number;
        type?: string;
        severity?: 'info' | 'warning' | 'critical';
      } = {
        start_date: startDate.toISOString(),
        end_date: endDate.toISOString(),
        limit: 100
      }
      
      if (eventType !== 'all') {
        params.type = eventType
      }
      
      if (severity !== 'all') {
        params.severity = severity
      }
      
      const response: EventsResponse = await apiService.getEvents(params)
      setEvents(response.events)
      setStatistics(response.statistics)
      
    } catch (err) {
      console.error('Failed to fetch events:', err)
      setError('Failed to load events')
    } finally {
      setLoading(false)
    }
  }

  // Generate chart data from events for visualization
  const generateChartDataFromEvents = () => {
    const chartData: Array<{
      timestamp: string
      date: string
      cost_impact: number
      events_count: number
    }> = []
    
    const days = timeRange === '24h' ? 1 : timeRange === '7d' ? 7 : timeRange === '30d' ? 30 : 90
    const intervals = timeRange === '24h' ? 24 : days
    
    for (let i = intervals - 1; i >= 0; i--) {
      const date = new Date()
      if (timeRange === '24h') {
        date.setHours(date.getHours() - i)
        date.setMinutes(0, 0, 0)
      } else {
        date.setDate(date.getDate() - i)
        date.setHours(0, 0, 0, 0)
      }
      
      const endDate = new Date(date)
      if (timeRange === '24h') {
        endDate.setHours(endDate.getHours() + 1)
      } else {
        endDate.setDate(endDate.getDate() + 1)
      }
      
      // Filter events for this time period
      const eventsInPeriod = events.filter(event => {
        const eventDate = new Date(event.created_at)
        return eventDate >= date && eventDate < endDate
      })
      
      const totalCostImpact = eventsInPeriod.reduce((sum, event) => sum + (event.cost_impact || 0), 0)
      
      chartData.push({
        timestamp: timeRange === '24h' 
          ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
          : date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' }),
        date: date.toISOString(),
        cost_impact: totalCostImpact,
        events_count: eventsInPeriod.length
      })
    }
    
    return chartData
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

  const totalEvents = statistics?.total_events || filteredEvents.length
  const acknowledgedEvents = filteredEvents.filter(e => e.acknowledged).length
  const totalCostImpact = Number(statistics?.total_cost_impact ?? filteredEvents.reduce((sum, e) => sum + Number(e.cost_impact || 0), 0) ?? 0)
  
  // Generate chart data from real events
  const chartData = generateChartDataFromEvents()

  const exportData = () => {
    const csvContent = [
      ['Date', 'Type', 'Gravité', 'Message', 'Salle', 'Coût Impact', 'Accusé Réception'].join(','),
      ...filteredEvents.map(event => [
        new Date(event.created_at).toLocaleString('fr-FR'),
        event.type,
        event.severity,
        `"${event.message}"`,
        event.room.name,
        Number(event.cost_impact || 0).toFixed(2),
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
        <ChartComponent data={chartData}>
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
          {chartView === 'area' && (
            <>
              <Area type="monotone" dataKey="cost_impact" stroke="#ef4444" fill="rgba(239,68,68,0.2)" name="Impact Coût (€)" />
            </>
          )}
          {chartView === 'bar' && (
            <>
              <Bar dataKey="events_count" fill="#8b5cf6" name="Nombre d'événements" />
              <Bar dataKey="cost_impact" fill="#ef4444" name="Impact Coût (€)" />
            </>
          )}
          {chartView === 'line' && (
            <>
              <Line type="monotone" dataKey="cost_impact" stroke="#ef4444" strokeWidth={2} name="Impact Coût (€)" />
              <Line type="monotone" dataKey="events_count" stroke="#8b5cf6" strokeWidth={2} name="Nombre d'événements" />
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
              <p className="text-2xl font-bold text-white">{Number(totalCostImpact || 0).toFixed(0)}€</p>
            </div>
            <TrendingUp className="w-8 h-8 text-red-400" />
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-white/70 text-sm">Événements Critiques</p>
              <p className="text-2xl font-bold text-white">{statistics?.critical_events || filteredEvents.filter(e => e.severity === 'critical').length}</p>
            </div>
            <Zap className="w-8 h-8 text-red-400" />
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
        
        {loading ? (
          <div className="text-center py-8 text-white/60">
            <Loader2 className="w-8 h-8 mx-auto mb-4 animate-spin" />
            <p>Chargement des événements...</p>
          </div>
        ) : error ? (
          <div className="text-center py-8 text-red-400">
            <AlertTriangle className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>{error}</p>
            <button 
              onClick={fetchEvents}
              className="mt-4 glass-button-primary"
            >
              Réessayer
            </button>
          </div>
        ) : filteredEvents.length === 0 ? (
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
                        <span>💰 {Number(event.cost_impact).toFixed(2)}€</span>
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