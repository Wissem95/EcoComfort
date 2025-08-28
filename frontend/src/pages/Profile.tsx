import { useState, useEffect } from 'react'
import { 
  User, 
  Mail, 
  Building, 
  Calendar,
  Trophy,
  Award,
  Target,
  TrendingUp,
  Zap,
  DoorClosed,
  Thermometer,
  Star,
  Crown,
  Medal,
  Edit,
  Save,
  X,
  Camera,
  Shield,
  BarChart3
} from 'lucide-react'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, RadialBarChart, RadialBar, BarChart, Bar } from 'recharts'
import type { Badge, GamificationLevel, Challenge } from '../types'

interface ProfileProps {
  userPoints: number
  userLevel: number
  gamificationLevel: GamificationLevel | null
}

const Profile = ({ userPoints, userLevel, gamificationLevel }: ProfileProps) => {
  const [isEditing, setIsEditing] = useState(false)
  const [userInfo, setUserInfo] = useState({
    name: 'Demo User',
    email: 'demo@ecocomfort.com',
    role: 'Gestionnaire Énergie',
    organization: 'EcoComfort Solutions',
    joinDate: '2024-01-15',
    avatar: null as string | null
  })
  const [editInfo, setEditInfo] = useState(userInfo)
  const [achievements, setAchievements] = useState<Badge[]>([])
  const [_challenges, _setChallenges] = useState<Challenge[]>([])
  const [activityData, setActivityData] = useState<Array<{
    date: string
    points: number
    actions: number
    energy_saved: number
  }>>([])
  const [stats, _setStats] = useState({
    totalActions: 156,
    energySaved: 2847, // kWh
    co2Reduced: 1205, // kg
    moneysSaved: 428, // €
    streakDays: 23,
    averageDaily: 18.5
  })

  useEffect(() => {
    generateMockProfileData()
  }, [])

  const generateMockProfileData = () => {
    // Mock achievements
    setAchievements([
      {
        id: '1',
        name: 'Premier Pas',
        description: 'Votre première connexion à EcoComfort',
        earned: true,
        progress: 100,
        threshold: 1,
        progress_percent: 100,
        points: 10,
        earned_at: '2024-01-15T10:00:00Z'
      },
      {
        id: '2',
        name: 'Éco-Warrior',
        description: 'Économisez 1000 kWh d\'énergie',
        earned: true,
        progress: 2847,
        threshold: 1000,
        progress_percent: 100,
        points: 200,
        earned_at: '2024-02-28T14:30:00Z'
      },
      {
        id: '3',
        name: 'Gardien des Portes',
        description: 'Fermez 50 portes ouvertes',
        earned: true,
        progress: 73,
        threshold: 50,
        progress_percent: 100,
        points: 100,
        earned_at: '2024-03-05T16:45:00Z'
      },
      {
        id: '4',
        name: 'Maître Thermique',
        description: 'Maintenez la température optimale pendant 30 jours',
        earned: false,
        progress: 23,
        threshold: 30,
        progress_percent: 77,
        points: 300
      },
      {
        id: '5',
        name: 'Champion Mensuel',
        description: 'Soyez #1 du leaderboard mensuel',
        earned: true,
        progress: 1,
        threshold: 1,
        progress_percent: 100,
        points: 500,
        earned_at: '2024-02-01T00:00:00Z'
      },
      {
        id: '6',
        name: 'Mentor Vert',
        description: 'Aidez 10 collègues à améliorer leur efficacité',
        earned: false,
        progress: 4,
        threshold: 10,
        progress_percent: 40,
        points: 250
      }
    ])

    // Mock activity data
    const activityDataArray = []
    for (let i = 29; i >= 0; i--) {
      const date = new Date()
      date.setDate(date.getDate() - i)
      
      activityDataArray.push({
        date: date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' }),
        points: Math.floor(Math.random() * 50) + 10,
        actions: Math.floor(Math.random() * 10) + 1,
        energy_saved: Math.floor(Math.random() * 100) + 20
      })
    }
    setActivityData(activityDataArray)
  }

  const handleEdit = () => {
    if (isEditing) {
      setUserInfo(editInfo)
    }
    setIsEditing(!isEditing)
  }

  const handleCancel = () => {
    setEditInfo(userInfo)
    setIsEditing(false)
  }

  const handleAvatarChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (file) {
      const reader = new FileReader()
      reader.onload = (e) => {
        const result = e.target?.result as string
        setEditInfo(prev => ({ ...prev, avatar: result }))
      }
      reader.readAsDataURL(file)
    }
  }

  const levelIcons = ['🌱', '🌿', '🌳', '🏆', '👑']
  const levelNames = ['Éco-Débutant', 'Éco-Économe', 'Éco-Expert', 'Éco-Champion', 'Éco-Maître']

  const earnedBadges = achievements.filter(badge => badge.earned)
  const totalBadgePoints = earnedBadges.reduce((sum, badge) => sum + badge.points, 0)

  // Profile completion
  const profileCompletion = [
    { name: 'Informations', value: 100, fill: '#22c55e' },
    { name: 'Achievements', value: (earnedBadges.length / achievements.length) * 100, fill: '#3b82f6' },
    { name: 'Activité', value: stats.streakDays > 7 ? 100 : (stats.streakDays / 7) * 100, fill: '#f59e0b' }
  ]

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Profile Header */}
      <div className="glass-card p-6">
        <div className="flex flex-col lg:flex-row gap-6">
          {/* Avatar & Basic Info */}
          <div className="flex flex-col items-center lg:items-start gap-4">
            <div className="relative">
              <div className="w-24 h-24 lg:w-32 lg:h-32 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-2xl lg:text-3xl overflow-hidden">
                {editInfo.avatar ? (
                  <img src={editInfo.avatar} alt="Avatar" className="w-full h-full object-cover" />
                ) : (
                  editInfo.name.charAt(0).toUpperCase()
                )}
              </div>
              {isEditing && (
                <label className="absolute bottom-0 right-0 p-2 bg-primary-500 rounded-full cursor-pointer hover:bg-primary-600 transition-colors">
                  <Camera className="w-4 h-4 text-white" />
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleAvatarChange}
                    className="hidden"
                  />
                </label>
              )}
            </div>
            
            <div className="text-center lg:text-left">
              <div className="text-3xl lg:text-4xl mb-2">
                {levelIcons[userLevel - 1] || levelIcons[0]}
              </div>
              <div className="text-primary-400 font-medium">
                {levelNames[userLevel - 1] || levelNames[0]}
              </div>
            </div>
          </div>

          {/* User Details */}
          <div className="flex-1">
            <div className="flex items-center justify-between mb-4">
              <h1 className="text-2xl lg:text-3xl font-bold text-white">Profil Utilisateur</h1>
              <div className="flex gap-2">
                {isEditing ? (
                  <>
                    <button
                      onClick={handleCancel}
                      className="glass-button p-2"
                    >
                      <X className="w-4 h-4" />
                    </button>
                    <button
                      onClick={handleEdit}
                      className="glass-button-primary p-2"
                    >
                      <Save className="w-4 h-4" />
                    </button>
                  </>
                ) : (
                  <button
                    onClick={handleEdit}
                    className="glass-button-primary p-2"
                  >
                    <Edit className="w-4 h-4" />
                  </button>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Name */}
              <div>
                <label className="block text-white/70 text-sm mb-2">Nom complet</label>
                {isEditing ? (
                  <input
                    type="text"
                    value={editInfo.name}
                    onChange={(e) => setEditInfo(prev => ({ ...prev, name: e.target.value }))}
                    className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                  />
                ) : (
                  <div className="flex items-center gap-2 text-white">
                    <User className="w-4 h-4 text-white/60" />
                    {userInfo.name}
                  </div>
                )}
              </div>

              {/* Email */}
              <div>
                <label className="block text-white/70 text-sm mb-2">Email</label>
                {isEditing ? (
                  <input
                    type="email"
                    value={editInfo.email}
                    onChange={(e) => setEditInfo(prev => ({ ...prev, email: e.target.value }))}
                    className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                  />
                ) : (
                  <div className="flex items-center gap-2 text-white">
                    <Mail className="w-4 h-4 text-white/60" />
                    {userInfo.email}
                  </div>
                )}
              </div>

              {/* Role */}
              <div>
                <label className="block text-white/70 text-sm mb-2">Rôle</label>
                {isEditing ? (
                  <input
                    type="text"
                    value={editInfo.role}
                    onChange={(e) => setEditInfo(prev => ({ ...prev, role: e.target.value }))}
                    className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                  />
                ) : (
                  <div className="flex items-center gap-2 text-white">
                    <Shield className="w-4 h-4 text-white/60" />
                    {userInfo.role}
                  </div>
                )}
              </div>

              {/* Organization */}
              <div>
                <label className="block text-white/70 text-sm mb-2">Organisation</label>
                {isEditing ? (
                  <input
                    type="text"
                    value={editInfo.organization}
                    onChange={(e) => setEditInfo(prev => ({ ...prev, organization: e.target.value }))}
                    className="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white"
                  />
                ) : (
                  <div className="flex items-center gap-2 text-white">
                    <Building className="w-4 h-4 text-white/60" />
                    {userInfo.organization}
                  </div>
                )}
              </div>

              {/* Join Date */}
              <div className="md:col-span-2">
                <label className="block text-white/70 text-sm mb-2">Membre depuis</label>
                <div className="flex items-center gap-2 text-white">
                  <Calendar className="w-4 h-4 text-white/60" />
                  {new Date(userInfo.joinDate).toLocaleDateString('fr-FR', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                  })}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Stats Overview */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div className="glass-card p-4">
          <div className="text-center">
            <Trophy className="w-8 h-8 text-yellow-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{userPoints.toLocaleString()}</p>
            <p className="text-white/70 text-sm">Points</p>
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="text-center">
            <Target className="w-8 h-8 text-green-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{stats.totalActions}</p>
            <p className="text-white/70 text-sm">Actions</p>
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="text-center">
            <Zap className="w-8 h-8 text-blue-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{stats.energySaved.toLocaleString()}</p>
            <p className="text-white/70 text-sm">kWh Économisés</p>
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="text-center">
            <Thermometer className="w-8 h-8 text-purple-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{stats.co2Reduced.toLocaleString()}</p>
            <p className="text-white/70 text-sm">kg CO₂ Évités</p>
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="text-center">
            <TrendingUp className="w-8 h-8 text-orange-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{stats.moneysSaved}€</p>
            <p className="text-white/70 text-sm">Économies</p>
          </div>
        </div>

        <div className="glass-card p-4">
          <div className="text-center">
            <Star className="w-8 h-8 text-pink-400 mx-auto mb-2" />
            <p className="text-2xl font-bold text-white">{stats.streakDays}</p>
            <p className="text-white/70 text-sm">Jours Consécutifs</p>
          </div>
        </div>
      </div>

      {/* Progress & Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Profile Completion */}
        <div className="glass-card p-6">
          <h3 className="text-xl font-semibold text-white mb-6 flex items-center gap-2">
            <BarChart3 className="w-5 h-5" />
            Profil & Progression
          </h3>
          
          <div className="space-y-4">
            {profileCompletion.map((item, index) => (
              <div key={index}>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-white/80">{item.name}</span>
                  <span className="text-white font-semibold">{item.value.toFixed(0)}%</span>
                </div>
                <div className="w-full bg-white/20 rounded-full h-2">
                  <div 
                    className="h-2 rounded-full transition-all"
                    style={{ 
                      width: `${item.value}%`,
                      backgroundColor: item.fill
                    }}
                  />
                </div>
              </div>
            ))}
          </div>

          <div className="mt-6 p-4 bg-primary-500/10 border border-primary-400/30 rounded-lg">
            <div className="flex items-center gap-2 mb-2">
              <Crown className="w-5 h-5 text-primary-400" />
              <span className="text-primary-300 font-medium">Niveau Actuel</span>
            </div>
            <div className="text-white/80 text-sm mb-3">
              {levelNames[userLevel - 1]} • {userPoints.toLocaleString()} points
            </div>
            {gamificationLevel && !gamificationLevel.is_max_level && (
              <div>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-white/70 text-sm">Prochain niveau</span>
                  <span className="text-white/80 text-sm">
                    {gamificationLevel.points_to_next} points restants
                  </span>
                </div>
                <div className="w-full bg-white/20 rounded-full h-2">
                  <div 
                    className="bg-primary-500 h-2 rounded-full transition-all"
                    style={{ width: `${gamificationLevel.progress_percent}%` }}
                  />
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Activity Chart */}
        <div className="glass-card p-6">
          <h3 className="text-xl font-semibold text-white mb-6">Activité des 30 Derniers Jours</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={activityData.slice(-14)}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.1)" />
                <XAxis dataKey="date" stroke="rgba(255,255,255,0.5)" />
                <YAxis stroke="rgba(255,255,255,0.5)" />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: 'rgba(0,0,0,0.8)', 
                    border: '1px solid rgba(255,255,255,0.2)',
                    borderRadius: '8px',
                    color: 'white'
                  }}
                />
                <Bar dataKey="points" fill="#8b5cf6" name="Points" />
                <Bar dataKey="actions" fill="#22c55e" name="Actions" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Achievements */}
      <div className="glass-card p-6">
        <h3 className="text-xl font-semibold text-white mb-6 flex items-center gap-2">
          <Award className="w-5 h-5" />
          Réalisations ({earnedBadges.length}/{achievements.length})
        </h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {achievements.map((badge) => (
            <div 
              key={badge.id}
              className={`p-4 rounded-lg border transition-all ${
                badge.earned
                  ? 'bg-gradient-to-r from-yellow-500/20 to-orange-500/20 border-yellow-400/30'
                  : 'bg-white/5 border-white/10'
              }`}
            >
              <div className="flex items-start gap-3">
                <div className={`p-3 rounded-lg text-2xl ${
                  badge.earned ? 'bg-yellow-500/30' : 'bg-white/10'
                }`}>
                  {badge.earned ? '🏆' : '🔒'}
                </div>
                
                <div className="flex-1 min-w-0">
                  <h4 className={`font-semibold mb-1 ${
                    badge.earned ? 'text-white' : 'text-white/60'
                  }`}>
                    {badge.name}
                  </h4>
                  <p className="text-white/70 text-sm mb-2">{badge.description}</p>
                  
                  {badge.earned ? (
                    <div className="flex items-center gap-2">
                      <Medal className="w-4 h-4 text-yellow-400" />
                      <span className="text-yellow-400 text-sm font-medium">
                        +{badge.points} points
                      </span>
                      {badge.earned_at && (
                        <span className="text-white/50 text-xs">
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

        <div className="mt-6 p-4 bg-gradient-to-r from-purple-500/20 to-pink-500/20 border border-purple-400/30 rounded-lg">
          <div className="flex items-center justify-between">
            <div>
              <h4 className="text-white font-medium mb-1">Total Points des Réalisations</h4>
              <p className="text-white/70 text-sm">
                Vous avez gagné {totalBadgePoints} points grâce à vos réalisations
              </p>
            </div>
            <div className="text-3xl font-bold text-purple-400">
              {totalBadgePoints}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Profile