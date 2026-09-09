import { Navigate, Outlet, useLocation } from 'react-router-dom';
import useAuth from '../context/useAuth';

const roleRoutes = {
  student: '/student/dashboard',
  coordinator: '/coordinator/dashboard',
  program_head: '/program-head/dashboard',
  vpaa: '/vpaa/dashboard',
  admin: '/admin/dashboard',
  supervisor: '/supervisor/dashboard',
};

function PrivateRoute() {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7D001D]"></div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  const requiresOnboarding = Boolean(
    user.onboarding_step
    || user.requires_password_change
    || user.requires_security_setup
    || user.requires_profile_setup
  );

  if (requiresOnboarding && location.pathname !== '/staff/onboarding') {
    return <Navigate to="/staff/onboarding" replace />;
  }

  if (!requiresOnboarding && location.pathname === '/staff/onboarding') {
    return <Navigate to={roleRoutes[user.role] || '/'} replace />;
  }

  return <Outlet />;
}

export default PrivateRoute;
