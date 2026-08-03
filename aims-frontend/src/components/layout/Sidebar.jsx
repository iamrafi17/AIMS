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
    { name: 'Profile', path: '/vpaa/profile', icon: UserIcon },
  ],
  admin: [
    { name: 'Dashboard', path: '/admin/dashboard', icon: HomeIcon },
    { name: 'Users', path: '/admin/users', icon: UsersIcon },
    { name: 'Academic', path: '/admin/academic', icon: AcademicCapIcon },
    { name: 'System', path: '/admin/system', icon: CogIcon },
    { name: 'Audit Logs', path: '/admin/audit', icon: ClipboardDocumentCheckIcon },
    { name: 'Announcements', path: '/admin/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/admin/notifications', icon: BellIcon },
    { name: 'Reports', path: '/admin/reports', icon: ChartBarIcon },
    { name: 'Profile', path: '/admin/profile', icon: UserIcon },
  ],
  supervisor: [
    { name: 'Dashboard', path: '/supervisor/dashboard', icon: HomeIcon },
    { name: 'Student Progress', path: '/supervisor/progress', icon: ChartBarIcon },
    { name: 'Evaluations', path: '/supervisor/evaluations', icon: StarIcon },
    { name: 'Attendance', path: '/supervisor/attendance', icon: ClockIcon },
    { name: 'Announcements', path: '/supervisor/announcements', icon: BellIcon },
    { name: 'Notifications', path: '/supervisor/notifications', icon: BellIcon },
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
        className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-hidden bg-gradient-to-b from-[#800000] via-[#720000] to-[#4b0000] text-white shadow-2xl shadow-[#3b0808]/20 transition-transform duration-300 ease-out ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}
      >
        <div className="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-4">
          <div className="grid size-12 shrink-0 place-items-center rounded-full bg-white p-1 shadow-lg">
            <img src={marsuLogo} alt="Marinduque State University seal" className="h-full w-full object-contain" />
          </div>
          <div className="min-w-0 flex-1 leading-tight">
            <p className="text-lg font-black tracking-tight">AIMS</p>
            <p className="mt-0.5 truncate text-[10px] font-semibold uppercase tracking-[0.12em] text-white/60">MarSU · Santa Cruz</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-white/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close sidebar">
            <XMarkIcon className="size-5" />
          </button>
        </div>

        <div className="shrink-0 px-4 pb-2 pt-5">
          <p className="px-3 text-[10px] font-black uppercase tracking-[0.18em] text-[#f2cf70]">{roleLabels[user?.role] || 'Portal'}</p>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Portal pages">
          <ul className="space-y-1.5">
            {items.map((item) => (
              <li key={item.path}>
                <NavLink
                  to={item.path}
                  end={item.name === 'Dashboard'}
                  className={({ isActive }) =>
                    `group relative flex min-h-11 items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-bold transition-all duration-200 ${
                      isActive
                        ? 'bg-white text-[#700000] shadow-lg shadow-black/10'
                        : 'text-white/75 hover:translate-x-0.5 hover:bg-white/10 hover:text-white'
                    }`
                  }
                  onClick={handleNavigate}
                >
                  {({ isActive }) => (
                    <>
                      <item.icon className={`size-5 shrink-0 ${isActive ? 'text-[#800000]' : 'text-white/65 group-hover:text-white'}`} />
                      <span className="min-w-0 flex-1 truncate">{item.name}</span>
                      {isActive && <span className="size-1.5 shrink-0 rounded-full bg-[#d4af37]" />}
                    </>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="shrink-0 border-t border-white/10 bg-black/10 p-3">
          <div className="mb-3 flex items-center gap-3 rounded-xl bg-white/[0.07] p-3">
            <UserAvatar user={user} className="size-10" />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-black">{user?.name}</p>
              <p className="mt-0.5 truncate text-[10px] font-semibold uppercase tracking-wide text-white/55">{roleLabels[user?.role] || user?.role}</p>
            </div>
          </div>
          <button type="button" onClick={handleLogout} className="w-full rounded-xl border border-white/10 px-4 py-2.5 text-xs font-black text-white/80 transition hover:bg-white/10 hover:text-white">
            Sign out
          </button>
        </div>
      </aside>
    </>
  );
}

export default Sidebar;
