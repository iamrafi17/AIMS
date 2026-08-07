import { useEffect, useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../components/layout/Sidebar';
import Header from '../components/layout/Header';

function DashboardLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(() => window.matchMedia('(min-width: 1024px)').matches);

  useEffect(() => {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const syncSidebar = (event) => setSidebarOpen(event.matches);
    desktop.addEventListener('change', syncSidebar);
    return () => desktop.removeEventListener('change', syncSidebar);
  }, []);

  useEffect(() => {
    const closeWithEscape = (event) => {
      if (event.key === 'Escape' && window.matchMedia('(max-width: 1023px)').matches) {
        setSidebarOpen(false);
      }
    };
    window.addEventListener('keydown', closeWithEscape);
    return () => window.removeEventListener('keydown', closeWithEscape);
  }, []);

  return (
    <div className="dashboard-shell min-h-screen bg-gray-100 text-gray-800 transition-colors duration-300 dark:bg-gray-950 dark:text-gray-100">
      <a
        href="#main-content"
        className="sr-only fixed left-4 top-4 z-[100] rounded-xl bg-[#800000] px-4 py-3 font-bold text-white shadow-lg focus:not-sr-only"
      >
        Skip to main content
      </a>
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      <div className={`min-h-screen min-w-0 transition-[margin] duration-300 ease-out ${sidebarOpen ? 'lg:ml-64' : 'lg:ml-0'}`}>
        <Header sidebarOpen={sidebarOpen} onMenuClick={() => setSidebarOpen((open) => !open)} />

        <main id="main-content" tabIndex="-1" className="min-w-0 p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

export default DashboardLayout;
