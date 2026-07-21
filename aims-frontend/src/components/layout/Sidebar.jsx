import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import UserAvatar from '../UserAvatar';
import {
  HomeIcon,
  ClockIcon,
  DocumentIcon,
  MapIcon,
  BellIcon,
  UserIcon,
  UsersIcon,
  AcademicCapIcon,
  BriefcaseIcon,
  ChartBarIcon,
  CogIcon,
  ClipboardDocumentCheckIcon,
  StarIcon,
  DocumentTextIcon,
  CheckCircleIcon,
} from '@heroicons/react/24/outline';

const menuItems = {
  student: [
    { name: 'Dashboard', path: '/student/dashboard', icon: HomeIcon },
    { name: 'Attendance', path: '/student/attendance', icon: ClockIcon },
    { name: 'Requirements', path: '/student/requirements', icon: DocumentIcon },
    { name: 'Travel', path: '/student/travel', icon: MapIcon },
    { name: 'Announcements', path: '/student/announcements', icon: BellIcon },
    { name: 'Profile', path: '/student/profile', icon: UserIcon },
  ],
  coordinator: [
    { name: 'Dashboard', path: '/coordinator/dashboard', icon: HomeIcon },
    { name: 'Students', path: '/coordinator/students', icon: UsersIcon },
    { name: 'Attendance', path: '/coordinator/attendance', icon: ClockIcon },
    { name: 'HTEs', path: '/coordinator/htes', icon: BriefcaseIcon },
    { name: 'Travel', path: '/coordinator/travel', icon: MapIcon },
    { name: 'Reports', path: '/coordinator/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/coordinator/announcements', icon: BellIcon },
  ],
  program_head: [
    { name: 'Dashboard', path: '/program-head/dashboard', icon: HomeIcon },
    { name: 'Documents', path: '/program-head/documents', icon: DocumentTextIcon },
    { name: 'Travel', path: '/program-head/travel', icon: MapIcon },
    { name: 'Reports', path: '/program-head/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/program-head/announcements', icon: BellIcon },
  ],
  vpaa: [
    { name: 'Dashboard', path: '/vpaa/dashboard', icon: HomeIcon },
    { name: 'Approvals', path: '/vpaa/approvals', icon: CheckCircleIcon },
    { name: 'MOAs', path: '/vpaa/moas', icon: DocumentTextIcon },
    { name: 'Announcements', path: '/vpaa/announcements', icon: BellIcon },
    { name: 'Reports', path: '/vpaa/reports', icon: ChartBarIcon },
  ],
  admin: [
    { name: 'Dashboard', path: '/admin/dashboard', icon: HomeIcon },
    { name: 'Users', path: '/admin/users', icon: UsersIcon },
    { name: 'Academic', path: '/admin/academic', icon: AcademicCapIcon },
    { name: 'System', path: '/admin/system', icon: CogIcon },
    { name: 'Audit Logs', path: '/admin/audit', icon: ClipboardDocumentCheckIcon },
    { name: 'Reports', path: '/admin/reports', icon: ChartBarIcon },
  ],
  supervisor: [
    { name: 'Dashboard', path: '/supervisor/dashboard', icon: HomeIcon },
    { name: 'Progress', path: '/supervisor/progress', icon: ChartBarIcon },
    { name: 'Evaluations', path: '/supervisor/evaluations', icon: StarIcon },
    { name: 'Attendance', path: '/supervisor/attendance', icon: ClockIcon },
    { name: 'Announcements', path: '/supervisor/announcements', icon: BellIcon },
  ],
};

function Sidebar({ isOpen, onClose }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const items = menuItems[user?.role] || [];

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <>
      {/* Overlay for mobile */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed top-0 left-0 h-full w-64 bg-gradient-to-b from-[#800000] to-[#5C0000] text-white z-30 transition-transform duration-300 ${
          isOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Logo */}
        <div className="p-4 border-b border-white/20">
          <h1 className="text-xl font-bold">AIMS</h1>
          <p className="text-xs text-white/70">MarSU Santa Cruz Campus</p>
        </div>

        {/* Navigation */}
        <nav className="p-4 overflow-y-auto h-[calc(100%-180px)]">
          <ul className="space-y-2">
            {items.map((item) => (
              <li key={item.path}>
                <NavLink
                  to={item.path}
                  className={({ isActive }) =>
                    `flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                      isActive
                        ? 'bg-white text-[#800000]'
                        : 'text-white hover:bg-white/10'
                    }`
                  }
                  onClick={onClose}
                >
                  <item.icon className="w-5 h-5" />
                  <span>{item.name}</span>
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        {/* User Info & Logout */}
        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-white/20">
          <div className="flex items-center gap-3 mb-3">
            <UserAvatar user={user} />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate">{user?.name}</p>
              <p className="text-xs text-white/70 capitalize">{user?.role?.replace('_', ' ')}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm transition-colors"
          >
            Logout
          </button>
        </div>
      </aside>
    </>
  );
}

export default Sidebar;
