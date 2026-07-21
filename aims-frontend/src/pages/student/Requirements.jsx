import { useState, useEffect } from 'react';
import api from '../../services/api';
import {
  DocumentIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';

function StudentRequirements() {
  const [requirements, setRequirements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(null);

  useEffect(() => {
    fetchRequirements();
  }, []);

  const fetchRequirements = async () => {
    try {
      const response = await api.get('/student/requirements');
      setRequirements(response.data);
    } catch (error) {
      console.error('Failed to fetch requirements:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleUpload = async (requirementId, file) => {
    setUploading(requirementId);
    try {
      const formData = new FormData();
      formData.append('file', file);
      
      await api.post(`/student/requirements/${requirementId}/upload`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('File uploaded successfully!');
      fetchRequirements();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Upload failed');
    } finally {
      setUploading(null);
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'approved':
        return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
      case 'rejected':
        return <XCircleIcon className="w-5 h-5 text-red-500" />;
      default:
        return <ClockIcon className="w-5 h-5 text-yellow-500" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'approved':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-yellow-100 text-yellow-800';
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
      <h1 className="text-2xl font-bold text-gray-800">Internship Requirements</h1>

      <div className="bg-white rounded-xl shadow-sm p-6">
        {requirements.length > 0 ? (
          <div className="space-y-4">
            {requirements.map((req) => (
              <div key={req.id} className="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-4">
                    <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                      <DocumentIcon className="w-5 h-5 text-gray-500" />
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-800">{req.requirement_name}</h3>
                      <p className="text-sm text-gray-500">
                        Type: {req.file_type.toUpperCase()}
                      </p>
                      {req.feedback && (
                        <p className="text-sm text-red-600 mt-1">
                          Feedback: {req.feedback}
                        </p>
                      )}
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-4">
                    <span className={`px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(req.status)}`}>
                      {req.status}
                    </span>
                    {getStatusIcon(req.status)}
                  </div>
                </div>

                <div className="mt-4 flex items-center gap-4">
                  {req.file_path ? (
                    <a
                      href={`/api/student/requirements/${req.id}/download`}
                      className="text-sm text-[#800000] hover:text-[#5C0000] font-medium"
                    >
                      Download File
                    </a>
                  ) : (
                    <label className="cursor-pointer">
                      <input
                        type="file"
                        className="hidden"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        onChange={(e) => handleUpload(req.id, e.target.files[0])}
                        disabled={uploading === req.id}
                      />
                      <span className={`inline-block px-4 py-2 bg-[#800000] hover:bg-[#5C0000] text-white text-sm font-medium rounded-lg transition-colors ${
                        uploading === req.id ? 'opacity-70 cursor-not-allowed' : ''
                      }`}>
                        {uploading === req.id ? 'Uploading...' : 'Upload File'}
                      </span>
                    </label>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8 text-gray-500">
            <DocumentIcon className="w-12 h-12 mx-auto mb-2 text-gray-300" />
            <p>No requirements found</p>
          </div>
        )}
      </div>
    </div>
  );
}

export default StudentRequirements;
