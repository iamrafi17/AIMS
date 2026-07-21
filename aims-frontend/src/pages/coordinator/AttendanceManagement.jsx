import { useState, useEffect } from 'react';
import api from '../../services/api';
import { ClockIcon, CheckCircleIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';

function CoordinatorAttendance() {
  const [attendance, setAttendance] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAttendance();
  }, []);

  const fetchAttendance = async () => {
    try {
      const response = await api.get('/coordinator/attendance');
      setAttendance(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch attendance:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async (id) => {
    try {
      await api.put(`/coordinator/attendance/${id}/verify`);
      toast.success('Attendance verified!');
      fetchAttendance();
    } catch (error) {
      toast.error('Failed to verify attendance');
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
      <h1 className="text-2xl font-bold text-gray-800">Attendance Management</h1>

      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Student</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Date</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Time In</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Time Out</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Status</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Verified</th>
                <th className="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
              </tr>
            </thead>
            <tbody>
              {attendance.map((record) => (
                <tr key={record.id} className="border-t hover:bg-gray-50">
                  <td className="py-3 px-4">{record.student?.first_name} {record.student?.last_name}</td>
                  <td className="py-3 px-4">{new Date(record.date).toLocaleDateString()}</td>
                  <td className="py-3 px-4">{record.time_in ? new Date(record.time_in).toLocaleTimeString() : '-'}</td>
                  <td className="py-3 px-4">{record.time_out ? new Date(record.time_out).toLocaleTimeString() : '-'}</td>
                  <td className="py-3 px-4">
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      record.status === 'present' ? 'bg-green-100 text-green-800' :
                      record.status === 'late' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {record.status}
                    </span>
                  </td>
                  <td className="py-3 px-4">
                    {record.is_verified ? (
                      <CheckCircleIcon className="w-5 h-5 text-green-500" />
                    ) : (
                      <span className="text-gray-400">Pending</span>
                    )}
                  </td>
                  <td className="py-3 px-4">
                    {!record.is_verified && (
                      <button
                        onClick={() => handleVerify(record.id)}
                        className="px-3 py-1 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 text-sm"
                      >
                        Verify
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

export default CoordinatorAttendance;
