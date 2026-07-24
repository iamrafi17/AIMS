import { useLocation } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Bars3Icon, BellIcon, SunIcon, MoonIcon } from '@heroicons/react/24/outline';
import UserAvatar from '../UserAvatar';

const pageNames = {
  dashboard: 'Dashboard',
  students: 'Student Management',
  attendance: 'Attendance & Journals',
  htes: 'HTE Management',
  travel: 'Travel Monitoring',
  reports: 'Reports',
  announcements: 'Announcements',
  requirements: 'Requirements',
  profile: 'Profile',
};

function Header({ onMenuClick, sidebarOpen }) {
  const { user } = useAuth();
  const { darkMode, toggleDarkMode } = useTheme();
  const location = useLocation();
  const pageKey = location.pathname.split('/').filter(Boolean).at(-1);
  const pageTitle = pageNames[pageKey] || 'AIMS Portal';

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 shadow-sm backdrop-blur-xl dark:border-gray-700 dark:bg-gray-800/90">
      <div className="flex min-h-20 items-center justify-between gap-3 px-4 sm:px-6">
        {/* Left side */}
        <div className="flex items-center gap-4">
          <button
            onClick={onMenuClick}
            className="rounded-xl p-2.5 transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
            aria-label={sidebarOpen ? 'Hide sidebar' : 'Show sidebar'}
            aria-expanded={sidebarOpen}
          >
            <Bars3Icon className="w-6 h-6 text-gray-600 dark:text-gray-300" />
          </button>
          <div>
            <h2 className="text-base font-black text-gray-800 sm:text-lg dark:text-white">{pageTitle}</h2>
            <p className="hidden text-xs text-gray-500 sm:block dark:text-gray-400">Welcome back, {user?.name}</p>
          </div>
        </div>

        {/* Right side */}
        <div className="flex items-center gap-1.5 sm:gap-3">
          {/* Dark mode toggle */}
          <button
            onClick={toggleDarkMode}
            className="relative grid h-10 w-10 place-items-center overflow-hidden rounded-xl border border-transparent hover:border-gray-200 hover:bg-gray-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#800000]/15 dark:hover:border-gray-600 dark:hover:bg-gray-700"
            aria-label={darkMode ? 'Switch to light mode' : 'Switch to dark mode'}
            title={darkMode ? 'Light mode' : 'Dark mode'}
            aria-pressed={darkMode}
          >
            <SunIcon
              className={`absolute h-6 w-6 text-yellow-500 transition-all duration-300 ease-out ${
                darkMode ? 'rotate-0 scale-100 opacity-100' : '-rotate-90 scale-50 opacity-0'
              }`}
            />
            <MoonIcon
              className={`absolute h-6 w-6 text-gray-600 transition-all duration-300 ease-out dark:text-blue-200 ${
                darkMode ? 'rotate-90 scale-50 opacity-0' : 'rotate-0 scale-100 opacity-100'
              }`}
            />
          </button>

          {/* Notifications */}
          <button className="relative p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
            <BellIcon className="w-6 h-6 text-gray-600 dark:text-gray-300" />
            <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
          </button>

          {/* User avatar */}
          <div className="flex items-center gap-3"><UserAvatar user={user} /></div>
        </div>
      </div>
    </header>
  );
}

export default Header;
