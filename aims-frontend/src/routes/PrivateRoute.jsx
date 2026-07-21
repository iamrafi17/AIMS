import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function PrivateRoute() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#800000]"></div>
      </div>
    );
  }

  return user ? <Outlet /> : <Navigate to="/login" replace />;
}

export default PrivateRoute;
