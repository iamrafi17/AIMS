import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  CheckCircleIcon,
  ClockIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  ShieldExclamationIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import UserAvatar from '../../components/UserAvatar';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

export default function ProgramHeadStudentMonitoring() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [selected, setSelected] = useState(null);
  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get('/program-head/students', { params: { search: search || undefined, status: status || undefined } })).data); }
    catch { toast.error('Unable to load student monitoring.'); }
    finally { setLoading(false); }
  }, [search, status]);
  useEffect(() => { const timer = setTimeout(load, 250); return () => clearTimeout(timer); }, [load]);
  const students = useMemo(() => data?.students || [], [data]);
  if (loading && !data) return <LoadingPanel />;
  return <div className="space-y-6">
    <PageIntro eyebrow="Program oversight" title="Student Internship Monitoring" description="Read-only program-level monitoring of deployment, schedule approval, attendance, requirements, supervisors, and OJT completion." icon={UserGroupIcon} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><MetricCard label="All student interns" value={data?.summary.total} icon={UserGroupIcon} /><MetricCard label="Active" value={data?.summary.active} icon={ClockIcon} tone="blue" /><MetricCard label="Near completion" value={data?.summary.near_completion} icon={ClockIcon} tone="gold" /><MetricCard label="Completed" value={data?.summary.completed} icon={CheckCircleIcon} tone="green" /><MetricCard label="Needs attention" value={data?.summary.needs_attention} icon={ShieldExclamationIcon} /></div>
    <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex flex-col gap-3 md:flex-row"><label className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" placeholder="Search student name or ID..." /></label><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 md:min-w-48"><option value="">All internship statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option><option value="dropped">Dropped</option></select></div></section>
    {loading ? <LoadingPanel /> : students.length === 0 ? <EmptyPanel icon={UserGroupIcon} title="No students found" description="Student internship records will appear here after registration." /> : <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="overflow-x-auto"><table className="w-full min-w-[1050px]"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800"><tr><th className="px-6 py-4">Student</th><th className="px-6 py-4">Deployment</th><th className="px-6 py-4">Progress</th><th className="px-6 py-4">Attendance</th><th className="px-6 py-4">Requirements</th><th className="px-6 py-4">Status</th><th className="px-6 py-4"></th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{students.map((student) => <tr key={student.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td className="px-6 py-4"><div className="flex items-center gap-3"><UserAvatar user={{ name: student.name, avatar_url: student.avatar_url }} className="h-10 w-10" /><div><p className="font-black">{student.name}</p><p className="text-xs text-slate-500">{student.student_id} · {student.program} {student.section}</p></div></div></td><td className="px-6 py-4"><p className="text-sm font-bold">{student.hte || 'Not deployed'}</p><p className="mt-1 text-xs text-slate-500">{student.supervisor || 'No supervisor assigned'}</p></td><td className="w-48 px-6 py-4"><div className="mb-1.5 flex justify-between text-xs"><span>{student.rendered_hours}/{student.required_hours}h</span><b>{student.progress}%</b></div><div className="h-2 rounded-full bg-slate-100 dark:bg-slate-700"><div className="h-full rounded-full bg-gradient-to-r from-[#800000] to-[#d4af37]" style={{ width: `${student.progress}%` }} /></div></td><td className="px-6 py-4 text-sm font-black">{student.attendance_days} days</td><td className="px-6 py-4 text-sm font-black">{student.requirements_approved}/{student.requirements_total}</td><td className="px-6 py-4"><span className="rounded-full bg-[#800000]/10 px-3 py-1 text-xs font-black capitalize text-[#800000] dark:text-red-300">{student.internship_status}</span></td><td className="px-6 py-4"><button onClick={() => setSelected(student)} className="rounded-xl bg-[#800000] p-2 text-white"><EyeIcon className="h-5 w-5" /></button></td></tr>)}</tbody></table></div></section>}
    {selected && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={() => setSelected(null)}><div onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-2xl rounded-3xl bg-white p-7 shadow-2xl dark:bg-slate-900"><div className="flex justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{selected.student_id}</p><h2 className="mt-1 text-2xl font-black">{selected.name}</h2></div><button onClick={() => setSelected(null)}><XMarkIcon className="h-6 w-6" /></button></div><dl className="mt-6 grid gap-3 sm:grid-cols-2">{[['Academic assignment', `${selected.college} · ${selected.program} · ${selected.section}`], ['HTE deployment', selected.hte || 'Not deployed'], ['HTE supervisor', selected.supervisor || 'Not assigned'], ['Schedule approval', selected.schedule_status], ['Attendance days', selected.attendance_days], ['OJT hours', `${selected.rendered_hours} / ${selected.required_hours}`], ['Requirements', `${selected.requirements_approved} / ${selected.requirements_total} approved`], ['Internship status', selected.internship_status]].map(([label, value]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 font-black capitalize">{value}</dd></div>)}</dl></div></div>}
  </div>;
}
