import { createContext, useContext, useState, useEffect } from 'react';

const ThemeContext = createContext(null);

export function ThemeProvider({ children }) {
  const [darkMode, setDarkMode] = useState(() => {
    try {
      const saved = localStorage.getItem('darkMode');
      const enabled = saved === null ? false : JSON.parse(saved) === true;
      document.documentElement.classList.toggle('dark', enabled);
      document.documentElement.style.colorScheme = enabled ? 'dark' : 'light';
      return enabled;
    } catch {
      localStorage.removeItem('darkMode');
      document.documentElement.classList.remove('dark');
      document.documentElement.style.colorScheme = 'light';
      return false;
    }
  });

  useEffect(() => {
    localStorage.setItem('darkMode', JSON.stringify(darkMode));
    document.documentElement.classList.toggle('dark', darkMode);
    document.documentElement.style.colorScheme = darkMode ? 'dark' : 'light';
  }, [darkMode]);

  const toggleDarkMode = () => setDarkMode((current) => !current);

  return (
    <ThemeContext.Provider value={{ darkMode, toggleDarkMode }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}
