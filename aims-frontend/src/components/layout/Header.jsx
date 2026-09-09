import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '../../services/api';
import useAuth from '../../context/useAuth';
import useTheme from '../../context/useTheme';
import { ArrowRightIcon, Bars3Icon, BellIcon, MoonIcon, SunIcon } from '@heroicons/react/24/outline';
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

function formatNotificationTime(value) {
  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

function Header({ onMenuClick, sidebarOpen }) {
  const { user } = useAuth();
  const { darkMode, toggleDarkMode } = useTheme();
  const location = useLocation();
  const navigate = useNavigate();
  const [unread, setUnread] = useState(0);
  const [notifications, setNotifications] = useState([]);
  const [notificationMenuOpen, setNotificationMenuOpen] = useState(false);
  const notificationMenuRef = useRef(null);
  const pageKey = location.pathname.split('/').filter(Boolean).at(-1);
  const pageTitle = pageNames[pageKey] || 'AIMS Portal';
  const rolePath = user?.role === 'program_head' ? 'program-head' : user?.role;

  const loadNotifications = useCallback(async () => {
    if (!user) {
      setUnread(0);
      setNotifications([]);
      return;
    }

    try {
      const response = await api.get('/notifications', { params: { unread: 1 } });
      setUnread(response.data.unread_count || 0);
      setNotifications((response.data.notifications?.data || []).slice(0, 5));
    } catch {
      // The page-level notification screen will display the request error if needed.
    }
  }, [user]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications, location.pathname]);

  useEffect(() => {
    if (!notificationMenuOpen) return undefined;

    const closeOnEscape = (event) => {
      if (event.key === 'Escape') setNotificationMenuOpen(false);
    };
    const closeOnOutsideClick = (event) => {
      if (!notificationMenuRef.current?.contains(event.target)) setNotificationMenuOpen(false);
    };

    document.addEventListener('keydown', closeOnEscape);
    document.addEventListener('mousedown', closeOnOutsideClick);
    return () => {
      document.removeEventListener('keydown', closeOnEscape);
      document.removeEventListener('mousedown', closeOnOutsideClick);
    };
  }, [notificationMenuOpen]);

  const openNotification = async (notification) => {
    if (!notification.read_at) {
      try {
        await api.put(`/notifications/${notification.id}/read`);
        setUnread((count) => Math.max(0, count - 1));
        setNotifications((items) => items.filter((item) => item.id !== notification.id));
      } catch {
        toast.error('Unable to update notification.');
        return;
      }
    }

    setNotificationMenuOpen(false);
    navigate(notification.action_url || `/${rolePath}/notifications`);
  };

  const openNotificationsPage = () => {
    setNotificationMenuOpen(false);
    navigate(`/${rolePath}/notifications`);
  };

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur-md dark:border-slate-700 dark:bg-slate-900/95">
      <div className="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-2 px-3 sm:gap-3 sm:px-5 lg:px-6">
        {/* Left side */}
        <div className="flex min-w-0 items-center gap-2 sm:gap-4">
          <button
            onClick={onMenuClick}
            className="grid h-10 w-10 shrink-0 place-items-center rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
            aria-label={sidebarOpen ? 'Hide sidebar' : 'Show sidebar'}
            aria-expanded={sidebarOpen}
          >
            <Bars3Icon className="w-6 h-6 text-gray-600 dark:text-gray-300" />
          </button>
          <div className="min-w-0">
            <h2 className="truncate text-base font-bold text-slate-800 dark:text-white">{pageTitle}</h2>
            <p className="hidden text-xs text-slate-500 md:block dark:text-slate-400">Welcome back, {user?.name}</p>
          </div>
        </div>

        {/* Right side */}
        <div className="flex items-center gap-1.5 sm:gap-3">
          {/* Dark mode toggle */}
          <button
            onClick={toggleDarkMode}
            className="relative grid h-10 w-10 place-items-center overflow-hidden rounded-lg border border-transparent hover:border-slate-200 hover:bg-slate-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#7D001D]/15 dark:hover:border-slate-700 dark:hover:bg-slate-800"
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

          <div className="relative" ref={notificationMenuRef}>
            <button
              type="button"
              onClick={() => {
                if (!notificationMenuOpen) loadNotifications();
                setNotificationMenuOpen((open) => !open);
              }}
              className="relative grid h-10 w-10 place-items-center rounded-lg transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label={`${unread} unread notifications`}
              aria-expanded={notificationMenuOpen}
              aria-controls="notification-preview"
              title="Notifications"
            >
              <BellIcon className="h-6 w-6 text-gray-600 dark:text-gray-300" />
              {unread > 0 && <span className="absolute right-0 top-0 grid min-h-5 min-w-5 place-items-center rounded-full bg-[#7D001D] px-1 text-[10px] font-black text-white">{unread > 9 ? '9+' : unread}</span>}
            </button>

            {notificationMenuOpen && (
              <section
                id="notification-preview"
                aria-label="Latest unread notifications"
                className="absolute right-0 top-[calc(100%+0.75rem)] z-50 w-[min(23rem,calc(100vw-1.5rem))] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
              >
                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                  <div>
                    <p className="text-sm font-bold text-slate-900 dark:text-white">Notifications</p>
                    <p className="text-xs text-slate-500">{unread ? `${unread} unread` : 'You are all caught up'}</p>
                  </div>
                  <button type="button" onClick={openNotificationsPage} className="text-xs font-bold text-[#7D001D] hover:underline dark:text-red-300">
                    View all
                  </button>
                </div>

                {notifications.length > 0 ? (
                  <div className="max-h-96 overflow-y-auto py-1">
                    {notifications.map((notification) => (
                      <button
                        key={notification.id}
                        type="button"
                        onClick={() => openNotification(notification)}
                        className="w-full border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 last:border-b-0 dark:border-slate-800 dark:hover:bg-slate-800/70"
                      >
                        <div className="flex items-start gap-3">
                          <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#7D001D]" aria-hidden="true" />
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold text-slate-900 dark:text-white">{notification.title}</p>
                            <p className="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-600 dark:text-slate-300">{notification.message}</p>
                            <p className="mt-1 text-xs text-slate-400">{formatNotificationTime(notification.created_at)}</p>
                          </div>
                        </div>
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="px-4 py-8 text-center">
                    <BellIcon className="mx-auto h-7 w-7 text-slate-300 dark:text-slate-600" />
                    <p className="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200">No new notifications</p>
                  </div>
                )}

                <button type="button" onClick={openNotificationsPage} className="flex w-full items-center justify-center gap-2 border-t border-slate-200 px-4 py-3 text-sm font-bold text-[#7D001D] transition hover:bg-slate-50 dark:border-slate-700 dark:text-red-300 dark:hover:bg-slate-800">
                  View all notifications
                  <ArrowRightIcon className="h-4 w-4" />
                </button>
              </section>
            )}
          </div>

          {/* User avatar */}
          <button
            type="button"
            onClick={() => rolePath && navigate(`/${rolePath}/profile`)}
            className="rounded-full transition hover:ring-4 hover:ring-[#7D001D]/15 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#7D001D]/25"
            aria-label="Open profile"
            title="Open profile"
          >
            <UserAvatar user={user} />
          </button>
        </div>
      </div>
    </header>
  );
}

export default Header;
