import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ChartBarIcon,
  CheckCircleIcon,
  ClipboardDocumentListIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const statusStyle = {
  assigned: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
  in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
  completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300',
};

function TaskModal({ students, onClose, onSaved }) {
  const [form, setForm] = useState({ student_id: '', title: '', description: '', due_date: '', priority: 'normal' });
  const [saving, setSaving] = useState(false);
  const submit = async (event) => {
    event.preventDefault(); setSaving(true);
    try { const response = await api.post('/supervisor/tasks', form); toast.success(response.data.message); onSaved(); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to assign task.'); }
    finally { setSaving(false); }
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}><form onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-xl rounded-3xl bg-white shadow-2xl dark:bg-slate-900"><div className="flex items-start justify-between border-b border-slate-200 p-6 dark:border-slate-700"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Task monitoring</p><h2 className="mt-1 text-2xl font-black text-slate-900 dark:text-white">Assign intern task</h2></div><button type="button" onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button></div><div className="space-y-4 p-6"><label className="block"><span className="mb-2 block text-sm font-bold">Student intern</span><select required value={form.student_id} onChange={(event) => setForm({ ...form, student_id: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="">Select student</option>{students.map((student) => <option key={student.id} value={student.id}>{student.student_id} — {student.name}</option>)}</select></label><label className="block"><span className="mb-2 block text-sm font-bold">Task title</span><input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Example: Prepare weekly accomplishment summary" /></label><label className="block"><span className="mb-2 block text-sm font-bold">Instructions</span><textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className="min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></label><div className="grid gap-4 sm:grid-cols-2"><label><span className="mb-2 block text-sm font-bold">Due date</span><input type="date" value={form.due_date} onChange={(event) => setForm({ ...form, due_date: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></label><label><span className="mb-2 block text-sm font-bold">Priority</span><select value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option></select></label></div></div><div className="flex justify-end gap-3 border-t border-slate-200 p-6 dark:border-slate-700"><button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button disabled={saving} className="rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white">{saving ? 'Assigning...' : 'Assign task'}</button></div></form></div>;
}

export default function SupervisorProgress() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [showTask, setShowTask] = useState(false);
  const [tab, setTab] = useState('interns');

  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get('/supervisor/progress')).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load assigned interns.'); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const students = useMemo(() => (data?.students || []).filter((student) => `${student.student_id} ${student.name} ${student.hte}`.toLowerCase().includes(search.toLowerCase())), [data, search]);
  const updateTask = async (task, status) => {
    try { const response = await api.put(`/supervisor/tasks/${task.id}`, { status }); toast.success(response.data.message); load(); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to update task.'); }
  };

  if (loading && !data) return <LoadingPanel />;
  return <div className="space-y-6">
    <PageIntro eyebrow="HTE supervisor portal" title="Intern Progress & Tasks" description="Monitor hours and completion, review each assigned intern, and manage workplace tasks from one connected workspace." icon={ChartBarIcon} actions={<button onClick={() => setShowTask(true)} disabled={!data?.students?.length} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#430909] disabled:opacity-50"><PlusIcon className="h-5 w-5" />Assign task</button>} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Assigned interns" value={data?.overview.assigned} icon={UserGroupIcon} /><MetricCard label="Active interns" value={data?.overview.active} icon={ChartBarIcon} tone="blue" /><MetricCard label="Open tasks" value={data?.overview.open_tasks} icon={ClipboardDocumentListIcon} tone="gold" /><MetricCard label="Completed tasks" value={data?.overview.completed_tasks} icon={CheckCircleIcon} tone="green" /></div>
    <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex flex-col gap-3 md:flex-row"><div className="flex rounded-2xl bg-slate-100 p-1 dark:bg-slate-800">{['interns', 'tasks'].map((name) => <button key={name} onClick={() => setTab(name)} className={`rounded-xl px-4 py-2 text-sm font-black capitalize ${tab === name ? 'bg-white text-[#800000] shadow-sm dark:bg-slate-700 dark:text-red-300' : 'text-slate-500'}`}>{name}</button>)}</div><label className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search assigned interns..." className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" /></label></div></section>
    {tab === 'interns' ? (students.length === 0 ? <EmptyPanel icon={UserGroupIcon} title="No assigned interns" description="The coordinator can assign an HTE supervisor from Student Deployment Management." /> : <div className="grid gap-5 xl:grid-cols-2">{students.map((student) => <article key={student.id} className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{student.student_id} · {student.program || 'Unassigned'}</p><h2 className="mt-1 text-xl font-black text-slate-900 dark:text-white">{student.name}</h2><p className="mt-1 text-sm text-slate-500">{student.hte || 'HTE not assigned'}</p></div><span className="rounded-full bg-[#800000]/10 px-3 py-1 text-xs font-black capitalize text-[#800000] dark:text-red-300">{student.internship_status}</span></div><div className="mt-6 flex items-end justify-between"><div><p className="text-3xl font-black text-slate-900 dark:text-white">{student.progress}%</p><p className="text-xs text-slate-500">{student.rendered_hours} of {student.required_hours} hours</p></div><p className="text-sm font-bold text-slate-600 dark:text-slate-300">{student.attendance_days} days present</p></div><div className="mt-3 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700"><div className="h-full rounded-full bg-gradient-to-r from-[#800000] to-[#d4af37]" style={{ width: `${student.progress}%` }} /></div><div className="mt-5 grid grid-cols-2 gap-3"><div className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p className="text-xs text-slate-500">Open tasks</p><p className="mt-1 text-xl font-black">{student.tasks.filter((task) => ['assigned', 'in_progress'].includes(task.status)).length}</p></div><div className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p className="text-xs text-slate-500">Evaluations</p><p className="mt-1 text-xl font-black">{student.evaluations.filter((evaluation) => evaluation.status === 'submitted').length}/2</p></div></div></article>)}</div>) : (
      (data?.tasks || []).length === 0 ? <EmptyPanel icon={ClipboardDocumentListIcon} title="No tasks assigned" description="Create a workplace task to begin monitoring deliverables." /> : <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="overflow-x-auto"><table className="w-full min-w-[820px]"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800"><tr><th className="px-6 py-4">Task</th><th className="px-6 py-4">Intern</th><th className="px-6 py-4">Due</th><th className="px-6 py-4">Priority</th><th className="px-6 py-4">Status</th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{data.tasks.map((task) => <tr key={task.id}><td className="px-6 py-4"><p className="font-black text-slate-900 dark:text-white">{task.title}</p><p className="mt-1 max-w-sm truncate text-xs text-slate-500">{task.description || 'No additional instructions'}</p></td><td className="px-6 py-4 text-sm font-bold">{task.student?.first_name} {task.student?.last_name}</td><td className="px-6 py-4 text-sm text-slate-500">{task.due_date ? new Date(task.due_date).toLocaleDateString() : 'No deadline'}</td><td className="px-6 py-4 text-sm font-bold capitalize">{task.priority}</td><td className="px-6 py-4"><select value={task.status} onChange={(event) => updateTask(task, event.target.value)} className={`rounded-xl border-0 px-3 py-2 text-xs font-black capitalize ${statusStyle[task.status]}`}><option value="assigned">Assigned</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></td></tr>)}</tbody></table></div></section>
    )}
    {showTask && <TaskModal students={data?.students || []} onClose={() => setShowTask(false)} onSaved={() => { setShowTask(false); load(); }} />}
  </div>;
}
