import { useCallback, useEffect, useState } from 'react';
import {
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  FunnelIcon,
  MapPinIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

function time(value) {
  return value ? new Date(value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '—';
}
function hours(record) {
  const diff = (start, end) => start && end ? Math.max((new Date(end) - new Date(start)) / 3600000, 0) : 0;
  return (diff(record.am_time_in, record.am_time_out) + diff(record.pm_time_in, record.pm_time_out) + Number(record.overtime_hours || 0)).toFixed(1);
}

function ReviewModal({ record, onClose, onSaved }) {
  const [status, setStatus] = useState(record.supervisor_review_status === 'flagged' ? 'flagged' : 'approved');
  const [feedback, setFeedback] = useState(record.supervisor_feedback || '');
  const [saving, setSaving] = useState(false);
  const submit = async () => {
    setSaving(true);
    try { const response = await api.put(`/supervisor/attendance/${record.id}/review`, { status, feedback }); toast.success(response.data.message); onSaved(); }
    catch (error) { const errors = error.response?.data?.errors; toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'Unable to save review.'); }
    finally { setSaving(false); }
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}><div onMouseDown={(event) => event.stopPropagation()} className="max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8"><div className="flex items-start justify-between"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">{record.student?.student_id} · {new Date(record.date).toLocaleDateString()}</p><h2 className="mt-1 text-2xl font-black text-slate-900 dark:text-white">{record.student?.first_name} {record.student?.last_name}</h2></div><button onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button></div><div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">{[['AM In', time(record.am_time_in)], ['AM Out', time(record.am_time_out)], ['PM In', time(record.pm_time_in)], ['PM Out', time(record.pm_time_out)]].map(([label, value]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 text-center dark:bg-slate-800"><p className="text-xs font-bold text-slate-500">{label}</p><p className="mt-1 font-black text-slate-900 dark:text-white">{value}</p></div>)}</div><div className="mt-5 grid gap-4 md:grid-cols-2"><div className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><h3 className="font-black text-slate-900 dark:text-white">AM accomplishment</h3><p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{record.am_activity || 'No AM accomplishment submitted.'}</p></div><div className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><h3 className="font-black text-slate-900 dark:text-white">PM accomplishment</h3><p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{record.pm_activity || 'No PM accomplishment submitted.'}</p></div></div><div className="mt-5 grid gap-3 sm:grid-cols-2">{[['approved', CheckCircleIcon, 'Approve record', 'Attendance and accomplishments are consistent.'], ['flagged', ExclamationTriangleIcon, 'Flag for review', 'Send a concern to the internship coordinator.']].map(([value, Icon, label, text]) => <button key={value} onClick={() => setStatus(value)} className={`rounded-2xl border p-4 text-left transition ${status === value ? value === 'approved' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : 'border-rose-500 bg-rose-50 dark:bg-rose-950/30' : 'border-slate-200 dark:border-slate-700'}`}><div className="flex items-center gap-2"><Icon className={`h-5 w-5 ${value === 'approved' ? 'text-emerald-600' : 'text-rose-600'}`} /><span className="font-black">{label}</span></div><p className="mt-1 text-xs text-slate-500">{text}</p></button>)}</div><label className="mt-5 block"><span className="mb-2 block text-sm font-bold">Supervisor feedback {status === 'flagged' && '*'}</span><textarea required={status === 'flagged'} value={feedback} onChange={(event) => setFeedback(event.target.value)} className="min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Add a review note or describe the concern..." /></label><div className="mt-6 flex justify-end gap-3"><button onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button onClick={submit} disabled={saving || (status === 'flagged' && !feedback.trim())} className="rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white disabled:opacity-50">{saving ? 'Saving...' : 'Save review'}</button></div></div></div>;
}

export default function SupervisorAttendance() {
  const [payload, setPayload] = useState({ records: { data: [] }, students: [] });
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ student_id: '', status: '', review_status: '', date_from: '', date_to: '' });
  const [selected, setSelected] = useState(null);
  const load = useCallback(async () => {
    setLoading(true);
    try { setPayload((await api.get('/supervisor/attendance', { params: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) })).data); }
    catch { toast.error('Unable to load attendance records.'); }
    finally { setLoading(false); }
  }, [filters]);
  useEffect(() => { const timer = setTimeout(load, 200); return () => clearTimeout(timer); }, [load]);
  const records = payload.records?.data || [];
  const pending = records.filter((record) => record.supervisor_review_status === 'pending').length;
  const approved = records.filter((record) => record.supervisor_review_status === 'approved').length;
  const flagged = records.filter((record) => record.supervisor_review_status === 'flagged').length;

  return <div className="space-y-6">
    <PageIntro eyebrow="Attendance oversight" title="Attendance & Journal Review" description="Review assigned interns’ daily time logs, accomplishment reports, overtime, and location-supported records." icon={ClockIcon} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Records shown" value={records.length} icon={UserGroupIcon} /><MetricCard label="Pending review" value={pending} icon={ClockIcon} tone="gold" /><MetricCard label="Approved" value={approved} icon={CheckCircleIcon} tone="green" /><MetricCard label="Flagged" value={flagged} icon={ExclamationTriangleIcon} tone="maroon" /></div>
    <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="mb-3 flex items-center gap-2 text-sm font-black text-slate-700 dark:text-slate-200"><FunnelIcon className="h-5 w-5 text-[#800000] dark:text-red-300" />Review filters</div><div className="grid gap-3 md:grid-cols-3 xl:grid-cols-5"><select value={filters.student_id} onChange={(event) => setFilters({ ...filters, student_id: event.target.value })} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="">All interns</option>{payload.students.map((student) => <option key={student.id} value={student.id}>{student.student_id} — {student.name}</option>)}</select><select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="">All attendance</option><option value="present">Present</option><option value="absent">Absent</option><option value="holiday">Holiday</option><option value="late">Late</option></select><select value={filters.review_status} onChange={(event) => setFilters({ ...filters, review_status: event.target.value })} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="">All reviews</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="flagged">Flagged</option></select><input type="date" value={filters.date_from} onChange={(event) => setFilters({ ...filters, date_from: event.target.value })} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /><input type="date" value={filters.date_to} onChange={(event) => setFilters({ ...filters, date_to: event.target.value })} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></div></section>
    {loading ? <LoadingPanel /> : records.length === 0 ? <EmptyPanel icon={ClockIcon} title="No attendance records" description="Attendance for your assigned interns will appear here." /> : <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="overflow-x-auto"><table className="w-full min-w-[1000px]"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800"><tr><th className="px-6 py-4">Intern</th><th className="px-6 py-4">Date</th><th className="px-6 py-4">Attendance</th><th className="px-6 py-4">Time record</th><th className="px-6 py-4">Hours</th><th className="px-6 py-4">Review</th><th className="px-6 py-4"></th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{records.map((record) => <tr key={record.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td className="px-6 py-4"><p className="font-black text-slate-900 dark:text-white">{record.student?.first_name} {record.student?.last_name}</p><p className="mt-1 text-xs text-slate-500">{record.student?.student_id} · {record.student?.program?.code}</p></td><td className="px-6 py-4 text-sm font-bold">{new Date(record.date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}</td><td className="px-6 py-4"><span className="rounded-full bg-[#800000]/8 px-3 py-1 text-xs font-black capitalize text-[#800000] dark:text-red-300">{record.status} · {record.work_mode?.toUpperCase()}</span></td><td className="px-6 py-4 text-xs text-slate-500">{time(record.am_time_in)}–{time(record.am_time_out)} / {time(record.pm_time_in)}–{time(record.pm_time_out)}{record.latitude_in && <span className="mt-1 flex items-center gap-1 text-blue-600"><MapPinIcon className="h-3.5 w-3.5" />Location captured</span>}</td><td className="px-6 py-4 font-black">{hours(record)}h</td><td className="px-6 py-4"><span className={`rounded-full px-3 py-1 text-xs font-black capitalize ${record.supervisor_review_status === 'approved' ? 'bg-emerald-100 text-emerald-700' : record.supervisor_review_status === 'flagged' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>{record.supervisor_review_status}</span></td><td className="px-6 py-4 text-right"><button onClick={() => setSelected(record)} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-3 py-2 text-xs font-black text-white"><EyeIcon className="h-4 w-4" />Review</button></td></tr>)}</tbody></table></div></section>}
    {selected && <ReviewModal record={selected} onClose={() => setSelected(null)} onSaved={() => { setSelected(null); load(); }} />}
  </div>;
}
