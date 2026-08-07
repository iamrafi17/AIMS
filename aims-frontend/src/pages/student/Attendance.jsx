import { useEffect, useMemo, useState } from 'react';
import {
  FiAlertCircle,
  FiCalendar,
  FiChevronLeft,
  FiChevronRight,
  FiEdit3,
  FiInfo,
  FiMapPin,
  FiSave,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const blankForm = {
  status: 'present', work_mode: 'wfo', session_type: 'full_day',
  am_time_in: '', am_time_out: '', pm_time_in: '', pm_time_out: '',
  ot_start: '', ot_end: '', am_activity: '', pm_activity: '',
};

const pad = (value) => String(value).padStart(2, '0');
const dateKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const monthKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
const prettyDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
const number = (value) => Number(value || 0).toFixed(1);

function SummaryCard({ label, value, suffix, tone }) {
  return <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><p className="text-xs font-extrabold uppercase tracking-[0.13em] text-slate-400">{label}</p><p className={`mt-2 text-3xl font-black ${tone}`}>{value} <span className="text-sm font-bold text-slate-400">{suffix}</span></p></div>;
}

function Field({ label, value, onChange, disabled, required }) {
  return <label><span className="mb-1.5 block text-xs font-extrabold uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span><input type="time" value={value || ''} onChange={onChange} disabled={disabled} required={required} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/5 disabled:bg-slate-100 disabled:text-slate-400" /></label>;
}

function StudentAttendance() {
  const now = new Date();
  const [month, setMonth] = useState(monthKey(now));
  const [selectedDate, setSelectedDate] = useState(dateKey(now));
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState(blankForm);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const today = workspace?.server_date || dateKey(now);
  const isToday = selectedDate === today;
  const isPast = selectedDate < today;
  const selectedRecord = workspace?.selected_attendance;
  const settings = workspace?.settings;
  const manualDisabled = isPast && !settings?.allow_past_attendance;

  const syncForm = (record, config) => {
    const schedule = config?.official_schedule || {};
    setForm(record ? {
      ...blankForm,
      ...record,
      status: record.status === 'late' ? 'present' : record.status,
    } : {
      ...blankForm,
      am_time_in: schedule.am_start || '08:00', am_time_out: schedule.am_end || '12:00',
      pm_time_in: schedule.pm_start || '13:00', pm_time_out: schedule.pm_end || '17:00',
    });
  };

  const loadWorkspace = async () => {
    setLoading(true);
    try {
      const response = await api.get('/student/attendance/workspace', { params: { month, date: selectedDate } });
      setWorkspace(response.data);
      syncForm(response.data.selected_attendance, response.data.settings);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load attendance workspace.');
    } finally { setLoading(false); }
  };

  const loadClock = async () => {
    try {
      const response = await api.get('/student/attendance/quick-clock');
      if (response.data.attendance) setForm((current) => ({ ...current, status: response.data.attendance.status === 'late' ? 'present' : response.data.attendance.status, work_mode: response.data.attendance.work_mode, session_type: response.data.attendance.session_type }));
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to sync Smart Clock.'); }
  };

  // The selected month/date intentionally drives a complete server refresh.
  // oxlint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { loadWorkspace(); if (isToday) loadClock(); }, [month, selectedDate]);

  const calendarDays = useMemo(() => {
    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(year, monthNumber - 1, 1);
    const leading = first.getDay();
    const total = new Date(year, monthNumber, 0).getDate();
    return [...Array(leading).fill(null), ...Array.from({ length: total }, (_, index) => `${year}-${pad(monthNumber)}-${pad(index + 1)}`)];
  }, [month]);
  const records = useMemo(() => Object.fromEntries((workspace?.calendar || []).map((record) => [record.date, record])), [workspace]);

  const moveMonth = (direction) => { const [year, mon] = month.split('-').map(Number); const next = new Date(year, mon - 1 + direction, 1); setMonth(monthKey(next)); setSelectedDate(dateKey(new Date(Math.min(next.getTime(), new Date().getTime())))); };
  const jumpToday = () => { const key = dateKey(new Date()); setMonth(key.slice(0, 7)); setSelectedDate(key); };
  const chooseDate = (key) => { if (!key || key > today) return; if (settings?.ojt_start_date && key < settings.ojt_start_date) return; if (settings?.ojt_end_date && key > settings.ojt_end_date) return; setSelectedDate(key); };
  const change = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }));

  const saveEntry = async (overrides = {}) => {
    setBusy(true);
    try {
      const payload = { ...form, ...overrides, date: selectedDate, status: selectedRecord?.status === 'late' ? 'late' : form.status };
      if (payload.status === 'late') payload.status = 'present';
      const response = await api.post('/student/attendance/entry', payload);
      toast.success(response.data.message);
      response.data.warnings?.forEach((warning) => toast(warning, { icon: '⚠️' }));
      await loadWorkspace();
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || 'Unable to save attendance.');
    } finally { setBusy(false); }
  };

  const otOverlap = form.ot_start && form.ot_end && settings?.official_schedule && form.ot_start < settings.official_schedule.pm_end && form.ot_end > settings.official_schedule.am_start;

  if (loading && !workspace) return <div className="grid h-64 place-items-center"><div className="h-12 w-12 animate-spin rounded-full border-b-2 border-[#800000]" /></div>;

  return <div className="space-y-6">
    <div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Student Portal</p><h1 className="mt-1 text-2xl font-black text-[#430909]">Attendance Monitoring</h1></div>

    <section><h2 className="mb-4 text-sm font-black uppercase tracking-widest text-slate-500">This Week Summary</h2><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><SummaryCard label="Rendered" value={number(workspace?.week_summary?.rendered_hours)} suffix="hrs" tone="text-emerald-600" /><SummaryCard label="Overtime" value={number(workspace?.week_summary?.overtime_hours)} suffix="hrs" tone="text-violet-600" /><SummaryCard label="Days Present" value={workspace?.week_summary?.days_present || 0} suffix="days" tone="text-blue-600" /><SummaryCard label="Days Left" value={workspace?.week_summary?.days_left || 0} suffix="days" tone="text-[#a8750b]" /></div></section>

    <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm lg:p-7">
      <div className="flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-3"><FiCalendar className="text-xl text-[#800000]" /><h2 className="text-lg font-black text-[#430909]">{new Date(`${month}-01T00:00:00`).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })}</h2></div><div className="flex gap-2"><button onClick={() => moveMonth(-1)} className="rounded-xl border border-slate-200 p-2.5"><FiChevronLeft /></button><button onClick={jumpToday} className="rounded-xl border border-slate-200 px-4 text-xs font-black text-[#800000]">Today</button><button onClick={() => moveMonth(1)} disabled={month >= today.slice(0, 7)} className="rounded-xl border border-slate-200 p-2.5 disabled:opacity-30"><FiChevronRight /></button></div></div>
      <div className="mt-5 grid grid-cols-7 text-center text-[11px] font-black uppercase text-slate-400">{['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map((day) => <div key={day} className="py-2">{day}</div>)}</div>
      <div className="grid grid-cols-7 gap-1 sm:gap-2">{calendarDays.map((key, index) => {
        if (!key) return <div key={`blank-${index}`} />;
        const record = records[key]; const future = key > today; const outside = (settings?.ojt_start_date && key < settings.ojt_start_date) || (settings?.ojt_end_date && key > settings.ojt_end_date); const selected = key === selectedDate;
        const color = record?.status === 'absent' ? 'bg-rose-50 text-rose-700' : record?.status === 'holiday' ? 'bg-violet-50 text-violet-700' : record ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-600';
        return <button key={key} onClick={() => chooseDate(key)} disabled={future || outside} className={`min-h-16 rounded-xl border p-1.5 text-left transition sm:min-h-20 sm:p-2 ${selected ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : `border-transparent ${color}`} ${(future || outside) ? 'cursor-not-allowed opacity-30' : 'hover:border-slate-300'}`}><span className="text-xs font-black">{Number(key.slice(-2))}</span>{record && <span className="mt-2 block text-[9px] font-extrabold sm:text-[11px]">{record.status === 'present' || record.status === 'late' ? `${number(record.hours)}h` : record.status}</span>}</button>;
      })}</div>
      <div className="mt-4 flex flex-wrap gap-4 text-[11px] font-bold text-slate-500"><span>🟢 Present</span><span>🔴 Absent</span><span>🟣 Holiday</span><span>🔵 Selected</span><span>⚪ Unavailable</span></div>
    </section>

    <div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs font-extrabold uppercase tracking-widest text-[#a8750b]">Selected Date</p><h2 className="mt-1 text-xl font-black text-[#430909]">{isToday ? 'Today' : `Editing — ${prettyDate(selectedDate)}`}</h2></div>{isPast && <span className="inline-flex items-center gap-2 rounded-full bg-orange-100 px-4 py-2 text-xs font-black uppercase text-orange-700"><FiEdit3 /> Manual Entry</span>}</div>

    {settings?.geofence?.active && <div className="flex gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"><FiMapPin className="mt-0.5 shrink-0 text-xl" /><div><p className="font-black">Geofence active for {settings.geofence.hte}</p><p className="mt-1 text-xs">WFO Smart Clock logs must be within {settings.geofence.radius} meters of your HTE.</p></div></div>}
    {manualDisabled && <div className="flex gap-3 rounded-2xl border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800"><FiAlertCircle className="mt-0.5 shrink-0 text-xl" /><div><p className="font-black">Past attendance logging is disabled</p><p className="mt-1 text-xs">Ask your administrator to enable manual past-date entries.</p></div></div>}

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Attendance Type</h2><div className="mt-5 grid gap-4 md:grid-cols-3"><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Status</span><select value={form.status} onChange={change('status')} disabled={isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="present">Present</option><option value="absent">Absent</option><option value="holiday">Holiday / No Work</option></select></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Work Mode</span><select value={form.work_mode} onChange={change('work_mode')} disabled={form.status !== 'present' || isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="wfo">WFO</option><option value="wfh">WFH</option><option value="field">Field Work</option></select></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Session Type</span><select value={form.session_type} onChange={change('session_type')} disabled={form.status !== 'present' || isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="full_day">Full Day</option><option value="am_half">AM Half</option><option value="pm_half">PM Half</option></select></label></div></section>

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Time Record</h2><p className="mt-1 text-xs text-slate-400">{isToday ? 'Regular time fields are read-only and controlled by Smart Clock.' : 'Official schedule times are pre-filled and may be adjusted.'}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><Field label="AM In" value={form.am_time_in} onChange={change('am_time_in')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="AM Out" value={form.am_time_out} onChange={change('am_time_out')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="PM In" value={form.pm_time_in} onChange={change('pm_time_in')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="PM Out" value={form.pm_time_out} onChange={change('pm_time_out')} disabled={isToday || manualDisabled || form.status !== 'present'} /></div><div id="overtime-section" className="mt-7 border-t border-slate-100 pt-6"><div className="flex items-center gap-2"><h3 className="font-black text-slate-700">Overtime</h3><span className="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-black uppercase text-violet-700">Optional</span></div><div className="mt-4 grid gap-4 sm:grid-cols-2"><Field label="OT Start" value={form.ot_start} onChange={change('ot_start')} disabled={manualDisabled || form.status !== 'present'} /><Field label="OT End" value={form.ot_end} onChange={change('ot_end')} disabled={isToday || manualDisabled || form.status !== 'present'} /></div>{isToday && <p className="mt-2 text-xs text-slate-400">Today’s OT End is fixed to your actual final time-out.</p>}{otOverlap && <div className="mt-4 flex gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs font-bold text-blue-800"><FiInfo className="shrink-0" /> OT range overlaps with the official schedule — only time outside the schedule counts. Lunch is excluded.</div>}</div></section>

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Accomplishment Report</h2><div className="mt-5 grid gap-5 lg:grid-cols-2"><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">AM Activities</span><textarea rows={5} value={form.am_activity || ''} onChange={change('am_activity')} disabled={manualDisabled || form.status !== 'present'} className="w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm outline-none focus:border-[#800000]/40 disabled:bg-slate-100" placeholder="Describe morning accomplishments..." /></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">PM Activities</span><textarea rows={5} value={form.pm_activity || ''} onChange={change('pm_activity')} disabled={manualDisabled || form.status !== 'present'} className="w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm outline-none focus:border-[#800000]/40 disabled:bg-slate-100" placeholder="Describe afternoon accomplishments..." /></label></div></section>

    <button onClick={() => saveEntry()} disabled={busy || manualDisabled || (isToday && !selectedRecord)} className="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#800000] px-6 py-4 font-black text-white shadow-xl shadow-[#800000]/15 disabled:cursor-not-allowed disabled:opacity-45"><FiSave />{busy ? 'Saving...' : isToday ? 'Update Accomplishment & Overtime' : `Save Attendance for ${prettyDate(selectedDate)}`}</button>

  </div>;
}

export default StudentAttendance;
