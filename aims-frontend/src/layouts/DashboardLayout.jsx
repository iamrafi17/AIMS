import { useCallback, useEffect, useRef, useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../components/layout/Sidebar';
import Header from '../components/layout/Header';
import StudentLiveLocationTracker from '../components/attendance/StudentLiveLocationTracker';

const DOCKED_NAV_QUERY = '(min-width: 1181px), (min-width: 1024px) and (hover: hover) and (pointer: fine)';

function DashboardLayout() {
  const [sidebarDocked, setSidebarDocked] = useState(() => window.matchMedia(DOCKED_NAV_QUERY).matches);
  const [sidebarOpen, setSidebarOpen] = useState(() => window.matchMedia(DOCKED_NAV_QUERY).matches);
  const menuButtonRef = useRef(null);
  const sidebarRef = useRef(null);

  const restoreFocusFromSidebar = useCallback(() => {
    if (sidebarRef.current?.contains(document.activeElement)) {
      menuButtonRef.current?.focus({ preventScroll: true });
    }
  }, []);

  const closeSidebar = useCallback(() => {
    if (!sidebarDocked) restoreFocusFromSidebar();
    setSidebarOpen(false);
  }, [restoreFocusFromSidebar, sidebarDocked]);

  useEffect(() => {
    const dockedNavigation = window.matchMedia(DOCKED_NAV_QUERY);
    const syncSidebar = (event) => {
      if (!event.matches) restoreFocusFromSidebar();
      setSidebarDocked(event.matches);
      setSidebarOpen(event.matches);
    };
    dockedNavigation.addEventListener('change', syncSidebar);
    return () => dockedNavigation.removeEventListener('change', syncSidebar);
  }, [restoreFocusFromSidebar]);

  useEffect(() => {
    const closeWithEscape = (event) => {
      if (event.key === 'Escape' && !sidebarDocked) {
        closeSidebar();
      }
    };
    window.addEventListener('keydown', closeWithEscape);
    return () => window.removeEventListener('keydown', closeWithEscape);
  }, [closeSidebar, sidebarDocked]);

  useEffect(() => {
    if (sidebarDocked || !sidebarOpen) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [sidebarDocked, sidebarOpen]);

  return (
    <div className="dashboard-shell min-h-screen text-[#1F2937] transition-colors duration-300 dark:text-gray-100">
      <StudentLiveLocationTracker />
      <a
        href="#main-content"
        className="sr-only fixed left-4 top-4 z-[100] rounded-xl bg-[#7D001D] px-4 py-3 font-bold text-white shadow-lg focus:not-sr-only"
      >
        Skip to main content
      </a>
      <Sidebar ref={sidebarRef} isOpen={sidebarOpen} isDocked={sidebarDocked} onClose={closeSidebar} />

      <div className={`min-h-screen min-w-0 transition-[margin] duration-300 ease-out ${sidebarOpen && sidebarDocked ? 'ml-64' : 'ml-0'}`}>
        <Header ref={menuButtonRef} sidebarOpen={sidebarOpen} onMenuClick={() => setSidebarOpen((open) => !open)} />

        <main id="main-content" tabIndex="-1" className="min-w-0 px-3 py-4 sm:px-5 sm:py-5 lg:px-7 lg:py-6">
          <div className="portal-page mx-auto w-full min-w-0">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}

export default DashboardLayout;
