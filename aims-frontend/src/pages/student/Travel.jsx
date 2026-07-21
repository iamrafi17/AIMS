import { useState, useEffect } from 'react';
import api from '../../services/api';
import { MapIcon } from '@heroicons/react/24/outline';

function StudentTravel() {
  const [travelLogs, setTravelLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchTravelLogs();
  }, []);

  const fetchTravelLogs = async () => {
    try {
      const response = await api.get('/student/travel');
      setTravelLogs(response.data);
    } catch (error) {
      console.error('Failed to fetch travel logs:', error);
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
      <h1 className="text-2xl font-bold text-gray-800">Travel Monitoring</h1>

      <div className="bg-white rounded-xl shadow-sm p-6">
        {travelLogs.length > 0 ? (
          <div className="space-y-4">
            {travelLogs.map((log) => (
              <div key={log.id} className="border rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-medium text-gray-800">Session: {log.session_code}</h3>
                    <p className="text-sm text-gray-500">
                      Started: {new Date(log.start_time).toLocaleString()}
                    </p>
                    {log.end_time && (
                      <p className="text-sm text-gray-500">
                        Ended: {new Date(log.end_time).toLocaleString()}
                      </p>
                    )}
                  </div>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                    log.status === 'active' ? 'bg-green-100 text-green-800' :
                    log.status === 'completed' ? 'bg-blue-100 text-blue-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {log.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8 text-gray-500">
            <MapIcon className="w-12 h-12 mx-auto mb-2 text-gray-300" />
            <p>No travel logs found</p>
          </div>
        )}
      </div>
    </div>
  );
}

export default StudentTravel;
