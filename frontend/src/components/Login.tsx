import { useState } from 'react';
import apiService from '../services/api';

interface LoginProps {
  onLoginSuccess: (token: string, user: any) => void;
  onSwitchToRegister: () => void;
}

export default function Login({ onLoginSuccess, onSwitchToRegister }: LoginProps) {
  const [formData, setFormData] = useState({
    email: '',
    password: ''
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    try {
      const response = await apiService.login(formData.email, formData.password);
      
      if (response.token && response.user) {
        // Store token and user data
        apiService.setAuthToken(response.token);
        localStorage.setItem('user_data', JSON.stringify(response.user));
        
        // Call success callback
        onLoginSuccess(response.token, response.user);
      } else {
        throw new Error('Réponse d\'authentification invalide');
      }
    } catch (err: any) {
      console.error('Login error:', err);
      setError(
        err.message || 
        'Erreur de connexion. Vérifiez vos identifiants.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    // Clear error when user starts typing
    if (error) setError(null);
  };

  const handleDemoLogin = async () => {
    setFormData({
      email: 'admin@ecocomfort.com',
      password: 'EcoAdmin2024!'
    });
    // Trigger form submission after a short delay to show the pre-filled data
    setTimeout(() => {
      const form = document.querySelector('form');
      form?.requestSubmit();
    }, 100);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 p-8">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="flex items-center justify-center mb-4">
            <div className="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
              <span className="text-white font-bold text-xl">🌱</span>
            </div>
          </div>
          <h1 className="text-3xl font-bold text-white mb-2">EcoComfort</h1>
          <p className="text-white/70">Système IoT de Gestion Énergétique</p>
        </div>

        {/* Login Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Email Field */}
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-white/80 mb-2">
              Email
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              required
              disabled={isLoading}
              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:opacity-50"
              placeholder="admin@ecocomfort.com"
            />
          </div>

          {/* Password Field */}
          <div>
            <label htmlFor="password" className="block text-sm font-medium text-white/80 mb-2">
              Mot de passe
            </label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                id="password"
                name="password"
                value={formData.password}
                onChange={handleChange}
                required
                disabled={isLoading}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:opacity-50 pr-12"
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 transform -translate-y-1/2 text-white/60 hover:text-white/80"
                disabled={isLoading}
              >
                {showPassword ? '🙈' : '👁️'}
              </button>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-500/20 border border-red-500/50 rounded-lg p-3">
              <p className="text-red-300 text-sm">{error}</p>
            </div>
          )}

          {/* Submit Button */}
          <button
            type="submit"
            disabled={isLoading}
            className="w-full bg-green-600 hover:bg-green-700 disabled:bg-green-600/50 text-white font-semibold py-3 rounded-lg transition-colors duration-200 flex items-center justify-center"
          >
            {isLoading ? (
              <>
                <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"></div>
                Connexion...
              </>
            ) : (
              'Se connecter'
            )}
          </button>

          {/* Demo Login Button */}
          <button
            type="button"
            onClick={handleDemoLogin}
            disabled={isLoading}
            className="w-full bg-blue-600/50 hover:bg-blue-600/70 disabled:bg-blue-600/30 text-white font-medium py-2 rounded-lg transition-colors duration-200 text-sm"
          >
            🚀 Connexion Admin Demo
          </button>
        </form>

        {/* Register Link */}
        <div className="mt-8 text-center">
          <p className="text-white/60">
            Pas encore de compte ?{' '}
            <button
              onClick={onSwitchToRegister}
              className="text-green-400 hover:text-green-300 font-medium transition-colors duration-200"
              disabled={isLoading}
            >
              S'inscrire
            </button>
          </p>
        </div>

        {/* Footer Info */}
        <div className="mt-8 pt-6 border-t border-white/10 text-center">
          <p className="text-white/50 text-sm">
            💡 Comptes de test disponibles
          </p>
          <div className="text-white/40 text-xs mt-2 space-y-1">
            <p>Admin: admin@ecocomfort.com</p>
            <p>Manager: manager@ecocomfort.com</p>
            <p>User: user@ecocomfort.com</p>
          </div>
        </div>
      </div>
    </div>
  );
}