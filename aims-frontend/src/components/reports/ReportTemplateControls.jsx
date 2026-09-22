import { useEffect, useMemo, useState } from 'react';
import { FiDownload, FiEye, FiFileText, FiUploadCloud, FiX } from 'react-icons/fi';
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
  const [uploading, setUploading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [preview, setPreview] = useState(null);

  const generationParams = useMemo(() => {
    const params = Object.fromEntries(Object.entries(filters).filter(([key, value]) => key !== 'report' && value !== '' && value !== undefined && value !== null));
    return { ...params, report_type: reportType };
  }, [filters, reportType]);

  const closeUpload = () => {
    if (!uploading) {
      setUploadOpen(false);
      setFile(null);
    }
  };

  const uploadTemplate = async (event) => {
    event.preventDefault();
    if (!file) return toast.error('Choose the official DOCX template first.');
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
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to upload the official template.'));
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
          <form onSubmit={uploadTemplate} className="w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white font-sans shadow-[0_20px_60px_rgba(15,23,42,0.22)] dark:border-slate-700 dark:bg-slate-900">
            <div className="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900">
              <div>
                <h2 id="upload-template-title" className="text-lg font-black text-[#690016] dark:text-white">Upload Official Template</h2>
                <p className="mt-1 text-xs text-slate-400">DOCX only, up to 10 MB. One template is shared by every report type.</p>
              </div>
              <button type="button" onClick={closeUpload} disabled={uploading} className="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-[#7D001D] dark:hover:bg-slate-800" aria-label="Close upload"><FiX /></button>
            </div>
            <div className="space-y-4 p-5">
              <label className="block cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-5 text-center transition hover:border-[#7D001D]/50 hover:bg-[#7D001D]/[0.03] dark:border-slate-700 dark:bg-slate-800/60">
                <span className="mx-auto grid h-9 w-9 place-items-center rounded-full bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-400/10 dark:text-rose-300"><FiUploadCloud className="text-lg" /></span>
                <span className="mt-2.5 block truncate text-sm font-black text-slate-700 dark:text-white">{file?.name || 'Choose official DOCX template'}</span>
                <span className="mt-1 block text-xs text-slate-400">Click to browse for a DOCX file</span>
                <input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" onChange={(event) => setFile(event.target.files?.[0] || null)} className="sr-only" />
              </label>
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                Upload the official blank DOCX once. AIMS will use it for Student Internship, HTE Deployment, Travel Monitoring, and the other report types available to your role. No template codes or separate uploads are required.
              </div>
            </div>
            <div className="flex flex-col-reverse gap-2.5 border-t border-slate-100 px-5 py-4 dark:border-slate-700 sm:flex-row sm:justify-end">
              <button type="button" onClick={closeUpload} disabled={uploading} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
              <button disabled={uploading} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-5 py-2.5 text-sm font-black text-white transition hover:bg-[#650018] disabled:opacity-60"><FiUploadCloud /> {uploading ? 'Uploading…' : 'Upload and Use Template'}</button>
            </div>
          </form>
        </Modal>
      )}

      {preview && (
        <Modal onClose={() => setPreview(null)} labelledBy="generated-preview-title">
          <div className={`flex w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 ${reportType === 'student_internship' ? 'max-h-[calc(100dvh-1.5rem)] max-w-2xl sm:max-h-[calc(100dvh-2.5rem)]' : 'h-[92vh] max-w-6xl'}`}>
            <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
              <div className="flex items-start justify-between gap-4">
                <div className="min-w-0"><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#7D001D] dark:text-rose-300">Generated using the official template</p><h2 id="generated-preview-title" className="mt-1 truncate text-lg font-black text-slate-900 dark:text-white">{reportLabel} Preview</h2><p className="mt-1 truncate text-xs text-slate-400">{preview.template?.file_name} · Shared across all report types and periods</p></div>
                <button type="button" onClick={() => setPreview(null)} className="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close preview"><FiX /></button>
              </div>
              <div className="mt-4 flex flex-wrap justify-end gap-2"><button type="button" onClick={generatePreview} disabled={generating} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-600 dark:border-slate-700 dark:text-slate-200"><FiEye /> {generating ? 'Refreshing…' : 'Refresh Preview'}</button><button type="button" onClick={downloadPdf} disabled={downloading} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white disabled:opacity-60"><FiDownload /> {downloading ? 'Preparing PDF…' : 'Download PDF'}</button></div>
            </div>
            <div className={`min-h-0 bg-slate-200 p-3 dark:bg-slate-950 ${reportType === 'student_internship' ? 'h-[55dvh] min-h-80 max-h-[32rem] shrink-0' : 'flex-1'}`}><iframe title={`${reportLabel} generated preview`} srcDoc={preview.html} sandbox="" className="h-full w-full rounded-xl bg-white shadow-inner" /></div>
          </div>
        </Modal>
      )}
    </>
  );
}
