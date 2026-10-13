import { useEffect, useMemo, useRef, useState } from 'react';
import { FiDownload, FiEye, FiFileText, FiTrash2, FiUploadCloud, FiX } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  const first = errors && Object.values(errors).flat()[0];
  return first || error.response?.data?.message || fallback;
}

function Modal({ children, onClose, labelledBy }) {
  useEffect(() => {
    const closeOnEscape = (event) => event.key === 'Escape' && onClose();
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[80] grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby={labelledBy}>
      {children}
    </div>
  );
}

export default function ReportTemplateControls({ reportType, reportLabel, filters = {}, tone = 'dark' }) {
  const [uploadOpen, setUploadOpen] = useState(false);
  const [file, setFile] = useState(null);
  const [fileError, setFileError] = useState('');
  const [uploading, setUploading] = useState(false);
  const [templates, setTemplates] = useState([]);
  const [templatesLoading, setTemplatesLoading] = useState(false);
  const [templateBusyId, setTemplateBusyId] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const fileInputRef = useRef(null);
  const [generating, setGenerating] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [preview, setPreview] = useState(null);

  const generationParams = useMemo(() => {
    const params = Object.fromEntries(Object.entries(filters).filter(([key, value]) => key !== 'report' && value !== '' && value !== undefined && value !== null));
    return { ...params, report_type: reportType };
  }, [filters, reportType]);

  useEffect(() => {
    if (!uploadOpen) return undefined;
    let cancelled = false;
    setTemplatesLoading(true);
    api.get('/report-templates', { params: { report_type: reportType } })
      .then(({ data }) => {
        if (!cancelled) setTemplates((data.templates || []).filter((template) => template.report_type === 'all_reports' && template.program_id === data.assigned_program_id));
      })
      .catch((error) => { if (!cancelled) toast.error(errorMessage(error, 'Unable to load uploaded templates.')); })
      .finally(() => { if (!cancelled) setTemplatesLoading(false); });
    return () => { cancelled = true; };
  }, [uploadOpen, reportType]);

  const closeUpload = () => {
    if (!uploading && templateBusyId === null) {
      setUploadOpen(false);
      setFile(null);
      setFileError('');
      setDeleteTarget(null);
    }
  };

  const deactivateTemplate = async (template) => {
    setTemplateBusyId(template.id);
    try {
      await api.post(`/report-templates/${template.id}/deactivate`);
      setTemplates((current) => current.map((item) => item.id === template.id ? { ...item, is_active: false } : item));
      toast.success('Template deactivated. You can now delete it.');
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to deactivate the template.'));
    } finally {
      setTemplateBusyId(null);
    }
  };

  const deleteTemplate = async () => {
    if (!deleteTarget) return;
    const target = deleteTarget;
    setTemplateBusyId(target.id);
    try {
      await api.delete(`/report-templates/${target.id}`);
      setTemplates((current) => current.filter((item) => item.id !== target.id));
      setDeleteTarget(null);
      toast.success('Template deleted. Historical generated reports were preserved.');
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to delete the template.'));
    } finally {
      setTemplateBusyId(null);
    }
  };

  const selectTemplate = (event) => {
    const selected = event.target.files?.[0];
    setFile(null);
    setFileError('');
    if (!selected) return;
    if (!selected.name.toLowerCase().endsWith('.docx')) {
      setFileError('Please choose a DOCX file.');
      event.target.value = '';
      return;
    }
    if (selected.size > 15 * 1024 * 1024) {
      setFileError('The DOCX template must be 15 MB or smaller.');
      event.target.value = '';
      return;
    }
    setFile(selected);
  };

  const uploadTemplate = async (event) => {
    event.preventDefault();
    if (!file || uploading || templateBusyId !== null) return;
    const data = new FormData();
    data.append('file', file);
    data.append('name', file.name.replace(/\.docx$/i, ''));
    data.append('activate', '1');

    setUploading(true);
    try {
      const response = await api.post('/report-templates', data, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message);
      setUploadOpen(false);
      setFile(null);
      setFileError('');
    } catch (error) {
      setFileError(errorMessage(error, 'Unable to upload the official template.'));
    } finally {
      setUploading(false);
    }
  };

  const generatePreview = async () => {
    setGenerating(true);
    try {
      const response = await api.get('/report-templates/active/preview', { params: generationParams });
      setPreview(response.data);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to generate the report preview.'));
    } finally {
      setGenerating(false);
    }
  };

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const response = await api.get('/report-templates/active/pdf', { params: generationParams, responseType: 'blob' });
      const disposition = response.headers['content-disposition'] || '';
      const match = disposition.match(/filename="?([^";]+)"?/i);
      const filename = match?.[1] || `${reportType}-${new Date().toISOString().slice(0, 10)}.pdf`;
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      toast.success('Official PDF report downloaded.');
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to download the generated PDF.'));
    } finally {
      setDownloading(false);
    }
  };

  return (
    <>
      <div className="flex flex-wrap gap-2 print:hidden">
        <button type="button" onClick={() => setUploadOpen(true)} className={`inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-xs font-black shadow-sm transition ${tone === 'light' ? 'border-slate-200 bg-white text-[#7D001D] hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-rose-300' : 'border-[#f1c54c]/60 bg-white/10 text-[#f7d979] hover:bg-white/20'}`}>
          <FiUploadCloud className="text-base" /> Upload Official Template
        </button>
        <button type="button" onClick={generatePreview} disabled={generating} className="inline-flex items-center gap-2 rounded-xl bg-[#f1c54c] px-5 py-3 text-xs font-black text-[#690016] shadow-lg shadow-black/10 transition hover:bg-[#f7d979] disabled:opacity-60">
          <FiFileText className="text-base" /> {generating ? 'Generating…' : 'Generate Report'}
        </button>
      </div>

      {uploadOpen && (
        <Modal onClose={closeUpload} labelledBy="upload-template-title">
          <form onSubmit={uploadTemplate} className="max-h-[calc(100dvh-1.5rem)] overflow-y-auto rounded-2xl bg-white p-5 font-sans shadow-2xl dark:bg-slate-900" style={{ width: 'min(30rem, calc(100vw - 2rem))', maxWidth: '30rem' }}>
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 id="upload-template-title" className="text-xl font-bold text-slate-900 dark:text-white">Upload Official Template</h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">This template will be shared by every report type available to your role.</p>
              </div>
              <button type="button" onClick={closeUpload} disabled={uploading || templateBusyId !== null} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 disabled:opacity-60 dark:hover:bg-slate-800" aria-label="Close upload"><FiX /></button>
            </div>
            <div className="mt-5">
              <input ref={fileInputRef} type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" onChange={selectTemplate} disabled={uploading} className="sr-only" tabIndex={-1} aria-label="Choose a DOCX template" />
              <div className="flex flex-wrap items-center gap-3">
                <button type="button" onClick={() => fileInputRef.current?.click()} disabled={uploading || templateBusyId !== null} className="rounded-lg border border-[#7D001D] px-4 py-2 text-sm font-semibold text-[#7D001D] hover:bg-rose-50 disabled:opacity-60 dark:text-rose-300">Choose File</button>
                <p className="min-w-0 break-all text-sm text-slate-600 dark:text-slate-300" aria-live="polite">{file?.name || 'No DOCX selected.'}</p>
              </div>
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">DOCX only. Maximum size: 15 MB.</p>
              {fileError && <p role="alert" className="mt-2 text-sm text-red-600 dark:text-red-400">{fileError}</p>}
            </div>
            <div className="mt-5 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
              <button type="submit" disabled={!file || uploading || templateBusyId !== null} className="rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#690016] disabled:cursor-not-allowed disabled:opacity-60">{uploading ? 'Uploading…' : 'Upload DOCX'}</button>
            </div>
            <div className="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Uploaded templates</h3>
              {templatesLoading ? <p className="mt-2 text-sm text-slate-500">Loading templates...</p> : templates.length === 0 ? <p className="mt-2 text-sm text-slate-500">No uploaded templates yet.</p> : (
                <ul className="mt-3 max-h-52 space-y-2 overflow-y-auto">
                  {templates.map((template) => (
                    <li key={template.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-slate-800 dark:text-white" title={template.file_name}>{template.file_name}</p>
                        <p className="text-xs text-slate-500">{template.is_active ? 'Active' : 'Inactive'}</p>
                      </div>
                      {template.is_active ? (
                        <button type="button" onClick={() => deactivateTemplate(template)} disabled={uploading || templateBusyId !== null} className="shrink-0 text-xs font-semibold text-[#7D001D] disabled:opacity-50 dark:text-rose-300">Deactivate</button>
                      ) : (
                        <button type="button" onClick={() => setDeleteTarget(template)} disabled={uploading || templateBusyId !== null} className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-red-700 disabled:opacity-50 dark:text-red-300"><FiTrash2 /> Delete</button>
                      )}
                    </li>
                  ))}
                </ul>
              )}
              {deleteTarget && (
                <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950/30">
                  <p className="break-all text-red-900 dark:text-red-200">Delete {deleteTarget.file_name}? The uploaded DOCX will be removed.</p>
                  <div className="mt-3 flex justify-end gap-3">
                    <button type="button" onClick={() => setDeleteTarget(null)} disabled={templateBusyId !== null} className="text-sm font-semibold text-slate-700 disabled:opacity-50 dark:text-slate-200">Cancel</button>
                    <button type="button" onClick={deleteTemplate} disabled={templateBusyId !== null} className="rounded-lg bg-red-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50">{templateBusyId === deleteTarget.id ? 'Deleting...' : 'Delete template'}</button>
                  </div>
                </div>
              )}
            </div>
          </form>
        </Modal>
      )}

      {preview && (
        <Modal onClose={() => setPreview(null)} labelledBy="generated-preview-title">
          <div className="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900" style={{ width: 'min(740px, calc(100vw - 2rem))', maxWidth: '740px', height: 'min(65dvh, 560px)' }}>
            <div className="shrink-0 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0"><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#7D001D] dark:text-rose-300">Generated using the official template</p><h2 id="generated-preview-title" className="mt-1 truncate text-lg font-black text-slate-900 dark:text-white">{reportLabel} Preview</h2><p className="mt-1 truncate text-xs text-slate-400">{preview.template?.file_name} · Shared across all report types and periods</p></div>
                <button type="button" onClick={() => setPreview(null)} className="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close preview"><FiX /></button>
              </div>
            </div>
            <div className="min-h-0 flex-1 bg-slate-200 p-2 dark:bg-slate-950">
              <div className="h-full overflow-hidden rounded-xl bg-white shadow-inner">
                <iframe title={`${reportLabel} generated preview`} srcDoc={preview.html} sandbox="" className="block h-full w-full border-0 bg-white" />
              </div>
            </div>
            <div className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-200 px-4 py-2 dark:border-slate-700">
              <button type="button" onClick={generatePreview} disabled={generating} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200"><FiEye /> {generating ? 'Refreshing…' : 'Refresh Preview'}</button>
              <button type="button" onClick={downloadPdf} disabled={downloading} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-3 py-2 text-xs font-black text-white disabled:opacity-60"><FiDownload /> {downloading ? 'Preparing PDF…' : 'Download PDF'}</button>
            </div>
          </div>
        </Modal>
      )}
    </>
  );
}
