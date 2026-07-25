import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiAlertCircle,
  FiBriefcase,
  FiCalendar,
  FiCheck,
  FiCheckCircle,
  FiChevronDown,
  FiClock,
  FiClipboard,
  FiFileText,
  FiGlobe,
  FiHome,
  FiLock,
  FiMapPin,
  FiRefreshCw,
  FiSun,
  FiTrendingUp,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import { BellIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import { useAuth } from '../../context/AuthContext';
import api from '../../services/api';

const statusOptions = [
  { value: 'present', label: 'Present', icon: FiCheckCircle },
  { value: 'absent', label: 'Absent', icon: FiXCircle },
  { value: 'holiday', label: 'Holiday / No Work', icon: FiSun },
];

const workModeOptions = [
  { value: 'wfo', label: 'WFO', description: 'Work From Office', icon: FiBriefcase },
  { value: 'wfh', label: 'WFH', description: 'Work From Home', icon: FiHome },
  { value: 'field', label: 'Field Work', description: 'Outside the office', icon: FiGlobe },
];

const sessionOptions = [
  { value: 'full_day', label: 'Full Day', description: 'AM + PM · 4 slots' },
  { value: 'am_half', label: 'AM Half', description: 'Morning · 2 slots' },
  { value: 'pm_half', label: 'PM Half', description: 'Afternoon · 2 slots' },
];

const previewSlots = {
  full_day: [
    { key: 'am_time_in', label: 'Time In', period: 'AM' },
    { key: 'am_time_out', label: 'Time Out', period: 'AM' },
    { key: 'pm_time_in', label: 'Time In', period: 'PM' },
    { key: 'pm_time_out', label: 'Time Out', period: 'PM' },
  ],
  am_half: [
    { key: 'am_time_in', label: 'Time In', period: 'AM' },
    { key: 'am_time_out', label: 'Time Out', period: 'AM' },
  ],
  pm_half: [
    { key: 'pm_time_in', label: 'Time In', period: 'PM' },
    { key: 'pm_time_out', label: 'Time Out', period: 'PM' },
  ],
};

const statusStyles = {
  pending: { label: 'Pending', badge: 'bg-amber-100 text-amber-700', ring: 'bg-amber-50 text-amber-600', dot: 'bg-amber-500' },
  rejected: { label: 'Rejected', badge: 'bg-rose-100 text-rose-700', ring: 'bg-rose-50 text-rose-600', dot: 'bg-rose-500' },
  done: { label: 'Done', badge: 'bg-emerald-100 text-emerald-700', ring: 'bg-emerald-50 text-emerald-600', dot: 'bg-emerald-500' },
  approved: { label: 'Approved', badge: 'bg-emerald-100 text-emerald-700', ring: 'bg-emerald-50 text-emerald-600', dot: 'bg-emerald-500' },
};

function StatusCard({ title, status, icon: Icon, description }) {
  const style = statusStyles[status] || statusStyles.pending;

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-4">
        <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-xl ${style.ring}`}><Icon /></div>
        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide ${style.badge}`}>
          <span className={`h-1.5 w-1.5 rounded-full ${style.dot}`} /> {style.label}
        </span>
      </div>
      <p className="mt-5 text-sm font-black text-slate-800">{title}</p>
      <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
    </div>
  );
}

function ProgressRing({ percent }) {
  const safePercent = Math.min(Math.max(Number(percent) || 0, 0), 100);

  return (
    <div className="relative h-40 w-40 shrink-0">
      <svg viewBox="0 0 120 120" className="h-full w-full -rotate-90" aria-label={`${safePercent}% complete`}>
        <circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" strokeWidth="10" className="text-slate-100 dark:text-gray-700" />
        <circle
          cx="60"
          cy="60"
          r="50"
          fill="none"
          stroke="#800000"
          strokeWidth="10"
          strokeLinecap="round"
          pathLength="100"
          strokeDasharray={`${safePercent} ${100 - safePercent}`}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        <div><p className="text-3xl font-black text-[#800000]">{safePercent.toFixed(1)}%</p><p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">complete</p></div>
      </div>
    </div>
  );
}

function formatMetric(value) {
  return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function formatOjtDate(value) {
  if (!value) return 'Not set';

  return new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function SelectControl({ label, value, onChange, disabled, children }) {
  return (
    <label className="block min-w-[150px] flex-1">
      <span className="mb-1.5 block text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-400">{label}</span>
      <span className="relative block">
        <select
          value={value}
          onChange={onChange}
          disabled={disabled}
          className="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-9 text-sm font-bold text-slate-700 outline-none transition focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/5 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:disabled:bg-gray-800"
        >
          {children}
        </select>
        <FiChevronDown className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
      </span>
    </label>
  );
}

function ActivityModal({ period, activity, setActivity, submitting, onCancel, onSubmit }) {
  return (
    <div className="fixed inset-0 z-[70] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="activity-title">
      <div className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800">
        <div className="flex items-start justify-between border-b border-slate-100 p-6">
          <div>
            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Activity / Accomplishment Report</p>
            <h2 id="activity-title" className="mt-2 text-2xl font-black text-[#430909]">{period} Activities</h2>
          </div>
          <button type="button" onClick={onCancel} disabled={submitting} className="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close activity modal"><FiX /></button>
        </div>
        <form onSubmit={onSubmit} className="p-6">
          <label htmlFor="activity" className="text-sm font-extrabold text-slate-700">What did you accomplish?</label>
          <p className="mt-1 text-xs leading-5 text-slate-400">Briefly describe the tasks, outputs, meetings, or learning activities completed during this session.</p>
          <textarea
            id="activity"
            value={activity}
            onChange={(event) => setActivity(event.target.value)}
            required
            maxLength={2000}
            rows={6}
            autoFocus
            className="mt-4 w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm leading-6 outline-none transition focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/5"
            placeholder={`Describe your ${period} activities...`}
          />
          <div className="mt-2 text-right text-xs font-medium text-slate-400">{activity.length} / 2000</div>
          <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" onClick={onCancel} disabled={submitting} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={submitting || !activity.trim()} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-[#800000]/15 disabled:cursor-not-allowed disabled:opacity-55">
              {submitting ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/35 border-t-white" /> Saving...</> : <><FiClock /> Submit & Time Out</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function CompletionModal({ onClose }) {
  return (
    <div className="fixed inset-0 z-[70] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="completion-title">
      <div className="w-full max-w-sm rounded-3xl bg-white p-8 text-center shadow-2xl dark:bg-gray-800">
        <div className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-4xl text-emerald-600"><FiCheck /></div>
        <h2 id="completion-title" className="mt-6 text-2xl font-black text-[#430909]">Attendance complete!</h2>
        <p className="mt-3 text-sm leading-6 text-slate-500">All required time slots for today were saved successfully using server time.</p>
        <button type="button" onClick={onClose} className="mt-7 w-full rounded-xl bg-[#800000] px-5 py-3 text-sm font-extrabold text-white">Done</button>
      </div>
    </div>
  );
}

function StudentDashboard() {
  const { user, student } = useAuth();
  const [dashboardData, setDashboardData] = useState(null);
  const [clockState, setClockState] = useState(null);
  const [loading, setLoading] = useState(true);
  const [clockLoading, setClockLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());
  const [status, setStatus] = useState('present');
  const [workMode, setWorkMode] = useState('wfo');
  const [sessionType, setSessionType] = useState('full_day');
  const [activityModalOpen, setActivityModalOpen] = useState(false);
  const [completionModalOpen, setCompletionModalOpen] = useState(false);
  const [activity, setActivity] = useState('');
  const serverOffsetRef = useRef(0);

  useEffect(() => {
    fetchDashboard();
    fetchQuickClock();

    const timer = window.setInterval(() => {
      setCurrentTime(new Date(Date.now() + serverOffsetRef.current));
    }, 1000);

    return () => window.clearInterval(timer);
  }, []);

  const applyClockState = (data) => {
    serverOffsetRef.current = new Date(data.server_time).getTime() - Date.now();
    setCurrentTime(new Date(data.server_time));
    setClockState(data);

    if (data.attendance) {
      setStatus(data.attendance.status === 'late' ? 'present' : data.attendance.status);
      setWorkMode(data.attendance.work_mode || 'wfo');
      setSessionType(data.attendance.session_type || 'full_day');
    }
  };

  const fetchDashboard = async () => {
    try {
      const response = await api.get('/student/dashboard');
      setDashboardData(response.data);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load your dashboard.');
    } finally {
      setLoading(false);
    }
  };

  const fetchQuickClock = async () => {
    setClockLoading(true);
    try {
      const response = await api.get('/student/attendance/quick-clock');
      applyClockState(response.data);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to sync the smart clock.');
    } finally {
      setClockLoading(false);
    }
  };

  const requestLocation = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('GPS is not supported by this browser.'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude }),
      (error) => {
        const messages = {
          1: 'Location permission was denied. Allow GPS access to record your time.',
          2: 'Your location is currently unavailable. Please try again.',
          3: 'Location request timed out. Move to an open area and try again.',
        };
        reject(new Error(messages[error.code] || 'Unable to capture your GPS location.'));
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  });

  const submitSmartLog = async (activityReport = '') => {
    setSubmitting(true);

    try {
      let location = {};
      if (status === 'present') {
        toast.loading('Capturing your GPS location...', { id: 'gps' });
        location = await requestLocation();
        toast.success('Location captured', { id: 'gps' });
      }

      const response = await api.post('/student/attendance/quick-clock', {
        status,
        work_mode: status === 'present' ? workMode : null,
        session_type: status === 'present' ? sessionType : null,
        activity: activityReport || null,
        ...location,
      });

      applyClockState(response.data);
      setActivityModalOpen(false);
      setActivity('');
      toast.success(response.data.message);

      if (response.data.just_completed) {
        setCompletionModalOpen(true);
      }

      fetchDashboard();
    } catch (error) {
      toast.dismiss('gps');
      toast.error(error.response?.data?.message || error.message || 'Unable to record your time.');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePrimaryAction = () => {
    if (status === 'present' && clockState?.next_action?.requires_activity) {
      setActivityModalOpen(true);
      return;
    }

    submitSmartLog();
  };

  const handleActivitySubmit = (event) => {
    event.preventDefault();
    submitSmartLog(activity.trim());
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-12 w-12 animate-spin rounded-full border-b-2 border-[#800000]" />
      </div>
    );
  }

  const settingsLocked = clockState?.settings_locked || false;
  const displaySlots = clockState?.slots?.length ? clockState.slots : previewSlots[sessionType];
  const nextKey = clockState?.next_action?.key || (status === 'present' && !settingsLocked ? displaySlots[0]?.key : null);
  const complete = clockState?.completed || false;
  const primaryLabel = status === 'absent'
    ? 'Submit as Absent'
    : status === 'holiday'
      ? 'Submit as No Work'
      : clockState?.next_action?.label || (complete ? 'All Time Slots Completed' : displaySlots[0]?.label || 'Time In');

  const formattedTime = currentTime.toLocaleTimeString('en-PH', {
    timeZone: clockState?.timezone || 'Asia/Manila',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  const formattedDate = currentTime.toLocaleDateString('en-PH', {
    timeZone: clockState?.timezone || 'Asia/Manila',
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  const dashboardStatus = dashboardData?.dashboard_status || {};
  const progress = dashboardData?.ojt_progress || {};
  const weeklyHours = progress.weekly_hours || [];
  const weeklyScale = Math.max(...weeklyHours.map((item) => Number(item.hours) || 0), 8);

  return (
    <div className="space-y-6">
      <div className="rounded-2xl bg-gradient-to-r from-[#800000] to-[#5c0000] p-6 text-white shadow-lg shadow-[#800000]/10">
        <h1 className="text-2xl font-black">Welcome, {student?.first_name || user?.name}!</h1>
        <p className="mt-1 text-white/75">
          {student?.college?.name || dashboardData?.student?.college} · {student?.program?.name || dashboardData?.student?.program}
        </p>
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <div className="rounded-xl bg-white/12 px-4 py-2 backdrop-blur"><p className="text-xs text-white/60">Year & Section</p><p className="font-bold">{student?.year_level} - {student?.section}</p></div>
          <div className="rounded-xl bg-white/12 px-4 py-2 backdrop-blur"><p className="text-xs text-white/60">Internship Status</p><p className="font-bold capitalize">{student?.internship_status}</p></div>
        </div>
      </div>

      <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-lg shadow-slate-900/[0.04] dark:border-gray-700 dark:bg-gray-800">
        <div className="border-b border-slate-100 bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.12),transparent_38%),linear-gradient(135deg,#fff,#fffaf7)] p-6 dark:border-gray-700 dark:bg-none dark:bg-gray-800 lg:p-8">
          <div className="flex flex-col justify-between gap-6 xl:flex-row xl:items-start">
            <div>
              <div className="flex items-center gap-3">
                <div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#800000] text-xl text-white shadow-lg shadow-[#800000]/15"><FiClock /></div>
                <div><h2 className="text-xl font-black text-[#430909]">Quick Smart Clock</h2><p className="text-xs font-semibold text-slate-400">Tamper-proof time synced with the AIMS server</p></div>
              </div>
              <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                <p className="font-mono text-4xl font-black tracking-tight text-[#800000] sm:text-5xl">{clockLoading ? '--:--:--' : formattedTime}</p>
                <span className="mb-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-extrabold text-emerald-700"><span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" /> LIVE SERVER TIME</span>
              </div>
              <p className="mt-2 text-sm font-semibold text-slate-500">{formattedDate} · Philippine Time</p>
            </div>

            <div className="flex max-w-2xl flex-wrap gap-3 xl:justify-end">
              <SelectControl label="Status" value={status} onChange={(event) => setStatus(event.target.value)} disabled={settingsLocked}>
                {statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </SelectControl>
              {status === 'present' && (
                <>
                  <SelectControl label="Work Mode" value={workMode} onChange={(event) => setWorkMode(event.target.value)} disabled={settingsLocked}>
                    {workModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                  </SelectControl>
                  <SelectControl label="Session Type" value={sessionType} onChange={(event) => setSessionType(event.target.value)} disabled={settingsLocked}>
                    {sessionOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                  </SelectControl>
                </>
              )}
            </div>
          </div>

          {settingsLocked && (
            <div className="mt-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-5 text-amber-800"><FiLock className="mt-0.5 shrink-0" /> Today’s status, work mode, and session type are locked because an attendance record has already been saved.</div>
          )}
        </div>

        <div className="p-6 lg:p-8">
          {status === 'present' ? (
            <>
              <div className="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-bold text-slate-500">
                <span className="inline-flex items-center gap-2"><FiCheckCircle className="text-emerald-500" /> Present</span>
                <span className="inline-flex items-center gap-2">{workModeOptions.find((option) => option.value === workMode)?.label}</span>
                <span className="inline-flex items-center gap-2">{sessionOptions.find((option) => option.value === sessionType)?.description}</span>
              </div>

              <div className={`grid gap-3 ${displaySlots.length === 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}>
                {displaySlots.map((slot, index) => {
                  const isCurrent = !slot.completed && slot.key === nextKey && !complete;
                  return (
                    <div key={slot.key} className={`relative rounded-2xl border p-4 transition ${slot.completed ? 'border-emerald-200 bg-emerald-50/60' : isCurrent ? 'border-[#800000]/35 bg-[#800000]/[0.025] shadow-[0_0_0_4px_rgba(128,0,0,0.05)]' : 'border-slate-200 bg-slate-50/60'}`}>
                      {isCurrent && <span className="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-[#800000]/60" />}
                      <div className={`grid h-9 w-9 place-items-center rounded-full text-sm font-black ${slot.completed ? 'bg-emerald-500 text-white' : isCurrent ? 'border-2 border-[#800000] bg-white text-[#800000]' : 'border-2 border-slate-200 bg-white text-slate-400'}`}>
                        {slot.completed ? <FiCheck /> : slot.number || index + 1}
                      </div>
                      <p className="mt-3 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-400">{slot.period}</p>
                      <p className="mt-0.5 text-sm font-black text-slate-700">{slot.label.replace(` (${slot.period})`, '')}</p>
                      <p className={`mt-2 text-xs font-bold ${slot.completed ? 'text-emerald-700' : isCurrent ? 'text-[#800000]' : 'text-slate-400'}`}>
                        {slot.completed
                          ? new Date(slot.time).toLocaleTimeString('en-PH', { timeZone: clockState?.timezone || 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit' })
                          : isCurrent ? 'Next action' : 'Not yet recorded'}
                      </p>
                    </div>
                  );
                })}
              </div>
            </>
          ) : (
            <div className={`rounded-2xl border p-5 ${status === 'absent' ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'}`}>
              <div className="flex items-start gap-3">
                {status === 'absent' ? <FiAlertCircle className="mt-0.5 text-xl text-rose-600" /> : <FiSun className="mt-0.5 text-xl text-amber-600" />}
                <div><p className="font-black text-slate-800">{status === 'absent' ? 'Mark today as absent' : 'Mark today as holiday / no work'}</p><p className="mt-1 text-sm leading-6 text-slate-500">This saves today with zero rendered hours. GPS and time slots are not required.</p></div>
              </div>
            </div>
          )}

          {complete ? (
            <div className="mt-6 flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-4 text-center text-sm font-extrabold text-white"><FiCheckCircle className="text-lg" /> {status === 'present' ? 'All time slots completed for today!' : status === 'absent' ? 'Today has been recorded as absent.' : 'Today has been recorded as holiday / no work.'}</div>
          ) : (
            <button
              type="button"
              onClick={handlePrimaryAction}
              disabled={submitting || clockLoading}
              className={`mt-6 flex w-full items-center justify-center gap-3 rounded-2xl px-6 py-4 text-base font-black text-white shadow-xl transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-55 ${status === 'absent' ? 'bg-rose-600 shadow-rose-600/15 hover:bg-rose-700' : status === 'holiday' ? 'bg-amber-500 shadow-amber-500/15 hover:bg-amber-600' : clockState?.next_action?.type === 'out' ? 'bg-[#a8750b] shadow-[#a8750b]/15 hover:bg-[#8f6207]' : 'bg-[#800000] shadow-[#800000]/15 hover:bg-[#650000]'}`}
            >
              {submitting ? <><span className="h-5 w-5 animate-spin rounded-full border-2 border-white/35 border-t-white" /> Recording with server time...</> : <><FiClock /> {primaryLabel}</>}
            </button>
          )}

          <div className="mt-4 flex flex-col justify-between gap-3 text-xs text-slate-400 sm:flex-row sm:items-center">
            <p className="flex items-start gap-2"><FiMapPin className="mt-0.5 shrink-0" /> GPS is requested only when you record a present time slot. WFO logs are checked against your HTE geofence.</p>
            <button type="button" onClick={fetchQuickClock} disabled={clockLoading} className="inline-flex shrink-0 items-center gap-1.5 font-extrabold text-[#800000] disabled:opacity-50"><FiRefreshCw className={clockLoading ? 'animate-spin' : ''} /> Resync</button>
          </div>
        </div>
      </section>

      <section>
        <div className="mb-4 flex items-end justify-between gap-4">
          <div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Internship requirements</p><h2 className="mt-1 text-xl font-black text-[#430909]">OJT Status</h2></div>
          <p className="hidden text-xs font-semibold text-slate-400 sm:block">Synced with your AIMS records</p>
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          <StatusCard
            title="Consent"
            status={dashboardStatus.consent}
            icon={FiFileText}
            description="Your consent form review and completion status."
          />
          <StatusCard
            title="Schedule"
            status={dashboardStatus.schedule}
            icon={FiCalendar}
            description="Coordinator approval status for your OJT schedule."
          />
          <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex items-start justify-between gap-4">
              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-xl text-blue-600"><FiCheckCircle /></div>
              <span className="rounded-full bg-blue-100 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide text-blue-700">Recorded</span>
            </div>
            <p className="mt-5 text-sm font-black text-slate-800">Attendance</p>
            <p className="mt-1 text-2xl font-black text-[#430909]">{dashboardStatus.attendance_days || 0} <span className="text-sm text-slate-400">days</span></p>
          </div>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-lg shadow-slate-900/[0.04] dark:border-gray-700 dark:bg-gray-800">
        <div className="border-b border-slate-100 bg-[radial-gradient(circle_at_top_right,rgba(212,175,55,0.13),transparent_38%),linear-gradient(135deg,#fff,#fffaf7)] p-6 dark:border-gray-700 dark:bg-none dark:bg-gray-800 lg:p-8">
          <div className="flex items-center gap-3">
            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#800000] text-xl text-white"><FiTrendingUp /></div>
            <div><h2 className="text-xl font-black text-[#430909]">OJT Progress</h2><p className="text-xs font-semibold text-slate-400">Calculated from completed attendance time slots</p></div>
          </div>

          <div className="mt-7 flex flex-col gap-7 xl:flex-row xl:items-center">
            <div className="flex flex-col items-center gap-6 sm:flex-row">
              <ProgressRing percent={progress.percent_complete} />
              <div className="grid w-full grid-cols-2 gap-3 sm:w-[340px]">
                {[
                  { value: formatMetric(progress.rendered_hours), label: 'hrs done', tone: 'text-emerald-600' },
                  { value: formatMetric(progress.hours_left), label: 'hrs left', tone: 'text-[#800000]' },
                  { value: progress.entries || 0, label: 'entries', tone: 'text-blue-600' },
                  { value: progress.days_left ?? '—', label: 'days left', tone: 'text-[#a8750b]' },
                ].map((metric) => (
                  <div key={metric.label} className="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/80">
                    <p className={`text-2xl font-black ${metric.tone}`}>{metric.value}</p>
                    <p className="mt-1 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-400">{metric.label}</p>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex-1 space-y-6 xl:border-l xl:border-slate-200 xl:pl-8">
              <div>
                <div className="mb-2 flex items-center justify-between gap-4 text-sm"><span className="font-bold text-slate-600">Hours</span><span className="font-black text-[#430909]">{formatMetric(progress.rendered_hours)} <span className="font-semibold text-slate-400">/ {formatMetric(progress.required_hours)}</span></span></div>
                <div className="h-3 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-gradient-to-r from-[#800000] to-[#b88918] transition-all duration-700" style={{ width: `${Math.min(Number(progress.percent_complete) || 0, 100)}%` }} /></div>
              </div>
              <div>
                <div className="mb-2 flex items-center justify-between gap-4 text-sm"><span className="font-bold text-slate-600">Semester</span><span className="font-black text-[#430909]">{formatMetric(progress.semester_elapsed_percent)}% <span className="font-semibold text-slate-400">elapsed</span></span></div>
                <div className="h-3 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-[#d4af37] transition-all duration-700" style={{ width: `${Math.min(Number(progress.semester_elapsed_percent) || 0, 100)}%` }} /></div>
                <div className="mt-3 flex justify-between text-xs font-bold text-slate-400"><span>{formatOjtDate(progress.start_date)}</span><span>{formatOjtDate(progress.end_date)}</span></div>
              </div>
              <div className={`rounded-xl border px-4 py-3 text-sm font-bold ${progress.period_state === 'ended' ? 'border-amber-200 bg-amber-50 text-amber-800' : progress.period_state === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
                {progress.period_message || 'OJT schedule dates are not configured yet.'}
              </div>
            </div>
          </div>
        </div>

        <div className="p-6 lg:p-8">
          <div className="flex items-end justify-between gap-4">
            <div><p className="text-xs font-extrabold uppercase tracking-[0.15em] text-slate-400">This Week</p><p className="mt-1 text-2xl font-black text-[#430909]">{formatMetric(progress.this_week_hours)} <span className="text-sm font-bold text-slate-400">hrs</span></p></div>
            <p className="text-xs font-semibold text-slate-400">Server-recorded hours</p>
          </div>
          <div className="mt-6 grid h-52 grid-cols-7 items-end gap-2 sm:gap-4">
            {weeklyHours.map((item) => {
              const barHeight = Number(item.hours) > 0 ? Math.max((Number(item.hours) / weeklyScale) * 100, 7) : 2;
              return (
                <div key={item.date} className="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                  <span className="text-[10px] font-extrabold text-slate-400 sm:text-xs">{formatMetric(item.hours)}</span>
                  <div className="flex h-36 w-full max-w-12 items-end overflow-hidden rounded-t-xl bg-slate-100">
                    <div className="w-full rounded-t-xl bg-gradient-to-t from-[#800000] to-[#d4af37] transition-all duration-700" style={{ height: `${barHeight}%` }} title={`${item.day}: ${formatMetric(item.hours)} hours`} />
                  </div>
                  <span className="text-[10px] font-black uppercase text-slate-500 sm:text-xs">{item.day}</span>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {dashboardData?.tasks?.length > 0 && (
        <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div className="mb-4 flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#800000]/10 text-[#800000]"><FiClipboard /></div>
            <div><h3 className="font-black text-[#430909] dark:text-white">Assigned HTE Tasks</h3><p className="text-xs text-slate-400">Current deliverables from your HTE supervisor</p></div>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            {dashboardData.tasks.map((task) => (
              <article key={task.id} className="rounded-2xl border border-slate-100 p-4 dark:border-gray-700">
                <div className="flex items-start justify-between gap-3"><h4 className="font-black text-slate-800 dark:text-white">{task.title}</h4><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${task.priority === 'high' ? 'bg-rose-100 text-rose-700' : task.priority === 'low' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}`}>{task.priority}</span></div>
                <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{task.description || 'No additional instructions.'}</p>
                <p className="mt-3 text-xs font-bold text-[#800000] dark:text-rose-300">{task.due_date ? `Due ${new Date(task.due_date).toLocaleDateString()}` : 'No fixed deadline'} · {task.status.replace('_', ' ')}</p>
              </article>
            ))}
          </div>
        </section>
      )}

      <div className="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-800">Recent Announcements</h3>
          <Link to="/student/announcements" className="text-sm font-medium text-[#800000] hover:text-[#5c0000]">View All</Link>
        </div>
        {dashboardData?.announcements?.length > 0 ? (
          <div className="grid gap-4 lg:grid-cols-2">
            {dashboardData.announcements.map((announcement) => (
              <div key={announcement.id} className="rounded-xl border border-slate-100 p-4">
                <h4 className="font-medium text-gray-800">{announcement.title}</h4>
                <p className="mt-1 line-clamp-2 text-sm text-gray-500">{announcement.content}</p>
                <p className="mt-2 text-xs text-gray-400">{new Date(announcement.created_at).toLocaleDateString()}</p>
              </div>
            ))}
          </div>
        ) : (
          <div className="py-8 text-center text-gray-500"><BellIcon className="mx-auto mb-2 h-12 w-12 text-gray-300" /><p>No announcements yet</p></div>
        )}
      </div>

      {activityModalOpen && (
        <ActivityModal
          period={clockState?.next_action?.period || 'Session'}
          activity={activity}
          setActivity={setActivity}
          submitting={submitting}
          onCancel={() => { setActivityModalOpen(false); setActivity(''); }}
          onSubmit={handleActivitySubmit}
        />
      )}
      {completionModalOpen && <CompletionModal onClose={() => setCompletionModalOpen(false)} />}
    </div>
  );
}

export default StudentDashboard;
