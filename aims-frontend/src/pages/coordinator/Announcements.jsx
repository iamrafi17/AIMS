import { useState, useEffect } from 'react';
import api from '../../services/api';
import { BellIcon } from '@heroicons/react/24/outline';

function CoordinatorAnnouncements() {
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAnnouncements();
  }, []);

  const fetchAnnouncements = async () => {
    try {
      const response = await api.get('/announcements');
      setAnnouncements(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch announcements:', error);
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
      <h1 className="text-2xl font-bold text-gray-800">Announcements</h1>

      <div className="space-y-4">
        {announcements.length > 0 ? (
          announcements.map((announcement) => (
            <div key={announcement.id} className="bg-white rounded-xl shadow-sm p-6">
              <div className="flex items-start gap-4">
                <div className="w-10 h-10 bg-[#800000]/10 rounded-lg flex items-center justify-center">
                  <BellIcon className="w-5 h-5 text-[#800000]" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-800">{announcement.title}</h3>
                  <p className="text-sm text-gray-500 mt-1">
                    {new Date(announcement.published_at || announcement.created_at).toLocaleDateString()}
                  </p>
                  <p className="text-gray-600 mt-3">{announcement.content}</p>
                </div>
              </div>
            </div>
          ))
        ) : (
          <div className="bg-white rounded-xl shadow-sm p-6 text-center text-gray-500">
            <BellIcon className="w-12 h-12 mx-auto mb-2 text-gray-300" />
            <p>No announcements yet</p>
          </div>
        )}
      </div>
    </div>
  );
}

export default CoordinatorAnnouncements;
