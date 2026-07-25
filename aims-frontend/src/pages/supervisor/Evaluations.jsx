import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  CheckBadgeIcon,
  ClockIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  StarIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const criteria = [
  ['work_quality', 'Work quality'],
  ['communication', 'Communication'],
  ['professionalism', 'Professionalism'],
  ['attendance', 'Attendance & punctuality'],
  ['technical_skills', 'Technical skills'],
  ['teamwork', 'Teamwork'],
];

function EvaluationModal({ student, type, existing, onClose, onSaved }) {
  const [form, setForm] = useState(() => ({
    student_id: student.id,
    evaluation_type: type,
    ...Object.fromEntries(criteria.map(([key]) => [key, existing?.[key] || 3])),
    recommendations: existing?.recommendations || '',
    feedback: existing?.feedback || '',
    status: existing?.status === 'submitted' ? 'submitted' : 'draft',
  }));
  const [saving, setSaving] = useState(false);
  const save = async (status) => {
    if (status === 'submitted' && !window.confirm('Submit this evaluation? It will be shared as an official supervisor evaluation.')) return;
    setSaving(true);
    try { const response = await api.post('/supervisor/evaluations', { ...form, status }); toast.success(response.data.message); onSaved(); }
    catch (error) { const errors = error.response?.data?.errors; toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'Unable to save evaluation.'); }
    finally { setSaving(false); }
  };
  const average = (criteria.reduce((total, [key]) => total + Number(form[key]), 0) / criteria.length).toFixed(1);
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}><div onMouseDown={(event) => event.stopPropagation()} className="flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"><div className="flex items-start justify-between border-b border-slate-200 px-6 py-5 dark:border-slate-700"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">{type} evaluation · {student.student_id}</p><h2 className="mt-1 text-2xl font-black text-slate-900 dark:text-white">{student.name}</h2></div><button onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button></div><div className="min-h-0 flex-1 overflow-y-auto p-6"><div className="mb-6 flex items-center justify-between rounded-2xl bg-gradient-to-r from-[#800000]/8 to-amber-50 p-4 dark:from-red-400/10 dark:to-amber-950/20"><div><p className="text-sm font-bold text-slate-600 dark:text-slate-300">Current average rating</p><p className="mt-1 text-3xl font-black text-[#800000] dark:text-red-300">{average} / 5.0</p></div><StarIcon className="h-10 w-10 text-[#d4af37]" /></div><div className="grid gap-4 md:grid-cols-2">{criteria.map(([key, label]) => <div key={key} className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><div className="flex items-center justify-between"><label className="font-bold text-slate-800 dark:text-white">{label}</label><span className="text-lg font-black text-[#800000] dark:text-red-300">{form[key]}</span></div><div className="mt-3 flex gap-2">{[1, 2, 3, 4, 5].map((score) => <button type="button" key={score} onClick={() => setForm({ ...form, [key]: score })} className={`grid h-10 flex-1 place-items-center rounded-xl text-sm font-black transition ${score <= form[key] ? 'bg-[#800000] text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800'}`}>{score}</button>)}</div></div>)}</div><div className="mt-5 grid gap-5 md:grid-cols-2"><label><span className="mb-2 block text-sm font-bold">Performance feedback</span><textarea value={form.feedback} onChange={(event) => setForm({ ...form, feedback: event.target.value })} className="min-h-32 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Describe strengths and areas for improvement..." /></label><label><span className="mb-2 block text-sm font-bold">Recommendations</span><textarea value={form.recommendations} onChange={(event) => setForm({ ...form, recommendations: event.target.value })} className="min-h-32 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Recommended next steps or development activities..." /></label></div></div><div className="flex flex-col-reverse gap-3 border-t border-slate-200 p-5 dark:border-slate-700 sm:flex-row sm:justify-end"><button onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button disabled={saving} onClick={() => save('draft')} className="rounded-2xl border border-[#800000] px-5 py-3 font-bold text-[#800000] dark:text-red-300">Save draft</button><button disabled={saving} onClick={() => save('submitted')} className="rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white">{saving ? 'Saving...' : 'Submit evaluation'}</button></div></div></div>;
}

export default function SupervisorEvaluations() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modal, setModal] = useState(null);
  const load = useCallback(async () => { setLoading(true); try { setData((await api.get('/supervisor/evaluations')).data); } catch { toast.error('Unable to load evaluations.'); } finally { setLoading(false); } }, []);
  useEffect(() => { load(); }, [load]);
  const students = useMemo(() => (data?.students || []).filter((student) => `${student.student_id} ${student.name}`.toLowerCase().includes(search.toLowerCase())), [data, search]);
  const evaluations = data?.evaluations || [];
  const submitted = evaluations.filter((item) => item.status === 'submitted').length;
  const open = (student, type) => setModal({ student, type, existing: evaluations.find((item) => item.student_id === student.id && item.evaluation_type === type) });
  if (loading && !data) return <LoadingPanel />;
  return <div className="space-y-6">
    <PageIntro eyebrow="Performance management" title="Student Evaluations" description="Complete structured midterm and final performance evaluations for every intern assigned to you." icon={StarIcon} />
    <div className="grid gap-4 sm:grid-cols-3"><MetricCard label="Assigned interns" value={data?.students?.length || 0} icon={UserGroupIcon} /><MetricCard label="Submitted evaluations" value={submitted} icon={CheckBadgeIcon} tone="green" /><MetricCard label="Remaining evaluations" value={Math.max((data?.students?.length || 0) * 2 - submitted, 0)} icon={ClockIcon} tone="gold" /></div>
    <label className="relative block rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><MagnifyingGlassIcon className="absolute left-8 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" placeholder="Search student intern..." /></label>
    {students.length === 0 ? <EmptyPanel icon={StarIcon} title="No interns to evaluate" description="Assigned interns will appear here after coordinator deployment." /> : <div className="grid gap-5 xl:grid-cols-2">{students.map((student) => <article key={student.id} className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{student.student_id} · {student.program}</p><h2 className="mt-1 text-xl font-black text-slate-900 dark:text-white">{student.name}</h2><p className="mt-1 text-sm text-slate-500">{student.hte || 'HTE not assigned'} · {student.progress}% OJT complete</p></div><div className="mt-6 grid gap-3 sm:grid-cols-2">{['midterm', 'final'].map((type) => { const evaluation = evaluations.find((item) => item.student_id === student.id && item.evaluation_type === type); return <button key={type} onClick={() => open(student, type)} className="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-[#800000]/40 hover:bg-[#800000]/5 dark:border-slate-700"><div className="flex items-center justify-between"><span className="font-black capitalize text-slate-800 dark:text-white">{type} evaluation</span><PencilSquareIcon className="h-5 w-5 text-[#800000] dark:text-red-300" /></div><span className={`mt-3 inline-block rounded-full px-3 py-1 text-xs font-bold capitalize ${evaluation?.status === 'submitted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : evaluation ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300'}`}>{evaluation?.status || 'Not started'}</span></button>; })}</div></article>)}</div>}
    {modal && <EvaluationModal {...modal} onClose={() => setModal(null)} onSaved={() => { setModal(null); load(); }} />}
  </div>;
}
