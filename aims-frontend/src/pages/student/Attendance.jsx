import { useEffect, useMemo, useRef, useState } from 'react';
import {
  FiAlertCircle,
  FiCalendar,
  FiCheck,
  FiChevronLeft,
  FiChevronRight,
  FiClock,
  FiEdit3,
  FiInfo,
  FiMapPin,
  FiRefreshCw,
  FiSave,
  FiX,
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
const timeFromIso = (value) => value ? new Date(value).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
const number = (value) => Number(value || 0).toFixed(1);

function SummaryCard({ label, value, suffix, tone }) {
  return <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><p className="text-xs font-extrabold uppercase tracking-[0.13em] text-slate-400">{label}</p><p className={`mt-2 text-3xl font-black ${tone}`}>{value} <span className="text-sm font-bold text-slate-400">{suffix}</span></p></div>;
}

function Field({ label, value, onChange, disabled, required }) {
  return <label><span className="mb-1.5 block text-xs font-extrabold uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span><input type="time" value={value || ''} onChange={onChange} disabled={disabled} required={required} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/5 disabled:bg-slate-100 disabled:text-slate-400" /></label>;
}

function ActivityModal({ period, value, onChange, onCancel, onSubmit, busy }) {
  return <div className="fixed inset-0 z-[80] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm"><form onSubmit={onSubmit} className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl"><div className="flex justify-between"><div><p className="text-xs font-extrabold uppercase tracking-widest text-[#a8750b]">Accomplishment Report</p><h2 className="mt-1 text-2xl font-black text-[#430909]">{period} Activities</h2></div><button type="button" onClick={onCancel} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100"><FiX /></button></div><textarea autoFocus required rows={6} maxLength={2000} value={value} onChange={onChange} placeholder={`Describe your ${period} accomplishments...`} className="mt-5 w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm outline-none focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/5" /><div className="mt-5 flex justify-end gap-3"><button type="button" onClick={onCancel} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold">Cancel</button><button disabled={busy || !value.trim()} className="rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-50">Submit & Time Out</button></div></form></div>;
}

function OvertimeQuestion({ onNo, onYes }) {
  return <div className="fixed inset-0 z-[80] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm"><div className="w-full max-w-md rounded-3xl bg-white p-7 text-center shadow-2xl"><div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-100 text-2xl text-amber-700"><FiClock /></div><h2 className="mt-5 text-2xl font-black text-[#430909]">Did you render overtime today?</h2><p className="mt-2 text-sm leading-6 text-slate-500">You can adjust OT Start. OT End is fixed to your actual final time-out.</p><div className="mt-6 grid grid-cols-2 gap-3"><button onClick={onNo} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-600">No</button><button onClick={onYes} className="rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white">Yes</button></div></div></div>;
}

function StudentAttendance() {
  const now = new Date();
  const [month, setMonth] = useState(monthKey(now));
  const [selectedDate, setSelectedDate] = useState(dateKey(now));
  const [workspace, setWorkspace] = useState(null);
  const [clock, setClock] = useState(null);
  const [form, setForm] = useState(blankForm);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [liveTime, setLiveTime] = useState(now);
  const [activityModal, setActivityModal] = useState(null);
  const [otQuestion, setOtQuestion] = useState(false);
  const serverOffset = useRef(0);

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
      serverOffset.current = new Date(response.data.server_time).getTime() - Date.now();
      setLiveTime(new Date(response.data.server_time));
      setClock(response.data);
      if (response.data.attendance) setForm((current) => ({ ...current, status: response.data.attendance.status === 'late' ? 'present' : response.data.attendance.status, work_mode: response.data.attendance.work_mode, session_type: response.data.attendance.session_type }));
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to sync Smart Clock.'); }
  };

  // The selected month/date intentionally drives a complete server refresh.
  // oxlint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { loadWorkspace(); if (isToday) loadClock(); }, [month, selectedDate]);
  useEffect(() => { const timer = window.setInterval(() => setLiveTime(new Date(Date.now() + serverOffset.current)), 1000); return () => window.clearInterval(timer); }, []);

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

  const requestLocation = () => new Promise((resolve, reject) => navigator.geolocation?.getCurrentPosition(({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude }), () => reject(new Error('Allow GPS access to record your time.')), { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }) || reject(new Error('GPS is not supported.')));
  const submitClock = async (activity = '') => {
    setBusy(true);
    try {
      const location = form.status === 'present' ? await requestLocation() : {};
      const response = await api.post('/student/attendance/quick-clock', { status: form.status, work_mode: form.status === 'present' ? form.work_mode : null, session_type: form.status === 'present' ? form.session_type : null, activity: activity || null, ...location });
      setClock(response.data); setActivityModal(null); toast.success(response.data.message);
      await loadWorkspace();
      if (response.data.just_completed) setOtQuestion(true);
    } catch (error) { toast.error(error.response?.data?.message || error.message || 'Unable to record time.'); } finally { setBusy(false); }
  };
  const primaryClockAction = () => clock?.next_action?.requires_activity ? setActivityModal(clock.next_action.period) : submitClock();

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

  const handleOtYes = () => {
    const completed = (clock?.slots || []).filter((slot) => slot.completed);
    setForm((current) => ({ ...current, ot_start: timeFromIso(completed[0]?.time), ot_end: timeFromIso(completed[completed.length - 1]?.time) }));
    setOtQuestion(false);
    window.setTimeout(() => document.getElementById('overtime-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
  };
  const handleOtNo = () => { setOtQuestion(false); setForm((current) => ({ ...current, ot_start: '', ot_end: '' })); saveEntry({ ot_start: '', ot_end: '' }); };

  const settingsLocked = clock?.settings_locked;
  const displaySlots = clock?.slots || [];
  const clockComplete = clock?.completed;
  const clockLabel = form.status === 'absent' ? 'Submit as Absent' : form.status === 'holiday' ? 'Submit as No Work' : clock?.next_action?.label || 'Time In';
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

    {isToday && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm"><div className="bg-gradient-to-br from-[#fffaf7] to-white p-6 lg:p-7"><div className="flex flex-col justify-between gap-5 lg:flex-row"><div><div className="flex items-center gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#800000] text-white"><FiClock /></div><div><h2 className="text-xl font-black text-[#430909]">Smart Clock</h2><p className="text-xs font-semibold text-slate-400">Synced with AIMS server time</p></div></div><p className="mt-5 font-mono text-4xl font-black text-[#800000]">{liveTime.toLocaleTimeString('en-PH')}</p></div><div className="grid gap-3 sm:grid-cols-3"><select value={form.status} onChange={change('status')} disabled={settingsLocked} className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold disabled:bg-slate-100"><option value="present">Present</option><option value="absent">Absent</option><option value="holiday">Holiday / No Work</option></select>{form.status === 'present' && <><select value={form.work_mode} onChange={change('work_mode')} disabled={settingsLocked} className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold disabled:bg-slate-100"><option value="wfo">WFO</option><option value="wfh">WFH</option><option value="field">Field Work</option></select><select value={form.session_type} onChange={change('session_type')} disabled={settingsLocked} className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold disabled:bg-slate-100"><option value="full_day">Full Day</option><option value="am_half">AM Half</option><option value="pm_half">PM Half</option></select></>}</div></div></div><div className="p-6 lg:p-7">{displaySlots.length > 0 && <div className={`grid gap-3 ${displaySlots.length === 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}>{displaySlots.map((slot) => <div key={slot.key} className={`rounded-2xl border p-4 ${slot.completed ? 'border-emerald-200 bg-emerald-50' : clock?.next_action?.key === slot.key ? 'border-[#800000]/40 bg-[#800000]/[0.03]' : 'border-slate-200 bg-slate-50'}`}><div className={`grid h-8 w-8 place-items-center rounded-full text-sm font-black ${slot.completed ? 'bg-emerald-500 text-white' : 'border-2 border-slate-300 text-slate-400'}`}>{slot.completed ? <FiCheck /> : slot.number}</div><p className="mt-3 text-xs font-black text-slate-700">{slot.label}</p><p className="mt-1 text-[11px] font-bold text-slate-400">{slot.completed ? timeFromIso(slot.time) : clock?.next_action?.key === slot.key ? 'Next action' : 'Not recorded'}</p></div>)}</div>}{clockComplete ? <div className="mt-5 rounded-2xl bg-emerald-600 p-4 text-center text-sm font-black text-white">✓ All time slots completed for today!</div> : <button onClick={primaryClockAction} disabled={busy} className="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#800000] px-5 py-4 font-black text-white disabled:opacity-50"><FiClock />{busy ? 'Recording...' : clockLabel}</button>}<button onClick={loadClock} className="mt-3 ml-auto flex items-center gap-2 text-xs font-black text-[#800000]"><FiRefreshCw /> Resync</button></div></section>}

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Attendance Type</h2><div className="mt-5 grid gap-4 md:grid-cols-3"><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Status</span><select value={form.status} onChange={change('status')} disabled={isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="present">Present</option><option value="absent">Absent</option><option value="holiday">Holiday / No Work</option></select></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Work Mode</span><select value={form.work_mode} onChange={change('work_mode')} disabled={form.status !== 'present' || isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="wfo">WFO</option><option value="wfh">WFH</option><option value="field">Field Work</option></select></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">Session Type</span><select value={form.session_type} onChange={change('session_type')} disabled={form.status !== 'present' || isToday || manualDisabled} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-bold disabled:bg-slate-100"><option value="full_day">Full Day</option><option value="am_half">AM Half</option><option value="pm_half">PM Half</option></select></label></div></section>

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Time Record</h2><p className="mt-1 text-xs text-slate-400">{isToday ? 'Regular time fields are read-only and controlled by Smart Clock.' : 'Official schedule times are pre-filled and may be adjusted.'}</p><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><Field label="AM In" value={form.am_time_in} onChange={change('am_time_in')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="AM Out" value={form.am_time_out} onChange={change('am_time_out')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="PM In" value={form.pm_time_in} onChange={change('pm_time_in')} disabled={isToday || manualDisabled || form.status !== 'present'} /><Field label="PM Out" value={form.pm_time_out} onChange={change('pm_time_out')} disabled={isToday || manualDisabled || form.status !== 'present'} /></div><div id="overtime-section" className="mt-7 border-t border-slate-100 pt-6"><div className="flex items-center gap-2"><h3 className="font-black text-slate-700">Overtime</h3><span className="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-black uppercase text-violet-700">Optional</span></div><div className="mt-4 grid gap-4 sm:grid-cols-2"><Field label="OT Start" value={form.ot_start} onChange={change('ot_start')} disabled={manualDisabled || form.status !== 'present'} /><Field label="OT End" value={form.ot_end} onChange={change('ot_end')} disabled={isToday || manualDisabled || form.status !== 'present'} /></div>{isToday && <p className="mt-2 text-xs text-slate-400">Today’s OT End is fixed to your actual final time-out.</p>}{otOverlap && <div className="mt-4 flex gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs font-bold text-blue-800"><FiInfo className="shrink-0" /> OT range overlaps with the official schedule — only time outside the schedule counts. Lunch is excluded.</div>}</div></section>

    <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:p-7"><h2 className="text-lg font-black text-[#430909]">Accomplishment Report</h2><div className="mt-5 grid gap-5 lg:grid-cols-2"><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">AM Activities</span><textarea rows={5} value={form.am_activity || ''} onChange={change('am_activity')} disabled={manualDisabled || form.status !== 'present'} className="w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm outline-none focus:border-[#800000]/40 disabled:bg-slate-100" placeholder="Describe morning accomplishments..." /></label><label><span className="mb-1.5 block text-xs font-black uppercase text-slate-400">PM Activities</span><textarea rows={5} value={form.pm_activity || ''} onChange={change('pm_activity')} disabled={manualDisabled || form.status !== 'present'} className="w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm outline-none focus:border-[#800000]/40 disabled:bg-slate-100" placeholder="Describe afternoon accomplishments..." /></label></div></section>

    <button onClick={() => saveEntry()} disabled={busy || manualDisabled || (isToday && !selectedRecord)} className="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#800000] px-6 py-4 font-black text-white shadow-xl shadow-[#800000]/15 disabled:cursor-not-allowed disabled:opacity-45"><FiSave />{busy ? 'Saving...' : isToday ? 'Update Accomplishment & Overtime' : `Save Attendance for ${prettyDate(selectedDate)}`}</button>

    {activityModal && <ActivityModal period={activityModal} value={activityModal === 'AM' ? form.am_activity : form.pm_activity} onChange={activityModal === 'AM' ? change('am_activity') : change('pm_activity')} onCancel={() => setActivityModal(null)} onSubmit={(event) => { event.preventDefault(); submitClock(activityModal === 'AM' ? form.am_activity : form.pm_activity); }} busy={busy} />}
    {otQuestion && <OvertimeQuestion onNo={handleOtNo} onYes={handleOtYes} />}
  </div>;
}

export default StudentAttendance;
