import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiBriefcase,
  FiCalendar,
  FiCheck,
  FiCheckCircle,
  FiClock,
  FiClipboard,
  FiGlobe,
  FiHome,
  FiMapPin,
  FiTrendingUp,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import { BellIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import useAuth from '../../context/useAuth';
import api from '../../services/api';

const statusOptions = [
  { value: 'present', label: 'Present', icon: FiCheckCircle },
  { value: 'absent', label: 'Absent', icon: FiXCircle },
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
          stroke="#7D001D"
          strokeWidth="10"
          strokeLinecap="round"
          pathLength="100"
          strokeDasharray={`${safePercent} ${100 - safePercent}`}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        <div><p className="text-3xl font-black text-[#7D001D]">{safePercent.toFixed(1)}%</p><p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">complete</p></div>
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

function ActivityModal({ period, activity, setActivity, submitting, onCancel, onSubmit }) {
  return (
    <div className="fixed inset-0 z-[70] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="activity-title">
      <div className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800">
        <div className="flex items-start justify-between border-b border-slate-100 p-6">
          <div>
            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Activity / Accomplishment Report</p>
            <h2 id="activity-title" className="mt-2 text-2xl font-black text-[#690016]">{period} Activities</h2>
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
            className="mt-4 w-full resize-none rounded-2xl border border-slate-200 p-4 text-sm leading-6 outline-none transition focus:border-[#7D001D]/40 focus:ring-4 focus:ring-[#7D001D]/5"
            placeholder={`Describe your ${period} activities...`}
          />
          <div className="mt-2 text-right text-xs font-medium text-slate-400">{activity.length} / 2000</div>
          <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" onClick={onCancel} disabled={submitting} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={submitting || !activity.trim()} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-[#7D001D]/15 disabled:cursor-not-allowed disabled:opacity-55">
              {submitting ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/35 border-t-white" /> Saving...</> : <><FiClock /> Submit & Time Out</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function CompletionModal({ onClose, usesServerTime }) {
  return (
    <div className="fixed inset-0 z-[70] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="completion-title">
      <div className="w-full max-w-sm rounded-3xl bg-white p-8 text-center shadow-2xl dark:bg-gray-800">
        <div className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-4xl text-emerald-600"><FiCheck /></div>
        <h2 id="completion-title" className="mt-6 text-2xl font-black text-[#690016]">Attendance complete!</h2>
        <p className="mt-3 text-sm leading-6 text-slate-500">All required time slots for today were saved successfully using {usesServerTime ? 'server time' : 'device time in test mode'}.</p>
        <button type="button" onClick={onClose} className="mt-7 w-full rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-extrabold text-white">Done</button>
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
  const [locationSharing, setLocationSharing] = useState('idle');
  const serverOffsetRef = useRef(0);
  const latestLocationRef = useRef(null);
  const latestLocationCapturedAtRef = useRef(0);
  const locationSendingRef = useRef(false);
  const outsideGeofenceRef = useRef(false);

  useEffect(() => {
    fetchDashboard();
    fetchQuickClock();

    const timer = window.setInterval(() => {
      setCurrentTime(new Date(Date.now() + serverOffsetRef.current));
    }, 1000);

    return () => window.clearInterval(timer);
  }, []);

  const liveTrackingActive = status === 'present'
    && !clockLoading
    && !clockState?.completed
    && Boolean(clockState?.attendance)
    && clockState?.next_action?.type === 'out';

  useEffect(() => {
    if (!liveTrackingActive) {
      latestLocationRef.current = null;
      latestLocationCapturedAtRef.current = 0;
      outsideGeofenceRef.current = false;
      toast.dismiss('live-geofence');
      setLocationSharing('idle');
      return undefined;
    }

    if (!navigator.geolocation) {
      setLocationSharing('unsupported');
      return undefined;
    }

    if (!window.isSecureContext) {
      setLocationSharing('insecure');
      return undefined;
    }

    let active = true;
    setLocationSharing('requesting');

    const shareLatestLocation = async () => {
      if (!active || !latestLocationRef.current || locationSendingRef.current) return;
      locationSendingRef.current = true;

      try {
        const response = await api.post('/student/attendance/live-location', latestLocationRef.current);
        if (!active) return;

        if (response.data.outside_geofence) {
          setLocationSharing('outside');
          if (!outsideGeofenceRef.current) {
            toast.error(response.data.message, { id: 'live-geofence', duration: 8000 });
          }
          outsideGeofenceRef.current = true;
        } else {
          setLocationSharing('sharing');
          if (outsideGeofenceRef.current) {
            toast.success('You are back within the HTE geofence.', { id: 'live-geofence' });
          }
          outsideGeofenceRef.current = false;
        }
      } catch (error) {
        if (active) setLocationSharing(error.response?.status === 422 ? 'idle' : 'error');
      } finally {
        locationSendingRef.current = false;
      }
    };

    const watchId = navigator.geolocation.watchPosition(
      ({ coords }) => {
        latestLocationRef.current = {
          latitude: coords.latitude,
          longitude: coords.longitude,
          accuracy: coords.accuracy,
        };
        latestLocationCapturedAtRef.current = Date.now();
        shareLatestLocation();
      },
      (error) => {
        if (!active) return;

        if (error.code === 1) {
          setLocationSharing('denied');
          return;
        }

        setLocationSharing(outsideGeofenceRef.current ? 'outside' : 'waiting');
      },
      { enableHighAccuracy: true, timeout: 30000, maximumAge: 30000 },
    );
    const heartbeat = window.setInterval(shareLatestLocation, 15000);

    return () => {
      active = false;
      navigator.geolocation.clearWatch(watchId);
      window.clearInterval(heartbeat);
      latestLocationRef.current = null;
      latestLocationCapturedAtRef.current = 0;
      outsideGeofenceRef.current = false;
      toast.dismiss('live-geofence');
    };
  }, [liveTrackingActive]);

  const applyClockState = (data) => {
    serverOffsetRef.current = data.uses_server_time
      ? new Date(data.server_time).getTime() - Date.now()
      : 0;
    setCurrentTime(data.uses_server_time ? new Date(data.server_time) : new Date());
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

  const captureLocation = (options) => new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy }),
      reject,
      options,
    );
  });

  const requestLocation = async () => {
    if (!navigator.geolocation) {
      throw new Error('GPS is not supported by this browser.');
    }

    if (!window.isSecureContext) {
      throw new Error('Location requires HTTPS or localhost. Open AIMS through a secure address and try again.');
    }

    const liveLocationAge = Date.now() - latestLocationCapturedAtRef.current;
    if (latestLocationRef.current && liveLocationAge >= 0 && liveLocationAge <= 30000) {
      return latestLocationRef.current;
    }

    try {
      return await captureLocation({ enableHighAccuracy: true, timeout: 25000, maximumAge: 10000 });
    } catch (firstError) {
      if (firstError.code === 1) {
        throw new Error('Location permission was denied. Allow precise location access to record your time.');
      }

      toast.loading('GPS is taking longer than usual. Retrying location...', { id: 'gps' });

      try {
        return await captureLocation({ enableHighAccuracy: false, timeout: 15000, maximumAge: 30000 });
      } catch (retryError) {
        const messages = {
          1: 'Location permission was denied. Allow precise location access to record your time.',
          2: 'Location is unavailable. Turn on your device Location Services and browser location permission, then try again.',
          3: 'Location could not be detected. Turn on Location Services and Precise Location, then try again.',
        };
        throw new Error(messages[retryError.code] || 'Unable to capture your GPS location.');
      }
    }
  };

  const submitSmartLog = async (activityReport = '') => {
    setSubmitting(true);

    try {
      let location = {};
      if (status === 'present') {
        toast.loading('Capturing your GPS location...', { id: 'gps' });
        location = await requestLocation();
        latestLocationRef.current = location;
        latestLocationCapturedAtRef.current = Date.now();
        toast.success('Location captured', { id: 'gps' });
      }

      const response = await api.post('/student/attendance/quick-clock', {
        status,
        work_mode: status === 'present' ? workMode : null,
        session_type: status === 'present' ? sessionType : null,
        activity: activityReport || null,
        client_time: new Date().toISOString(),
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
        <div className="h-12 w-12 animate-spin rounded-full border-b-2 border-[#7D001D]" />
      </div>
    );
  }

  const settingsLocked = clockState?.settings_locked || false;
  const todayHoliday = clockState?.today_holiday;
  const clockDisabled = Boolean(clockState?.clock_disabled);
  const displaySlots = clockDisabled ? [] : clockState?.slots?.length ? clockState.slots : previewSlots[sessionType];
  const nextKey = clockState?.next_action?.key || (status === 'present' && !settingsLocked ? displaySlots[0]?.key : null);
  const complete = clockState?.completed || false;
  const primaryLabel = status === 'absent'
    ? 'Submit as Absent'
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
  const requirementsProgress = dashboardData?.requirements_progress || {};
  const requirementsPercent = Math.min(Math.max(Number(requirementsProgress.percent_complete) || 0, 0), 100);
  const progress = dashboardData?.ojt_progress || {};
  const holidayForecast = dashboardData?.holiday_forecast || {};
  const weeklyHours = progress.weekly_hours || [];
  const weeklyScale = Math.max(...weeklyHours.map((item) => Number(item.hours) || 0), 8);
  const completedSlots = displaySlots.filter((slot) => slot.completed).length;
  const clockProgress = displaySlots.length ? Math.round((completedSlots / displaySlots.length) * 100) : 0;

  return (
    <div className="space-y-6">
      <div className="border-b border-slate-200 pb-5 dark:border-slate-700">
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Welcome, {student?.first_name || user?.name}!</h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {student?.college?.name || dashboardData?.student?.college} · {student?.program?.name || dashboardData?.student?.program}
        </p>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <div className="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800"><p className="text-xs text-slate-500 dark:text-slate-400">Year & Section</p><p className="text-sm font-bold">{student?.year_level} - {student?.section}</p></div>
          <div className="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800"><p className="text-xs text-slate-500 dark:text-slate-400">Internship Status</p><p className="text-sm font-bold capitalize">{student?.internship_status}</p></div>
        </div>
      </div>

      <section className="quick-smart-clock relative isolate mx-auto flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-[linear-gradient(135deg,#690016_0%,#820020_55%,#690016_100%)] px-4 py-4 text-white sm:px-5">

        <header className="quick-smart-clock-header relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-3">
            <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-white/10 ring-1 ring-inset ring-white/15">
              <FiClock className="size-5" aria-hidden="true" />
            </div>
            <div>
              <p className="text-[9px] font-bold uppercase tracking-[0.14em] text-[#f4cf63]">Attendance</p>
              <h2 className="text-lg font-bold text-white">Quick Smart Clock</h2>
            </div>
          </div>

          <div className={`quick-smart-clock-controls grid w-full grid-cols-3 gap-1.5 p-1.5 md:w-32 md:grid-cols-1 ${status === 'present' ? '' : 'quick-smart-clock-controls--single'}`}>
            <label className="flex h-8 min-h-0 min-w-0 items-center overflow-hidden rounded-lg border border-white/25 bg-white/10">
              <span className="grid h-full w-8 shrink-0 place-items-center border-r border-white/20">
                <FiCheckCircle className="size-3.5" aria-hidden="true" />
              </span>
              <select id="quick-clock-attendance-status" name="attendance_status" value={status} onChange={(event) => setStatus(event.target.value)} disabled={settingsLocked} aria-label="Attendance status" className="h-full min-h-0 w-full min-w-0 flex-1 appearance-none border-0 bg-transparent p-0 text-center text-[11px] font-bold text-white outline-none">
                {statusOptions.map((option) => <option key={option.value} value={option.value} className="bg-[#720b0b] text-white">{option.label}</option>)}
              </select>
            </label>
            {status === 'present' && <>
              <label className="flex h-8 min-h-0 min-w-0 items-center overflow-hidden rounded-lg border border-white/25 bg-white/10">
                <span className="grid h-full w-8 shrink-0 place-items-center border-r border-white/20">
                  <FiBriefcase className="size-3.5" aria-hidden="true" />
                </span>
                <select id="quick-clock-work-mode" name="work_mode" value={workMode} onChange={(event) => setWorkMode(event.target.value)} disabled={settingsLocked} aria-label="Work mode" className="h-full min-h-0 w-full min-w-0 flex-1 appearance-none border-0 bg-transparent p-0 text-center text-[11px] font-bold text-white outline-none">
                  {workModeOptions.map((option) => <option key={option.value} value={option.value} className="bg-[#720b0b] text-white">{option.label}</option>)}
                </select>
              </label>
              <label className="flex h-8 min-h-0 min-w-0 items-center overflow-hidden rounded-lg border border-white/25 bg-white/10">
                <span className="grid h-full w-8 shrink-0 place-items-center border-r border-white/20">
                  <FiClock className="size-3.5" aria-hidden="true" />
                </span>
                <select id="quick-clock-session-type" name="session_type" value={sessionType} onChange={(event) => setSessionType(event.target.value)} disabled={settingsLocked} aria-label="Session type" className="h-full min-h-0 w-full min-w-0 flex-1 appearance-none border-0 bg-transparent p-0 text-center text-[11px] font-bold text-white outline-none">
                  {sessionOptions.map((option) => <option key={option.value} value={option.value} className="bg-[#720b0b] text-white">{option.label}</option>)}
                </select>
              </label>
            </>}
          </div>
        </header>

        <div className="quick-smart-clock-face relative mx-auto flex w-full max-w-xl flex-col items-center py-6 text-center sm:py-7">
          <time className="font-mono text-3xl font-black leading-none tabular-nums sm:text-4xl">
            {clockLoading ? '--:--:--' : formattedTime}
          </time>
          <p className="mt-2 text-sm font-bold text-white/85">{formattedDate}</p>
          <p className="mt-1 text-[10px] font-black uppercase tracking-wider text-white/55">{clockState?.uses_server_time ? 'Server-synchronized time' : 'Device time · test mode'}</p>

          {liveTrackingActive && (
            <div className={`mt-3 inline-flex items-center gap-2 rounded-2xl border px-3 py-2 text-[10px] font-black uppercase tracking-wide ${locationSharing === 'outside' ? 'border-amber-300/50 bg-amber-400/20 text-amber-100' : locationSharing === 'sharing' ? 'border-emerald-300/40 bg-emerald-400/15 text-emerald-100' : 'border-white/20 bg-white/10 text-white/70'}`}>
              <FiMapPin className={locationSharing === 'sharing' || locationSharing === 'outside' ? 'animate-pulse' : ''} />
              {locationSharing === 'outside'
                ? 'Warning: move closer to your assigned HTE'
                : locationSharing === 'sharing'
                  ? 'Live monitoring active until Time Out'
                  : locationSharing === 'denied'
                    ? 'Location permission was blocked'
                    : locationSharing === 'waiting'
                      ? 'Last location retained · waiting for GPS update'
                    : locationSharing === 'unsupported'
                      ? 'Live location is not supported'
                      : locationSharing === 'insecure'
                        ? 'GPS requires HTTPS or localhost'
                        : locationSharing === 'error'
                          ? 'Unable to update live location'
                          : 'Starting live monitoring...'}
            </div>
          )}

          {status === 'present' && displaySlots.length > 0 && (
            <ol className={'mt-4 grid w-full max-w-xs gap-2 ' + (displaySlots.length === 4 ? 'grid-cols-4' : 'grid-cols-2')} aria-label="Clock progress">
              {displaySlots.map((slot, index) => {
                const isCurrent = !slot.completed && slot.key === nextKey && !complete;
                const period = slot.period || (slot.key?.startsWith('am_') ? 'AM' : 'PM');
                const action = slot.label?.includes('Out') ? 'Out' : 'In';
                return <li key={slot.key} className="flex min-w-0 flex-col items-center">
                  <span className={'grid size-9 place-items-center rounded-full border-2 text-xs font-bold ' + (slot.completed ? 'border-[#f2cf70] bg-[#f2cf70] text-[#690016]' : isCurrent ? 'border-white bg-white/15 text-white' : 'border-white/20 bg-black/10 text-white/70')}>
                    {slot.completed ? <FiCheck aria-label="Completed" /> : slot.number || index + 1}
                  </span>
                  <span className="mt-1.5 text-[10px] font-bold text-white/75 sm:text-[11px]">{period} {action}</span>
                  {slot.late_minutes > 0 && <span className="mt-1 text-[9px] font-black text-amber-200">{slot.late_minutes}m late</span>}
                </li>;
              })}
            </ol>
          )}

          {status !== 'present' && !clockDisabled && <div className="mt-4 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white/80">Record today as absent</div>}

          {clockDisabled ? (
            <div className="mt-6 w-full max-w-lg rounded-2xl border border-violet-200/30 bg-white/15 px-5 py-4 text-left shadow-lg">
              <p className="text-sm font-black text-amber-200">Official Holiday · Smart Clock unavailable</p>
              <p className="mt-1 text-lg font-black">{todayHoliday?.name}</p>
              <p className="mt-2 text-xs leading-5 text-white/75">No OJT attendance is required today. No hours, absence, or late record will be added.</p>
              {todayHoliday?.official_reference && <p className="mt-2 text-[10px] font-bold uppercase tracking-wide text-white/55">{todayHoliday.official_reference}</p>}
            </div>
          ) : complete ? (
            <div className="mt-8 rounded-full border border-white/20 bg-white/15 px-7 py-4 text-sm font-black shadow-lg"><FiCheckCircle className="mr-2 inline h-5 w-5" />Today’s attendance is complete</div>
          ) : (
            <button type="button" onClick={handlePrimaryAction} disabled={submitting || clockLoading} className="quick-smart-clock-action mt-4 inline-flex w-full max-w-44 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#7D001D] transition hover:bg-[#fff9e8] disabled:cursor-not-allowed disabled:opacity-60">
              {submitting ? <><span className="h-5 w-5 animate-spin rounded-full border-2 border-[#7D001D]/25 border-t-[#7D001D]" />Recording...</> : <><FiClock />{primaryLabel}</>}
            </button>
          )}
        </div>

        <footer className="quick-smart-clock-footer relative">
          <div className="h-1 overflow-hidden rounded-full bg-white/15" aria-hidden="true"><div className="h-full rounded-full bg-[#d4af37] transition-[width] duration-300" style={{ width: clockProgress + '%' }} /></div>
          <div className="mt-2.5 flex items-center justify-between gap-4 text-xs font-bold text-white/70">
            <span>{clockDisabled ? 'Official holiday · no attendance required' : `${completedSlots}/${displaySlots.length} completed`}</span>
            <Link to="/student/attendance" className="inline-flex items-center gap-1.5 text-sm font-black text-white/75 transition hover:text-white">See full log <span aria-hidden="true">↗</span></Link>
          </div>
          <button type="button" onClick={fetchQuickClock} disabled={clockLoading} className="sr-only">Resync clock</button>
        </footer>
      </section>
      <section className="rounded-3xl border border-violet-100 bg-[linear-gradient(135deg,#faf7ff,#fff)] p-5 shadow-sm dark:border-violet-900 dark:bg-none dark:bg-gray-800">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-violet-100 text-xl text-violet-700"><FiCalendar /></span>
            <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-violet-600">Holiday Forecast</p><h2 className="mt-1 text-lg font-black text-slate-800 dark:text-white">{holidayForecast.next?.name || 'No upcoming official holiday'}</h2>{holidayForecast.next && <p className="mt-1 text-xs text-slate-500">{new Date(`${holidayForecast.next.date}T00:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })} · {holidayForecast.next.days_until === 0 ? 'Today' : `${holidayForecast.next.days_until} days remaining`}</p>}</div>
          </div>
          {holidayForecast.next && <span className="rounded-full bg-violet-100 px-3 py-1.5 text-[10px] font-black uppercase text-violet-700">{holidayForecast.next.type_label}</span>}
        </div>
        {holidayForecast.next && <div className="mt-4 rounded-2xl border border-violet-100 bg-white/80 p-4 text-xs text-slate-600 dark:border-violet-900 dark:bg-gray-900 dark:text-slate-300"><p className="font-black text-violet-800 dark:text-violet-300">No OJT attendance required</p><p className="mt-1">{holidayForecast.next.affects_workday ? 'This holiday falls on your scheduled working day. Your remaining required hours and working days will not decrease.' : 'This holiday does not fall on your scheduled working day, so it has no attendance impact.'}</p></div>}
        {!!holidayForecast.upcoming?.length && <Link to="/student/attendance" className="mt-4 inline-flex items-center gap-2 text-xs font-black text-[#7D001D] hover:underline"><FiCalendar /> View holidays in calendar</Link>}
      </section>
      <section>
        <div className="mb-4 flex items-end justify-between gap-4">
          <div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Internship requirements</p><h2 className="mt-1 text-xl font-black text-[#690016]">OJT Status</h2></div>
          <p className="hidden text-xs font-semibold text-slate-400 sm:block">Synced with your AIMS records</p>
        </div>
        <div className="grid gap-4 md:grid-cols-3">
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
            <p className="mt-1 text-2xl font-black text-[#690016]">{dashboardStatus.attendance_days || 0} <span className="text-sm text-slate-400">days</span></p>
          </div>
          <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex items-start justify-between gap-4">
              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-violet-50 text-xl text-violet-600"><FiClipboard /></div>
              <span className="rounded-full bg-violet-100 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide text-violet-700">{requirementsPercent.toFixed(1)}%</span>
            </div>
            <p className="mt-5 text-sm font-black text-slate-800">Requirements</p>
            <div className="mt-2 flex items-center justify-between gap-3 text-xs font-bold">
              <span className="text-slate-500">{requirementsProgress.approved || 0} of {requirementsProgress.total || 0} approved</span>
              <Link to="/student/requirements" className="text-[#7D001D] hover:underline">View</Link>
            </div>
            <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="Requirements progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow={requirementsPercent}>
              <div className="h-full rounded-full bg-gradient-to-r from-violet-600 to-[#d4af37] transition-all duration-700" style={{ width: `${requirementsPercent}%` }} />
            </div>
          </div>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-lg shadow-slate-900/[0.04] dark:border-gray-700 dark:bg-gray-800">
        <div className="border-b border-slate-100 bg-[radial-gradient(circle_at_top_right,rgba(212,175,55,0.13),transparent_38%),linear-gradient(135deg,#fff,#fffaf7)] p-6 dark:border-gray-700 dark:bg-none dark:bg-gray-800 lg:p-8">
          <div className="flex items-center gap-3">
            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#7D001D] text-xl text-white"><FiTrendingUp /></div>
            <div><h2 className="text-xl font-black text-[#690016]">OJT Progress</h2><p className="text-xs font-semibold text-slate-400">Calculated from completed attendance time slots</p></div>
          </div>

          <div className="mt-7 flex flex-col gap-7 xl:flex-row xl:items-center">
            <div className="flex flex-col items-center gap-6 sm:flex-row">
              <ProgressRing percent={progress.percent_complete} />
              <div className="grid w-full grid-cols-2 gap-3 sm:w-[340px]">
                {[
                  { value: formatMetric(progress.rendered_hours), label: 'hrs done', tone: 'text-emerald-600' },
                  { value: formatMetric(progress.hours_left), label: 'hrs left', tone: 'text-[#7D001D]' },
                  { value: progress.entries || 0, label: 'entries', tone: 'text-blue-600' },
                  { value: progress.working_days_left ?? progress.days_left ?? '—', label: 'working days left', tone: 'text-[#a8750b]' },
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
                <div className="mb-2 flex items-center justify-between gap-4 text-sm"><span className="font-bold text-slate-600">Hours</span><span className="font-black text-[#690016]">{formatMetric(progress.rendered_hours)} <span className="font-semibold text-slate-400">/ {formatMetric(progress.required_hours)}</span></span></div>
                <div className="h-3 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-gradient-to-r from-[#7D001D] to-[#b88918] transition-all duration-700" style={{ width: `${Math.min(Number(progress.percent_complete) || 0, 100)}%` }} /></div>
              </div>
              <div>
                <div className="mb-2 flex items-center justify-between gap-4 text-sm"><span className="font-bold text-slate-600">Semester</span><span className="font-black text-[#690016]">{formatMetric(progress.semester_elapsed_percent)}% <span className="font-semibold text-slate-400">elapsed</span></span></div>
                <div className="h-3 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-[#d4af37] transition-all duration-700" style={{ width: `${Math.min(Number(progress.semester_elapsed_percent) || 0, 100)}%` }} /></div>
                <div className="mt-3 flex justify-between text-xs font-bold text-slate-400"><span>{formatOjtDate(progress.start_date)}</span><span>{formatOjtDate(progress.end_date)}</span></div>
              </div>
              <div className={`rounded-xl border px-4 py-3 text-sm font-bold ${progress.period_state === 'ended' ? 'border-amber-200 bg-amber-50 text-amber-800' : progress.period_state === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
                {progress.period_message || 'OJT schedule dates are not configured yet.'}
                {progress.projected_completion_date && <span className="mt-1 block text-xs font-semibold">Projected completion from remaining hours: {formatOjtDate(progress.projected_completion_date)}</span>}
              </div>
            </div>
          </div>
        </div>

        <div className="p-6 lg:p-8">
          <div className="flex items-end justify-between gap-4">
            <div><p className="text-xs font-extrabold uppercase tracking-[0.15em] text-slate-400">This Week</p><p className="mt-1 text-2xl font-black text-[#690016]">{formatMetric(progress.this_week_hours)} <span className="text-sm font-bold text-slate-400">hrs</span></p></div>
            <p className="text-xs font-semibold text-slate-400">Server-recorded hours</p>
          </div>
          <div className="mt-6 grid h-52 grid-cols-7 items-end gap-2 sm:gap-4">
            {weeklyHours.map((item) => {
              const barHeight = Number(item.hours) > 0 ? Math.max((Number(item.hours) / weeklyScale) * 100, 7) : 2;
              return (
                <div key={item.date} className="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                  <span className="text-[10px] font-extrabold text-slate-400 sm:text-xs">{formatMetric(item.hours)}</span>
                  <div className="flex h-36 w-full max-w-12 items-end overflow-hidden rounded-t-xl bg-slate-100">
                    <div className="w-full rounded-t-xl bg-gradient-to-t from-[#7D001D] to-[#d4af37] transition-all duration-700" style={{ height: `${barHeight}%` }} title={`${item.day}: ${formatMetric(item.hours)} hours`} />
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
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#7D001D]/10 text-[#7D001D]"><FiClipboard /></div>
            <div><h3 className="font-black text-[#690016] dark:text-white">Assigned HTE Tasks</h3><p className="text-xs text-slate-400">Current deliverables from your HTE supervisor</p></div>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            {dashboardData.tasks.map((task) => (
              <article key={task.id} className="rounded-2xl border border-slate-100 p-4 dark:border-gray-700">
                <div className="flex items-start justify-between gap-3"><h4 className="font-black text-slate-800 dark:text-white">{task.title}</h4><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${task.priority === 'high' ? 'bg-rose-100 text-rose-700' : task.priority === 'low' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}`}>{task.priority}</span></div>
                <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{task.description || 'No additional instructions.'}</p>
                <p className="mt-3 text-xs font-bold text-[#7D001D] dark:text-rose-300">{task.due_date ? `Due ${new Date(task.due_date).toLocaleDateString()}` : 'No fixed deadline'} · {task.status.replace('_', ' ')}</p>
              </article>
            ))}
          </div>
        </section>
      )}

      <div className="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-800">Recent Announcements</h3>
          <Link to="/student/announcements" className="text-sm font-medium text-[#7D001D] hover:text-[#5c0000]">View All</Link>
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
      {completionModalOpen && <CompletionModal usesServerTime={clockState?.uses_server_time} onClose={() => setCompletionModalOpen(false)} />}
    </div>
  );
}

export default StudentDashboard;
