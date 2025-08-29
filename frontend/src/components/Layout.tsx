import { useState, useEffect } from 'react'
import type { ReactNode } from 'react'
import Navigation from './Navigation'
import NotificationSystem from './NotificationSystem'

interface LayoutProps {
  children: ReactNode
  isConnected: boolean
  userPoints: number
  userLevel: number
  onLogout?: () => void
}

const Layout = ({ 
  children, 
  isConnected, 
  userPoints, 
  userLevel,
  onLogout
}: LayoutProps) => {
  const [darkMode, setDarkMode] = useState(true)
  const [unreadNotifications] = useState(0)

  useEffect(() => {
    // Load dark mode preference
    const savedTheme = localStorage.getItem('ecocomfort-theme')
    if (savedTheme) {
      setDarkMode(savedTheme === 'dark')
    }
    
    // Apply theme to document
    document.documentElement.classList.toggle('dark', darkMode)
  }, [darkMode])

  const toggleDarkMode = () => {
    const newMode = !darkMode
    setDarkMode(newMode)
    localStorage.setItem('ecocomfort-theme', newMode ? 'dark' : 'light')
    document.documentElement.classList.toggle('dark', newMode)
  }

  return (
    <div className={`min-h-screen ${darkMode ? 'dark' : ''}`}>
      {/* Background */}
      <div className="fixed inset-0 bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 dark:from-gray-900 dark:via-purple-900 dark:to-gray-900" />
      
      {/* Navigation */}
      <Navigation
        isConnected={isConnected}
        userPoints={userPoints}
        userLevel={userLevel}
        darkMode={darkMode}
        onToggleDarkMode={toggleDarkMode}
        unreadNotifications={unreadNotifications}
        onLogout={onLogout}
      />
      
      {/* Main Content */}
      <main className="relative z-10 lg:ml-64">
        {/* Mobile Header Padding */}
        <div className="lg:hidden h-16" />
        
        {/* Content */}
        <div className="p-4 lg:p-6 pb-20 lg:pb-6">
          {children}
        </div>
        
        {/* Mobile Bottom Navigation Padding */}
        <div className="lg:hidden h-20" />
      </main>
      
      {/* Notification System */}
      <NotificationSystem />
    </div>
  )
}

export default Layout