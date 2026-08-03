import { Navigate, Outlet } from 'react-router-dom';
import useAuth from '../context/useAuth';

const roleRoutes = {
  student: '/student/dashboard',
  coordinator: '/coordinator/dashboard',
  program_head: '/program-head/dashboard',
  vpaa: '/vpaa/dashboard',
  admin: '/admin/dashboard',
  supervisor: '/supervisor/dashboard',
};

function RoleBasedRoute({ allowedRoles }) {
  const { user } = useAuth();
  if (!allowedRoles.includes(user?.role)) {
    const defaultRoute = roleRoutes[user?.role] || '/login';
    return <Navigate to={defaultRoute} replace />;
  }

  return <Outlet />;
}

export default RoleBasedRoute;
