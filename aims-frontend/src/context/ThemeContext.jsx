import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';

const ThemeContext = createContext(null);

export function ThemeProvider({ children }) {
  const transitionTimer = useRef(null);
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

  useEffect(() => () => {
    if (transitionTimer.current) window.clearTimeout(transitionTimer.current);
  }, []);

  const toggleDarkMode = useCallback(() => {
    const root = document.documentElement;
    root.classList.add('theme-transitioning');
    if (transitionTimer.current) window.clearTimeout(transitionTimer.current);

    setDarkMode((current) => !current);
    transitionTimer.current = window.setTimeout(() => {
      root.classList.remove('theme-transitioning');
      transitionTimer.current = null;
    }, 400);
  }, []);

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
