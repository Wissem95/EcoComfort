import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { 
  Home, 
  History, 
  Settings, 
  User, 
  Menu, 
  X,
  Wifi,
  WifiOff,
  Bell,
  Trophy,
  Handshake,
  Zap,
  Moon,
  Sun,
  Shield,
  LogOut
} from 'lucide-react'

interface NavigationProps {
  isConnected: boolean
  userPoints: number
  userLevel: number
  darkMode: boolean
  onToggleDarkMode: () => void
  unreadNotifications?: number
  onLogout?: () => void
}

const Navigation = ({ 
  isConnected, 
  userPoints, 
  userLevel, 
  darkMode, 
  onToggleDarkMode,
  unreadNotifications = 0,
  onLogout 
}: NavigationProps) => {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const location = useLocation()

  const navigationItems = [
    { path: '/', icon: Home, label: 'Dashboard' },
    { path: '/history', icon: History, label: 'Historique' },
    { path: '/profile', icon: User, label: 'Profil' },
    { path: '/settings', icon: Settings, label: 'Paramètres' },
    { path: '/admin', icon: Shield, label: 'Administration' }
  ]

  const isActivePath = (path: string) => {
    if (path === '/') {
      return location.pathname === '/'
    }
    return location.pathname.startsWith(path)
  }

  const levelIcons = ['🌱', '🌿', '🌳', '🏆', '👑']

  return (
    <>
      {/* Desktop Sidebar */}
      <aside className="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:bg-black/20 lg:backdrop-blur-xl lg:border-r lg:border-white/10 lg:z-40">
        {/* Logo */}
        <div className="flex items-center gap-3 p-6 border-b border-white/10">
          <div className="w-10 h-10 bg-gradient-to-r from-green-400 to-emerald-500 rounded-xl flex items-center justify-center text-white font-bold text-lg">
            🌿
          </div>
          <div>
            <h1 className="text-white font-bold text-lg">EcoComfort</h1>
            <p className="text-white/60 text-xs">Gestion Énergétique IoT</p>
          </div>
        </div>

        {/* User Info */}
        <div className="p-6 border-b border-white/10">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold">
              U
            </div>
            <div>
              <p className="text-white font-medium">Demo User</p>
              <p className="text-white/60 text-sm">demo@ecocomfort.com</p>
            </div>
          </div>
          
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span className="text-white/70">Points</span>
              <span className="text-white font-semibold">{userPoints.toLocaleString()}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-white/70">Niveau</span>
              <div className="flex items-center gap-1">
                <span className="text-lg">{levelIcons[userLevel - 1] || levelIcons[0]}</span>
                <span className="text-white font-semibold">{userLevel}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-4">
          <ul className="space-y-2">
            {navigationItems.map((item) => {
              const Icon = item.icon
              const isActive = isActivePath(item.path)
              
              return (
                <li key={item.path}>
                  <Link
                    to={item.path}
                    className={`flex items-center gap-3 p-3 rounded-xl transition-all duration-200 ${
                      isActive
                        ? 'bg-primary-500/20 text-primary-300 border border-primary-400/30'
                        : 'text-white/70 hover:text-white hover:bg-white/5'
                    }`}
                  >
                    <Icon className="w-5 h-5" />
                    <span className="font-medium">{item.label}</span>
                  </Link>
                </li>
              )
            })}
          </ul>
        </nav>

        {/* Status & Controls */}
        <div className="p-4 border-t border-white/10 space-y-3">
          {/* Connection Status */}
          <div className="flex items-center gap-2 text-sm">
            {isConnected ? (
              <Wifi className="w-4 h-4 text-green-400" />
            ) : (
              <WifiOff className="w-4 h-4 text-red-400" />
            )}
            <span className={isConnected ? 'text-green-400' : 'text-red-400'}>
              {isConnected ? 'Connecté' : 'Déconnecté'}
            </span>
          </div>

          {/* Quick Actions */}
          <div className="flex gap-2">
            <button
              onClick={onToggleDarkMode}
              className="flex-1 glass-button-primary py-2 px-3 text-sm flex items-center justify-center gap-2"
              title={darkMode ? 'Mode clair' : 'Mode sombre'}
            >
              {darkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
            </button>
            {onLogout && (
              <button
                onClick={onLogout}
                className="glass-button-danger py-2 px-3 text-sm flex items-center justify-center gap-2"
                title="Se déconnecter"
              >
                <LogOut className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </aside>

      {/* Mobile Header */}
      <header className="lg:hidden fixed top-0 left-0 right-0 bg-black/40 backdrop-blur-xl border-b border-white/10 z-50">
        <div className="flex items-center justify-between p-4">
          {/* Logo */}
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-gradient-to-r from-green-400 to-emerald-500 rounded-lg flex items-center justify-center text-white font-bold text-sm">
              🌿
            </div>
            <h1 className="text-white font-bold">EcoComfort</h1>
          </div>

          {/* Status & Menu */}
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2">
              {isConnected ? (
                <Wifi className="w-4 h-4 text-green-400" />
              ) : (
                <WifiOff className="w-4 h-4 text-red-400" />
              )}
            </div>

            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="p-2 text-white hover:bg-white/10 rounded-lg transition-colors"
            >
              {isMobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        {isMobileMenuOpen && (
          <div className="border-t border-white/10 bg-black/60 backdrop-blur-xl">
            <nav className="p-4">
              <ul className="space-y-2">
                {navigationItems.map((item) => {
                  const Icon = item.icon
                  const isActive = isActivePath(item.path)
                  
                  return (
                    <li key={item.path}>
                      <Link
                        to={item.path}
                        onClick={() => setIsMobileMenuOpen(false)}
                        className={`flex items-center gap-3 p-3 rounded-lg transition-all ${
                          isActive
                            ? 'bg-primary-500/20 text-primary-300'
                            : 'text-white/70 hover:text-white hover:bg-white/5'
                        }`}
                      >
                        <Icon className="w-5 h-5" />
                        <span>{item.label}</span>
                      </Link>
                    </li>
                  )
                })}
              </ul>

              {/* Mobile User Info */}
              <div className="mt-4 pt-4 border-t border-white/10">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                    U
                  </div>
                  <div>
                    <p className="text-white font-medium">Demo User</p>
                    <p className="text-white/60 text-sm">{userPoints.toLocaleString()} pts • Niveau {userLevel}</p>
                  </div>
                </div>
                
                <div className="flex gap-2">
                  <button
                    onClick={onToggleDarkMode}
                    className="flex-1 glass-button-primary py-2 flex items-center justify-center gap-2"
                  >
                    {darkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    {darkMode ? 'Mode Clair' : 'Mode Sombre'}
                  </button>
                  {onLogout && (
                    <button
                      onClick={() => {
                        setIsMobileMenuOpen(false)
                        onLogout()
                      }}
                      className="glass-button-danger py-2 px-4 flex items-center justify-center gap-2"
                      title="Se déconnecter"
                    >
                      <LogOut className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            </nav>
          </div>
        )}
      </header>

      {/* Mobile Bottom Navigation */}
      <nav className="lg:hidden fixed bottom-0 left-0 right-0 bg-black/60 backdrop-blur-xl border-t border-white/10 z-40 safe-area-pb">
        <div className="flex items-center justify-around p-2">
          {navigationItems.slice(0, 4).map((item) => {
            const Icon = item.icon
            const isActive = isActivePath(item.path)
            
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex flex-col items-center gap-1 p-3 rounded-lg transition-all min-w-[4rem] ${
                  isActive
                    ? 'text-primary-300'
                    : 'text-white/60 hover:text-white'
                }`}
              >
                <div className="relative">
                  <Icon className="w-5 h-5" />
                  {item.path === '/' && unreadNotifications > 0 && (
                    <div className="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full flex items-center justify-center">
                      <span className="text-[8px] text-white font-bold">
                        {unreadNotifications > 9 ? '9+' : unreadNotifications}
                      </span>
                    </div>
                  )}
                </div>
                <span className="text-xs font-medium">{item.label}</span>
              </Link>
            )
          })}
        </div>
      </nav>

      {/* Mobile Menu Backdrop */}
      {isMobileMenuOpen && (
        <div 
          className="lg:hidden fixed inset-0 bg-black/50 z-40"
          onClick={() => setIsMobileMenuOpen(false)}
        />
      )}
    </>
  )
}

export default Navigation