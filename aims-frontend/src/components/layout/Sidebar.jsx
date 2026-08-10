import { NavLink, useNavigate } from 'react-router-dom';
import {
  AcademicCapIcon,
  BellIcon,
  BriefcaseIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
  ClockIcon,
  CogIcon,
  DocumentIcon,
  DocumentTextIcon,
  HomeIcon,
  MapIcon,
  StarIcon,
  UserIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import useAuth from '../../context/useAuth';
import marsuLogo from '../../assets/marsu-logo.png';
import UserAvatar from '../UserAvatar';

const menuItems = {
  student: [
    { name: 'Dashboard', path: '/student/dashboard', icon: HomeIcon },
    { name: 'Attendance', path: '/student/attendance', icon: ClockIcon },
    { name: 'Requirements', path: '/student/requirements', icon: DocumentIcon },
    { name: 'Travel Monitoring', path: '/student/travel', icon: MapIcon },
    { name: 'Announcements', path: '/student/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/student/notifications', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/student/profile', icon: UserIcon },
  ],
  coordinator: [
    { name: 'Dashboard', path: '/coordinator/dashboard', icon: HomeIcon },
    { name: 'Student Management', path: '/coordinator/students', icon: UsersIcon },
    { name: 'Attendance & Journals', path: '/coordinator/attendance', icon: ClockIcon },
    { name: 'HTE Management', path: '/coordinator/htes', icon: BriefcaseIcon },
    { name: 'Travel Monitoring', path: '/coordinator/travel', icon: MapIcon },
    { name: 'Reports', path: '/coordinator/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/coordinator/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/coordinator/notifications', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/coordinator/profile', icon: UserIcon },
  ],
  program_head: [
    { name: 'Dashboard', path: '/program-head/dashboard', icon: HomeIcon },
    { name: 'Documents', path: '/program-head/documents', icon: DocumentTextIcon },
    { name: 'Student Monitoring', path: '/program-head/students', icon: UsersIcon },
    { name: 'Travel Monitoring', path: '/program-head/travel', icon: MapIcon },
    { name: 'Reports', path: '/program-head/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/program-head/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/program-head/notifications', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/program-head/profile', icon: UserIcon },
  ],
  vpaa: [
    { name: 'Dashboard', path: '/vpaa/dashboard', icon: HomeIcon },
    { name: 'Approvals', path: '/vpaa/approvals', icon: CheckCircleIcon },
    { name: 'MOAs', path: '/vpaa/moas', icon: DocumentTextIcon },
    { name: 'Travel Monitoring', path: '/vpaa/travel', icon: MapIcon },
    { name: 'Announcements', path: '/vpaa/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/vpaa/notifications', icon: BellIcon },
    { name: 'Reports', path: '/vpaa/reports', icon: ChartBarIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/vpaa/profile', icon: UserIcon },
  ],
  admin: [
    { name: 'Dashboard', path: '/admin/dashboard', icon: HomeIcon },
    { name: 'Users', path: '/admin/users', icon: UsersIcon },
    { name: 'Academic', path: '/admin/academic', icon: AcademicCapIcon },
    { name: 'System', path: '/admin/system', icon: CogIcon },
    { name: 'Announcements', path: '/admin/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/admin/notifications', icon: BellIcon },
    { name: 'Reports', path: '/admin/reports', icon: ChartBarIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/admin/profile', icon: UserIcon },
  ],
  supervisor: [
    { name: 'Dashboard', path: '/supervisor/dashboard', icon: HomeIcon },
    { name: 'Student Progress', path: '/supervisor/progress', icon: ChartBarIcon },
    { name: 'Evaluations', path: '/supervisor/evaluations', icon: StarIcon },
    { name: 'Attendance', path: '/supervisor/attendance', icon: ClockIcon },
    { name: 'Announcements', path: '/supervisor/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/supervisor/notifications', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
    { name: 'Profile', path: '/supervisor/profile', icon: UserIcon },
  ],
};

const roleLabels = {
  student: 'Student Intern',
  coordinator: 'Internship Coordinator',
  program_head: 'Program Head',
  vpaa: 'VPAA',
  admin: 'System Administrator',
  supervisor: 'HTE Supervisor',
};

function Sidebar({ isOpen, onClose }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const items = menuItems[user?.role] || [];

  const handleNavigate = () => {
    if (window.matchMedia('(max-width: 1023px)').matches) onClose();
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <>
      <button
        type="button"
        aria-label="Close navigation menu"
        className={`fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm transition-opacity duration-300 lg:hidden ${isOpen ? 'visible opacity-100' : 'invisible opacity-0'}`}
        onClick={onClose}
        tabIndex={isOpen ? 0 : -1}
      />

      <aside
        aria-label={`${roleLabels[user?.role] || 'AIMS'} navigation`}
        className={`fixed inset-y-0 left-0 z-40 flex w-[min(16rem,calc(100vw-3rem))] flex-col overflow-hidden border-r border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] text-slate-700 shadow-lg shadow-slate-900/5 transition-transform duration-300 ease-out dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}
      >
        <div className="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-700">
          <div className="sidebar-logo grid size-10 shrink-0 place-items-center rounded-full bg-white p-1 ring-1 ring-slate-200">
            <img src={marsuLogo} alt="Marinduque State University seal" className="h-full w-full object-contain" />
          </div>
          <div className="min-w-0 flex-1 leading-tight">
            <p className="text-base font-black text-[#690016] dark:text-white">AIMS</p>
            <p className="mt-0.5 truncate text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">MarSU · Santa Cruz</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white lg:hidden" aria-label="Close sidebar">
            <XMarkIcon className="size-5" />
          </button>
        </div>

        <div className="shrink-0 px-4 pb-2 pt-4">
          <p className="px-3 text-[10px] font-black uppercase text-[#7D001D] dark:text-rose-300">{roleLabels[user?.role] || 'Portal'}</p>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Portal pages">
          <ul className="space-y-1">
            {items.map((item) => (
              <li key={item.path}>
                <NavLink
                  to={item.path}
                  end={item.name === 'Dashboard'}
                  className={({ isActive }) =>
                    `group relative flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-150 ${
                      isActive
                        ? 'bg-[#7D001D]/10 text-[#7D001D] before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-r-full before:bg-[#7D001D] dark:bg-rose-400/10 dark:text-rose-200 dark:before:bg-rose-300'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                    }`
                  }
                  onClick={handleNavigate}
                >
                  {({ isActive }) => (
                    <>
                      <item.icon className={`size-5 shrink-0 ${isActive ? 'text-[#7D001D] dark:text-rose-200' : 'text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-white'}`} />
                      <span className="min-w-0 flex-1 truncate">{item.name}</span>
                    </>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="shrink-0 border-t border-slate-200 p-3 dark:border-slate-700">
          <div className="mb-2 flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
            <UserAvatar user={user} className="size-10" />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-bold text-slate-900 dark:text-white">{user?.name}</p>
              <p className="mt-0.5 truncate text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">{roleLabels[user?.role] || user?.role}</p>
            </div>
          </div>
          <button type="button" onClick={handleLogout} className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-[#7D001D] hover:text-[#7D001D] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
            Sign out
          </button>
        </div>
      </aside>
    </>
  );
}

export default Sidebar;
