import { createContext, useContext, useState, useEffect } from 'react';
import api from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [student, setStudent] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (token) {
      fetchUser();
    } else {
      setLoading(false);
    }
  }, []);

  const fetchUser = async () => {
    try {
      const response = await api.get('/user');
      setUser(response.data.user);
      setStudent(response.data.student);
    } catch (error) {
      localStorage.removeItem('token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (identifier, password) => {
    const response = await api.post('/login', {
      login: identifier,
      password,
      device_name: navigator.userAgent,
    });
    localStorage.setItem('token', response.data.token);
    setUser(response.data.user);
    setStudent(response.data.student);
    return response.data;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch (error) {
      // Ignore logout errors
    } finally {
      localStorage.removeItem('token');
      setUser(null);
      setStudent(null);
    }
  };

  const register = async (data) => {
    const response = await api.post('/register', data);
    return response.data;
  };

  return (
    <AuthContext.Provider value={{ user, student, loading, login, logout, register, refreshUser: fetchUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
