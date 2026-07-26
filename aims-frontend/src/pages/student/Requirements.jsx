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
    if (!file) return;
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

  const handleDownload = async (requirement) => {
    try {
      const response = await api.get(`/student/requirements/${requirement.id}/download`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = requirement.file_path?.split('/').pop() || `${requirement.requirement_name}.file`;
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error('Unable to download this file.');
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'approved':
        return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
      case 'rejected':
        return <XCircleIcon className="w-5 h-5 text-red-500" />;
      case 'missing':
        return <DocumentIcon className="w-5 h-5 text-gray-400" />;
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
      case 'missing':
        return 'bg-gray-100 text-gray-600';
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
      <div>
        <p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Program checklist</p>
        <h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">Internship Requirements</h1>
        <p className="mt-1 text-sm text-slate-500">Upload the documents configured by your internship coordinator.</p>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        {requirements.length > 0 ? (
          <div className="space-y-4">
            {requirements.map((req) => (
              <div key={req.id} className="rounded-xl border border-slate-200 p-4 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60">
                {(() => {
                  const displayStatus = req.file_path ? req.status : 'missing';
                  return <>
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-4">
                    <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                      <DocumentIcon className="w-5 h-5 text-gray-500" />
                    </div>
                    <div>
                      <h3 className="font-bold text-gray-800 dark:text-white">{req.requirement_name}</h3>
                      <p className="text-sm text-gray-500">
                        {req.file_path ? `Type: ${req.file_type?.toUpperCase() || 'FILE'}` : 'No file uploaded yet'}
                      </p>
                      {req.definition?.instructions && <p className="mt-2 max-w-2xl text-xs leading-5 text-slate-500 dark:text-slate-400">{req.definition.instructions}</p>}
                      {req.feedback && (
                        <p className="text-sm text-red-600 mt-1">
                          Feedback: {req.feedback}
                        </p>
                      )}
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-4">
                    <span className={`px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(displayStatus)}`}>
                      {displayStatus}
                    </span>
                    {getStatusIcon(displayStatus)}
                  </div>
                </div>

                <div className="mt-4 flex items-center gap-4">
                  {req.file_path ? (
                    <button type="button" onClick={() => handleDownload(req)} className="text-sm text-[#800000] hover:text-[#5C0000] font-medium">Download File</button>
                  ) : null}
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
                        {uploading === req.id ? 'Uploading...' : req.file_path ? 'Replace File' : 'Upload File'}
                      </span>
                  </label>
                </div>
                  </>;
                })()}
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
