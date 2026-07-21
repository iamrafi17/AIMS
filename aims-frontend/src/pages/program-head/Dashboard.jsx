import { useState, useEffect } from 'react';
import api from '../../services/api';
import { UsersIcon, CheckCircleIcon, ClockIcon } from '@heroicons/react/24/outline';

function ProgramHeadDashboard() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/program-head/dashboard');
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
      <h1 className="text-2xl font-bold text-gray-800">Program Head Dashboard</h1>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-[#800000]/10 rounded-lg flex items-center justify-center">
              <UsersIcon className="w-6 h-6 text-[#800000]" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Total Students</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.statistics?.total_students || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <CheckCircleIcon className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Active Interns</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.statistics?.active_interns || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <ClockIcon className="w-6 h-6 text-yellow-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending Reviews</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.statistics?.pending_reviews || 0}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default ProgramHeadDashboard;
