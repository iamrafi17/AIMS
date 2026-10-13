import { lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { FiCheck, FiEye, FiFileText, FiRefreshCw, FiSearch, FiX } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const PdfPages = lazy(() => import('../../components/PdfPages'));
const emptyData = { folders: [], history: [], summary: { for_dean_review: 0, approved: 0, returned: 0 } };
const dateLabel = (value) => value ? new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const statusLabel = (status) => ({ for_dean_review: 'For Dean Approval', for_review: 'Approved · VPAA Review Pending', reviewed: 'Approved · VPAA Reviewed', returned: 'Returned', final_approved: 'Legacy Final Approved' })[status] || status;

export default function DeanDocuments() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [tab, setTab] = useState('pending');
  const [selected, setSelected] = useState(null);
  const [preview, setPreview] = useState(null);
  const [decision, setDecision] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    try { setData((await api.get('/dean/documents')).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load student folders.'); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  const records = useMemo(() => {
    const query = search.trim().toLowerCase();
    return (tab === 'pending' ? data.folders : data.history).filter((item) =>
      `${item.student?.name} ${item.student?.student_id} ${item.student?.program_code}`.toLowerCase().includes(query));
  }, [data, tab, search]);

  const openFolder = async (item) => {
    try { setSelected((await api.get(`/dean/documents/${item.id}`)).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to open this folder.'); }
  };
  const openFile = async (item) => {
    setPreview({ name: item.name, loading: true });
    try {
      const response = await api.get(`/dean/documents/${selected.id}/requirements/${item.id}/view`, { responseType: 'blob' });
      setPreview({ name: item.name, file: response.data });
    } catch { setPreview({ name: item.name, error: 'File unavailable.' }); }
  };
  const submit = async () => {
    if (decision === 'return' && !remarks.trim()) { toast.error('Enter the reason for returning the folder.'); return; }
    setBusy(true);
    try {
      const response = await api.post(`/dean/documents/${selected.id}/${decision}`, { remarks: remarks.trim() || null });
      toast.success(response.data.message);
      setSelected(null); setDecision(null); setRemarks('');
      await load();
    } catch (error) {
      toast.error(error.response?.data?.errors?.folder?.[0] || error.response?.data?.errors?.remarks?.[0] || error.response?.data?.message || 'Unable to save the decision.');
    } finally { setBusy(false); }
  };

  if (loading) return <div className="grid min-h-80 place-items-center text-sm text-slate-500">Loading student folders…</div>;
  return <div className="space-y-5">
    <header className="rounded-3xl bg-[#690016] p-6 text-white">
      <p className="text-xs font-bold uppercase tracking-widest text-amber-200">Dean workspace</p>
      <div className="mt-2 flex flex-wrap items-center justify-between gap-3"><h1 className="text-3xl font-black">Student Document Folders</h1><button onClick={load} className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-2 text-sm"><FiRefreshCw /> Refresh</button></div>
      <p className="mt-2 text-sm text-white/75">Review complete folders endorsed by Program Heads in your college.</p>
    </header>
    <div className="grid gap-3 sm:grid-cols-3">
      {[['For Approval', data.summary.for_dean_review], ['Approved', data.summary.approved], ['Returned', data.summary.returned]].map(([label, value]) =>
        <div key={label} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><p className="text-xs font-bold uppercase text-slate-500">{label}</p><p className="mt-1 text-3xl font-black text-[#690016] dark:text-white">{value || 0}</p></div>)}
    </div>
    <section className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <div className="flex flex-wrap gap-2"><button onClick={() => setTab('pending')} className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === 'pending' ? 'bg-[#690016] text-white' : 'bg-slate-100 text-slate-700'}`}>For Approval</button><button onClick={() => setTab('history')} className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === 'history' ? 'bg-[#690016] text-white' : 'bg-slate-100 text-slate-700'}`}>History</button></div>
      <label className="mt-4 flex items-center gap-2 rounded-xl border border-slate-200 px-3 dark:border-slate-700"><FiSearch className="text-slate-400" /><input className="w-full bg-transparent py-3 text-sm outline-none" placeholder="Search student, ID, or program" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
      <div className="mt-4 space-y-3">{records.length ? records.map((item) => <article key={item.id} className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div><p className="font-black">{item.student?.name}</p><p className="text-xs text-slate-500">{item.student?.student_id} · {item.student?.program_code} · {item.approved_count}/{item.required_count} requirements approved</p><p className="mt-1 text-xs text-slate-500">{statusLabel(item.status)} · Endorsed {dateLabel(item.endorsed_at)}</p></div><button onClick={() => openFolder(item)} className="inline-flex items-center gap-2 rounded-lg bg-[#690016] px-3 py-2 text-sm font-bold text-white"><FiEye /> View Folder</button></article>) : <p className="py-10 text-center text-sm text-slate-500">No student folders found.</p>}</div>
    </section>

    {selected && !preview && !decision && <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-3" onMouseDown={(event) => event.target === event.currentTarget && setSelected(null)}><div role="dialog" aria-modal="true" aria-label="Student document folder" className="max-h-[92dvh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 dark:bg-slate-900"><div className="flex items-start justify-between gap-3"><div><h2 className="text-xl font-black">{selected.student?.name}</h2><p className="text-sm text-slate-500">{selected.student?.student_id} · {selected.student?.program_code} · {statusLabel(selected.status)}</p></div><button aria-label="Close folder" onClick={() => setSelected(null)}><FiX /></button></div><p className="mt-4 text-sm">Endorsed by {selected.endorsed_by} on {dateLabel(selected.endorsed_at)}. {selected.approved_count}/{selected.required_count} requirements approved.</p><div className="mt-5 divide-y divide-slate-200 border-y border-slate-200 dark:divide-slate-700 dark:border-slate-700">{selected.requirements?.map((item) => <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><div className="flex items-center gap-2"><FiFileText className="text-[#690016]" /><span>{item.name}</span><span className="font-bold text-emerald-700">{item.status}</span></div><button disabled={!item.file_available} onClick={() => openFile(item)} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-[#690016] disabled:opacity-40">{item.file_available ? 'View File' : 'File Unavailable'}</button></div>)}</div>{selected.dean_remarks && <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Dean remarks: {selected.dean_remarks}</p>}{selected.status === 'for_dean_review' ? <div className="mt-5 flex justify-end gap-2"><button onClick={() => setDecision('return')} className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-bold text-rose-700">Return</button><button onClick={() => setDecision('approve')} className="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white"><FiCheck /> Final Approve</button></div> : <p className="mt-5 text-sm text-slate-500">Dean decision: {selected.dean_decided_by || 'Legacy record'} · {dateLabel(selected.dean_decided_at)}</p>}</div></div>}
    {decision && selected && <div className="fixed inset-0 z-[60] grid place-items-center bg-slate-950/60 p-3"><div role="dialog" aria-modal="true" aria-label="Confirm Dean decision" className="w-full max-w-lg rounded-2xl bg-white p-6 dark:bg-slate-900"><h2 className="text-xl font-black">{decision === 'approve' ? 'Final Approve Folder' : 'Return Folder'}</h2><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{decision === 'approve' ? 'The folder will go to VPAA for review.' : 'The folder will return to the Program Head for correction.'}</p><label className="mt-4 block text-sm font-bold">Remarks {decision === 'return' && '(required)'}<textarea value={remarks} onChange={(event) => setRemarks(event.target.value)} rows={3} className="mt-2 w-full rounded-lg border border-slate-200 p-3 dark:border-slate-700 dark:bg-slate-800" /></label><div className="mt-5 flex justify-end gap-2"><button disabled={busy} onClick={() => { setDecision(null); setRemarks(''); }} className="rounded-lg border px-4 py-2 text-sm">Cancel</button><button disabled={busy} onClick={submit} className="rounded-lg bg-[#690016] px-4 py-2 text-sm font-bold text-white">{busy ? 'Saving…' : 'Confirm'}</button></div></div></div>}
    {preview && <div className="fixed inset-0 z-[70] grid place-items-center bg-slate-950/70 p-3"><div role="dialog" aria-modal="true" aria-label="Document preview" className="max-h-[92dvh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-5 dark:bg-slate-900"><div className="mb-3 flex justify-between"><h2 className="font-black">{preview.name}</h2><button aria-label="Close preview" onClick={() => setPreview(null)}><FiX /></button></div>{preview.loading ? <p className="py-10 text-center">Loading file…</p> : preview.file ? <Suspense fallback={<p>Rendering PDF…</p>}><PdfPages file={preview.file} /></Suspense> : <p className="py-10 text-center text-rose-600">{preview.error}</p>}</div></div>}
  </div>;
}
