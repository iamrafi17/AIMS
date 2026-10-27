import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ArrowPathIcon, CheckCircleIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { FiEye } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import RequirementFilePreview from '../../components/requirements/RequirementFilePreview';

const field = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900';
const labels = { for_review: 'For Review', incomplete: 'Incomplete', needs_resubmission: 'Needs Resubmission', completed: 'Completed' };

function Status({ value }) {
  const color = value === 'completed' || value === 'approved' ? 'bg-emerald-100 text-emerald-700'
    : value === 'needs_resubmission' || value === 'rejected' ? 'bg-rose-100 text-rose-700'
      : 'bg-amber-100 text-amber-800';
  return <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${color}`}>{labels[value] || (value === 'pending' ? 'For Review' : value === 'missing' ? 'Missing' : value)}</span>;
}

export default function InternshipRequirements() {
  const [params, setParams] = useSearchParams();
  const [list, setList] = useState(null);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [decision, setDecision] = useState(null);
  const [feedback, setFeedback] = useState('');
  const [preview, setPreview] = useState(null);
  const previewRequest = useRef(0);
  const [search, setSearch] = useState(params.get('search') || '');
  const listParams = new URLSearchParams(params);
  listParams.delete('student');
  const listQuery = listParams.toString();
  const studentId = params.get('student');

  const load = useCallback(async () => {
    setLoading(true);
    try { setList((await api.get('/coordinator/internship-requirements', { params: Object.fromEntries(new URLSearchParams(listQuery)) })).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load requirements.'); }
    finally { setLoading(false); }
  }, [listQuery]);
  const openStudent = useCallback(async (id) => {
    try { setSelected((await api.get(`/coordinator/internship-requirements/${id}`)).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load student requirements.'); }
  }, []);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { if (studentId) openStudent(studentId); }, [studentId, openStudent]);
  useEffect(() => () => { if (preview?.url) URL.revokeObjectURL(preview.url); }, [preview]);
  const closePreview = useCallback(() => { previewRequest.current += 1; setPreview(null); }, []);
  useEffect(() => {
    if (!preview) return undefined;
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKeyDown = (event) => { if (event.key === 'Escape') closePreview(); };
    document.addEventListener('keydown', onKeyDown);
    return () => { document.body.style.overflow = previous; document.removeEventListener('keydown', onKeyDown); };
  }, [preview, closePreview]);
  const update = (key, value) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value); else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next);
  };
  const closeStudent = () => { setSelected(null); update('student', ''); };
  const showStudent = (id) => { update('student', String(id)); };
  const viewFile = async (requirement) => {
    const student = selected;
    const requestId = ++previewRequest.current;
    setPreview({ requirement, student, loading: true });
    try {
      const result = await api.get(`/coordinator/students/${student.id}/requirements/${requirement.id}/download`, { responseType: 'blob' });
      const blob = new Blob([result.data], { type: requirement.file_type === 'pdf' ? 'application/pdf' : result.data.type });
      if (requestId === previewRequest.current) setPreview({ url: URL.createObjectURL(blob), file: blob, requirement, student, type: requirement.file_type });
    } catch { if (requestId === previewRequest.current) { closePreview(); toast.error('Unable to open document.'); } }
  };
  const review = async () => {
    if (decision.value === 'rejected' && !feedback.trim()) { toast.error('Feedback is required.'); return; }
    setBusy(true);
    try {
      await api.post(`/coordinator/students/${selected.id}/requirements/${decision.requirement.id}/review`, { decision: decision.value, feedback: feedback.trim() || null });
      toast.success(`Requirement ${decision.value}.`);
      setDecision(null); setFeedback('');
      await Promise.all([openStudent(selected.id), load()]);
    } catch (error) { toast.error(error.response?.data?.message || 'Review failed.'); }
    finally { setBusy(false); }
  };
  const summary = list?.summary || {};
  const selectedDefinition = list?.definitions?.find((item) => String(item.id) === params.get('requirement_id'));
  const previewDownloadName = preview ? `${preview.requirement.requirement_name.replace(/[\\/:*?"<>|]/g, '-')}.${preview.type === 'image' ? preview.requirement.file_path?.split('.').pop() || 'png' : preview.type || 'pdf'}` : '';

  return <div className="space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-[#a8750b]">Coordinator workspace</p><h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">Internship Requirements</h1><p className="text-sm text-slate-500">Review and monitor student internship documents.</p><p className="mt-1 text-sm font-bold text-[#7D001D]">{list?.academic_period ? `AY ${list.academic_period.academic_year} · ${list.academic_period.semester}` : loading ? 'Loading academic period…' : 'No active academic period'}</p></div><button type="button" onClick={load} className="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold"><ArrowPathIcon className="h-4 w-4" /> Refresh</button></header>
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{[['Total Students', summary.total], ['For Review', summary.for_review], ['Incomplete', summary.incomplete], ['Completed', summary.completed]].map(([label, value]) => <div key={label} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800"><p className="text-xs font-bold uppercase text-slate-500">{label}</p><p className="mt-2 text-3xl font-black text-[#690016] dark:text-white">{loading ? 'Loading…' : value ?? 0}</p></div>)}</div>
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
      <div className="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-5">
        <form onSubmit={(event) => { event.preventDefault(); update('search', search.trim()); }} className="flex gap-2 xl:col-span-2"><input aria-label="Search students" className={field} placeholder="Name, student ID, or email" value={search} onChange={(event) => setSearch(event.target.value)} /><button type="submit" aria-label="Search" className="rounded-xl bg-[#7D001D] px-3 text-white"><MagnifyingGlassIcon className="h-4 w-4" /></button></form>
        <select aria-label="Status" className={field} value={params.get('status') || ''} onChange={(event) => update('status', event.target.value)}><option value="">All statuses</option>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
        <select aria-label="Section" className={field} value={params.get('section_id') || ''} onChange={(event) => update('section_id', event.target.value)}><option value="">All sections</option>{list?.sections?.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}</select>
        <select aria-label="Requirement" className={field} value={params.get('requirement_id') || ''} onChange={(event) => update('requirement_id', event.target.value)}><option value="">All requirements</option>{list?.definitions?.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
      </div>
      {loading ? <p className="p-8 text-center text-slate-500">Loading student requirements…</p> : list?.data?.length ? <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900"><tr><th className="px-5 py-3">Student</th><th className="px-5 py-3">Section</th><th className="px-5 py-3">{selectedDefinition ? selectedDefinition.name : 'Progress'}</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Action</th></tr></thead><tbody>{list.data.map((student) => <tr key={student.id} className="border-t border-slate-100 dark:border-slate-700"><td className="px-5 py-4"><b>{student.name}</b><p className="text-xs text-slate-500">{student.student_id} · {student.email}</p></td><td className="px-5 py-4">{student.section || '—'}</td><td className="px-5 py-4">{student.overview.approved}/{student.overview.total} · {student.overview.percent}%</td><td className="px-5 py-4"><Status value={student.overview.status} /></td><td className="px-5 py-4"><button type="button" onClick={() => showStudent(student.id)} title="View student requirements" aria-label={`View requirements for ${student.name}`} className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60"><FiEye aria-hidden="true" /></button></td></tr>)}</tbody></table></div> : <div className="p-10 text-center"><CheckCircleIcon className="mx-auto text-3xl text-emerald-600" /><p className="mt-2 font-bold">No matching students found.</p><button type="button" onClick={() => { setSearch(''); setParams({}); }} className="mt-3 text-sm font-bold text-[#7D001D]">Clear filters</button></div>}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-4 text-sm"><label>Entries per page <select value={params.get('per_page') || '10'} onChange={(event) => update('per_page', event.target.value)} className="ml-2 rounded-lg border px-2 py-1">{[10, 25, 50].map((n) => <option key={n}>{n}</option>)}</select></label><p>Showing {list?.total ? ((list.current_page - 1) * Number(params.get('per_page') || 10)) + 1 : 0}–{Math.min((list?.current_page || 1) * Number(params.get('per_page') || 10), list?.total || 0)} of {list?.total || 0}</p><div className="flex gap-2"><button disabled={!list || list.current_page <= 1} onClick={() => update('page', String(list.current_page - 1))}>Previous</button><button disabled={!list || list.current_page >= list.last_page} onClick={() => update('page', String(list.current_page + 1))}>Next</button></div></div>
    </section>
    {selected && <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-3"><div role="dialog" aria-modal="true" aria-label="Student requirements" className="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-xl dark:bg-slate-800"><div className="flex items-start justify-between border-b p-5"><div><h2 className="text-xl font-black text-[#690016] dark:text-white">{selected.name}</h2><p className="text-sm text-slate-500">{selected.student_id} · {selected.section}</p></div><button onClick={closeStudent} aria-label="Close student requirements" className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><XMarkIcon className="h-5 w-5" /></button></div><div className="overflow-y-auto p-5"><div className="mb-5"><div className="flex justify-between font-bold"><span>Internship Requirements</span><span>{selected.overview.approved}/{selected.overview.total} approved · {selected.overview.percent}%</span></div><div className="mt-2 h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-[#7D001D]" style={{ width: `${selected.overview.percent}%` }} /></div><p className="mt-3 text-xs text-slate-500">Missing {selected.overview.missing} · For review {selected.overview.pending} · Approved {selected.overview.approved} · Rejected {selected.overview.rejected}</p></div><div className="space-y-3">{selected.requirements.map((item, index) => { const status = item.file_path ? item.status : 'missing'; return <article key={item.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div className="flex flex-wrap items-center justify-between gap-2"><b>{String(index + 1).padStart(2, '0')} {item.requirement_name}</b><Status value={status} /></div><p className="mt-2 text-xs text-slate-500">{item.file_path ? `${item.file_type?.toUpperCase() || 'FILE'} · Submitted ${new Date(item.updated_at || item.created_at).toLocaleDateString()}` : 'Waiting for student submission.'}</p>{item.reviewer && <p className="mt-1 text-xs text-slate-500">Reviewed by {item.reviewer.name} · {new Date(item.reviewed_at).toLocaleDateString()}</p>}{item.feedback && <p className="mt-2 rounded-lg bg-rose-50 p-2 text-sm text-rose-700">Feedback: {item.feedback}</p>}<div className="mt-3 flex gap-2">{item.file_path && <button onClick={() => viewFile(item)} title="View document" aria-label={`View ${item.requirement_name}`} className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60"><FiEye aria-hidden="true" /></button>}{status === 'pending' && <><button onClick={() => setDecision({ value: 'rejected', requirement: item })} className="rounded-lg bg-rose-100 px-3 py-1.5 text-sm text-rose-700">Reject</button><button onClick={() => setDecision({ value: 'approved', requirement: item })} className="rounded-lg bg-emerald-100 px-3 py-1.5 text-sm text-emerald-700">Approve</button></>}</div></article>; })}</div></div></div></div>}
    {decision && <div className="fixed inset-0 z-[90] grid place-items-center bg-slate-950/60 p-3"><div role="dialog" aria-modal="true" aria-label="Review requirement" className="w-full max-w-md rounded-2xl bg-white p-5 dark:bg-slate-800"><h2 className="text-lg font-black">{decision.value === 'approved' ? 'Approve' : 'Reject'} Requirement?</h2><p className="mt-2 text-sm">{decision.requirement.requirement_name} · {selected.name}</p>{decision.value === 'rejected' && <textarea aria-label="Reason or feedback" required value={feedback} onChange={(event) => setFeedback(event.target.value)} className={`${field} mt-4`} rows={4} placeholder="Reason / feedback" />}<div className="mt-5 flex justify-end gap-2"><button disabled={busy} onClick={() => { setDecision(null); setFeedback(''); }} className="rounded-lg border px-4 py-2">Cancel</button><button disabled={busy} onClick={review} className="rounded-lg bg-[#7D001D] px-4 py-2 font-bold text-white">Confirm</button></div></div></div>}
    {preview && <RequirementFilePreview preview={preview} onClose={closePreview} downloadName={previewDownloadName} />}
  </div>;
}
