import { useEffect, useState } from 'react';
import {
  FiActivity,
  FiCamera,
  FiCheck,
  FiCheckCircle,
  FiEye,
  FiImage,
  FiMap,
  FiMapPin,
  FiNavigation,
  FiPhone,
  FiRefreshCw,
  FiSearch,
  FiShield,
  FiUserCheck,
  FiUsers,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyData = {
  summary: {
    total_sessions: 0,
    active_sessions: 0,
    completed_this_month: 0,
    participants: 0,
    total_checkpoints: 0,
    pending_checkpoints: 0,
    photos_for_review: 0,
    verified_checkpoints: 0,
  },
  sessions: [],
};

const tabs = [
  { id: 'sessions', label: 'Travel Sessions', icon: FiNavigation },
  { id: 'participants', label: 'Participants', icon: FiUsers },
  { id: 'routes', label: 'Route Monitoring', icon: FiMap },
  { id: 'checkpoints', label: 'Checkpoint Review', icon: FiMapPin },
  { id: 'photos', label: 'Photo Review', icon: FiCamera },
];

const statusStyles = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
  completed: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',
  cancelled: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
  verified: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
  pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-300 focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${statusStyles[status] || statusStyles.pending}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${status === 'active' || status === 'verified' ? 'bg-emerald-500' : status === 'completed' ? 'bg-blue-500' : status === 'cancelled' ? 'bg-rose-500' : 'bg-amber-500'}`} />
      {status}
    </span>
  );
}

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
  };
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div><p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#430909] dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div>
        <div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return (
    <section className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div>
          <div><h2 className="font-black text-[#430909] dark:text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function ProgressBar({ value, color = 'bg-[#800000]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function Photo({ checkpoint, className = '' }) {
  return (
    <div className={`relative grid place-items-center overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 dark:from-gray-900 dark:to-gray-700 ${className}`}>
      <div className="text-center"><FiImage className="mx-auto text-4xl text-slate-300" /><p className="mt-2 text-[9px] font-black uppercase tracking-wider text-slate-400">Photo unavailable</p></div>
      {checkpoint.photo_url && <img src={checkpoint.photo_url} alt={`Verification evidence at ${checkpoint.checkpoint_name}`} className="absolute inset-0 h-full w-full object-cover" onError={(event) => { event.currentTarget.style.display = 'none'; }} />}
    </div>
  );
}

function Modal({ title, subtitle, onClose, children }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div className="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-gray-800">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
          <div><h2 className="text-lg font-black text-[#430909] dark:text-white">{title}</h2><p className="mt-1 text-xs text-slate-400">{subtitle}</p></div>
          <button type="button" onClick={onClose} aria-label="Close dialog" className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-200"><FiX /></button>
        </div>
        {children}
      </div>
    </div>
  );
}

function RouteMap({ session }) {
  const checkpoints = session?.checkpoints || [];
  const positions = [[70, 245], [210, 190], [350, 220], [495, 125], [650, 82]];

  return (
    <div className="relative min-h-[340px] overflow-hidden rounded-3xl bg-[#f4efe5] dark:bg-gray-900">
      <div className="absolute inset-0 opacity-60" style={{ backgroundImage: 'linear-gradient(#d9d0be 1px, transparent 1px), linear-gradient(90deg, #d9d0be 1px, transparent 1px)', backgroundSize: '34px 34px' }} />
      {checkpoints.length ? (
        <svg className="absolute inset-0 h-full w-full" viewBox="0 0 720 340" preserveAspectRatio="none" aria-label={`Checkpoint route for ${session.session_code}`}>
          <path d="M70 245 C145 278 160 185 210 190 S295 250 350 220 S425 120 495 125 S590 105 650 82" fill="none" stroke="#d7cbb5" strokeWidth="20" strokeLinecap="round" />
          <path d="M70 245 C145 278 160 185 210 190 S295 250 350 220 S425 120 495 125 S590 105 650 82" fill="none" stroke="#800000" strokeWidth="5" strokeDasharray="10 8" strokeLinecap="round" />
          {checkpoints.slice(0, positions.length).map((checkpoint, index) => {
            const [x, y] = positions[index];
            return <g key={checkpoint.id}><circle cx={x} cy={y} r="15" fill={checkpoint.is_verified ? '#059669' : '#ffffff'} stroke={checkpoint.is_verified ? '#047857' : '#800000'} strokeWidth="4" />{checkpoint.is_verified && <path d={`M${x - 5} ${y} l4 4 8 -9`} fill="none" stroke="#ffffff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />}</g>;
          })}
        </svg>
      ) : <div className="absolute inset-0 grid place-items-center text-center"><div><FiMap className="mx-auto text-5xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No checkpoint coordinates recorded.</p></div></div>}
      <div className="absolute left-4 top-4 max-w-[240px] rounded-2xl border border-white/80 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
        <p className="text-[9px] font-black uppercase tracking-widest text-[#a8750b]">Session route</p>
        <p className="mt-1 text-sm font-black text-[#430909] dark:text-white">{session?.route?.origin || 'No origin'}</p>
        <p className="mt-1 truncate text-[11px] text-slate-400">to {session?.route?.destination || 'No destination'}</p>
      </div>
      <div className="absolute bottom-4 right-4 rounded-xl bg-[#430909] px-3 py-2 text-xs font-black text-white shadow-lg">{session?.route?.progress || 0}% verified</div>
    </div>
  );
}

function TravelMonitoringManagement() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState('sessions');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedSessionId, setSelectedSessionId] = useState(null);
  const [photoReview, setPhotoReview] = useState(null);
  const [busyCheckpointId, setBusyCheckpointId] = useState(null);

  const applyResponse = (response) => {
    const next = { ...emptyData, ...response.data };
    setData(next);
    setSelectedSessionId((current) => current || next.sessions[0]?.id || null);
  };

  useEffect(() => {
    let mounted = true;
    api.get('/program-head/travel')
      .then((response) => {
        if (mounted) applyResponse(response);
      })
      .catch((error) => {
        if (mounted) toast.error(error.response?.data?.message || 'Unable to load travel monitoring data.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => { mounted = false; };
  }, []);

  const refreshTravel = async () => {
    setRefreshing(true);
    try {
      const response = await api.get('/program-head/travel');
      applyResponse(response);
      toast.success('Travel monitoring data refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to refresh travel monitoring data.');
    } finally {
      setRefreshing(false);
    }
  };

  const normalizedSearch = search.trim().toLowerCase();
  const filteredSessions = data.sessions.filter((session) => {
    const matchesSearch = !normalizedSearch || `${session.session_code} ${session.student?.name} ${session.student?.student_id} ${session.student?.program}`.toLowerCase().includes(normalizedSearch);
    return matchesSearch && (statusFilter === 'all' || session.status === statusFilter);
  });
  const participants = data.sessions.flatMap((session) => session.participants.map((participant) => ({ ...participant, session_id: session.id, session_code: session.session_code, session_status: session.status, start_time: session.start_time })));
  const filteredParticipants = participants.filter((participant) => !normalizedSearch || `${participant.name} ${participant.student_id} ${participant.program} ${participant.session_code}`.toLowerCase().includes(normalizedSearch));
  const checkpoints = data.sessions.flatMap((session) => session.checkpoints.map((checkpoint) => ({ ...checkpoint, session_code: session.session_code, session_status: session.status, student: session.student })));
  const filteredCheckpoints = checkpoints.filter((checkpoint) => {
    const status = checkpoint.is_verified ? 'verified' : 'pending';
    const matchesSearch = !normalizedSearch || `${checkpoint.checkpoint_name} ${checkpoint.session_code} ${checkpoint.student?.name}`.toLowerCase().includes(normalizedSearch);
    return matchesSearch && (statusFilter === 'all' || status === statusFilter);
  });
  const photos = filteredCheckpoints.filter((checkpoint) => checkpoint.photo_path);
  const selectedSession = data.sessions.find((session) => session.id === selectedSessionId) || data.sessions[0] || null;
  const summary = data.summary || emptyData.summary;

  const verifyCheckpoint = async (checkpoint, verified) => {
    setBusyCheckpointId(checkpoint.id);
    try {
      const response = await api.put(`/program-head/travel/checkpoints/${checkpoint.id}/verify`, { verified });
      const updated = response.data.checkpoint;
      const wasVerified = checkpoint.is_verified;
      setData((current) => ({
        ...current,
        sessions: current.sessions.map((session) => ({
          ...session,
          checkpoints: session.checkpoints.map((item) => item.id === checkpoint.id ? updated : item),
          checkpoint_summary: session.id === checkpoint.travel_log_id ? {
            ...session.checkpoint_summary,
            verified: session.checkpoint_summary.verified + (updated.is_verified && !wasVerified ? 1 : !updated.is_verified && wasVerified ? -1 : 0),
            pending: session.checkpoint_summary.pending + (!updated.is_verified && wasVerified ? 1 : updated.is_verified && !wasVerified ? -1 : 0),
          } : session.checkpoint_summary,
          route: session.id === checkpoint.travel_log_id ? {
            ...session.route,
            progress: session.checkpoint_summary.total ? Math.round(((session.checkpoint_summary.verified + (updated.is_verified && !wasVerified ? 1 : !updated.is_verified && wasVerified ? -1 : 0)) / session.checkpoint_summary.total) * 100) : 0,
          } : session.route,
        })),
        summary: {
          ...current.summary,
          verified_checkpoints: current.summary.verified_checkpoints + (updated.is_verified && !wasVerified ? 1 : !updated.is_verified && wasVerified ? -1 : 0),
          pending_checkpoints: current.summary.pending_checkpoints + (!updated.is_verified && wasVerified ? 1 : updated.is_verified && !wasVerified ? -1 : 0),
          photos_for_review: current.summary.photos_for_review + (checkpoint.photo_path ? (!updated.is_verified && wasVerified ? 1 : updated.is_verified && !wasVerified ? -1 : 0) : 0),
        },
      }));
      if (photoReview?.id === checkpoint.id) setPhotoReview({ ...photoReview, ...updated });
      toast.success(response.data.message);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update checkpoint verification.');
    } finally {
      setBusyCheckpointId(null);
    }
  };

  if (loading) {
    return <div className="grid min-h-[420px] place-items-center"><div className="text-center"><div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#800000]/15 border-b-[#800000]" /><p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading travel sessions</p></div></div>;
  }

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#430909] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiShield /> Travel Oversight</div>
            <h1 className="text-2xl font-black sm:text-3xl">Travel Monitoring Management</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Oversee active travel sessions, participants, checkpoint routes, and verification evidence.</p>
          </div>
          <button type="button" onClick={refreshTravel} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Monitor</button>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Active sessions" value={summary.active_sessions} note={`${summary.total_sessions} total travel records`} icon={FiActivity} tone="green" />
        <MetricCard label="Participants" value={summary.participants} note="Registered lead travelers" icon={FiUsers} tone="blue" />
        <MetricCard label="Pending checkpoints" value={summary.pending_checkpoints} note={`${summary.total_checkpoints} checkpoint records`} icon={FiMapPin} tone="amber" />
        <MetricCard label="Photos for review" value={summary.photos_for_review} note={`${summary.verified_checkpoints} checkpoints verified`} icon={FiCamera} />
      </section>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-label="Travel monitoring sections">
        <div className="flex min-w-max gap-1">
          {tabs.map((tab) => <button key={tab.id} type="button" onClick={() => { setActiveTab(tab.id); setStatusFilter('all'); }} className={`inline-flex items-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition sm:px-5 ${activeTab === tab.id ? 'bg-[#800000] text-white shadow-md shadow-[#800000]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#800000] dark:text-gray-300 dark:hover:bg-gray-700'}`}><tab.icon className="text-base" /> {tab.label}{tab.id === 'photos' && summary.photos_for_review > 0 && <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[9px] ${activeTab === tab.id ? 'bg-white text-[#800000]' : 'bg-amber-100 text-amber-700'}`}>{summary.photos_for_review}</span>}</button>)}
        </div>
      </nav>

      {activeTab !== 'routes' && (
        <div className="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:flex-row">
          <label className="relative flex-1"><FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search session, participant, or checkpoint..." className={`${inputClass} pl-10`} /></label>
          {(activeTab === 'sessions' || activeTab === 'checkpoints' || activeTab === 'photos') && <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className={`${inputClass} md:w-48`}><option value="all">All statuses</option>{activeTab === 'sessions' ? <><option value="active">Active</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></> : <><option value="pending">Pending review</option><option value="verified">Verified</option></>}</select>}
        </div>
      )}

      {activeTab === 'sessions' && (
        <Panel title="Travel Session Monitoring" subtitle={`${filteredSessions.length} travel sessions shown`} icon={FiNavigation}>
          <div className="space-y-3">
            {filteredSessions.map((session) => (
              <article key={session.id} className="rounded-2xl border border-slate-100 p-4 transition hover:border-[#800000]/20 hover:shadow-md dark:border-gray-700">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                  <div className="flex min-w-0 flex-1 items-start gap-3"><div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><FiNavigation /></div><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="text-sm font-black text-slate-800 dark:text-white">{session.session_code}</h3><StatusBadge status={session.status} /></div><p className="mt-1 text-xs font-bold text-slate-600 dark:text-gray-300">{session.student?.name || 'No registered traveler'}</p><p className="mt-1 text-[10px] text-slate-400">{session.student?.student_id} · {session.student?.program || 'No program'}</p></div></div>
                  <div className="grid grid-cols-3 gap-5 border-y border-slate-100 py-3 xl:w-[390px] xl:border-x xl:border-y-0 xl:px-5 xl:py-0 dark:border-gray-700"><div><p className="text-[9px] font-black uppercase text-slate-400">Started</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{session.start_time ? new Date(session.start_time).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'}</p></div><div><p className="text-[9px] font-black uppercase text-slate-400">Duration</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{Math.floor(session.duration_minutes / 60)}h {session.duration_minutes % 60}m</p></div><div><p className="text-[9px] font-black uppercase text-slate-400">Participants</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{session.participant_count}</p></div></div>
                  <div className="xl:w-48"><div className="mb-2 flex justify-between text-[10px] font-black"><span className="text-slate-400">{session.checkpoint_summary.verified}/{session.checkpoint_summary.total} checkpoints</span><span className="text-[#800000] dark:text-rose-300">{session.route.progress}%</span></div><ProgressBar value={session.route.progress} color={session.status === 'completed' ? 'bg-emerald-500' : 'bg-[#800000]'} /></div>
                  <button type="button" onClick={() => { setSelectedSessionId(session.id); setActiveTab('routes'); }} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#800000]/10 px-4 py-2.5 text-xs font-black text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><FiEye /> Monitor</button>
                </div>
              </article>
            ))}
            {!filteredSessions.length && <div className="py-16 text-center"><FiNavigation className="mx-auto text-4xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No travel sessions match these filters.</p></div>}
          </div>
        </Panel>
      )}

      {activeTab === 'participants' && (
        <Panel title="Participant Monitoring" subtitle="Registered lead travelers linked to recorded sessions" icon={FiUsers}>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[850px] text-left">
              <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Participant</th><th className="pb-3 font-black">Role</th><th className="pb-3 font-black">Academic Program</th><th className="pb-3 font-black">HTE</th><th className="pb-3 font-black">Contact</th><th className="pb-3 font-black">Session</th><th className="pb-3 font-black">Travel Status</th></tr></thead>
              <tbody>{filteredParticipants.map((participant) => <tr key={`${participant.session_id}-${participant.id}`} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4"><div className="flex items-center gap-3"><div className="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300"><FiUserCheck /></div><div><p className="text-sm font-black text-slate-800 dark:text-white">{participant.name}</p><p className="mt-1 text-[10px] text-slate-400">{participant.student_id}</p></div></div></td><td className="py-4 text-xs font-black text-slate-600 dark:text-gray-300">{participant.role}</td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{participant.program || '—'}<p className="mt-1 text-[9px] text-slate-400">{participant.college || 'No college'}</p></td><td className="py-4 text-xs text-slate-500">{participant.hte}</td><td className="py-4">{participant.contact ? <a href={`tel:${participant.contact}`} className="inline-flex items-center gap-2 text-xs font-black text-[#800000] dark:text-rose-300"><FiPhone /> {participant.contact}</a> : <span className="text-xs text-slate-400">Not provided</span>}</td><td className="py-4"><button type="button" onClick={() => { setSelectedSessionId(participant.session_id); setActiveTab('routes'); }} className="text-xs font-black text-[#800000] dark:text-rose-300">{participant.session_code}</button></td><td className="py-4"><StatusBadge status={participant.session_status} /></td></tr>)}</tbody>
            </table>
          </div>
          {!filteredParticipants.length && <div className="py-16 text-center"><FiUsers className="mx-auto text-4xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No registered travel participants found.</p></div>}
        </Panel>
      )}

      {activeTab === 'routes' && (
        <div className="grid gap-6 xl:grid-cols-[310px_1fr]">
          <Panel title="Travel Sessions" subtitle="Select a session route" icon={FiNavigation} className="h-fit">
            <div className="space-y-2">{data.sessions.map((session) => <button key={session.id} type="button" onClick={() => setSelectedSessionId(session.id)} className={`w-full rounded-2xl border p-3.5 text-left transition ${selectedSession?.id === session.id ? 'border-[#800000] bg-[#800000]/5 dark:bg-rose-950/30' : 'border-slate-100 hover:border-slate-200 dark:border-gray-700'}`}><div className="flex items-center justify-between gap-2"><p className="truncate text-sm font-black text-slate-800 dark:text-white">{session.session_code}</p><StatusBadge status={session.status} /></div><p className="mt-2 truncate text-[11px] text-slate-400">{session.student?.name || 'No traveler'}</p><div className="mt-3"><ProgressBar value={session.route.progress} /></div></button>)}</div>
          </Panel>
          <div className="space-y-6">
            <Panel title="Route Monitoring" subtitle={selectedSession ? `${selectedSession.session_code} · ${selectedSession.student?.name || 'No traveler'}` : 'No session selected'} icon={FiMap} action={selectedSession && <StatusBadge status={selectedSession.status} />}>
              {selectedSession ? <><RouteMap session={selectedSession} /><div className="mt-5 grid gap-3 sm:grid-cols-3"><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase text-slate-400">Origin</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.route.origin}</p></div><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase text-slate-400">Destination</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.route.destination}</p></div><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase text-slate-400">Route evidence</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.checkpoint_summary.with_photos}/{selectedSession.checkpoint_summary.total} photos</p></div></div></> : <div className="py-20 text-center text-sm font-bold text-slate-400">No route is available.</div>}
            </Panel>
            {selectedSession && <Panel title="Checkpoint Route Timeline" subtitle="Recorded sequence and geographic coordinates" icon={FiMapPin}><div className="space-y-0">{selectedSession.checkpoints.map((checkpoint, index) => <div key={checkpoint.id} className="relative flex gap-4 pb-6 last:pb-0">{index < selectedSession.checkpoints.length - 1 && <div className="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-0.5 bg-slate-200 dark:bg-gray-700" />}<div className={`relative z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full border-4 border-white text-xs dark:border-gray-800 ${checkpoint.is_verified ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white'}`}>{checkpoint.is_verified ? <FiCheck /> : index + 1}</div><div className="flex flex-1 flex-wrap items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-700 dark:text-white">{checkpoint.checkpoint_name}</p><p className="mt-1 text-[10px] text-slate-400">{checkpoint.latitude.toFixed(6)}, {checkpoint.longitude.toFixed(6)}</p></div><div className="text-right"><StatusBadge status={checkpoint.is_verified ? 'verified' : 'pending'} /><p className="mt-1 text-[9px] text-slate-400">{checkpoint.recorded_at ? new Date(checkpoint.recorded_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'}</p></div></div></div>)}</div>{!selectedSession.checkpoints.length && <div className="py-12 text-center text-sm font-bold text-slate-400">No checkpoints recorded for this route.</div>}</Panel>}
          </div>
        </div>
      )}

      {activeTab === 'checkpoints' && (
        <Panel title="Checkpoint Review" subtitle={`${filteredCheckpoints.length} checkpoint records shown`} icon={FiMapPin}>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[980px] text-left">
              <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Checkpoint</th><th className="pb-3 font-black">Session / Traveler</th><th className="pb-3 font-black">Coordinates</th><th className="pb-3 font-black">Recorded</th><th className="pb-3 font-black">Evidence</th><th className="pb-3 font-black">Review Status</th><th className="pb-3 text-right font-black">Action</th></tr></thead>
              <tbody>{filteredCheckpoints.map((checkpoint) => <tr key={checkpoint.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{checkpoint.checkpoint_name}</p>{checkpoint.notes && <p className="mt-1 max-w-52 truncate text-[10px] text-slate-400">{checkpoint.notes}</p>}</td><td className="py-4"><p className="text-xs font-black text-[#800000] dark:text-rose-300">{checkpoint.session_code}</p><p className="mt-1 text-[10px] text-slate-400">{checkpoint.student?.name || 'No traveler'}</p></td><td className="py-4 text-[10px] font-bold text-slate-500">{checkpoint.latitude.toFixed(6)}<br />{checkpoint.longitude.toFixed(6)}</td><td className="py-4 text-xs text-slate-500">{checkpoint.recorded_at ? new Date(checkpoint.recorded_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'}</td><td className="py-4">{checkpoint.photo_path ? <button type="button" onClick={() => setPhotoReview(checkpoint)} className="inline-flex items-center gap-1.5 text-xs font-black text-blue-600"><FiCamera /> View photo</button> : <span className="text-xs text-slate-400">No photo</span>}</td><td className="py-4"><StatusBadge status={checkpoint.is_verified ? 'verified' : 'pending'} />{checkpoint.verified_by && <p className="mt-1 text-[9px] text-slate-400">by {checkpoint.verified_by}</p>}</td><td className="py-4 text-right"><button type="button" onClick={() => verifyCheckpoint(checkpoint, !checkpoint.is_verified)} disabled={busyCheckpointId === checkpoint.id} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-black disabled:opacity-50 ${checkpoint.is_verified ? 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'}`}>{busyCheckpointId === checkpoint.id ? <FiRefreshCw className="animate-spin" /> : checkpoint.is_verified ? <FiXCircle /> : <FiCheckCircle />}{checkpoint.is_verified ? 'Remove verification' : 'Verify'}</button></td></tr>)}</tbody>
            </table>
          </div>
          {!filteredCheckpoints.length && <div className="py-16 text-center"><FiMapPin className="mx-auto text-4xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No checkpoints match these filters.</p></div>}
        </Panel>
      )}

      {activeTab === 'photos' && (
        <Panel title="Verification Photo Review" subtitle={`${photos.length} checkpoint photos shown`} icon={FiCamera}>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {photos.map((checkpoint) => <article key={checkpoint.id} className="overflow-hidden rounded-2xl border border-slate-100 bg-white dark:border-gray-700 dark:bg-gray-800"><Photo checkpoint={checkpoint} className="h-52" /><div className="p-4"><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-800 dark:text-white">{checkpoint.checkpoint_name}</p><p className="mt-1 text-[10px] font-bold text-[#800000] dark:text-rose-300">{checkpoint.session_code}</p></div><StatusBadge status={checkpoint.is_verified ? 'verified' : 'pending'} /></div><p className="mt-3 text-xs text-slate-400">{checkpoint.student?.name || 'No traveler'} · {checkpoint.recorded_at ? new Date(checkpoint.recorded_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'}</p><button type="button" onClick={() => setPhotoReview(checkpoint)} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#800000] px-4 py-3 text-xs font-black text-white"><FiEye /> Inspect Evidence</button></div></article>)}
          </div>
          {!photos.length && <div className="py-16 text-center"><FiCamera className="mx-auto text-4xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No verification photos match these filters.</p></div>}
        </Panel>
      )}

      {photoReview && (
        <Modal title="Verification Photo Review" subtitle={`${photoReview.session_code} · ${photoReview.checkpoint_name}`} onClose={() => setPhotoReview(null)}>
          <div className="grid gap-6 p-6 lg:grid-cols-[1fr_290px]">
            <Photo checkpoint={photoReview} className="min-h-[440px] rounded-3xl" />
            <div>
              <div className="space-y-5">
                <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Checkpoint</p><p className="mt-1 text-base font-black text-slate-800 dark:text-white">{photoReview.checkpoint_name}</p></div>
                <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Traveler</p><p className="mt-1 text-sm font-black text-slate-700 dark:text-gray-200">{photoReview.student?.name || 'No traveler'}</p><p className="mt-1 text-[10px] text-slate-400">{photoReview.student?.student_id} · {photoReview.student?.program}</p></div>
                <div className="grid grid-cols-2 gap-3"><div><p className="text-[9px] font-black uppercase text-slate-400">Latitude</p><p className="mt-1 text-xs font-bold text-slate-600 dark:text-gray-300">{photoReview.latitude.toFixed(6)}</p></div><div><p className="text-[9px] font-black uppercase text-slate-400">Longitude</p><p className="mt-1 text-xs font-bold text-slate-600 dark:text-gray-300">{photoReview.longitude.toFixed(6)}</p></div></div>
                <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Recorded</p><p className="mt-1 text-xs font-bold text-slate-600 dark:text-gray-300">{photoReview.recorded_at ? new Date(photoReview.recorded_at).toLocaleString('en-PH') : '—'}</p></div>
                {photoReview.notes && <div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-[9px] font-black uppercase text-slate-400">Traveler notes</p><p className="mt-1 text-xs leading-5 text-slate-600 dark:text-gray-300">{photoReview.notes}</p></div>}
                <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Review status</p><div className="mt-2"><StatusBadge status={photoReview.is_verified ? 'verified' : 'pending'} /></div>{photoReview.verified_by && <p className="mt-2 text-[10px] text-slate-400">Verified by {photoReview.verified_by}</p>}</div>
              </div>
              <div className="mt-6 grid gap-2">{!photoReview.is_verified ? <button type="button" onClick={() => verifyCheckpoint(photoReview, true)} disabled={busyCheckpointId === photoReview.id} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-xs font-black text-white disabled:opacity-50">{busyCheckpointId === photoReview.id ? <FiRefreshCw className="animate-spin" /> : <FiCheck />} Verify Photo Evidence</button> : <button type="button" onClick={() => verifyCheckpoint(photoReview, false)} disabled={busyCheckpointId === photoReview.id} className="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-black text-rose-700 disabled:opacity-50 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">{busyCheckpointId === photoReview.id ? <FiRefreshCw className="animate-spin" /> : <FiX />} Remove Verification</button>}</div>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}

export default TravelMonitoringManagement;
