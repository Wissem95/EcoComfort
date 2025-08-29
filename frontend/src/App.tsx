import { useState, useEffect, useCallback } from 'react'
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import AuthWrapper from './components/AuthWrapper'
import Dashboard from './pages/Dashboard'
import History from './pages/History'
import Profile from './pages/Profile'
import Settings from './pages/Settings'
import Admin from './pages/Admin'
import webSocketService from './services/websocket'
import apiService from './services/api'
import type { GamificationLevel } from './types'

function App() {
  const [isConnected, setIsConnected] = useState(false)
  const [gamification, setGamification] = useState<GamificationLevel | null>(null)
  const [currentUser, setCurrentUser] = useState<{
    name: string
    points: number
    level: number
    id?: string
    organizationId?: string
  } | null>(null)
  const [isAuthenticated, setIsAuthenticated] = useState(false)
  const [loading, setLoading] = useState(true)

  const handleLogout = useCallback(() => {
    // Clear authentication
    apiService.clearAuthToken()
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_data')
    
    // Reset state
    setIsAuthenticated(false)
    setCurrentUser(null)
    setGamification(null)
    setIsConnected(false)
    
    // Disconnect WebSocket
    webSocketService.disconnect()
    
    console.log('User logged out successfully')
  }, [])

  useEffect(() => {
    initializeApp()

    // Listen for token expiration events
    const handleTokenExpired = () => {
      console.warn('Token expired, logging out user...')
      handleLogout()
    }

    window.addEventListener('auth:token-expired', handleTokenExpired)

    return () => {
      window.removeEventListener('auth:token-expired', handleTokenExpired)
    }
  }, [handleLogout])

  const initializeApp = async () => {
    try {
      setLoading(true)
      
      // Check if user has auth token
      const authToken = localStorage.getItem('auth_token')
      
      if (!authToken) {
        // No token, user needs to authenticate
        setIsAuthenticated(false)
        setLoading(false)
        return
      }

      // Set token and validate with backend
      apiService.setAuthToken(authToken)
      
      try {
        // Try to fetch user profile to validate token
        const userData = await apiService.getUserProfile()
        
        setCurrentUser({
          id: userData.id,
          name: userData.name,
          points: userData.points || 0,
          level: userData.level || 1,
          organizationId: userData.organization_id
        })
        
        // Try to fetch gamification data
        try {
          const gamificationData = await apiService.getGamificationData()
          if (gamificationData.user) {
            setGamification({
              current_level: gamificationData.user.level?.current_level || userData.level || 1,
              next_level: gamificationData.user.level?.next_level || (userData.level || 1) + 1,
              total_points: gamificationData.user.total_points || userData.points || 0,
              points_for_current: gamificationData.user.level?.points_for_current || 0,
              points_for_next: gamificationData.user.level?.points_for_next || 100,
              points_to_next: gamificationData.user.level?.points_to_next || 100,
              progress_percent: gamificationData.user.level?.progress_percent || 0,
              is_max_level: gamificationData.user.level?.is_max_level || false
            })
          }
        } catch (gamificationError) {
          console.warn('Failed to fetch gamification data:', gamificationError)
        }
        
        // Initialize WebSocket with user data
        webSocketService.initializeUser(userData.id, userData.organization_id)
        
        // Subscribe to connection events
        const unsubscribeConnected = webSocketService.on('connected', () => {
          setIsConnected(true)
        })

        const unsubscribeDisconnected = webSocketService.on('disconnected', () => {
          setIsConnected(false)
        })

        // Store cleanup functions
        window.addEventListener('beforeunload', () => {
          unsubscribeConnected()
          unsubscribeDisconnected()
        })
        
        setIsAuthenticated(true)
      } catch (error) {
        console.error('Authentication failed:', error)
        // Clear invalid token
        apiService.clearAuthToken()
        localStorage.removeItem('user_data')
        setIsAuthenticated(false)
      }
      
      setLoading(false)
    } catch (error) {
      console.error('Failed to initialize app:', error)
      setIsAuthenticated(false)
      setLoading(false)
    }
  }

  const handleAuthSuccess = (token: string, user: any) => {
    apiService.setAuthToken(token)
    localStorage.setItem('user_data', JSON.stringify(user))
    
    setCurrentUser({
      id: user.id,
      name: user.name,
      points: user.points || 0,
      level: user.level || 1,
      organizationId: user.organization_id
    })
    
    setIsAuthenticated(true)
    
    // Initialize WebSocket
    webSocketService.initializeUser(user.id, user.organization_id)
    
    // Fetch additional data after successful auth
    initializeApp()
  }


  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center">
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <h2 className="text-xl font-semibold text-white mb-2">Initialisation...</h2>
          <p className="text-white/70">Connexion aux services EcoComfort</p>
        </div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <AuthWrapper onAuthSuccess={handleAuthSuccess} />
  }

  if (!currentUser) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center">
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <h2 className="text-xl font-semibold text-white mb-2">Chargement du profil...</h2>
          <p className="text-white/70">Récupération des données utilisateur</p>
        </div>
      </div>
    )
  }

  return (
    <Router>
      <Layout
        isConnected={isConnected}
        userPoints={currentUser.points}
        userLevel={currentUser.level}
        onLogout={handleLogout}
      >
        <Routes>
          <Route 
            path="/" 
            element={
              <Dashboard 
                setIsConnected={setIsConnected}
                gamification={gamification}
                currentUser={currentUser}
              />
            } 
          />
          <Route path="/history" element={<History />} />
          <Route 
            path="/profile" 
            element={
              <Profile 
                userPoints={currentUser.points}
                userLevel={currentUser.level}
                gamificationLevel={gamification}
              />
            } 
          />
          <Route path="/settings" element={<Settings />} />
          <Route path="/admin" element={<Admin />} />
        </Routes>
      </Layout>
    </Router>
  )
}

export default App
