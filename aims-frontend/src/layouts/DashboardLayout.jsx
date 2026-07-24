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
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      <div className={`min-h-screen transition-[margin] duration-300 ease-out ${sidebarOpen ? 'lg:ml-64' : 'lg:ml-0'}`}>
        <Header sidebarOpen={sidebarOpen} onMenuClick={() => setSidebarOpen((open) => !open)} />

        <main className="p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

export default DashboardLayout;
