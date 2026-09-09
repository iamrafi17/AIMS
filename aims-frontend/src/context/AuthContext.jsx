import { useEffect, useState } from 'react';
import api from '../services/api';
import AuthContext from './auth-context';

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
      return response.data;
    } catch {
      localStorage.removeItem('token');
      setUser(null);
      setStudent(null);
      return null;
    } finally {
      setLoading(false);
    }
  };

  const login = async (identifier, password) => {
    const platform = navigator.userAgentData?.platform || navigator.platform || 'Browser';
    const response = await api.post('/login', {
      login: identifier,
      password,
      device_name: `AIMS Web - ${platform}`.slice(0, 100),
    });
    localStorage.setItem('token', response.data.token);
    setUser(response.data.user);
    setStudent(response.data.student);
    return response.data;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch {
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
