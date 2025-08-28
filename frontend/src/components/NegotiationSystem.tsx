import { useState, useEffect } from 'react'
import { 
  MessageCircle, 
  Clock, 
  CheckCircle, 
  XCircle, 
  Coins, 
  Zap, 
  DoorOpen, 
  Thermometer,
  Target,
  TrendingUp,
  Handshake,
  AlertTriangle,
  Gift,
  Timer,
  ArrowRight
} from 'lucide-react'
import type { Negotiation } from '../types'

interface NegotiationSystemProps {
  className?: string
}

interface NegotiationProposal {
  id: string
  type: 'energy_saving' | 'temperature_control' | 'door_management' | 'efficiency_boost'
  title: string
  description: string
  action: string
  detection_reason: string
  original_reward: number
  negotiated_reward?: number
  duration_minutes: number
  expires_at: string
  status: 'pending' | 'negotiating' | 'accepted' | 'rejected' | 'completed' | 'expired'
  counter_offers: number
  max_counter_offers: number
  difficulty: 'easy' | 'medium' | 'hard'
  impact_level: 'low' | 'medium' | 'high'
  created_at: string
  user_response?: 'accept' | 'counter' | 'reject'
}

const NegotiationSystem = ({ className = '' }: NegotiationSystemProps) => {
  const [negotiations, setNegotiations] = useState<NegotiationProposal[]>([])
  const [isExpanded, setIsExpanded] = useState(false)
  const [activeNegotiation, setActiveNegotiation] = useState<string | null>(null)
  const [counterOfferPoints, setCounterOfferPoints] = useState<number>(0)
  const [showSuccess, setShowSuccess] = useState<string | null>(null)

  useEffect(() => {
    initializeMockNegotiations()
    
    // Simulate new negotiations appearing
    const interval = setInterval(() => {
      if (Math.random() > 0.7) { // 30% chance every 30 seconds
        generateRandomNegotiation()
      }
    }, 30000)

    return () => clearInterval(interval)
  }, [])

  const initializeMockNegotiations = () => {
    const mockNegotiations: NegotiationProposal[] = [
      {
        id: 'nego-1',
        type: 'door_management',
        title: '🚪 Mission Porte Ouverte',
        description: 'Une porte de la Salle de Réunion A est ouverte depuis 8 minutes.',
        action: 'Fermer la porte et maintenir fermée pendant 30 minutes',
        detection_reason: 'Perte énergétique de 156W détectée',
        original_reward: 75,
        duration_minutes: 30,
        expires_at: new Date(Date.now() + 600000).toISOString(), // 10 minutes
        status: 'pending',
        counter_offers: 0,
        max_counter_offers: 2,
        difficulty: 'easy',
        impact_level: 'medium',
        created_at: new Date().toISOString()
      },
      {
        id: 'nego-2',
        type: 'temperature_control',
        title: '🌡️ Défi Température Optimale',
        description: 'La température du Bureau Principal est de 26.2°C.',
        action: 'Ajuster et maintenir entre 22-24°C pendant 2 heures',
        detection_reason: 'Surconsommation de climatisation détectée',
        original_reward: 120,
        negotiated_reward: 140,
        duration_minutes: 120,
        expires_at: new Date(Date.now() + 900000).toISOString(), // 15 minutes
        status: 'negotiating',
        counter_offers: 1,
        max_counter_offers: 2,
        difficulty: 'medium',
        impact_level: 'high',
        created_at: new Date(Date.now() - 300000).toISOString()
      },
      {
        id: 'nego-3',
        type: 'efficiency_boost',
        title: '⚡ Mission Efficacité Énergétique',
        description: 'Opportunité d\'optimisation dans le Couloir Est.',
        action: 'Optimiser l\'utilisation pendant la période de pic (14h-16h)',
        detection_reason: 'Pattern de consommation inefficace identifié',
        original_reward: 200,
        duration_minutes: 120,
        expires_at: new Date(Date.now() + 1800000).toISOString(), // 30 minutes
        status: 'pending',
        counter_offers: 0,
        max_counter_offers: 3,
        difficulty: 'hard',
        impact_level: 'high',
        created_at: new Date(Date.now() - 600000).toISOString()
      }
    ]
    
    setNegotiations(mockNegotiations)
  }

  const generateRandomNegotiation = () => {
    const types = ['energy_saving', 'temperature_control', 'door_management', 'efficiency_boost'] as const
    const rooms = ['Bureau Principal', 'Salle de Réunion A', 'Couloir Est', 'Espace Détente']
    const type = types[Math.floor(Math.random() * types.length)]
    const room = rooms[Math.floor(Math.random() * rooms.length)]
    
    const templates = {
      energy_saving: {
        title: '💡 Économie d\'Énergie Détectée',
        description: `Consommation élevée détectée dans ${room}`,
        action: 'Réduire la consommation de 20% pendant 1 heure',
        reason: 'Pic de consommation anormal'
      },
      temperature_control: {
        title: '🌡️ Optimisation Thermique',
        description: `Température non-optimale dans ${room}`,
        action: 'Maintenir la température idéale pendant 90 minutes',
        reason: 'Écart de température détecté'
      },
      door_management: {
        title: '🚪 Gestion des Ouvertures',
        description: `Porte ouverte détectée dans ${room}`,
        action: 'Fermer et surveiller pendant 45 minutes',
        reason: 'Perte thermique en cours'
      },
      efficiency_boost: {
        title: '⚡ Boost d\'Efficacité',
        description: `Opportunité d\'optimisation dans ${room}`,
        action: 'Appliquer les recommandations d\'efficacité',
        reason: 'Pattern d\'utilisation optimisable'
      }
    }

    const template = templates[type]
    const difficulty = ['easy', 'medium', 'hard'][Math.floor(Math.random() * 3)] as 'easy' | 'medium' | 'hard'
    const baseReward = difficulty === 'easy' ? 50 : difficulty === 'medium' ? 100 : 150

    const newNegotiation: NegotiationProposal = {
      id: `nego-${Date.now()}`,
      type,
      title: template.title,
      description: template.description,
      action: template.action,
      detection_reason: template.reason,
      original_reward: baseReward + Math.floor(Math.random() * 50),
      duration_minutes: [30, 45, 60, 90, 120][Math.floor(Math.random() * 5)],
      expires_at: new Date(Date.now() + 600000 + Math.random() * 900000).toISOString(),
      status: 'pending',
      counter_offers: 0,
      max_counter_offers: Math.floor(Math.random() * 3) + 1,
      difficulty,
      impact_level: ['low', 'medium', 'high'][Math.floor(Math.random() * 3)] as 'low' | 'medium' | 'high',
      created_at: new Date().toISOString()
    }

    setNegotiations(prev => [newNegotiation, ...prev.slice(0, 9)]) // Keep max 10 negotiations
  }

  const handleAccept = (negotiationId: string) => {
    setNegotiations(prev => prev.map(nego => 
      nego.id === negotiationId 
        ? { ...nego, status: 'accepted' as const, user_response: 'accept' as const }
        : nego
    ))
    
    setShowSuccess(negotiationId)
    setTimeout(() => setShowSuccess(null), 3000)
    
    // Simulate completion after some time
    setTimeout(() => {
      setNegotiations(prev => prev.map(nego => 
        nego.id === negotiationId 
          ? { ...nego, status: 'completed' as const }
          : nego
      ))
    }, 5000)
  }

  const handleReject = (negotiationId: string) => {
    setNegotiations(prev => prev.map(nego => 
      nego.id === negotiationId 
        ? { ...nego, status: 'rejected' as const, user_response: 'reject' as const }
        : nego
    ))
  }

  const handleCounterOffer = (negotiationId: string) => {
    const negotiation = negotiations.find(n => n.id === negotiationId)
    if (!negotiation) return

    if (negotiation.counter_offers >= negotiation.max_counter_offers) {
      return // Max counter offers reached
    }

    const proposedPoints = counterOfferPoints || negotiation.original_reward + 25
    const maxIncrease = negotiation.original_reward * 0.5 // Max 50% increase
    const finalPoints = Math.min(proposedPoints, negotiation.original_reward + maxIncrease)

    setNegotiations(prev => prev.map(nego => 
      nego.id === negotiationId 
        ? { 
            ...nego, 
            status: 'negotiating' as const,
            negotiated_reward: finalPoints,
            counter_offers: nego.counter_offers + 1,
            user_response: 'counter' as const
          }
        : nego
    ))

    setActiveNegotiation(null)
    setCounterOfferPoints(0)

    // Simulate system response to counter-offer
    setTimeout(() => {
      const acceptChance = 0.7 - (negotiation.counter_offers * 0.2) // Lower chance with more counters
      if (Math.random() < acceptChance) {
        setNegotiations(prev => prev.map(nego => 
          nego.id === negotiationId 
            ? { ...nego, status: 'pending' as const }
            : nego
        ))
      } else {
        handleReject(negotiationId)
      }
    }, 2000)
  }

  const getRemainingTime = (expiresAt: string): string => {
    const now = new Date().getTime()
    const expires = new Date(expiresAt).getTime()
    const diff = expires - now

    if (diff <= 0) return 'Expiré'

    const minutes = Math.floor(diff / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)

    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  }

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'door_management': return <DoorOpen className="w-5 h-5" />
      case 'temperature_control': return <Thermometer className="w-5 h-5" />
      case 'energy_saving': return <Zap className="w-5 h-5" />
      case 'efficiency_boost': return <TrendingUp className="w-5 h-5" />
      default: return <Target className="w-5 h-5" />
    }
  }

  const getDifficultyColor = (difficulty: string) => {
    switch (difficulty) {
      case 'easy': return 'text-green-400 bg-green-500/20'
      case 'medium': return 'text-orange-400 bg-orange-500/20'
      case 'hard': return 'text-red-400 bg-red-500/20'
      default: return 'text-white/60 bg-white/10'
    }
  }

  const getImpactColor = (impact: string) => {
    switch (impact) {
      case 'low': return 'text-blue-400'
      case 'medium': return 'text-orange-400'
      case 'high': return 'text-red-400'
      default: return 'text-white/60'
    }
  }

  const activeNegotiations = negotiations.filter(n => 
    ['pending', 'negotiating', 'accepted'].includes(n.status)
  )

  if (!isExpanded && activeNegotiations.length === 0) {
    return null // Don't show if no active negotiations and not expanded
  }

  if (!isExpanded) {
    return (
      <button
        onClick={() => setIsExpanded(true)}
        className={`glass-card-hover p-4 flex items-center gap-3 ${className}`}
      >
        <div className="p-2 bg-purple-500/20 rounded-lg text-purple-300">
          <Handshake className="w-5 h-5" />
        </div>
        <div className="flex-1 text-left">
          <div className="text-white font-semibold">Négociations Actives</div>
          <div className="text-white/70 text-sm">
            {activeNegotiations.length} proposition{activeNegotiations.length > 1 ? 's' : ''} disponible{activeNegotiations.length > 1 ? 's' : ''}
          </div>
        </div>
        <div className="bg-purple-500 text-white text-xs px-2 py-1 rounded-full">
          {activeNegotiations.length}
        </div>
      </button>
    )
  }

  return (
    <div className={`glass-card p-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold text-white flex items-center gap-2">
          <Handshake className="w-6 h-6 text-purple-500" />
          Négociations Énergétiques
        </h2>
        <button
          onClick={() => setIsExpanded(false)}
          className="text-white/70 hover:text-white p-1"
        >
          ×
        </button>
      </div>

      {/* Active Negotiations */}
      <div className="space-y-4">
        {activeNegotiations.length === 0 ? (
          <div className="text-center py-8 text-white/60">
            <MessageCircle className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>Aucune négociation active</p>
            <p className="text-sm">De nouvelles opportunités apparaîtront automatiquement</p>
          </div>
        ) : (
          activeNegotiations.map((negotiation) => (
            <div 
              key={negotiation.id} 
              className={`glass-card p-4 border-l-4 transition-all ${
                negotiation.status === 'accepted' ? 'border-l-green-400 bg-green-500/10' :
                negotiation.status === 'negotiating' ? 'border-l-orange-400 bg-orange-500/10' :
                'border-l-purple-400 bg-purple-500/10'
              }`}
            >
              {showSuccess === negotiation.id && (
                <div className="mb-4 p-3 bg-green-500/20 border border-green-400/30 rounded-lg flex items-center gap-2 text-green-400">
                  <CheckCircle className="w-5 h-5" />
                  <span>Négociation acceptée! +{negotiation.negotiated_reward || negotiation.original_reward} points en cours...</span>
                </div>
              )}
              
              <div className="flex items-start gap-4">
                <div className="p-3 bg-purple-500/20 rounded-lg text-purple-300">
                  {getTypeIcon(negotiation.type)}
                </div>
                
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-white">{negotiation.title}</h3>
                    <div className="flex items-center gap-2">
                      <span className={`text-xs px-2 py-1 rounded ${getDifficultyColor(negotiation.difficulty)}`}>
                        {negotiation.difficulty}
                      </span>
                      <div className="flex items-center gap-1 text-white/60 text-sm">
                        <Timer className="w-4 h-4" />
                        {getRemainingTime(negotiation.expires_at)}
                      </div>
                    </div>
                  </div>
                  
                  <p className="text-white/80 text-sm mb-2">{negotiation.description}</p>
                  <p className="text-white/60 text-xs mb-3">
                    <AlertTriangle className="w-3 h-3 inline mr-1" />
                    {negotiation.detection_reason}
                  </p>
                  
                  <div className="bg-white/5 p-3 rounded-lg mb-4">
                    <div className="flex items-center gap-2 mb-2">
                      <Target className="w-4 h-4 text-purple-400" />
                      <span className="text-white font-medium text-sm">Action demandée:</span>
                    </div>
                    <p className="text-white/80 text-sm ml-6">{negotiation.action}</p>
                    <div className="flex items-center justify-between mt-2 ml-6">
                      <span className="text-white/60 text-xs">
                        Durée: {negotiation.duration_minutes} minutes
                      </span>
                      <span className={`text-xs ${getImpactColor(negotiation.impact_level)}`}>
                        Impact: {negotiation.impact_level}
                      </span>
                    </div>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                      <div className="flex items-center gap-1">
                        <Coins className="w-4 h-4 text-yellow-400" />
                        <span className="text-white font-medium">
                          {negotiation.negotiated_reward || negotiation.original_reward} points
                        </span>
                        {negotiation.negotiated_reward && negotiation.negotiated_reward > negotiation.original_reward && (
                          <span className="text-green-400 text-xs">
                            (+{negotiation.negotiated_reward - negotiation.original_reward})
                          </span>
                        )}
                      </div>
                      
                      {negotiation.counter_offers > 0 && (
                        <span className="text-orange-400 text-xs">
                          {negotiation.counter_offers}/{negotiation.max_counter_offers} contre-offres
                        </span>
                      )}
                    </div>
                    
                    {negotiation.status === 'pending' && (
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleReject(negotiation.id)}
                          className="px-3 py-1 bg-red-500/20 text-red-400 rounded text-xs hover:bg-red-500/30 transition-colors"
                        >
                          Refuser
                        </button>
                        
                        {negotiation.counter_offers < negotiation.max_counter_offers && (
                          <button
                            onClick={() => setActiveNegotiation(negotiation.id)}
                            className="px-3 py-1 bg-orange-500/20 text-orange-400 rounded text-xs hover:bg-orange-500/30 transition-colors"
                          >
                            Négocier
                          </button>
                        )}
                        
                        <button
                          onClick={() => handleAccept(negotiation.id)}
                          className="px-3 py-1 bg-green-500/20 text-green-400 rounded text-xs hover:bg-green-500/30 transition-colors"
                        >
                          Accepter
                        </button>
                      </div>
                    )}
                    
                    {negotiation.status === 'negotiating' && (
                      <div className="text-orange-400 text-sm flex items-center gap-1">
                        <Clock className="w-4 h-4" />
                        En négociation...
                      </div>
                    )}
                    
                    {negotiation.status === 'accepted' && (
                      <div className="text-green-400 text-sm flex items-center gap-1">
                        <CheckCircle className="w-4 h-4" />
                        Accepté - En cours
                      </div>
                    )}
                  </div>
                  
                  {/* Counter Offer Interface */}
                  {activeNegotiation === negotiation.id && (
                    <div className="mt-4 p-4 bg-orange-500/10 border border-orange-400/30 rounded-lg">
                      <h4 className="text-white font-medium mb-3">Faire une contre-offre</h4>
                      <div className="flex items-center gap-3 mb-3">
                        <span className="text-white/70 text-sm">Points proposés:</span>
                        <input
                          type="number"
                          value={counterOfferPoints}
                          onChange={(e) => setCounterOfferPoints(Number(e.target.value))}
                          placeholder={`Min: ${negotiation.original_reward + 10}`}
                          className="bg-white/10 border border-white/20 rounded px-3 py-1 text-white text-sm w-24"
                          min={negotiation.original_reward + 10}
                          max={negotiation.original_reward + (negotiation.original_reward * 0.5)}
                        />
                        <span className="text-white/60 text-xs">
                          (Max: {negotiation.original_reward + Math.floor(negotiation.original_reward * 0.5)})
                        </span>
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => setActiveNegotiation(null)}
                          className="px-3 py-1 bg-white/10 text-white/70 rounded text-sm hover:bg-white/20 transition-colors"
                        >
                          Annuler
                        </button>
                        <button
                          onClick={() => handleCounterOffer(negotiation.id)}
                          className="px-3 py-1 bg-orange-500/20 text-orange-400 rounded text-sm hover:bg-orange-500/30 transition-colors flex items-center gap-1"
                        >
                          <ArrowRight className="w-3 h-3" />
                          Proposer
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* History Preview */}
      {negotiations.filter(n => ['completed', 'rejected', 'expired'].includes(n.status)).length > 0 && (
        <div className="mt-6 pt-4 border-t border-white/10">
          <h3 className="text-white/70 text-sm mb-3">Négociations récentes</h3>
          <div className="space-y-2">
            {negotiations
              .filter(n => ['completed', 'rejected', 'expired'].includes(n.status))
              .slice(0, 3)
              .map((negotiation) => (
                <div key={negotiation.id} className="flex items-center gap-3 p-2 bg-white/5 rounded">
                  <div className={`w-2 h-2 rounded-full ${
                    negotiation.status === 'completed' ? 'bg-green-400' :
                    negotiation.status === 'rejected' ? 'bg-red-400' :
                    'bg-gray-400'
                  }`} />
                  <span className="text-white/70 text-sm flex-1">{negotiation.title}</span>
                  <span className={`text-xs ${
                    negotiation.status === 'completed' ? 'text-green-400' :
                    negotiation.status === 'rejected' ? 'text-red-400' :
                    'text-gray-400'
                  }`}>
                    {negotiation.status === 'completed' && `+${negotiation.negotiated_reward || negotiation.original_reward}`}
                    {negotiation.status === 'rejected' && 'Refusé'}
                    {negotiation.status === 'expired' && 'Expiré'}
                  </span>
                </div>
              ))}
          </div>
        </div>
      )}
    </div>
  )
}

export default NegotiationSystem