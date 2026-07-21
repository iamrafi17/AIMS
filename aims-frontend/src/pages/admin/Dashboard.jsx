import { useState, useEffect } from 'react';
import api from '../../services/api';
import { UsersIcon, UserGroupIcon, CheckCircleIcon, ClockIcon } from '@heroicons/react/24/outline';

function AdminDashboard() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/admin/dashboard');
      setStats(response.data);
    } catch (error) {
      console.error('Failed to fetch dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#800000]"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-800">Admin Dashboard</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-[#800000]/10 rounded-lg flex items-center justify-center">
              <UsersIcon className="w-6 h-6 text-[#800000]" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Total Users</p>
              <p className="text-2xl font-bold text-gray-800">
                {Object.values(stats?.user_statistics || {}).reduce((a, b) => a + b, 0)}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <UserGroupIcon className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Active Interns</p>
              <p className="text-2xl font-bold text-gray-800">
                {stats?.student_statistics?.active || 0}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <ClockIcon className="w-6 h-6 text-yellow-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending Registrations</p>
              <p className="text-2xl font-bold text-gray-800">
                {stats?.student_statistics?.pending || 0}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <CheckCircleIcon className="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Completed</p>
              <p className="text-2xl font-bold text-gray-800">
                {stats?.student_statistics?.completed || 0}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* User Statistics */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">Users by Role</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          {Object.entries(stats?.user_statistics || {}).map(([role, count]) => (
            <div key={role} className="text-center p-4 bg-gray-50 rounded-lg">
              <p className="text-2xl font-bold text-[#800000]">{count}</p>
              <p className="text-sm text-gray-500 capitalize">{role.replace('_', ' ')}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h2>
        <div className="space-y-3">
          {stats?.recent_activity?.map((user) => (
            <div key={user.id} className="flex items-center justify-between py-2 border-b last:border-0">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 bg-[#800000] rounded-full flex items-center justify-center">
                  <span className="text-white text-sm">{user.name.charAt(0)}</span>
                </div>
                <div>
                  <p className="font-medium text-gray-800">{user.name}</p>
                  <p className="text-sm text-gray-500">{user.email}</p>
                </div>
              </div>
              <p className="text-sm text-gray-400">
                {user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never'}
              </p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default AdminDashboard;
