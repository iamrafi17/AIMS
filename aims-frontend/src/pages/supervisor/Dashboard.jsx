import { useState, useEffect } from 'react';
import api from '../../services/api';
import { UsersIcon, CheckCircleIcon, ClockIcon, StarIcon } from '@heroicons/react/24/outline';

function SupervisorDashboard() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/supervisor/dashboard');
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
      <h1 className="text-2xl font-bold text-gray-800">Supervisor Dashboard</h1>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-[#800000]/10 rounded-lg flex items-center justify-center">
              <UsersIcon className="w-6 h-6 text-[#800000]" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Assigned Interns</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.statistics?.total_assigned || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <StarIcon className="w-6 h-6 text-yellow-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending Evaluations</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.statistics?.pending_evaluations || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 card-hover">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <CheckCircleIcon className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Attendance Present</p>
              <p className="text-2xl font-bold text-gray-800">{stats?.attendance_summary?.present || 0}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Assigned Students */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">Assigned Interns</h2>
        {stats?.assigned_students?.length > 0 ? (
          <div className="space-y-3">
            {stats.assigned_students.map((student) => (
              <div key={student.id} className="flex items-center justify-between py-3 border-b last:border-0">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-[#800000] rounded-full flex items-center justify-center">
                    <span className="text-white">{student.first_name.charAt(0)}</span>
                  </div>
                  <div>
                    <p className="font-medium text-gray-800">{student.first_name} {student.last_name}</p>
                    <p className="text-sm text-gray-500">{student.student_id}</p>
                  </div>
                </div>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                  student.internship_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                }`}>
                  {student.internship_status}
                </span>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-gray-500 text-center py-4">No assigned interns</p>
        )}
      </div>
    </div>
  );
}

export default SupervisorDashboard;
