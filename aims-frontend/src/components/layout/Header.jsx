import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import api from '../../services/api';
import useAuth from '../../context/useAuth';
import useTheme from '../../context/useTheme';
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
  notifications: 'Notifications',
};

function Header({ onMenuClick, sidebarOpen }) {
  const { user } = useAuth();
  const { darkMode, toggleDarkMode } = useTheme();
  const location = useLocation();
  const navigate = useNavigate();
  const [unread, setUnread] = useState(0);
  const pageKey = location.pathname.split('/').filter(Boolean).at(-1);
  const pageTitle = pageNames[pageKey] || 'AIMS Portal';
  const rolePath = user?.role === 'program_head' ? 'program-head' : user?.role;

  useEffect(() => {
    if (!user) return;
    api.get('/notifications', { params: { unread: 1 } })
      .then((response) => setUnread(response.data.unread_count || 0))
      .catch(() => {});
  }, [location.pathname, user]);

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
          <button onClick={() => navigate(`/${rolePath}/notifications`)} className="relative p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" aria-label={`${unread} unread notifications`}>
            <BellIcon className="w-6 h-6 text-gray-600 dark:text-gray-300" />
            {unread > 0 && <span className="absolute right-0 top-0 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-black text-white">{unread > 9 ? '9+' : unread}</span>}
          </button>

          {/* User avatar */}
          <div className="flex items-center gap-3"><UserAvatar user={user} /></div>
        </div>
      </div>
    </header>
  );
}

export default Header;
