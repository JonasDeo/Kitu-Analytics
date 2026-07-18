import React, { createContext, useContext, useState, useEffect } from 'react';
import { me } from '../api/auth';

interface User {
  id: number;
  name: string;
  phone: string;
  role: string;
  is_verified: boolean;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  setToken: (token: string) => void;
  logout: () => void;
  loading: boolean;
}

const AuthContext = createContext<AuthContextType>({} as AuthContextType);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setTokenState] = useState<string | null>(localStorage.getItem('kitu_token'));
  const [loading, setLoading] = useState(true);

  const setToken = (newToken: string) => {
    localStorage.setItem('kitu_token', newToken);
    setTokenState(newToken);
  };

  const logout = () => {
    localStorage.removeItem('kitu_token');
    setTokenState(null);
    setUser(null);
  };

  useEffect(() => {
    if (token) {
      me()
        .then((res) => setUser(res.data))
        .catch(() => logout())
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [token]);

  return (
    <AuthContext.Provider value={{ user, token, setToken, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);