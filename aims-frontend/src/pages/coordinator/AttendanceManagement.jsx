import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  FiAlertCircle,
  FiBarChart2,
  FiBookOpen,
  FiCalendar,
  FiCheck,
  FiChevronLeft,
  FiChevronRight,
  FiClock,
  FiEdit2,
  FiEye,
  FiSearch,
  FiShield,
  FiTrash2,
  FiX,
} from 'react-icons/fi';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptySummary = {
  total: 0, present: 0, late: 0, absent: 0, holiday: 0, verified: 0,
  pending_verification: 0, attendance_rate: 0, total_hours: 0,
  journals_submitted: 0, journals_pending: 0,
};

const badgeStyles = {
  present: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  late: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  absent: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  holiday: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
};

function Badge({ value }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${badgeStyles[value] || badgeStyles.pending}`}>{value || 'pending'}</span>;
}

function Modal({ title, subtitle, onClose, children, width = 'max-w-4xl' }) {
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previous; };
  }, []);

  return <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5">
    <div role="dialog" aria-modal="true" aria-label={title} className={`flex max-h-[calc(100vh-1.5rem)] w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800 ${width}`}>
      <header className="flex shrink-0 items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700">
        <div><h2 className="text-lg font-black text-[#430909] dark:text-white">{title}</h2>{subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}</div>
        <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close"><FiX /></button>
      </header>
      <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
    </div>
  </div>;
}

function Field({ label, children }) {
  return <label><span className="mb-1.5 block text-[10px] font-black uppercase tracking-wide text-slate-400">{label}</span>{children}</label>;
}

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const formatTime = (value) => value ? new Date(value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '—';
const toLocalInput = (value) => {
  if (!value) return '';
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

function AttendanceManagement() {
  const [records, setRecords] = useState([]);
  const [summary, setSummary] = useState(emptySummary);
  const [analytics, setAnalytics] = useState({ status_breakdown: [], work_modes: [], daily_hours: [] });
  const [tab, setTab] = useState('logs');
  const [filters, setFilters] = useState({ search: '', status: '', verification: '', journal: '', date_from: '', date_to: '' });
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState(null);
  const [editor, setEditor] = useState(null);

  const fetchRecords = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/attendance', { params: { ...filters, page } });
      setRecords(response.data.data || []);
      setSummary(response.data.summary || emptySummary);
      setAnalytics(response.data.analytics || { status_breakdown: [], work_modes: [], daily_hours: [] });
      setLastPage(response.data.last_page || 1);
      setTotal(response.data.total || 0);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load attendance records.');
    } finally {
      setLoading(false);
    }
  }, [filters, page]);

  useEffect(() => {
    const timer = window.setTimeout(fetchRecords, 250);
    return () => window.clearTimeout(timer);
  }, [fetchRecords]);

  const updateFilter = (name, value) => {
    setPage(1);
    setFilters((current) => ({ ...current, [name]: value }));
  };

  const openDetails = async (record) => {
    try {
      const response = await api.get(`/coordinator/attendance/${record.id}`);
      setSelected(response.data);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load attendance details.');
    }
  };

  const verifyAttendance = async (record, verified = true) => {
    setBusy(true);
    try {
      const response = await api.put(`/coordinator/attendance/${record.id}/verify`, { verified });
      toast.success(response.data.message);
      setSelected((current) => current?.id === record.id ? response.data.attendance : current);
      fetchRecords();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update verification.');
    } finally {
      setBusy(false);
    }
  };

  const reviewJournal = async (record, decision) => {
    let feedback = '';
    if (decision === 'rejected') {
      feedback = window.prompt('Explain what the student must correct:') || '';
      if (!feedback.trim()) return;
    }
    setBusy(true);
    try {
      const response = await api.post(`/coordinator/attendance/${record.id}/journal-review`, { decision, feedback: feedback || null });
      toast.success(response.data.message);
      setSelected((current) => current?.id === record.id ? response.data.attendance : current);
      fetchRecords();
    } catch (error) {
      const validation = error.response?.data?.errors;
      toast.error(validation ? Object.values(validation)[0]?.[0] : error.response?.data?.message || 'Unable to review journal.');
    } finally {
      setBusy(false);
    }
  };

  const startEdit = (record) => {
    setSelected(null);
    setEditor({
      ...record,
      am_time_in: toLocalInput(record.am_time_in),
      am_time_out: toLocalInput(record.am_time_out),
      pm_time_in: toLocalInput(record.pm_time_in),
      pm_time_out: toLocalInput(record.pm_time_out),
      ot_start: toLocalInput(record.ot_start),
      ot_end: toLocalInput(record.ot_end),
    });
  };

  const saveAttendance = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      const response = await api.put(`/coordinator/attendance/${editor.id}`, {
        status: editor.status,
        work_mode: editor.work_mode,
        session_type: editor.session_type,
        am_time_in: editor.am_time_in || null,
        am_time_out: editor.am_time_out || null,
        pm_time_in: editor.pm_time_in || null,
        pm_time_out: editor.pm_time_out || null,
        ot_start: editor.ot_start || null,
        ot_end: editor.ot_end || null,
        overtime_hours: Number(editor.overtime_hours || 0),
        am_activity: editor.am_activity || null,
        pm_activity: editor.pm_activity || null,
      });
      toast.success(response.data.message);
      setEditor(null);
      fetchRecords();
    } catch (error) {
      const validation = error.response?.data?.errors;
      toast.error(validation ? Object.values(validation)[0]?.[0] : error.response?.data?.message || 'Unable to update attendance.');
    } finally {
      setBusy(false);
    }
  };

  const deleteAttendance = async (record) => {
    if (!window.confirm(`Delete ${record.student?.first_name} ${record.student?.last_name}'s attendance for ${formatDate(record.date)}?`)) return;
    setBusy(true);
    try {
      const response = await api.delete(`/coordinator/attendance/${record.id}`);
      toast.success(response.data.message);
      setSelected(null);
      fetchRecords();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to delete attendance.');
    } finally {
      setBusy(false);
    }
  };

  const journalRows = useMemo(() => records.filter((record) => record.journal_submitted || ['present', 'late'].includes(record.status)), [records]);
  const verifiedPercent = summary.total ? Math.round((summary.verified / summary.total) * 100) : 0;
  const chartTooltipStyle = { borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 };

  return <div className="space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-4">
      <div><p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Coordinator Portal</p><h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">Attendance & Journal Monitoring</h1><p className="mt-1 text-sm text-slate-400">Review time logs, verify attendance, and evaluate daily accomplishment reports.</p></div>
      <div className="rounded-2xl bg-[#800000] px-5 py-3 text-white shadow-lg shadow-[#800000]/15"><p className="text-[10px] font-black uppercase tracking-wider text-white/60">Filtered Total</p><p className="text-xl font-black">{summary.total_hours} hours</p></div>
    </header>

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      {[
        ['Attendance Records', summary.total, FiCalendar, 'text-[#800000] bg-rose-50 dark:bg-rose-950/40'],
        ['Attendance Rate', `${summary.attendance_rate}%`, FiBarChart2, 'text-blue-600 bg-blue-50 dark:bg-blue-950/40'],
        ['Verified Logs', `${summary.verified}/${summary.total}`, FiShield, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'],
        ['Submitted Journals', summary.journals_submitted, FiBookOpen, 'text-violet-600 bg-violet-50 dark:bg-violet-950/40'],
        ['Pending Reviews', summary.journals_pending, FiAlertCircle, 'text-amber-600 bg-amber-50 dark:bg-amber-950/40'],
      ].map(([label, value, Icon, colors]) => <article key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center gap-3"><span className={`grid h-10 w-10 place-items-center rounded-xl ${colors}`}><Icon /></span><div><p className="text-xl font-black text-slate-800 dark:text-white">{value}</p><p className="text-[10px] font-black uppercase tracking-wide text-slate-400">{label}</p></div></div></article>)}
    </section>

    <section className="rounded-3xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="grid gap-3 xl:grid-cols-[1fr_repeat(5,minmax(130px,auto))]">
        <label className="relative"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={filters.search} onChange={(event) => updateFilter('search', event.target.value)} placeholder="Search student name or ID..." className={`${inputClass} pl-10`} /></label>
        <select value={filters.status} onChange={(event) => updateFilter('status', event.target.value)} className={inputClass}><option value="">All statuses</option><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="holiday">Holiday</option></select>
        <select value={filters.verification} onChange={(event) => updateFilter('verification', event.target.value)} className={inputClass}><option value="">All verification</option><option value="pending">Pending</option><option value="verified">Verified</option></select>
        <select value={filters.journal} onChange={(event) => updateFilter('journal', event.target.value)} className={inputClass}><option value="">All journals</option><option value="submitted">Submitted</option><option value="missing">Missing</option><option value="pending">Awaiting review</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
        <input type="date" value={filters.date_from} onChange={(event) => updateFilter('date_from', event.target.value)} className={inputClass} title="Start date" />
        <input type="date" value={filters.date_to} onChange={(event) => updateFilter('date_to', event.target.value)} className={inputClass} title="End date" />
      </div>
    </section>

    <nav className="flex w-fit max-w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 dark:bg-gray-800">
      {[
        ['logs', 'Attendance Logs', FiClock],
        ['journals', 'Daily Journals', FiBookOpen],
        ['analytics', 'Analytics', FiBarChart2],
      ].map(([value, label, Icon]) => <button key={value} onClick={() => setTab(value)} className={`inline-flex whitespace-nowrap items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black transition ${tab === value ? 'bg-white text-[#800000] shadow-sm dark:bg-gray-700 dark:text-rose-300' : 'text-slate-500'}`}><Icon /> {label}</button>)}
    </nav>

    {tab === 'logs' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">Student</th><th className="px-4 py-4">Date & Mode</th><th className="px-4 py-4">Time Record</th><th className="px-4 py-4">Hours</th><th className="px-4 py-4">Status</th><th className="px-4 py-4">Verification</th><th className="px-5 py-4 text-right">Actions</th></tr></thead>
        <tbody>{loading ? <tr><td colSpan="7" className="py-16 text-center text-slate-400">Loading attendance records...</td></tr> : records.length ? records.map((record) => <tr key={record.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{record.student?.last_name}, {record.student?.first_name}</p><p className="mt-1 text-[11px] text-slate-400">{record.student?.student_id} · {record.student?.program?.code}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{formatDate(record.date)}</p><p className="mt-1 text-[11px] uppercase text-slate-400">{record.work_mode} · {record.session_type?.replace('_', ' ')}</p></td><td className="px-4 py-4 text-[11px] text-slate-500"><p>AM {formatTime(record.am_time_in)} – {formatTime(record.am_time_out)}</p><p className="mt-1">PM {formatTime(record.pm_time_in)} – {formatTime(record.pm_time_out)}</p></td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{record.total_hours}h</p><p className="text-[10px] text-slate-400">{record.overtime_hours || 0}h OT</p></td><td className="px-4 py-4"><Badge value={record.status} /></td><td className="px-4 py-4">{record.is_verified ? <div className="flex items-center gap-2 text-xs font-black text-emerald-600"><FiCheck /> Verified</div> : <button disabled={busy} onClick={() => verifyAttendance(record)} className="rounded-lg bg-amber-100 px-3 py-2 text-xs font-black text-amber-700">Verify</button>}</td><td className="px-5 py-4"><div className="flex justify-end gap-1.5"><button onClick={() => openDetails(record)} className="rounded-lg bg-blue-100 p-2 text-blue-700" title="View"><FiEye /></button><button onClick={() => startEdit(record)} className="rounded-lg bg-amber-100 p-2 text-amber-700" title="Edit"><FiEdit2 /></button><button onClick={() => deleteAttendance(record)} className="rounded-lg bg-rose-100 p-2 text-rose-700" title="Delete"><FiTrash2 /></button></div></td></tr>) : <tr><td colSpan="7" className="py-16 text-center text-slate-400">No attendance logs match the selected filters.</td></tr>}</tbody>
      </table></div>
    </section>}

    {tab === 'journals' && <section className="grid gap-4 lg:grid-cols-2">
      {loading ? <div className="col-span-full rounded-3xl bg-white py-16 text-center text-slate-400 dark:bg-gray-800">Loading journals...</div> : journalRows.length ? journalRows.map((record) => <article key={record.id} className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-800 dark:text-white">{record.student?.first_name} {record.student?.last_name}</p><p className="mt-1 text-[11px] text-slate-400">{record.student?.student_id} · {formatDate(record.date)} · {record.session_type?.replace('_', ' ')}</p></div>{record.journal_submitted ? <Badge value={record.journal_status} /> : <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-500 dark:bg-gray-700">Missing</span>}</div><div className="mt-4 grid gap-3 sm:grid-cols-2"><div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">AM Activities</p><p className="mt-2 line-clamp-3 text-xs leading-5 text-slate-600 dark:text-gray-300">{record.am_activity || 'No AM report submitted.'}</p></div><div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">PM Activities</p><p className="mt-2 line-clamp-3 text-xs leading-5 text-slate-600 dark:text-gray-300">{record.pm_activity || 'No PM report submitted.'}</p></div></div>{record.journal_feedback && <p className="mt-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">Feedback: {record.journal_feedback}</p>}<div className="mt-4 flex flex-wrap items-center justify-between gap-2"><button onClick={() => openDetails(record)} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-gray-600"><FiEye /> Review details</button>{record.journal_submitted && <div className="flex gap-2"><button disabled={busy} onClick={() => reviewJournal(record, 'approved')} className="rounded-lg bg-emerald-100 px-3 py-2 text-xs font-black text-emerald-700">Approve</button><button disabled={busy} onClick={() => reviewJournal(record, 'rejected')} className="rounded-lg bg-rose-100 px-3 py-2 text-xs font-black text-rose-700">Reject</button></div>}</div></article>) : <div className="col-span-full rounded-3xl border border-dashed border-slate-300 py-16 text-center text-slate-400 dark:border-gray-700">No journal records match the selected filters.</div>}
    </section>}

    {tab === 'analytics' && <section className="grid gap-5 xl:grid-cols-2">
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div><h2 className="font-black text-slate-800 dark:text-white">Attendance Distribution</h2><p className="mt-1 text-xs text-slate-400">Status breakdown for the selected date range</p></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={analytics.status_breakdown} dataKey="value" nameKey="name" innerRadius={55} outerRadius={90} paddingAngle={3}>{analytics.status_breakdown.map((item) => <Cell key={item.name} fill={item.color} />)}</Pie><Tooltip contentStyle={chartTooltipStyle} /><Legend /></PieChart></ResponsiveContainer></div></article>
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div><h2 className="font-black text-slate-800 dark:text-white">Work Mode Usage</h2><p className="mt-1 text-xs text-slate-400">WFO, WFH, and field-work records</p></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><BarChart data={analytics.work_modes}><CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" /><XAxis dataKey="name" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip contentStyle={chartTooltipStyle} /><Bar dataKey="value" name="Records" fill="#800000" radius={[8, 8, 0, 0]} /></BarChart></ResponsiveContainer></div></article>
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-700 dark:bg-gray-800"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-black text-slate-800 dark:text-white">Rendered Hours Trend</h2><p className="mt-1 text-xs text-slate-400">Daily regular and overtime hours across the latest filtered dates</p></div><span className="text-xs font-black text-[#800000] dark:text-rose-300">{verifiedPercent}% logs verified</span></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><AreaChart data={analytics.daily_hours}><defs><linearGradient id="hoursFill" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="#800000" stopOpacity={0.35} /><stop offset="95%" stopColor="#800000" stopOpacity={0} /></linearGradient></defs><CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" /><XAxis dataKey="date" tick={{ fontSize: 10 }} tickFormatter={(date) => new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })} /><YAxis tick={{ fontSize: 11 }} /><Tooltip contentStyle={chartTooltipStyle} /><Area type="monotone" dataKey="hours" stroke="#800000" strokeWidth={3} fill="url(#hoursFill)" /></AreaChart></ResponsiveContainer></div></article>
    </section>}

    {tab !== 'analytics' && <footer className="flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-5 py-4 text-xs dark:border-gray-700 dark:bg-gray-800"><p className="text-slate-400">{total} record(s) · Page {page} of {lastPage}</p><div className="flex gap-2"><button disabled={page <= 1} onClick={() => setPage((current) => Math.max(current - 1, 1))} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600"><FiChevronLeft /></button><button disabled={page >= lastPage} onClick={() => setPage((current) => Math.min(current + 1, lastPage))} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600"><FiChevronRight /></button></div></footer>}

    {selected && <Modal title={`${selected.student?.first_name} ${selected.student?.last_name}`} subtitle={`${selected.student?.student_id} · ${formatDate(selected.date)}`} onClose={() => setSelected(null)}><div className="grid gap-5 p-5 lg:grid-cols-[0.85fr_1.15fr]"><div className="space-y-4"><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><div className="flex flex-wrap gap-2"><Badge value={selected.status} />{selected.is_verified ? <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-700">Verified</span> : <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase text-amber-700">Unverified</span>}</div><p className="mt-3 text-xs text-slate-500">{selected.work_mode?.toUpperCase()} · {selected.session_type?.replace('_', ' ')} · {selected.total_hours} total hours</p></div><div className="grid grid-cols-2 gap-3 text-xs">{[['AM In', selected.am_time_in], ['AM Out', selected.am_time_out], ['PM In', selected.pm_time_in], ['PM Out', selected.pm_time_out], ['OT Start', selected.ot_start], ['OT End', selected.ot_end]].map(([label, value]) => <div key={label} className="rounded-xl border border-slate-100 p-3 dark:border-gray-700"><p className="text-[10px] font-black uppercase text-slate-400">{label}</p><p className="mt-1 font-bold text-slate-700 dark:text-gray-200">{formatTime(value)}</p></div>)}</div><div className="flex flex-wrap gap-2">{!selected.is_verified ? <button disabled={busy} onClick={() => verifyAttendance(selected)} className="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white">Verify attendance</button> : <button disabled={busy} onClick={() => verifyAttendance(selected, false)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black dark:border-gray-600">Remove verification</button>}<button onClick={() => startEdit(selected)} className="rounded-xl bg-amber-100 px-4 py-2.5 text-xs font-black text-amber-700">Edit log</button></div></div><div><div className="flex items-center justify-between"><h3 className="font-black text-slate-800 dark:text-white">Daily Journal</h3>{selected.journal_submitted ? <Badge value={selected.journal_status} /> : <span className="text-xs font-bold text-slate-400">Not submitted</span>}</div><div className="mt-4 space-y-3"><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">AM Accomplishments</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-gray-300">{selected.am_activity || 'No AM report submitted.'}</p></div><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">PM Accomplishments</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-gray-300">{selected.pm_activity || 'No PM report submitted.'}</p></div>{selected.journal_feedback && <div className="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">Coordinator feedback: {selected.journal_feedback}</div>}</div>{selected.journal_submitted && <div className="mt-4 flex gap-2"><button disabled={busy} onClick={() => reviewJournal(selected, 'approved')} className="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white">Approve journal</button><button disabled={busy} onClick={() => reviewJournal(selected, 'rejected')} className="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-black text-white">Reject journal</button></div>}</div></div></Modal>}

    {editor && <Modal title="Edit Attendance Log" subtitle={`${editor.student?.first_name} ${editor.student?.last_name} · ${formatDate(editor.date)}`} onClose={() => setEditor(null)}><form onSubmit={saveAttendance}><div className="grid gap-4 p-5 sm:grid-cols-3"><Field label="Status"><select value={editor.status} onChange={(event) => setEditor((current) => ({ ...current, status: event.target.value }))} className={inputClass}><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="holiday">Holiday</option></select></Field><Field label="Work Mode"><select value={editor.work_mode} onChange={(event) => setEditor((current) => ({ ...current, work_mode: event.target.value }))} className={inputClass}><option value="wfo">WFO</option><option value="wfh">WFH</option><option value="field">Field Work</option></select></Field><Field label="Session Type"><select value={editor.session_type} onChange={(event) => setEditor((current) => ({ ...current, session_type: event.target.value }))} className={inputClass}><option value="full_day">Full Day</option><option value="am_half">AM Half</option><option value="pm_half">PM Half</option></select></Field>{[['AM Time In', 'am_time_in'], ['AM Time Out', 'am_time_out'], ['PM Time In', 'pm_time_in'], ['PM Time Out', 'pm_time_out'], ['OT Start', 'ot_start'], ['OT End', 'ot_end']].map(([label, name]) => <Field key={name} label={label}><input type="datetime-local" value={editor[name] || ''} onChange={(event) => setEditor((current) => ({ ...current, [name]: event.target.value }))} className={inputClass} /></Field>)}<Field label="Overtime Hours"><input type="number" min="0" max="24" step="0.01" value={editor.overtime_hours || 0} onChange={(event) => setEditor((current) => ({ ...current, overtime_hours: event.target.value }))} className={inputClass} /></Field><div className="sm:col-span-3 grid gap-4 sm:grid-cols-2"><Field label="AM Activities"><textarea rows="4" value={editor.am_activity || ''} onChange={(event) => setEditor((current) => ({ ...current, am_activity: event.target.value }))} className={inputClass} /></Field><Field label="PM Activities"><textarea rows="4" value={editor.pm_activity || ''} onChange={(event) => setEditor((current) => ({ ...current, pm_activity: event.target.value }))} className={inputClass} /></Field></div></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : 'Save Changes'}</button></div></form></Modal>}
  </div>;
}

export default AttendanceManagement;
