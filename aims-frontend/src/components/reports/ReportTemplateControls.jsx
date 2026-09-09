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
    <div className="fixed inset-0 z-[80] grid place-items-center bg-slate-950/65 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby={labelledBy}>
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
          <form onSubmit={uploadTemplate} className="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
            <div className="flex items-start justify-between gap-4 bg-[#690016] px-6 py-5 text-white">
              <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]">Shared official format</p><h2 id="upload-template-title" className="mt-1 text-xl font-black">Upload Official Template</h2><p className="mt-1 text-xs text-white/65">One template for every report type on this page</p></div>
              <button type="button" onClick={closeUpload} disabled={uploading} className="rounded-xl p-2 text-white/70 transition hover:bg-white/10 hover:text-white" aria-label="Close upload"><FiX /></button>
            </div>
            <div className="space-y-5 p-6">
              <label className="block cursor-pointer rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 p-6 text-center transition hover:border-[#7D001D]/40 dark:border-slate-700 dark:bg-slate-800/60">
                <FiUploadCloud className="mx-auto text-3xl text-[#7D001D] dark:text-rose-300" />
                <span className="mt-2 block text-sm font-black text-slate-700 dark:text-white">{file?.name || 'Choose official DOCX template'}</span>
                <span className="mt-1 block text-xs text-slate-400">DOCX only, maximum 10 MB</span>
                <input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" onChange={(event) => setFile(event.target.files?.[0] || null)} className="sr-only" />
              </label>
              <div className="rounded-2xl bg-amber-50 p-4 text-xs leading-5 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Upload the official blank DOCX once. AIMS will use it for Student Internship, HTE Deployment, Travel Monitoring, and the other report types available to your role. No template codes or separate uploads are required.
              </div>
              <div className="flex justify-end gap-3">
                <button type="button" onClick={closeUpload} disabled={uploading} className="rounded-xl border border-slate-200 px-5 py-3 text-xs font-black text-slate-600 dark:border-slate-700 dark:text-slate-200">Cancel</button>
                <button disabled={uploading} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 text-xs font-black text-white disabled:opacity-60"><FiUploadCloud /> {uploading ? 'Uploading…' : 'Upload and Use Template'}</button>
              </div>
            </div>
          </form>
        </Modal>
      )}

      {preview && (
        <Modal onClose={() => setPreview(null)} labelledBy="generated-preview-title">
          <div className="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-slate-700">
              <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#7D001D] dark:text-rose-300">Generated using the official template</p><h2 id="generated-preview-title" className="mt-1 text-xl font-black text-slate-900 dark:text-white">{reportLabel} Preview</h2><p className="mt-1 text-xs text-slate-400">{preview.template?.file_name} · Shared across all report types and periods</p></div>
              <div className="flex flex-wrap gap-2"><button type="button" onClick={generatePreview} disabled={generating} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-600 dark:border-slate-700 dark:text-slate-200"><FiEye /> {generating ? 'Refreshing…' : 'Refresh Preview'}</button><button type="button" onClick={downloadPdf} disabled={downloading} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white disabled:opacity-60"><FiDownload /> {downloading ? 'Preparing PDF…' : 'Download PDF'}</button><button type="button" onClick={() => setPreview(null)} className="rounded-xl border border-slate-200 p-3 text-slate-500 dark:border-slate-700" aria-label="Close preview"><FiX /></button></div>
            </div>
            <div className="min-h-0 flex-1 bg-slate-200 p-3 dark:bg-slate-950"><iframe title={`${reportLabel} generated preview`} srcDoc={preview.html} sandbox="" className="h-full w-full rounded-xl bg-white shadow-inner" /></div>
          </div>
        </Modal>
      )}
    </>
  );
}
