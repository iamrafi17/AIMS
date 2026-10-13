import { lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { FiArchive, FiCheck, FiCheckCircle, FiClock, FiEye, FiFileText, FiRefreshCw, FiSearch, FiShield, FiX, FiXCircle } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const PdfPages = lazy(() => import('../../components/PdfPages'));
const emptyData = { summary: { for_review: 0, reviewed: 0, final_approved: 0 }, folders: [], history: [] };
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—';
const statusLabel = (status) => ({ for_review: 'For VPAA Review', reviewed: 'Reviewed', final_approved: 'Legacy Final Approved' })[status] || status;
const statusTone = (status) => ({ for_review: 'bg-amber-50 text-amber-700 border-amber-200', reviewed: 'bg-emerald-50 text-emerald-700 border-emerald-200', final_approved: 'bg-emerald-50 text-emerald-700 border-emerald-200' })[status] || '';

function Modal({ title, subtitle, onClose, children, size = 'max-w-3xl' }) {
  return <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
    <div role="dialog" aria-modal="true" aria-label={title} className={`max-h-[92dvh] w-full overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-gray-800 ${size}`}>
      <div className="sticky top-0 z-10 flex justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-800"><div><h2 className="text-lg font-black text-[#690016] dark:text-white">{title}</h2><p className="mt-1 text-xs text-slate-400">{subtitle}</p></div><button type="button" onClick={onClose} aria-label="Close dialog" className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-white"><FiX /></button></div>
      {children}
    </div>
  </div>;
}

function Metric({ label, value, note, icon: Icon, tone }) {
  return <div className="portal-metric-card rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-start justify-between"><div><p className="text-[10px] font-black uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#690016] dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div><div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl ${tone}`}><Icon /></div></div></div>;
}

function StudentSummary({ item }) {
  return <><p className="text-base font-black text-slate-800 dark:text-white">{item.student?.name}</p><p className="mt-1 text-xs text-slate-400">{item.student?.student_id} · {item.student?.program || item.student?.program_code}</p></>;
}

function VPAAApprovals() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState('folders');
  const [search, setSearch] = useState('');
  const [program, setProgram] = useState('all');
  const [status, setStatus] = useState('all');
  const [sort, setSort] = useState('newest');
  const [selectedFolder, setSelectedFolder] = useState(null);
  const [confirmation, setConfirmation] = useState(null);
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = async (quiet = false) => {
    if (quiet) setRefreshing(true); else setLoading(true);
    try {
      const response = await api.get('/vpaa/student-document-approvals');
      setData({ ...emptyData, ...response.data });
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load student folders.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    let mounted = true;
    api.get('/vpaa/student-document-approvals')
      .then((response) => { if (mounted) setData({ ...emptyData, ...response.data }); })
      .catch((error) => { if (mounted) toast.error(error.response?.data?.message || 'Unable to load student folders.'); })
      .finally(() => { if (mounted) setLoading(false); });
    return () => { mounted = false; };
  }, []);

  const programs = useMemo(() => [...new Set([...data.folders, ...data.history].map((item) => item.student?.program_code).filter(Boolean))].sort(), [data]);
  const records = useMemo(() => {
    const query = search.trim().toLowerCase();
    return (activeTab === 'folders' ? data.folders : data.history)
      .filter((item) => (!query || `${item.student?.name} ${item.student?.student_id}`.toLowerCase().includes(query))
        && (program === 'all' || item.student?.program_code === program)
        && (status === 'all' || item.status === status))
      .sort((a, b) => (sort === 'newest' ? -1 : 1) * (new Date(a.vpaa_reviewed_at || a.decided_at || a.dean_decided_at || a.endorsed_at) - new Date(b.vpaa_reviewed_at || b.decided_at || b.dean_decided_at || b.endorsed_at)));
  }, [data, activeTab, search, program, status, sort]);

  const openFolder = async (item) => {
    try {
      const response = await api.get(`/vpaa/student-document-approvals/${item.id}`);
      setSelectedFolder(response.data);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to open this folder.');
    }
  };

  const openFile = async (item) => {
    setPreview({ name: item.name, loading: true });
    try {
      const response = await api.get(`/vpaa/student-document-approvals/${selectedFolder.id}/requirements/${item.id}/view`, { responseType: 'blob' });
      setPreview({ name: item.name, file: response.data });
    } catch {
      setPreview({ name: item.name, error: 'File unavailable.' });
    }
  };

  const decide = async () => {
    if (!confirmation) return;
    setBusy(true);
    try {
      const response = await api.post(`/vpaa/student-document-approvals/${selectedFolder.id}/review`);
      toast.success(response.data.message);
      setConfirmation(null);
      setSelectedFolder(null);
      await load(true);
    } catch (error) {
      toast.error(error.response?.data?.errors?.folder?.[0] || error.response?.data?.message || 'Unable to save the folder decision.');
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <div className="grid min-h-[420px] place-items-center text-sm font-bold text-slate-400">Loading approval workspace...</div>;

  return <div className="space-y-6">
    <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#690016] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8"><div className="relative flex flex-wrap items-end justify-between gap-5"><div><div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-[#f7d979]"><FiShield /> VPAA Document Review</div><h1 className="text-2xl font-black sm:text-3xl">Student Folder Review</h1><p className="mt-2 text-sm text-white/70">Review student folders after final Dean approval.</p></div><button type="button" onClick={() => load(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black disabled:opacity-50"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Folders</button></div></header>

    <section className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><Metric label="For Review" value={data.summary.for_review} note="Dean-approved folders" icon={FiClock} tone="bg-amber-100 text-amber-700" /><Metric label="Reviewed" value={data.summary.reviewed} note="VPAA acknowledgments" icon={FiCheckCircle} tone="bg-emerald-100 text-emerald-700" /><Metric label="Legacy Final Approved" value={data.summary.final_approved} note="Previous records" icon={FiXCircle} tone="bg-rose-100 text-rose-700" /></section>

    <nav aria-label="Approval sections" className="flex flex-wrap gap-2 rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">{[['folders', 'Dean-Approved Student Folders', data.summary.for_review, FiFileText], ['history', 'Approval History', data.history.length, FiArchive]].map(([id, label, count, Icon]) => <button key={id} type="button" onClick={() => { setActiveTab(id); setStatus('all'); }} className={`inline-flex items-center gap-2 rounded-xl px-5 py-3 text-xs font-black ${activeTab === id ? 'bg-[#7D001D] text-white' : 'text-slate-500 dark:text-gray-300'}`}><Icon /> {label} <span className="rounded-full bg-white/20 px-2">{count}</span></button>)}</nav>

    <section className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><label className="relative block"><FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search student name or Student ID..." className="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" /></label><div className="mt-3 flex flex-wrap gap-2"><select aria-label="Program filter" value={program} onChange={(event) => setProgram(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"><option value="all">All Programs</option>{programs.map((code) => <option key={code} value={code}>{code}</option>)}</select><select aria-label="Status filter" value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"><option value="all">All Status</option>{activeTab === 'history' ? <><option value="reviewed">Reviewed</option><option value="final_approved">Legacy Final Approved</option></> : <option value="for_review">For VPAA Review</option>}</select><select aria-label="Sort order" value={sort} onChange={(event) => setSort(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select></div></section>

    {activeTab === 'folders' ? <section className="space-y-4"><h2 className="text-lg font-black text-slate-800 dark:text-white">Dean-Approved Student Folders</h2>{records.length ? records.map((item) => <article key={item.id} className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6"><div className="flex flex-wrap items-start justify-between gap-2"><StudentSummary item={item} /><span className={`rounded-full border px-3 py-1 text-[10px] font-black uppercase ${statusTone(item.status)}`}>{statusLabel(item.status)}</span></div><div className="mt-5 grid gap-2 text-xs text-slate-600 dark:text-gray-300 sm:grid-cols-2"><p><strong>Requirements:</strong> {item.approved_count}/{item.required_count} Approved</p><p><strong>Endorsed By:</strong> {item.endorsed_by}</p><p><strong>Endorsed Date:</strong> {formatDate(item.endorsed_at)}</p><p><strong>Dean Approved By:</strong> {item.dean_decided_by || 'Legacy record'}</p><p><strong>Dean Approval Date:</strong> {formatDate(item.dean_decided_at)}</p></div><p className="mt-4 text-xs font-bold text-emerald-700"><FiCheck className="mr-1 inline" /> All required documents were approved before endorsement</p><div className="mt-5 flex justify-end"><button type="button" onClick={() => openFolder(item)} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white"><FiEye /> View Folder →</button></div></article>) : <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-sm text-slate-400 dark:border-gray-700 dark:bg-gray-800">No Dean-approved student folders match these filters.</div>}</section>
      : <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><h2 className="mb-5 text-lg font-black text-slate-800 dark:text-white">Approval History</h2>{records.length ? <div className="overflow-x-auto"><table className="w-full min-w-[700px] text-left text-xs"><thead><tr className="border-b border-slate-100 text-slate-400 dark:border-gray-700"><th className="pb-3">Student</th><th className="pb-3">Program</th><th className="pb-3">Decision</th><th className="pb-3">Date</th><th className="pb-3 text-right">Action</th></tr></thead><tbody>{records.map((item) => <tr key={item.id} className="border-b border-slate-100 dark:border-gray-700"><td className="py-4 font-bold text-slate-800 dark:text-white">{item.student?.name}</td><td>{item.student?.program_code}</td><td><span className={`rounded-full border px-2 py-1 font-bold ${statusTone(item.status)}`}>{statusLabel(item.status)}</span></td><td>{formatDate(item.vpaa_reviewed_at || item.decided_at)}</td><td className="text-right"><button type="button" onClick={() => openFolder(item)} className="rounded-lg bg-[#7D001D]/10 px-3 py-2 font-bold text-[#7D001D]">View</button></td></tr>)}</tbody></table></div> : <p className="py-12 text-center text-sm text-slate-400">No matching approval history.</p>}</section>}

    {selectedFolder && !preview && !confirmation && <Modal title={activeTab === 'history' ? 'Approval Record' : 'Student Document Folder'} subtitle={activeTab === 'history' ? 'VPAA review record' : 'VPAA Review'} onClose={() => setSelectedFolder(null)}><div className="space-y-5 p-6"><div><StudentSummary item={selectedFolder} /><p className="mt-3 text-xs text-slate-600 dark:text-gray-300">Endorsed by: {selectedFolder.endorsed_by}<br />Endorsed date: {formatDate(selectedFolder.endorsed_at)}<br />Final approved by Dean: {selectedFolder.dean_decided_by || 'Legacy record'}<br />Dean approval date: {formatDate(selectedFolder.dean_decided_at)}<br />Status: <strong>{statusLabel(selectedFolder.status)}</strong></p></div><div className="border-t border-slate-100 pt-4 dark:border-gray-700"><h3 className="mb-3 text-xs font-black uppercase text-slate-700 dark:text-white">Document Requirements</h3><div className="divide-y divide-slate-100 dark:divide-gray-700">{selectedFolder.requirements?.map((item) => <div key={item.name} className="flex flex-wrap items-center gap-3 py-3 text-xs"><span className="min-w-48 flex-1 font-bold text-slate-700 dark:text-gray-200">{item.name}</span><span className="font-black capitalize text-emerald-700">{item.status}</span><button type="button" disabled={!item.file_available} onClick={() => openFile(item)} className="rounded-lg bg-slate-100 px-3 py-2 font-bold text-[#7D001D] disabled:opacity-40 dark:bg-gray-700 dark:text-rose-300">{item.file_available ? 'View' : 'File unavailable'}</button></div>)}</div><p className="mt-4 text-center text-sm font-black text-[#7D001D]">{selectedFolder.approved_count}/{selectedFolder.required_count} Requirements Approved</p></div>{selectedFolder.status === 'for_review' ? <div className="flex justify-end gap-2 border-t border-slate-100 pt-5 dark:border-gray-700"><button type="button" onClick={() => setConfirmation('review')} className="rounded-xl bg-emerald-600 px-4 py-3 text-xs font-black text-white">Mark as Reviewed</button></div> : <div className="border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-gray-700 dark:text-gray-300"><p>Review status: <strong>{statusLabel(selectedFolder.status)}</strong></p><p>Review date: {formatDate(selectedFolder.vpaa_reviewed_at || selectedFolder.decided_at)}</p><p>Reviewed by: {selectedFolder.vpaa_reviewed_by || selectedFolder.decided_by || '—'}</p></div>}</div></Modal>}

    {confirmation && <Modal title="Mark Student Folder Reviewed" subtitle={`${selectedFolder.student?.name} · ${selectedFolder.student?.student_id}`} onClose={() => !busy && setConfirmation(null)} size="max-w-lg"><div className="p-6"><p className="text-sm text-slate-600 dark:text-gray-300">Confirm that VPAA has reviewed this Dean-approved student document folder?</p><div className="mt-6 flex justify-end gap-2"><button type="button" disabled={busy} onClick={() => setConfirmation(null)} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold">Cancel</button><button type="button" disabled={busy} onClick={decide} className={`rounded-xl px-4 py-2 text-xs font-bold text-white bg-emerald-600`}>{busy ? 'Saving...' : 'Mark as Reviewed'}</button></div></div></Modal>}

    {preview && <Modal title={preview.name} subtitle="Student document preview" onClose={() => setPreview(null)} size="max-w-4xl"><div className="min-h-80 bg-slate-100 p-4 dark:bg-gray-900">{preview.loading ? <p className="py-16 text-center text-sm">Loading file...</p> : preview.file ? <Suspense fallback={<p className="py-16 text-center text-sm">Rendering PDF...</p>}><PdfPages file={preview.file} /></Suspense> : <p className="py-16 text-center text-sm text-rose-600">{preview.error}</p>}</div></Modal>}
  </div>;
}

export default VPAAApprovals;
