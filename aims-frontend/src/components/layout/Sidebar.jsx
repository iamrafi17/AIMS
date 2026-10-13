import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import {
  AcademicCapIcon,
  ArrowRightOnRectangleIcon,
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
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  coordinator: [
    { name: 'Dashboard', path: '/coordinator/dashboard', icon: HomeIcon },
    { name: 'Student Management', path: '/coordinator/students', icon: UsersIcon },
    { name: 'Attendance & Journals', path: '/coordinator/attendance', icon: ClockIcon },
    { name: 'Internship Requirements', path: '/coordinator/internship-requirements', icon: DocumentIcon },
    { name: 'HTE Management', path: '/coordinator/htes', icon: BriefcaseIcon },
    { name: 'MOA Management', path: '/coordinator/moas', icon: DocumentTextIcon },
    { name: 'Travel Monitoring', path: '/coordinator/travel', icon: MapIcon },
    { name: 'Reports', path: '/coordinator/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/coordinator/announcements', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  program_head: [
    { name: 'Dashboard', path: '/program-head/dashboard', icon: HomeIcon },
    { name: 'OJT Setup', path: '/program-head/ojt-setup', icon: AcademicCapIcon },
    { name: 'Documents', path: '/program-head/documents', icon: DocumentTextIcon },
    { name: 'Student Monitoring', path: '/program-head/students', icon: UsersIcon },
    { name: 'Coordinator Assignments', path: '/program-head/coordinator-assignments', icon: AcademicCapIcon },
    { name: 'Travel Monitoring', path: '/program-head/travel', icon: MapIcon },
    { name: 'Reports', path: '/program-head/reports', icon: ChartBarIcon },
    { name: 'Announcements', path: '/program-head/announcements', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  dean: [
    { name: 'Dashboard', path: '/dean/dashboard', icon: HomeIcon },
    { name: 'Live Map', path: '/dean/live-map', icon: MapIcon },
    { name: 'Deployment Approvals', path: '/dean/approvals', icon: CheckCircleIcon },
    { name: 'Student Document Folders', path: '/dean/documents', icon: DocumentIcon },
    { name: 'MOA Final Approval', path: '/dean/moas', icon: DocumentTextIcon },
    { name: 'Travel Validation', path: '/dean/travel', icon: MapIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  vpaa: [
    { name: 'Dashboard', path: '/vpaa/dashboard', icon: HomeIcon },
    { name: 'Student Folder Review', path: '/vpaa/approvals', icon: CheckCircleIcon },
    { name: 'MOA Review', path: '/vpaa/moas', icon: DocumentTextIcon },
    { name: 'Travel Monitoring', path: '/vpaa/travel', icon: MapIcon },
    { name: 'Announcements', path: '/vpaa/announcements', icon: BellIcon },
    { name: 'Reports', path: '/vpaa/reports', icon: ChartBarIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  admin: [
    { name: 'Dashboard', path: '/admin/dashboard', icon: HomeIcon },
    { name: 'Users', path: '/admin/users', icon: UsersIcon },
    { name: 'Academic', path: '/admin/academic', icon: AcademicCapIcon },
    { name: 'System', path: '/admin/system', icon: CogIcon },
    { name: 'Announcements', path: '/admin/announcements', icon: BellIcon },
    { name: 'Reports', path: '/admin/reports', icon: ChartBarIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
  supervisor: [
    { name: 'Dashboard', path: '/supervisor/dashboard', icon: HomeIcon },
    { name: 'Student Progress', path: '/supervisor/progress', icon: ChartBarIcon },
    { name: 'Evaluations', path: '/supervisor/evaluations', icon: StarIcon },
    { name: 'Attendance', path: '/supervisor/attendance', icon: ClockIcon },
    { name: 'Announcements', path: '/supervisor/announcements', icon: BellIcon },
    { name: 'My Activity', path: '/my-activity', icon: ClipboardDocumentCheckIcon },
  ],
};

const roleLabels = {
  student: 'Student Intern',
  coordinator: 'Internship Coordinator',
  program_head: 'Program Head',
  dean: 'Dean',
  vpaa: 'VPAA',
  admin: 'System Administrator',
  supervisor: 'HTE Supervisor',
};

function Sidebar({ ref, isOpen, isDocked, onClose }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const items = menuItems[user?.role] || [];
  const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  const handleNavigate = () => {
    if (!isDocked) onClose();
  };

  const handleLogout = async () => {
    setLoggingOut(true);
    await logout();
    navigate('/login');
  };

  return (
    <>
      <button
        type="button"
        aria-label="Close navigation menu"
        className={`fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm transition-opacity duration-300 ${isOpen && !isDocked ? 'visible opacity-100' : 'invisible opacity-0'}`}
        onClick={onClose}
        tabIndex={isOpen && !isDocked ? 0 : -1}
      />

      <aside
        ref={ref}
        id="portal-sidebar"
        aria-label={`${roleLabels[user?.role] || 'AIMS'} navigation`}
        inert={!isOpen}
        data-docked={isDocked ? 'true' : 'false'}
        className={`portal-sidebar fixed inset-y-0 left-0 z-40 flex w-[min(16rem,calc(100vw-3rem))] flex-col overflow-hidden border-r pb-[env(safe-area-inset-bottom)] text-slate-700 transition-transform duration-300 ease-out dark:text-slate-200 ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}
      >
        <div className="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-700">
          <div className="sidebar-logo grid size-10 shrink-0 place-items-center rounded-full bg-white p-1 ring-1 ring-slate-200">
            <img src={marsuLogo} alt="Marinduque State University seal" className="h-full w-full object-contain" />
          </div>
          <div className="min-w-0 flex-1 leading-tight">
            <p className="text-base font-bold text-[#690016] dark:text-white">AIMS</p>
            <p className="mt-0.5 truncate text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">MarSU · Santa Cruz</p>
          </div>
          <button type="button" onClick={onClose} className={`rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white ${isDocked ? 'hidden' : ''}`} aria-label="Close sidebar">
            <XMarkIcon className="size-5" />
          </button>
        </div>

        <div className="shrink-0 px-4 pb-2 pt-4">
          <p className="px-3 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500">{roleLabels[user?.role] || 'Portal'}</p>
        </div>

        <nav className="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Portal pages">
          <ul className="space-y-1">
            {items.map((item) => (
              <li key={item.path}>
                <NavLink
                  to={item.path}
                  end={item.name === 'Dashboard'}
                  className={({ isActive }) =>
                    `group relative flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ${
                      isActive
                        ? 'bg-[#7D001D]/8 font-semibold text-[#7D001D] dark:bg-rose-400/10 dark:text-rose-200'
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
          <button type="button" onClick={() => setLogoutConfirmOpen(true)} className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-[#7D001D] hover:text-[#7D001D] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
            Sign out
          </button>
        </div>
      </aside>

      {logoutConfirmOpen && (
        <div className="sign-out-backdrop fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-3 backdrop-blur-[3px] sm:p-5" onMouseDown={() => !loggingOut && setLogoutConfirmOpen(false)}>
          <section role="dialog" aria-modal="true" aria-labelledby="sign-out-title" aria-describedby="sign-out-description" onMouseDown={(event) => event.stopPropagation()} className="sign-out-dialog w-full max-w-[22rem] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.24)] dark:border-slate-700 dark:bg-slate-900">
            <div className="p-5">
              <div className="flex items-start gap-3">
                <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-[#7D001D] ring-1 ring-inset ring-[#7D001D]/10 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-300/10">
                  <ArrowRightOnRectangleIcon className="size-5" aria-hidden="true" />
                </div>
                <div className="min-w-0 flex-1 pt-0.5">
                  <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A6A08] dark:text-amber-300">Account session</p>
                  <h2 id="sign-out-title" className="mt-0.5 text-lg font-bold tracking-tight text-slate-900 dark:text-white">Sign out of AIMS?</h2>
                </div>
                <button type="button" onClick={() => setLogoutConfirmOpen(false)} disabled={loggingOut} className="grid size-8 shrink-0 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close sign out dialog">
                  <XMarkIcon className="size-4.5" />
                </button>
              </div>

              <p id="sign-out-description" className="mt-4 text-sm leading-5 text-slate-500 dark:text-slate-300">You’ll need to sign in again to continue.</p>

              <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/70">
                <UserAvatar user={user} className="size-8" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-bold text-slate-800 dark:text-white">{user?.name}</p>
                  <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">{roleLabels[user?.role] || user?.role}</p>
                </div>
              </div>
            </div>

            <div className="flex gap-2 border-t border-slate-200 bg-slate-50/80 p-3.5 dark:border-slate-700 dark:bg-slate-950/30">
              <button type="button" onClick={() => setLogoutConfirmOpen(false)} disabled={loggingOut} className="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-40 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
              <button type="button" onClick={handleLogout} disabled={loggingOut} className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#650018] disabled:cursor-not-allowed disabled:opacity-50"><ArrowRightOnRectangleIcon className="size-4.5" />{loggingOut ? 'Signing out...' : 'Sign out'}</button>
            </div>
          </section>
        </div>
      )}
    </>
  );
}

export default Sidebar;
