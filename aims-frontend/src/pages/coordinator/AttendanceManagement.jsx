import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  FiAlertCircle,
  FiBarChart2,
  FiBookOpen,
  FiCalendar,
  FiCheck,
  FiChevronLeft,
  FiChevronRight,
  FiClock,
  FiEye,
  FiFileText,
  FiMapPin,
  FiSearch,
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
import { useSearchParams } from 'react-router-dom';
import api from '../../services/api';
import CoordinatorLiveMap from './CoordinatorLiveMap';

const emptySummary = {
  total: 0, present: 0, late: 0, absent: 0, holiday: 0,
  attendance_rate: 0, total_hours: 0,
  journals_submitted: 0, journals_pending: 0,
};

const pageSizes = [10, 25, 50, 100];

const badgeStyles = {
  present: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  late: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  absent: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  holiday: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  reviewed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
};

function Badge({ value }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${badgeStyles[value] || badgeStyles.pending}`}>{value || 'pending'}</span>;
}

function Modal({ title, subtitle, onClose, children, width = 'max-w-2xl' }) {
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previous; };
  }, []);

  return <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5">
    <div role="dialog" aria-modal="true" aria-label={title} className={`flex max-h-[86dvh] w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800 sm:max-h-[72dvh] ${width}`}>
      <header className="flex shrink-0 items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700">
        <div><h2 className="text-lg font-black text-[#690016] dark:text-white">{title}</h2>{subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}</div>
        <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close"><FiX /></button>
      </header>
      <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
    </div>
  </div>;
}

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#7D001D]/50 focus:ring-4 focus:ring-[#7D001D]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const formatTime = (value) => value ? new Date(value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '—';
function AttendanceManagement() {
  const [urlParams] = useSearchParams();
  const initialDate = urlParams.get('date') === 'today' ? new Date().toLocaleDateString('sv-SE') : '';
  const [records, setRecords] = useState([]);
  const [summary, setSummary] = useState(emptySummary);
  const [analytics, setAnalytics] = useState({ status_breakdown: [], work_modes: [], daily_hours: [] });
  const [tab, setTab] = useState(urlParams.get('view') === 'journals' ? 'journals' : 'logs');
  const [filters, setFilters] = useState({ search: '', status: '', journal: '', date_from: initialDate, date_to: initialDate });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [clockControl, setClockControl] = useState(null);
  const [clockControlBusy, setClockControlBusy] = useState(false);
  const [selected, setSelected] = useState(null);
  const [journalPreview, setJournalPreview] = useState(null);
  const [previewHeight, setPreviewHeight] = useState(1020);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState('');
  const previewRequestId = useRef(0);
  const [liveData, setLiveData] = useState({ active_students: [], htes: [], server_time: null });
  const [liveLoading, setLiveLoading] = useState(false);

  const fetchRecords = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/attendance', { params: { ...filters, page, per_page: perPage } });
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
  }, [filters, page, perPage]);

  useEffect(() => {
    const timer = window.setTimeout(fetchRecords, 250);
    return () => window.clearTimeout(timer);
  }, [fetchRecords]);

  useEffect(() => {
    api.get('/coordinator/attendance/smart-clock-control')
      .then((response) => setClockControl(response.data))
      .catch((error) => toast.error(error.response?.data?.message || 'Unable to load the Smart Clock control.'));
  }, []);

  const toggleClockControl = async () => {
    if (!clockControl || clockControlBusy) return;
    setClockControlBusy(true);
    try {
      const response = await api.put('/coordinator/attendance/smart-clock-control', { enabled: !clockControl.enabled });
      setClockControl(response.data);
      toast.success(`Smart Clock ${response.data.enabled ? 'enabled' : 'disabled'} for ${response.data.program_code}.`);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update the Smart Clock control.');
    } finally {
      setClockControlBusy(false);
    }
  };

  const fetchLiveLocations = useCallback(async (showLoading = false) => {
    if (showLoading) setLiveLoading(true);

    try {
      const response = await api.get('/coordinator/attendance/live-locations');
      setLiveData({ active_students: response.data.active_students || [], htes: response.data.htes || [], server_time: response.data.server_time || null });
    } catch (error) {
      if (showLoading) toast.error(error.response?.data?.message || 'Unable to load live student locations.');
    } finally {
      if (showLoading) setLiveLoading(false);
    }
  }, []);

  useEffect(() => {
    if (tab !== 'live-map') return undefined;

    fetchLiveLocations(true);
    const timer = window.setInterval(() => fetchLiveLocations(false), 15000);
    return () => window.clearInterval(timer);
  }, [fetchLiveLocations, tab]);

  const updateFilter = (name, value) => {
    setPage(1);
    setFilters((current) => ({ ...current, [name]: value }));
  };

  const openDetails = async (record) => {
    const requestId = ++previewRequestId.current;
    setSelected(record);
    setJournalPreview(null);
    setPreviewError('');
    setPreviewLoading(true);
    try {
      const response = await api.get(`/coordinator/attendance/${record.id}/journal-preview`);
      if (previewRequestId.current === requestId) setJournalPreview(response.data);
    } catch (error) {
      if (previewRequestId.current === requestId) setPreviewError(error.response?.data?.message || 'Unable to generate the Daily Journal preview.');
    } finally {
      if (previewRequestId.current === requestId) setPreviewLoading(false);
    }
  };

  const reviewJournal = async (record) => {
    setBusy(true);
    try {
      const response = await api.post(`/coordinator/attendance/${record.id}/journal-review`);
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

  const journalRows = useMemo(() => records.filter((record) => record.journal_submitted || ['present', 'late'].includes(record.status)), [records]);
  const chartTooltipStyle = { borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 };

  return <div className="coordinator-attendance portal-card-tables space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-4">
      <div><p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Coordinator Portal</p><h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">Attendance & Journal Monitoring</h1><p className="mt-1 text-sm text-slate-400">View automatic Smart Clock records and review daily accomplishment reports.</p></div>
      <div className="rounded-2xl bg-[#7D001D] px-5 py-3 text-white shadow-lg shadow-[#7D001D]/15"><p className="text-[10px] font-black uppercase tracking-wider text-white/60">Filtered Total</p><p className="text-xl font-black">{Number(summary.total_hours).toFixed(2)} hours</p></div>
    </header>

    <section className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-label="Quick Smart Clock control">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-black text-[#690016] dark:text-white">Quick Smart Clock Control</h2>
          <p className="mt-0.5 text-xs text-slate-600 dark:text-gray-300">Program: {clockControl?.program_code || 'Loading…'} · Enable manually when OJT starts.</p>
        </div>
        <div className="flex items-center gap-2">
          <span className={`text-xs font-bold ${clockControl?.enabled ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300'}`} aria-live="polite">
            {clockControlBusy ? 'Saving…' : clockControl?.enabled ? 'Enabled' : 'Disabled'}
          </span>
          <label className="inline-flex cursor-pointer items-center">
            <input type="checkbox" role="switch" aria-label={`Quick Smart Clock for ${clockControl?.program_code || 'your program'}`} checked={Boolean(clockControl?.enabled)} onChange={toggleClockControl} disabled={!clockControl || clockControlBusy} className="peer sr-only" />
            <span aria-hidden="true" className={`relative h-5 w-9 rounded-full transition-colors peer-focus-visible:ring-4 peer-focus-visible:ring-[#7D001D]/30 peer-disabled:opacity-50 ${clockControl?.enabled ? 'bg-emerald-600' : 'bg-slate-400 dark:bg-slate-600'}`}>
              <span className={`absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${clockControl?.enabled ? 'translate-x-4' : ''}`} />
            </span>
          </label>
        </div>
      </div>
    </section>

    <section className="portal-summary-grid grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {[
        ['Attendance Records', summary.total, FiCalendar, 'text-[#7D001D] bg-rose-50 dark:bg-rose-950/40'],
        ['Attendance Rate', `${summary.attendance_rate}%`, FiBarChart2, 'text-blue-600 bg-blue-50 dark:bg-blue-950/40'],
        ['Submitted Journals', summary.journals_submitted, FiBookOpen, 'text-violet-600 bg-violet-50 dark:bg-violet-950/40'],
        ['Pending Reviews', summary.journals_pending, FiAlertCircle, 'text-amber-600 bg-amber-50 dark:bg-amber-950/40'],
      ].map(([label, value, Icon, colors]) => <article key={label} className="portal-metric-card portal-summary-card rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center gap-3"><span className={`grid h-10 w-10 place-items-center rounded-xl ${colors}`}><Icon /></span><div><p className="text-xl font-black text-slate-800 dark:text-white">{value}</p><p className="text-[10px] font-black uppercase tracking-wide text-slate-400">{label}</p></div></div></article>)}
    </section>

    {tab !== 'live-map' && <section className="portal-filter-panel rounded-3xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="portal-filter-grid grid gap-3 xl:grid-cols-[1fr_repeat(4,minmax(130px,auto))]">
        <label className="portal-filter-search relative"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={filters.search} onChange={(event) => updateFilter('search', event.target.value)} placeholder="Search student name or ID..." className={`${inputClass} pl-10`} /></label>
        <select value={filters.status} onChange={(event) => updateFilter('status', event.target.value)} className={inputClass}><option value="">All statuses</option><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="holiday">Holiday</option></select>
        <select value={filters.journal} onChange={(event) => updateFilter('journal', event.target.value)} className={inputClass}><option value="">All journals</option><option value="submitted">Submitted</option><option value="pending">Awaiting review</option><option value="reviewed">Reviewed</option></select>
        <input type="date" value={filters.date_from} onChange={(event) => updateFilter('date_from', event.target.value)} className={inputClass} title="Start date" />
        <input type="date" value={filters.date_to} onChange={(event) => updateFilter('date_to', event.target.value)} className={inputClass} title="End date" />
      </div>
    </section>}

    <nav className="portal-mobile-tabs flex w-fit max-w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 dark:bg-gray-800">
      {[
        ['logs', 'Attendance Logs', FiClock],
        ['live-map', 'Live Map', FiMapPin],
        ['journals', 'Daily Journals', FiBookOpen],
        ['analytics', 'Analytics', FiBarChart2],
      ].map(([value, label, Icon]) => <button key={value} onClick={() => setTab(value)} className={`inline-flex whitespace-nowrap items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black transition ${tab === value ? 'bg-white text-[#7D001D] shadow-sm dark:bg-gray-700 dark:text-rose-300' : 'text-slate-500'}`}><Icon /> {label}</button>)}
    </nav>

    {tab === 'live-map' && <CoordinatorLiveMap students={liveData.active_students} htes={liveData.htes} serverTime={liveData.server_time} loading={liveLoading} onRefresh={() => fetchLiveLocations(true)} />}

    {tab === 'logs' && <section className="portal-table-panel overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="overflow-x-auto"><table className="portal-card-table portal-card-table--attendance w-full min-w-[900px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">Student</th><th className="px-4 py-4">Date & Mode</th><th className="px-4 py-4">Time Record</th><th className="px-4 py-4">Hours</th><th className="px-4 py-4">Status</th><th className="px-5 py-4 text-right">Action</th></tr></thead>
        <tbody>{loading ? <tr><td colSpan="6" className="py-16 text-center text-slate-400">Loading attendance records...</td></tr> : records.length ? records.map((record) => <tr key={record.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{record.student?.last_name}, {record.student?.first_name}</p><p className="mt-1 text-[11px] text-slate-400">{record.student?.student_id} · {record.student?.program?.code}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{formatDate(record.date)}</p><p className="mt-1 text-[11px] uppercase text-slate-400">{record.work_mode} · {record.session_type?.replace('_', ' ')}</p></td><td className="px-4 py-4 text-[11px] text-slate-500"><p>AM {formatTime(record.am_time_in)} – {formatTime(record.am_time_out)} {record.am_late_minutes > 0 && <span className="font-black text-amber-600">· {record.am_late_minutes}m late</span>}</p><p className="mt-1">PM {formatTime(record.pm_time_in)} – {formatTime(record.pm_time_out)} {record.pm_late_minutes > 0 && <span className="font-black text-amber-600">· {record.pm_late_minutes}m late</span>}</p></td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{record.total_hours}h</p><p className="text-[10px] text-slate-400">{record.overtime_hours || 0}h OT</p></td><td className="px-4 py-4"><Badge value={record.status} /></td><td className="px-5 py-4 text-right"><button type="button" onClick={() => openDetails(record)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700" title="View automatic attendance and journal" aria-label={`View attendance and journal for ${record.student?.first_name || 'student'} ${record.student?.last_name || ''}`}><FiEye aria-hidden="true" /></button></td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-slate-400">No attendance logs match the selected filters.</td></tr>}</tbody>
      </table></div>
    </section>}

    {tab === 'journals' && <section className="grid gap-4 lg:grid-cols-2">
      {loading ? <div className="col-span-full rounded-3xl bg-white py-16 text-center text-slate-400 dark:bg-gray-800">Loading journals...</div> : journalRows.length ? journalRows.map((record) => <article key={record.id} className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-800 dark:text-white">{record.student?.first_name} {record.student?.last_name}</p><p className="mt-1 text-[11px] text-slate-400">{record.student?.student_id} · {formatDate(record.date)} · {record.session_type?.replace('_', ' ')}</p></div>{record.journal_submitted ? <Badge value={record.journal_status} /> : <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-500 dark:bg-gray-700">Missing</span>}</div><div className="mt-4 grid gap-3 sm:grid-cols-2"><div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">AM Activities</p><p className="mt-2 line-clamp-3 text-xs leading-5 text-slate-600 dark:text-gray-300">{record.am_activity || 'No AM report submitted.'}</p></div><div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-[10px] font-black uppercase text-slate-400">PM Activities</p><p className="mt-2 line-clamp-3 text-xs leading-5 text-slate-600 dark:text-gray-300">{record.pm_activity || 'No PM report submitted.'}</p></div></div>{record.journal_feedback && <p className="mt-3 rounded-xl bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Review notes: {record.journal_feedback}</p>}<div className="mt-4 flex flex-wrap items-center justify-between gap-2"><button onClick={() => openDetails(record)} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-gray-600"><FiEye /> Review details</button>{record.journal_submitted && record.journal_status !== 'reviewed' && <button disabled={busy} onClick={() => reviewJournal(record)} className="inline-flex items-center gap-2 rounded-lg bg-blue-100 px-3 py-2 text-xs font-black text-blue-700 disabled:opacity-50"><FiCheck /> Mark as Reviewed</button>}</div></article>) : <div className="col-span-full rounded-3xl border border-dashed border-slate-300 py-16 text-center text-slate-400 dark:border-gray-700">No journal records match the selected filters.</div>}
    </section>}

    {tab === 'analytics' && <section className="grid gap-5 xl:grid-cols-2">
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div><h2 className="font-black text-slate-800 dark:text-white">Attendance Distribution</h2><p className="mt-1 text-xs text-slate-400">Status breakdown for the selected date range</p></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={analytics.status_breakdown} dataKey="value" nameKey="name" innerRadius={55} outerRadius={90} paddingAngle={3}>{analytics.status_breakdown.map((item) => <Cell key={item.name} fill={item.color} />)}</Pie><Tooltip contentStyle={chartTooltipStyle} /><Legend /></PieChart></ResponsiveContainer></div></article>
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div><h2 className="font-black text-slate-800 dark:text-white">Work Mode Usage</h2><p className="mt-1 text-xs text-slate-400">WFO, WFH, and field-work records</p></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><BarChart data={analytics.work_modes}><CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" /><XAxis dataKey="name" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip contentStyle={chartTooltipStyle} /><Bar dataKey="value" name="Records" fill="#7D001D" radius={[8, 8, 0, 0]} /></BarChart></ResponsiveContainer></div></article>
      <article className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-700 dark:bg-gray-800"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-black text-slate-800 dark:text-white">Rendered Hours Trend</h2><p className="mt-1 text-xs text-slate-400">Daily regular and overtime hours across the latest filtered dates</p></div><span className="text-xs font-black text-[#7D001D] dark:text-rose-300">Automatic Smart Clock records</span></div><div className="mt-4 h-72"><ResponsiveContainer width="100%" height="100%"><AreaChart data={analytics.daily_hours}><defs><linearGradient id="hoursFill" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="#7D001D" stopOpacity={0.35} /><stop offset="95%" stopColor="#7D001D" stopOpacity={0} /></linearGradient></defs><CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" /><XAxis dataKey="date" tick={{ fontSize: 10 }} tickFormatter={(date) => new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })} /><YAxis tick={{ fontSize: 11 }} /><Tooltip contentStyle={chartTooltipStyle} /><Area type="monotone" dataKey="hours" stroke="#7D001D" strokeWidth={3} fill="url(#hoursFill)" /></AreaChart></ResponsiveContainer></div></article>
    </section>}

    {tab !== 'analytics' && tab !== 'live-map' && <footer className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-5 py-4 text-xs dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center gap-3"><label className="flex items-center gap-2 font-bold text-slate-500 dark:text-gray-300"><span>Entries per page</span><select value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }} aria-label="Attendance entries per page" className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-700 outline-none focus:border-[#7D001D] focus:ring-2 focus:ring-[#7D001D]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">{pageSizes.map((size) => <option key={size} value={size}>{size}</option>)}</select></label><p className="text-slate-400">{total} record(s) · Page {page} of {lastPage}</p></div><div className="flex gap-2"><button disabled={page <= 1} onClick={() => setPage((current) => Math.max(current - 1, 1))} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Previous attendance page"><FiChevronLeft /></button><button disabled={page >= lastPage} onClick={() => setPage((current) => Math.min(current + 1, lastPage))} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Next attendance page"><FiChevronRight /></button></div></footer>}

    {selected && <Modal title="View Daily Journal" subtitle={`${selected.student?.first_name} ${selected.student?.last_name} · ${selected.student?.student_id} · ${formatDate(selected.date)}`} onClose={() => { previewRequestId.current += 1; setSelected(null); setJournalPreview(null); setPreviewLoading(false); setPreviewError(''); }}>
      <div className="space-y-4 p-4 sm:p-5">
        <details className="group rounded-xl border border-slate-200 bg-slate-50 dark:border-gray-700 dark:bg-gray-900">
          <summary className="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 [&::-webkit-details-marker]:hidden">
            <Badge value={selected.status} />
            <span className="flex-1 text-xs font-semibold text-slate-600 dark:text-gray-300">{selected.work_mode?.toUpperCase()} · {selected.session_type?.replace('_', ' ')} · {selected.total_hours} hours</span>
            <span className="inline-flex items-center gap-1 text-xs font-bold text-[#690016] dark:text-rose-300">Attendance times <FiChevronRight className="transition-transform group-open:rotate-90" /></span>
          </summary>
          <div className="border-t border-slate-200 px-4 py-3 dark:border-gray-700">
            <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">{[['AM In', selected.am_time_in], ['AM Out', selected.am_time_out], ['PM In', selected.pm_time_in], ['PM Out', selected.pm_time_out], ...(Number(selected.overtime_hours) > 0 ? [['Credited OT', selected.overtime_hours]] : [])].map(([label, value]) => <div key={label} className="rounded-lg bg-white px-3 py-2 dark:bg-gray-800"><p className="text-[10px] font-black uppercase text-slate-400">{label}</p><p className="mt-0.5 font-bold text-slate-700 dark:text-gray-200">{label === 'Credited OT' ? `${Number(value).toFixed(2)}h` : formatTime(value)}</p></div>)}</div>
            <p className="mt-3 text-[11px] text-slate-500 dark:text-gray-400">Smart Clock times are view-only. Overtime is credited outside the official schedule.</p>
          </div>
        </details>
        <div className="flex min-h-0 flex-col"><div className="flex items-center justify-between gap-3"><div><h3 className="text-lg font-black text-slate-800 dark:text-white">Daily Journal</h3>{journalPreview?.template?.file_name && <p className="mt-1 text-[10px] text-slate-400">Official template: {journalPreview.template.file_name}</p>}</div>{selected.journal_submitted ? <Badge value={selected.journal_status} /> : <span className="text-xs font-bold text-slate-400">Not submitted</span>}</div>
          <div className="mt-3 grid min-h-[22rem] flex-1 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-200 p-2 dark:border-gray-700 dark:bg-gray-950">{previewLoading ? <p className="text-xs font-bold text-slate-500">Generating official Daily Journal preview…</p> : journalPreview?.html ? <iframe title="Generated Daily Journal preview" srcDoc={journalPreview.html} sandbox="allow-same-origin" onLoad={(event) => setPreviewHeight(Math.max(1020, event.currentTarget.contentDocument?.documentElement.scrollHeight || 0))} style={{ height: previewHeight }} className="w-full rounded-lg bg-white" /> : <div className="w-full rounded-lg bg-white p-5 dark:bg-gray-900">
              <div className="text-center">
                <FiFileText className="mx-auto text-3xl text-slate-400" />
                <p className="mt-2 text-sm font-black text-slate-700 dark:text-gray-200">Official Daily Journal preview is unavailable</p>
                <p className="mt-1 text-xs leading-5 text-slate-500">{previewError || 'Upload the official DOCX once in Report Management.'}</p>
              </div>
              {selected.journal_submitted && <div className="mt-5 grid gap-3 border-t border-slate-200 pt-4 text-left dark:border-gray-700 sm:grid-cols-2">
                <div><p className="text-xs font-bold text-slate-500">AM activities</p><p className="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-gray-200">{selected.am_activity || 'No AM report submitted.'}</p></div>
                <div><p className="text-xs font-bold text-slate-500">PM activities</p><p className="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-gray-200">{selected.pm_activity || 'No PM report submitted.'}</p></div>
              </div>}
            </div>}</div>
          {selected.journal_feedback && <div className="mt-3 rounded-xl bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Coordinator review notes: {selected.journal_feedback}</div>}<div className="mt-4 flex min-h-10 justify-end">{selected.journal_submitted && selected.journal_status !== 'reviewed' && <button disabled={busy} onClick={() => reviewJournal(selected)} className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black text-white disabled:opacity-50"><FiCheck /> {busy ? 'Marking as Reviewed...' : 'Mark as Reviewed'}</button>}</div>
        </div>
      </div>
    </Modal>}
  </div>;
}

export default AttendanceManagement;
