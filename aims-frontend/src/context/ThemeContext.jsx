import { useCallback, useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import ThemeContext from './theme-context';

export function ThemeProvider({ children }) {
  const transitionTimer = useRef(null);
  const activeViewTransition = useRef(null);
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
    activeViewTransition.current?.skipTransition?.();
  }, []);

  const toggleDarkMode = useCallback((event) => {
    const root = document.documentElement;
    const nextDarkMode = !root.classList.contains('dark');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const trigger = event?.currentTarget;
    const triggerRect = trigger?.getBoundingClientRect?.();
    const triggeredByKeyboard = event?.detail === 0;
    const originX = triggeredByKeyboard && triggerRect
      ? triggerRect.left + triggerRect.width / 2
      : event?.clientX || triggerRect?.left + triggerRect?.width / 2 || window.innerWidth / 2;
    const originY = triggeredByKeyboard && triggerRect
      ? triggerRect.top + triggerRect.height / 2
      : event?.clientY || triggerRect?.top + triggerRect?.height / 2 || window.innerHeight / 2;
    const revealRadius = Math.hypot(
      Math.max(originX, window.innerWidth - originX),
      Math.max(originY, window.innerHeight - originY),
    );

    const applyTheme = () => {
      root.classList.toggle('dark', nextDarkMode);
      root.style.colorScheme = nextDarkMode ? 'dark' : 'light';
      localStorage.setItem('darkMode', JSON.stringify(nextDarkMode));
      flushSync(() => setDarkMode(nextDarkMode));
    };

    if (transitionTimer.current) window.clearTimeout(transitionTimer.current);
    activeViewTransition.current?.skipTransition?.();

    if (!reduceMotion && typeof document.startViewTransition === 'function') {
      root.classList.remove('theme-transitioning');
      root.style.setProperty('--theme-transition-x', `${originX}px`);
      root.style.setProperty('--theme-transition-y', `${originY}px`);
      root.style.setProperty('--theme-transition-radius', `${revealRadius}px`);

      const transition = document.startViewTransition(applyTheme);
      activeViewTransition.current = transition;
      transition.finished
        .catch(() => {})
        .finally(() => {
          if (activeViewTransition.current === transition) activeViewTransition.current = null;
        });
      return;
    }

    if (reduceMotion) {
      root.classList.remove('theme-transitioning');
      applyTheme();
      return;
    }

    root.classList.add('theme-transitioning');
    applyTheme();
    transitionTimer.current = window.setTimeout(() => {
      root.classList.remove('theme-transitioning');
      transitionTimer.current = null;
    }, 340);
  }, []);

  return (
    <ThemeContext.Provider value={{ darkMode, toggleDarkMode }}>
      {children}
    </ThemeContext.Provider>
  );
}
