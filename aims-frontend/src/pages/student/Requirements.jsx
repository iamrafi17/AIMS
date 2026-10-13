import { useState, useEffect, useRef, useCallback, lazy, Suspense } from 'react';
import api from '../../services/api';
import {
  DocumentIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';

const PdfPages = lazy(() => import('../../components/PdfPages'));

function StudentRequirements() {
  const [requirements, setRequirements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(null);
  const [selectedRequirement, setSelectedRequirement] = useState(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [fileError, setFileError] = useState('');
  const [preview, setPreview] = useState(null);
  const [removeTarget, setRemoveTarget] = useState(null);
  const [removing, setRemoving] = useState(false);
  const fileInputRef = useRef(null);
  const chooseFileButtonRef = useRef(null);
  const dialogRef = useRef(null);
  const modalTriggerRef = useRef(null);
  const previewDialogRef = useRef(null);
  const previewCancelRef = useRef(null);
  const previewTriggerRef = useRef(null);
  const previewAbortRef = useRef(null);
  const removeTriggerRef = useRef(null);
  const removeCancelRef = useRef(null);
  const removeDialogRef = useRef(null);

  const closeUploadModal = useCallback(() => {
    if (uploading !== null || preview) return;
    setSelectedRequirement(null);
    setSelectedFile(null);
    setFileError('');
    modalTriggerRef.current?.focus();
  }, [uploading, preview]);

  const closePreview = useCallback(() => {
    previewAbortRef.current?.abort();
    previewAbortRef.current = null;
    setPreview(null);
    previewTriggerRef.current?.focus();
  }, []);

  useEffect(() => {
    fetchRequirements();
  }, []);

  useEffect(() => {
    if (selectedRequirement) chooseFileButtonRef.current?.focus();
  }, [selectedRequirement]);

  useEffect(() => {
    if (!selectedRequirement) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const handleModalKeys = (event) => {
      if (preview) return;
      if (event.key === 'Escape' && uploading === null) closeUploadModal();
      if (event.key !== 'Tab') return;

      const buttons = [...(dialogRef.current?.querySelectorAll('button:not(:disabled)') || [])];
      if (!buttons.length) return;
      if (event.shiftKey && document.activeElement === buttons[0]) {
        event.preventDefault();
        buttons[buttons.length - 1].focus();
      } else if (!event.shiftKey && document.activeElement === buttons[buttons.length - 1]) {
        event.preventDefault();
        buttons[0].focus();
      }
    };
    document.addEventListener('keydown', handleModalKeys);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleModalKeys);
    };
  }, [selectedRequirement, uploading, preview, closeUploadModal]);

  useEffect(() => {
    if (!preview) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    previewCancelRef.current?.focus();
    const handlePreviewKeys = (event) => {
      if (event.key === 'Escape') closePreview();
      if (event.key !== 'Tab') return;

      const buttons = [...(previewDialogRef.current?.querySelectorAll('button:not(:disabled)') || [])];
      if (!buttons.length) return;
      if (event.shiftKey && document.activeElement === buttons[0]) {
        event.preventDefault();
        buttons[buttons.length - 1].focus();
      } else if (!event.shiftKey && document.activeElement === buttons[buttons.length - 1]) {
        event.preventDefault();
        buttons[0].focus();
      }
    };
    document.addEventListener('keydown', handlePreviewKeys);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handlePreviewKeys);
    };
  }, [preview, closePreview]);

  useEffect(() => {
    if (!removeTarget) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    removeCancelRef.current?.focus();
    const handleKeys = (event) => {
      if (event.key === 'Escape' && !removing) {
        setRemoveTarget(null);
        removeTriggerRef.current?.focus();
      }
      if (event.key !== 'Tab') return;
      const buttons = [...(removeDialogRef.current?.querySelectorAll('button:not(:disabled)') || [])];
      if (!buttons.length) return;
      if (event.shiftKey && document.activeElement === buttons[0]) {
        event.preventDefault();
        buttons[buttons.length - 1].focus();
      } else if (!event.shiftKey && document.activeElement === buttons[buttons.length - 1]) {
        event.preventDefault();
        buttons[0].focus();
      }
    };
    document.addEventListener('keydown', handleKeys);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeys);
    };
  }, [removeTarget, removing]);

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

  const openUploadModal = (requirement, trigger) => {
    modalTriggerRef.current = trigger;
    setSelectedRequirement(requirement);
    setSelectedFile(null);
    setFileError('');
  };

  const handleFileSelection = (event) => {
    const file = event.target.files?.[0];
    setSelectedFile(null);
    setFileError('');
    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.pdf') || (file.type && file.type !== 'application/pdf')) {
      setFileError('Please choose a PDF file.');
      event.target.value = '';
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      setFileError('The PDF must be 10 MB or smaller.');
      event.target.value = '';
      return;
    }

    setSelectedFile(file);
  };

  const handleUpload = async (event) => {
    event.preventDefault();
    if (!selectedRequirement || !selectedFile || uploading !== null) return;

    setUploading(selectedRequirement.id);
    setFileError('');
    try {
      const formData = new FormData();
      formData.append('file', selectedFile);

      await api.post(`/student/requirements/${selectedRequirement.id}/upload`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success(selectedRequirement.file_path ? 'PDF replaced successfully!' : 'PDF uploaded successfully!');
      setSelectedRequirement(null);
      setSelectedFile(null);
      await fetchRequirements();
      modalTriggerRef.current?.focus();
    } catch (error) {
      setFileError(error.response?.data?.errors?.file?.[0] || error.response?.data?.message || 'Upload failed. Please try again.');
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
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
    } catch {
      toast.error('Unable to download this file.');
    }
  };

  const handleViewSelectedFile = (trigger) => {
    if (!selectedFile) return;
    previewTriggerRef.current = trigger;
    setPreview({ file: selectedFile, mode: 'selected' });
  };

  const handleViewCurrentFile = async (requirement, trigger) => {
    previewTriggerRef.current = trigger;
    if (requirement.file_type !== 'pdf') {
      setPreview({ file: null, requirement, mode: 'current' });
      return;
    }
    const controller = new AbortController();
    previewAbortRef.current = controller;
    setPreview({ file: null, requirement, mode: 'current', loading: true });
    try {
      const response = await api.get(`/student/requirements/${requirement.id}/download`, {
        responseType: 'blob',
        signal: controller.signal,
      });
      if (!controller.signal.aborted) {
        setPreview({ file: response.data, requirement, mode: 'current' });
      }
    } catch {
      if (!controller.signal.aborted) {
        setPreview(null);
        toast.error('Unable to view this file.');
        trigger?.focus();
      }
    } finally {
      if (previewAbortRef.current === controller) previewAbortRef.current = null;
    }
  };

  const openRemoveModal = (requirement, trigger) => {
    removeTriggerRef.current = trigger;
    setRemoveTarget(requirement);
  };

  const closeRemoveModal = () => {
    if (removing) return;
    setRemoveTarget(null);
    removeTriggerRef.current?.focus();
  };

  const handleRemove = async () => {
    if (!removeTarget || removing) return;
    setRemoving(true);
    try {
      await api.delete(`/student/requirements/${removeTarget.id}/file`);
      setRemoveTarget(null);
      await fetchRequirements();
      toast.success('File removed successfully.');
      removeTriggerRef.current?.focus();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to remove this file.');
    } finally {
      setRemoving(false);
    }
  };

  const handleDownloadSelectedFile = (file) => {
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
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
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7D001D]"></div>
      </div>
    );
  }

  return (
    <div className="student-requirements space-y-6">
      <div>
        <p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Program checklist</p>
        <h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">Internship Requirements</h1>
        <p className="mt-1 text-sm text-slate-500">Upload PDF documents configured by your internship coordinator.</p>
      </div>

      <div className="student-requirements-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        {requirements.length > 0 ? (
          <div className="student-requirements-grid grid gap-3">
            {requirements.map((req) => (
              <div key={req.id} className="student-requirement-card min-w-0 rounded-xl border border-slate-200 p-4 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60">
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

                <div className="student-requirement-actions mt-4 flex items-center gap-4">
                  <button
                    type="button"
                    onClick={(event) => openUploadModal(req, event.currentTarget)}
                    className="rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#690016]"
                  >
                    {req.file_path ? 'Replace File' : 'Upload File'}
                  </button>
                  {req.file_path && (
                    <>
                      <button type="button" onClick={(event) => handleViewCurrentFile(req, event.currentTarget)} className="text-sm font-medium text-[#7D001D] hover:text-[#690016] dark:text-rose-300">View File</button>
                      <button type="button" onClick={(event) => openRemoveModal(req, event.currentTarget)} className="text-sm font-medium text-red-700 hover:text-red-900 dark:text-red-300">Remove File</button>
                    </>
                  )}
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

      {selectedRequirement && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4"
          onMouseDown={(event) => { if (event.target === event.currentTarget) closeUploadModal(); }}
        >
          <div
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-hidden={Boolean(preview)}
            inert={Boolean(preview)}
            aria-labelledby="requirement-upload-title"
            aria-describedby="requirement-upload-description"
            className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900"
          >
            <h2 id="requirement-upload-title" className="text-xl font-bold text-slate-900 dark:text-white">
              {selectedRequirement.file_path ? 'Replace File' : 'Upload File'}
            </h2>
            <p id="requirement-upload-description" className="mt-2 text-sm text-slate-600 dark:text-slate-300">
              Requirement: <span className="font-semibold">{selectedRequirement.requirement_name}</span>
            </p>

            {selectedRequirement.file_path && (
              <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                <p className="font-semibold">Current submission: {selectedRequirement.file_type?.toUpperCase() || 'File'} · {selectedRequirement.status}</p>
                <p className="mt-1">The new PDF will replace this submission after a successful upload.</p>
              </div>
            )}

            <form onSubmit={handleUpload} className="mt-5 space-y-4">
              <input
                ref={fileInputRef}
                type="file"
                accept=".pdf,application/pdf"
                onChange={handleFileSelection}
                className="sr-only"
                tabIndex={-1}
                disabled={uploading !== null}
                aria-label="Choose a PDF file"
              />
              <div>
                <div className="flex flex-wrap items-center gap-3">
                  <button
                    ref={chooseFileButtonRef}
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploading !== null}
                    className="rounded-lg border border-[#7D001D] px-4 py-2 text-sm font-semibold text-[#7D001D] hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:text-rose-300"
                  >
                    Choose File
                  </button>
                  <p className="min-w-0 break-all text-sm text-slate-600 dark:text-slate-300" aria-live="polite">
                    {selectedFile ? selectedFile.name : 'No PDF selected.'}
                  </p>
                </div>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF only. Maximum size: 10 MB.</p>
                {fileError && <p role="alert" className="mt-2 text-sm text-red-600 dark:text-red-400">{fileError}</p>}
                <button
                  type="button"
                  onClick={(event) => handleViewSelectedFile(event.currentTarget)}
                  disabled={!selectedFile || uploading !== null}
                  className="mt-3 text-sm font-semibold text-[#7D001D] underline underline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:text-rose-300"
                >
                  View selected file
                </button>
              </div>
              <div className="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <button type="button" onClick={closeUploadModal} disabled={uploading !== null} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-60 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                <button type="submit" disabled={!selectedFile || uploading !== null} className="rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#690016] disabled:cursor-not-allowed disabled:opacity-60">
                  {uploading !== null ? 'Uploading...' : 'Upload PDF'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {removeTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget) closeRemoveModal(); }}>
          <div ref={removeDialogRef} role="dialog" aria-modal="true" aria-labelledby="requirement-remove-title" className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <h2 id="requirement-remove-title" className="text-xl font-bold text-slate-900 dark:text-white">Remove File</h2>
            <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">Remove the current submission for <strong>{removeTarget.requirement_name}</strong>? Its review status will return to missing.</p>
            <div className="mt-6 flex justify-end gap-3">
              <button ref={removeCancelRef} type="button" onClick={closeRemoveModal} disabled={removing} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-60 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
              <button type="button" onClick={handleRemove} disabled={removing} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">{removing ? 'Removing...' : 'Remove File'}</button>
            </div>
          </div>
        </div>
      )}

      {preview && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-3 sm:p-5">
          <div
            ref={previewDialogRef}
            role="dialog"
            aria-modal="true"
            aria-labelledby="requirement-preview-title"
            className="flex h-[min(85vh,700px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900"
          >
            <div className="shrink-0 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
              <h2 id="requirement-preview-title" className="break-all text-lg font-bold text-slate-900 dark:text-white">{preview.mode === 'selected' ? `Selected PDF: ${preview.file.name}` : `Current file: ${preview.requirement.requirement_name}`}</h2>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto bg-slate-100 p-3 dark:bg-slate-800">
              {preview.loading ? <p className="py-12 text-center text-sm text-slate-600">Loading file...</p> : preview.file ? (
                <Suspense fallback={<p className="py-12 text-center text-sm text-slate-600">Rendering PDF...</p>}><PdfPages file={preview.file} /></Suspense>
              ) : <p className="py-12 text-center text-sm text-slate-600">Preview is available for PDF files only. You can download this older submission.</p>}
            </div>
            <div className="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
              <button ref={previewCancelRef} type="button" onClick={closePreview} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
              <button type="button" onClick={() => preview.mode === 'selected' ? handleDownloadSelectedFile(preview.file) : handleDownload(preview.requirement)} disabled={preview.loading} className="rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#690016] disabled:opacity-60">{preview.mode === 'selected' || preview.requirement.file_type === 'pdf' ? 'Download PDF' : 'Download File'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default StudentRequirements;
