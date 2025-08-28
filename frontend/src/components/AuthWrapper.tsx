import { useState } from 'react';
import Login from './Login';
import Register from './Register';

interface AuthWrapperProps {
  onAuthSuccess: (token: string, user: any) => void;
}

export default function AuthWrapper({ onAuthSuccess }: AuthWrapperProps) {
  const [isLoginMode, setIsLoginMode] = useState(true);

  const handleLoginSuccess = (token: string, user: any) => {
    onAuthSuccess(token, user);
  };

  const handleRegisterSuccess = (token: string, user: any) => {
    onAuthSuccess(token, user);
  };

  return (
    <>
      {isLoginMode ? (
        <Login
          onLoginSuccess={handleLoginSuccess}
          onSwitchToRegister={() => setIsLoginMode(false)}
        />
      ) : (
        <Register
          onRegisterSuccess={handleRegisterSuccess}
          onSwitchToLogin={() => setIsLoginMode(true)}
        />
      )}
    </>
  );
}