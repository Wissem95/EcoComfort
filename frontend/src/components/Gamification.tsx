import { useState, useEffect } from 'react'
import { 
  Trophy, 
  Award, 
  Star, 
  Target, 
  TrendingUp, 
  Users, 
  Zap, 
  Thermometer,
  DoorClosed,
  Calendar,
  Clock,
  ChevronRight,
  Medal,
  Crown,
  Flame,
  Leaf
} from 'lucide-react'
import type { 
  Badge, 
  Challenge, 
  GamificationLevel, 
  LeaderboardEntry, 
  GamificationLevelName,
  GamificationLevelConfig 
} from '../types'

interface GamificationProps {
  userLevel: GamificationLevel | null
  userPoints: number
  className?: string
}

const LEVEL_CONFIGS: GamificationLevelConfig[] = [
  {
    name: 'beginner',
    icon: '🌱',
    title: 'Éco-Débutant',
    minPoints: 0,
    maxPoints: 499,
    color: 'text-green-400',
    benefits: ['Accès au tableau de bord', 'Notifications de base', 'Suivi énergétique simple']
  },
  {
    name: 'saver',
    icon: '🌿',
    title: 'Éco-Économe',
    minPoints: 500,
    maxPoints: 1499,
    color: 'text-green-500',
    benefits: ['Défis hebdomadaires', 'Badges avancés', 'Comparaisons mensuelles', 'Recommandations personnalisées']
  },
  {
    name: 'expert',
    icon: '🌳',
    title: 'Éco-Expert',
    minPoints: 1500,
    maxPoints: 4999,
    color: 'text-green-600',
    benefits: ['Défis complexes', 'Analyse prédictive', 'Leaderboard équipe', 'Actions automatisées']
  },
  {
    name: 'champion',
    icon: '🏆',
    title: 'Éco-Champion',
    minPoints: 5000,
    maxPoints: 14999,
    color: 'text-yellow-500',
    benefits: ['Défis sur mesure', 'Coaching virtuel', 'Récompenses exclusives', 'Mentor junior']
  },
  {
    name: 'master',
    icon: '👑',
    title: 'Éco-Maître',
    minPoints: 15000,
    maxPoints: 999999,
    color: 'text-purple-500',
    benefits: ['Accès VIP', 'Influence sur les fonctionnalités', 'Récompenses premium', 'Statut légendaire']
  }
]

const Gamification = ({ userLevel, userPoints, className = '' }: GamificationProps) => {
  const [activeTab, setActiveTab] = useState<'level' | 'badges' | 'challenges' | 'leaderboard'>('level')
  const [badges, setBadges] = useState<Badge[]>([])
  const [challenges, setChallenges] = useState<Challenge[]>([])
  const [leaderboard, setLeaderboard] = useState<LeaderboardEntry[]>([])
  const [isExpanded, setIsExpanded] = useState(false)

  useEffect(() => {
    initializeMockGamificationData()
  }, [])

  const initializeMockGamificationData = () => {
    // Mock badges
    setBadges([
      {
        id: '1',
        name: 'Première Économie',
        description: 'Votre première action d\'économie d\'énergie',
        earned: true,
        progress: 100,
        threshold: 1,
        progress_percent: 100,
        points: 10,
        earned_at: new Date(Date.now() - 86400000 * 5).toISOString()
      },
      {
        id: '2',
        name: 'Ferme-Porte',
        description: 'Fermez 10 portes ouvertes',
        earned: true,
        progress: 10,
        threshold: 10,
        progress_percent: 100,
        points: 25,
        earned_at: new Date(Date.now() - 86400000 * 3).toISOString()
      },
      {
        id: '3',
        name: 'Détective Énergie',
        description: 'Identifiez 5 pertes énergétiques',
        earned: true,
        progress: 5,
        threshold: 5,
        progress_percent: 100,
        points: 50,
        earned_at: new Date(Date.now() - 86400000 * 1).toISOString()
      },
      {
        id: '4',
        name: 'Gardien Thermique',
        description: 'Maintenez la température optimale pendant 7 jours',
        earned: false,
        progress: 4,
        threshold: 7,
        progress_percent: 57,
        points: 100
      },
      {
        id: '5',
        name: 'Éco-Warrior',
        description: 'Économisez 1000 kWh d\'énergie',
        earned: false,
        progress: 650,
        threshold: 1000,
        progress_percent: 65,
        points: 200
      },
      {
        id: '6',
        name: 'Mentor Vert',
        description: 'Aidez 3 collègues à améliorer leur score',
        earned: false,
        progress: 1,
        threshold: 3,
        progress_percent: 33,
        points: 150
      }
    ])

    // Mock challenges
    setChallenges([
      {
        id: '1',
        name: 'Semaine Zéro Gaspillage',
        description: 'Aucune porte ouverte > 5 minutes pendant 7 jours',
        target: 7,
        metric: 'actions',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 86400000 * 7).toISOString(),
        reward_points: 150,
        status: 'active',
        progress: 4,
        progress_percent: 57,
        participants: ['user-1', 'user-2', 'user-3']
      },
      {
        id: '2',
        name: 'Défi Température',
        description: 'Maintenez 22-24°C dans votre zone',
        target: 168, // heures dans une semaine
        metric: 'points',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 86400000 * 7).toISOString(),
        reward_points: 200,
        status: 'active',
        progress: 98,
        progress_percent: 58,
        participants: ['user-1', 'user-2', 'user-3', 'user-4', 'user-5']
      },
      {
        id: '3',
        name: 'Champion du Mois',
        description: 'Soyez le #1 du leaderboard mensuel',
        target: 1,
        metric: 'points',
        start_date: new Date(Date.now() - 86400000 * 15).toISOString(),
        end_date: new Date(Date.now() + 86400000 * 15).toISOString(),
        reward_points: 500,
        status: 'active',
        progress: 1850,
        progress_percent: 75,
        participants: ['user-1', 'user-2', 'user-3', 'user-4', 'user-5', 'user-6']
      }
    ])

    // Mock leaderboard
    setLeaderboard([
      {
        rank: 1,
        user_id: 'current-user',
        name: 'Demo User (Vous)',
        total_points: 1250,
        period_points: 285,
        period_actions: 12,
        level: 2,
        badges_count: 3
      },
      {
        rank: 2,
        user_id: 'user-2',
        name: 'Marie Dupont',
        total_points: 1180,
        period_points: 245,
        period_actions: 8,
        level: 2,
        badges_count: 4
      },
      {
        rank: 3,
        user_id: 'user-3',
        name: 'Thomas Martin',
        total_points: 1050,
        period_points: 190,
        period_actions: 15,
        level: 2,
        badges_count: 2
      },
      {
        rank: 4,
        user_id: 'user-4',
        name: 'Sophie Bernard',
        total_points: 890,
        period_points: 165,
        period_actions: 6,
        level: 1,
        badges_count: 3
      },
      {
        rank: 5,
        user_id: 'user-5',
        name: 'Lucas Petit',
        total_points: 720,
        period_points: 120,
        period_actions: 9,
        level: 1,
        badges_count: 1
      }
    ])
  }

  const getCurrentLevelConfig = (): GamificationLevelConfig => {
    return LEVEL_CONFIGS.find(config => 
      userPoints >= config.minPoints && userPoints <= config.maxPoints
    ) || LEVEL_CONFIGS[0]
  }

  const getNextLevelConfig = (): GamificationLevelConfig | null => {
    const currentLevel = getCurrentLevelConfig()
    const currentIndex = LEVEL_CONFIGS.findIndex(config => config.name === currentLevel.name)
    return currentIndex < LEVEL_CONFIGS.length - 1 ? LEVEL_CONFIGS[currentIndex + 1] : null
  }

  const getProgressToNextLevel = (): number => {
    const current = getCurrentLevelConfig()
    const next = getNextLevelConfig()
    
    if (!next) return 100
    
    const pointsInCurrentLevel = userPoints - current.minPoints
    const pointsNeededForNext = next.minPoints - current.minPoints
    
    return Math.min(100, (pointsInCurrentLevel / pointsNeededForNext) * 100)
  }

  const getBadgeIcon = (badge: Badge) => {
    if (badge.name.includes('Porte')) return <DoorClosed className="w-6 h-6" />
    if (badge.name.includes('Thermique') || badge.name.includes('Température')) return <Thermometer className="w-6 h-6" />
    if (badge.name.includes('Énergie')) return <Zap className="w-6 h-6" />
    if (badge.name.includes('Warrior') || badge.name.includes('Champion')) return <Crown className="w-6 h-6" />
    if (badge.name.includes('Mentor')) return <Users className="w-6 h-6" />
    return <Star className="w-6 h-6" />
  }

  const getChallengeIcon = (challenge: Challenge) => {
    if (challenge.name.includes('Température')) return <Thermometer className="w-5 h-5" />
    if (challenge.name.includes('Gaspillage') || challenge.name.includes('Porte')) return <DoorClosed className="w-5 h-5" />
    if (challenge.name.includes('Champion')) return <Trophy className="w-5 h-5" />
    return <Target className="w-5 h-5" />
  }

  const getRemainingTime = (endDate: string): string => {
    const now = new Date().getTime()
    const end = new Date(endDate).getTime()
    const diff = end - now
    
    if (diff <= 0) return 'Terminé'
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    
    if (days > 0) return `${days}j ${hours}h`
    return `${hours}h`
  }

  if (!isExpanded) {
    return (
      <button
        onClick={() => setIsExpanded(true)}
        className={`glass-card-hover p-4 flex items-center gap-3 ${className}`}
      >
        <div className="text-2xl">{getCurrentLevelConfig().icon}</div>
        <div className="flex-1 text-left">
          <div className="text-white font-semibold">{getCurrentLevelConfig().title}</div>
          <div className="text-white/70 text-sm">{userPoints} points • Niveau {userLevel?.current_level}</div>
        </div>
        <ChevronRight className="w-5 h-5 text-white/70" />
      </button>
    )
  }

  const currentLevel = getCurrentLevelConfig()
  const nextLevel = getNextLevelConfig()
  const progress = getProgressToNextLevel()

  return (
    <div className={`glass-card p-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold text-white flex items-center gap-2">
          <Trophy className="w-6 h-6 text-yellow-500" />
          Gamification
        </h2>
        <button
          onClick={() => setIsExpanded(false)}
          className="text-white/70 hover:text-white p-1"
        >
          ×
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-6">
        {(['level', 'badges', 'challenges', 'leaderboard'] as const).map((tab) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              activeTab === tab
                ? 'bg-primary-500/30 text-primary-300 border border-primary-400/30'
                : 'text-white/70 hover:text-white hover:bg-white/10'
            }`}
          >
            {tab === 'level' && 'Niveau'}
            {tab === 'badges' && 'Badges'}
            {tab === 'challenges' && 'Défis'}
            {tab === 'leaderboard' && 'Classement'}
          </button>
        ))}
      </div>

      {/* Level Tab */}
      {activeTab === 'level' && (
        <div className="space-y-6">
          {/* Current Level */}
          <div className="text-center">
            <div className="text-6xl mb-4">{currentLevel.icon}</div>
            <h3 className="text-2xl font-bold text-white mb-2">{currentLevel.title}</h3>
            <p className="text-white/70 mb-4">{userPoints.toLocaleString()} points</p>
            
            {nextLevel && (
              <div>
                <div className="text-white/70 text-sm mb-2">
                  Progression vers {nextLevel.title}
                </div>
                <div className="w-full bg-white/20 rounded-full h-3 mb-2">
                  <div 
                    className="bg-gradient-to-r from-primary-500 to-primary-400 h-3 rounded-full transition-all duration-500"
                    style={{ width: `${progress}%` }}
                  />
                </div>
                <p className="text-white/60 text-sm">
                  {(nextLevel.minPoints - userPoints).toLocaleString()} points restants
                </p>
              </div>
            )}
          </div>

          {/* Benefits */}
          <div>
            <h4 className="text-lg font-semibold text-white mb-3">Avantages actuels</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {currentLevel.benefits.map((benefit, index) => (
                <div key={index} className="flex items-center gap-2 text-white/80 text-sm">
                  <Star className="w-4 h-4 text-primary-400 flex-shrink-0" />
                  {benefit}
                </div>
              ))}
            </div>
          </div>

          {/* All Levels Overview */}
          <div>
            <h4 className="text-lg font-semibold text-white mb-3">Tous les niveaux</h4>
            <div className="space-y-2">
              {LEVEL_CONFIGS.map((level, _index) => (
                <div 
                  key={level.name}
                  className={`flex items-center gap-4 p-3 rounded-lg ${
                    level.name === currentLevel.name 
                      ? 'bg-primary-500/20 border border-primary-400/30' 
                      : 'bg-white/5'
                  }`}
                >
                  <div className="text-2xl">{level.icon}</div>
                  <div className="flex-1">
                    <div className="text-white font-medium">{level.title}</div>
                    <div className="text-white/60 text-sm">
                      {level.minPoints.toLocaleString()} - {level.maxPoints.toLocaleString()} points
                    </div>
                  </div>
                  {level.name === currentLevel.name && (
                    <div className="text-primary-400 text-sm font-medium">Actuel</div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Badges Tab */}
      {activeTab === 'badges' && (
        <div className="space-y-4">
          <div className="text-white/70 text-sm">
            {badges.filter(b => b.earned).length} / {badges.length} badges obtenus
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {badges.map((badge) => (
              <div 
                key={badge.id}
                className={`p-4 rounded-lg border transition-all ${
                  badge.earned
                    ? 'bg-gradient-to-r from-yellow-500/20 to-orange-500/20 border-yellow-400/30'
                    : 'bg-white/5 border-white/10'
                }`}
              >
                <div className="flex items-start gap-3">
                  <div className={`p-2 rounded-lg ${
                    badge.earned ? 'bg-yellow-500/30 text-yellow-300' : 'bg-white/10 text-white/50'
                  }`}>
                    {getBadgeIcon(badge)}
                  </div>
                  
                  <div className="flex-1 min-w-0">
                    <h4 className={`font-semibold mb-1 ${
                      badge.earned ? 'text-white' : 'text-white/60'
                    }`}>
                      {badge.name}
                    </h4>
                    <p className="text-white/70 text-sm mb-2">{badge.description}</p>
                    
                    {badge.earned ? (
                      <div className="flex items-center gap-2 text-yellow-400 text-sm">
                        <Award className="w-4 h-4" />
                        +{badge.points} points
                        {badge.earned_at && (
                          <span className="text-white/50">
                            • {new Date(badge.earned_at).toLocaleDateString('fr-FR')}
                          </span>
                        )}
                      </div>
                    ) : (
                      <div>
                        <div className="w-full bg-white/20 rounded-full h-2 mb-1">
                          <div 
                            className="bg-primary-500 h-2 rounded-full transition-all"
                            style={{ width: `${badge.progress_percent}%` }}
                          />
                        </div>
                        <div className="text-white/60 text-xs">
                          {badge.progress} / {badge.threshold} • {badge.progress_percent}%
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Challenges Tab */}
      {activeTab === 'challenges' && (
        <div className="space-y-4">
          {challenges.map((challenge) => (
            <div key={challenge.id} className="glass-card p-4">
              <div className="flex items-start gap-3">
                <div className="p-2 bg-primary-500/20 rounded-lg text-primary-300">
                  {getChallengeIcon(challenge)}
                </div>
                
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-semibold text-white">{challenge.name}</h4>
                    <div className="flex items-center gap-2 text-white/60 text-sm">
                      <Clock className="w-4 h-4" />
                      {getRemainingTime(challenge.end_date)}
                    </div>
                  </div>
                  
                  <p className="text-white/70 text-sm mb-3">{challenge.description}</p>
                  
                  <div className="mb-3">
                    <div className="flex items-center justify-between text-sm mb-1">
                      <span className="text-white/70">Progression</span>
                      <span className="text-white">{challenge.progress_percent}%</span>
                    </div>
                    <div className="w-full bg-white/20 rounded-full h-2">
                      <div 
                        className="bg-gradient-to-r from-primary-500 to-primary-400 h-2 rounded-full transition-all"
                        style={{ width: `${challenge.progress_percent}%` }}
                      />
                    </div>
                  </div>
                  
                  <div className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-4 text-white/60">
                      <div className="flex items-center gap-1">
                        <Users className="w-4 h-4" />
                        {challenge.participants.length} participants
                      </div>
                      <div className="flex items-center gap-1">
                        <Trophy className="w-4 h-4" />
                        +{challenge.reward_points} points
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Leaderboard Tab */}
      {activeTab === 'leaderboard' && (
        <div className="space-y-3">
          <div className="text-white/70 text-sm mb-4">Classement mensuel</div>
          
          {leaderboard.map((entry) => (
            <div 
              key={entry.user_id}
              className={`flex items-center gap-4 p-3 rounded-lg ${
                entry.user_id === 'current-user'
                  ? 'bg-gradient-to-r from-primary-500/20 to-primary-400/20 border border-primary-400/30'
                  : 'bg-white/5'
              }`}
            >
              <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm ${
                entry.rank === 1 ? 'bg-yellow-500 text-yellow-900' :
                entry.rank === 2 ? 'bg-gray-400 text-gray-900' :
                entry.rank === 3 ? 'bg-amber-600 text-amber-100' :
                'bg-white/20 text-white'
              }`}>
                {entry.rank <= 3 ? (
                  entry.rank === 1 ? '👑' : entry.rank === 2 ? '🥈' : '🥉'
                ) : (
                  entry.rank
                )}
              </div>
              
              <div className="flex-1">
                <div className="text-white font-medium">{entry.name}</div>
                <div className="text-white/60 text-sm">
                  Niveau {entry.level} • {entry.badges_count} badges
                </div>
              </div>
              
              <div className="text-right">
                <div className="text-white font-semibold">
                  {entry.total_points.toLocaleString()}
                </div>
                <div className="text-primary-400 text-sm">
                  +{entry.period_points} ce mois
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default Gamification