import { useMemo, useState } from 'react';
import {
  FiActivity,
  FiAlertTriangle,
  FiArrowRight,
  FiBarChart2,
  FiCalendar,
  FiCheck,
  FiCheckCircle,
  FiClock,
  FiDownload,
  FiEye,
  FiFileText,
  FiFlag,
  FiMap,
  FiMapPin,
  FiNavigation,
  FiPhone,
  FiPlus,
  FiRefreshCw,
  FiSearch,
  FiShield,
  FiUserCheck,
  FiUsers,
  FiX,
} from 'react-icons/fi';
import toast from 'react-hot-toast';

const initialSessions = [
  {
    id: 'TRV-260725-014',
    student: 'Alyssa Reyes',
    studentId: '2022-10482',
    program: 'BS Information Technology',
    destination: 'Marinduque Provincial Capitol',
    purpose: 'Submit internship partnership documents',
    route: 'Main Campus → Boac Town Proper → Provincial Capitol',
    startedAt: '8:12 AM',
    scheduledAt: '8:00 AM',
    eta: '9:40 AM',
    progress: 68,
    status: 'active',
    currentLocation: 'Gov. Reyes Street, Boac',
    checkpointsPassed: 3,
    checkpointsTotal: 5,
    companionCount: 2,
    travelDate: 'Jul 25, 2026',
    verified: true,
  },
  {
    id: 'TRV-260725-011',
    student: 'Marco Villanueva',
    studentId: '2022-11806',
    program: 'BS Business Administration',
    destination: 'Torrijos Municipal Hall',
    purpose: 'HTE coordination and document turnover',
    route: 'Main Campus → Buenavista → Torrijos Municipal Hall',
    startedAt: '7:35 AM',
    scheduledAt: '7:30 AM',
    eta: '10:15 AM',
    progress: 42,
    status: 'delayed',
    currentLocation: 'National Road, Buenavista',
    checkpointsPassed: 2,
    checkpointsTotal: 6,
    companionCount: 1,
    travelDate: 'Jul 25, 2026',
    verified: true,
  },
  {
    id: 'TRV-260725-018',
    student: 'Sofia Mendoza',
    studentId: '2022-10931',
    program: 'BS Hospitality Management',
    destination: 'Bellavista Hotel',
    purpose: 'Pre-deployment site orientation',
    route: 'Main Campus → Gasan Port → Bellavista Hotel',
    startedAt: '—',
    scheduledAt: '1:30 PM',
    eta: '2:35 PM',
    progress: 0,
    status: 'scheduled',
    currentLocation: 'Awaiting departure',
    checkpointsPassed: 0,
    checkpointsTotal: 4,
    companionCount: 3,
    travelDate: 'Jul 25, 2026',
    verified: true,
  },
  {
    id: 'TRV-260724-027',
    student: 'Daniel Cruz',
    studentId: '2021-09218',
    program: 'BS Information Systems',
    destination: 'DICT Marinduque',
    purpose: 'Technical internship coordination',
    route: 'Main Campus → Boac Town Proper → DICT Marinduque',
    startedAt: '9:05 AM',
    scheduledAt: '9:00 AM',
    eta: '10:10 AM',
    progress: 100,
    status: 'completed',
    currentLocation: 'Travel completed',
    checkpointsPassed: 5,
    checkpointsTotal: 5,
    companionCount: 1,
    travelDate: 'Jul 24, 2026',
    verified: true,
  },
];

const initialCompanions = [
  { id: 1, sessionId: 'TRV-260725-014', name: 'Joshua Lim', type: 'Student', relation: 'Classmate', contact: '0917 324 1180', status: 'on-trip', verified: true },
  { id: 2, sessionId: 'TRV-260725-014', name: 'Prof. Maria Santos', type: 'Faculty', relation: 'Travel supervisor', contact: '0918 552 9041', status: 'on-trip', verified: true },
  { id: 3, sessionId: 'TRV-260725-011', name: 'Kenneth Flores', type: 'Student', relation: 'Classmate', contact: '0916 782 3402', status: 'on-trip', verified: true },
  { id: 4, sessionId: 'TRV-260725-018', name: 'Lea Dela Cruz', type: 'Student', relation: 'Classmate', contact: '0915 620 8147', status: 'expected', verified: true },
  { id: 5, sessionId: 'TRV-260725-018', name: 'Nicole Ramos', type: 'Student', relation: 'Classmate', contact: '0920 415 7228', status: 'expected', verified: false },
  { id: 6, sessionId: 'TRV-260725-018', name: 'Mr. Adrian Tan', type: 'Faculty', relation: 'Travel supervisor', contact: '0917 843 1192', status: 'expected', verified: true },
];

const initialCheckpoints = [
  { id: 1, sessionId: 'TRV-260725-014', name: 'Campus Main Gate', type: 'Departure', expected: '8:00 AM', arrived: '8:12 AM', proof: 'Geotagged photo', status: 'verified', verifiedBy: 'Travel Desk' },
  { id: 2, sessionId: 'TRV-260725-014', name: 'Boac Town Boundary', type: 'Route', expected: '8:35 AM', arrived: '8:39 AM', proof: 'GPS + selfie', status: 'verified', verifiedBy: 'Travel Desk' },
  { id: 3, sessionId: 'TRV-260725-014', name: 'Boac Town Proper', type: 'Route', expected: '9:00 AM', arrived: '9:04 AM', proof: 'Geotagged photo', status: 'pending', verifiedBy: '—' },
  { id: 4, sessionId: 'TRV-260725-014', name: 'Provincial Capitol', type: 'Destination', expected: '9:40 AM', arrived: '—', proof: 'Required on arrival', status: 'upcoming', verifiedBy: '—' },
  { id: 5, sessionId: 'TRV-260725-011', name: 'Buenavista Junction', type: 'Route', expected: '8:45 AM', arrived: '9:18 AM', proof: 'GPS location', status: 'flagged', verifiedBy: '—' },
  { id: 6, sessionId: 'TRV-260725-011', name: 'Torrijos Municipal Hall', type: 'Destination', expected: '10:15 AM', arrived: '—', proof: 'Required on arrival', status: 'upcoming', verifiedBy: '—' },
  { id: 7, sessionId: 'TRV-260724-027', name: 'DICT Marinduque', type: 'Destination', expected: '10:10 AM', arrived: '10:06 AM', proof: 'Geotagged photo', status: 'verified', verifiedBy: 'C. Manalo' },
];

const modules = [
  { id: 'sessions', label: 'Travel Sessions', shortLabel: 'Sessions', icon: FiNavigation },
  { id: 'companions', label: 'Companions', shortLabel: 'Companions', icon: FiUsers },
  { id: 'routes', label: 'Route Monitor', shortLabel: 'Routes', icon: FiMap },
  { id: 'checkpoints', label: 'Checkpoints', shortLabel: 'Checkpoints', icon: FiMapPin },
  { id: 'verification', label: 'Verification', shortLabel: 'Verify', icon: FiShield },
  { id: 'reports', label: 'Travel Reports', shortLabel: 'Reports', icon: FiBarChart2 },
];

const statusStyles = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
  delayed: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300',
  scheduled: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300',
  completed: 'border-slate-200 bg-slate-100 text-slate-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200',
  verified: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
  pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300',
  flagged: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300',
  upcoming: 'border-slate-200 bg-white text-slate-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
  'on-trip': 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
  expected: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300',
};

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${statusStyles[status] || statusStyles.upcoming}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${status === 'active' || status === 'verified' || status === 'on-trip' ? 'bg-emerald-500' : status === 'delayed' || status === 'pending' ? 'bg-amber-500' : status === 'flagged' ? 'bg-rose-500' : 'bg-slate-400'}`} />
      {status.replace('-', ' ')}
    </span>
  );
}

function ProgressBar({ value, tone = 'maroon' }) {
  const colors = {
    maroon: 'bg-[#800000]',
    green: 'bg-emerald-500',
    amber: 'bg-amber-500',
    blue: 'bg-blue-500',
  };
  return (
    <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700">
      <div className={`h-full rounded-full transition-all duration-500 ${colors[tone]}`} style={{ width: `${Math.min(Math.max(value, 0), 100)}%` }} />
    </div>
  );
}

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
  };

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-black text-[#430909] dark:text-white">{value}</p>
          <p className="mt-1 text-xs text-slate-400">{note}</p>
        </div>
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
          {Icon && <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div>}
          <div>
            <h2 className="font-black text-[#430909] dark:text-white">{title}</h2>
            {subtitle && <p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p>}
          </div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function RouteMap({ session }) {
  const routeStops = session.status === 'completed' ? [true, true, true, true] : session.status === 'scheduled' ? [false, false, false, false] : [true, true, session.progress > 55, false];

  return (
    <div className="relative min-h-[330px] overflow-hidden rounded-3xl bg-[#f4efe5] dark:bg-gray-900">
      <div className="absolute inset-0 opacity-60" style={{ backgroundImage: 'linear-gradient(#d9d0be 1px, transparent 1px), linear-gradient(90deg, #d9d0be 1px, transparent 1px)', backgroundSize: '34px 34px' }} />
      <svg className="absolute inset-0 h-full w-full" viewBox="0 0 720 330" preserveAspectRatio="none" aria-label={`Route map for ${session.student}`}>
        <path d="M75 250 C160 270 170 155 265 180 S365 265 435 160 S550 70 655 92" fill="none" stroke="#d7cbb5" strokeWidth="20" strokeLinecap="round" />
        <path d="M75 250 C160 270 170 155 265 180 S365 265 435 160 S550 70 655 92" fill="none" stroke="#800000" strokeWidth="5" strokeDasharray="10 8" strokeLinecap="round" />
        <path d="M75 250 C160 270 170 155 265 180 S365 265 435 160 S550 70 655 92" fill="none" stroke="#f1c54c" strokeWidth="2" strokeLinecap="round" />
        {[[75, 250], [265, 180], [435, 160], [655, 92]].map(([x, y], index) => (
          <g key={`${x}-${y}`}>
            <circle cx={x} cy={y} r="14" fill={routeStops[index] ? '#059669' : '#ffffff'} stroke={routeStops[index] ? '#047857' : '#800000'} strokeWidth="4" />
            {routeStops[index] && <path d={`M${x - 5} ${y} l4 4 8 -9`} fill="none" stroke="#ffffff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />}
          </g>
        ))}
        {session.status !== 'scheduled' && session.status !== 'completed' && (
          <g transform={`translate(${190 + (session.progress * 3.5)} ${205 - (session.progress * 1.15)})`}>
            <circle r="22" fill="#800000" opacity="0.18" />
            <circle r="12" fill="#800000" stroke="#ffffff" strokeWidth="4" />
          </g>
        )}
      </svg>
      <div className="absolute left-4 top-4 max-w-[230px] rounded-2xl border border-white/80 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
        <p className="text-[10px] font-black uppercase tracking-widest text-[#a8750b]">Live position</p>
        <p className="mt-1 text-sm font-black text-[#430909] dark:text-white">{session.currentLocation}</p>
        <p className="mt-1 text-[11px] text-slate-400">Updated just now</p>
      </div>
      <div className="absolute bottom-4 right-4 flex items-center gap-2 rounded-xl bg-[#430909] px-3 py-2 text-xs font-black text-white shadow-lg">
        <FiNavigation /> {session.progress}% complete
      </div>
    </div>
  );
}

function Modal({ title, subtitle, onClose, children }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-gray-800">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
          <div>
            <h2 className="text-lg font-black text-[#430909] dark:text-white">{title}</h2>
            <p className="mt-1 text-xs text-slate-400">{subtitle}</p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close dialog" className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200 dark:bg-gray-700 dark:text-gray-200"><FiX /></button>
        </div>
        {children}
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return <label className="block"><span className="mb-2 block text-xs font-black text-slate-600 dark:text-gray-200">{label}</span>{children}</label>;
}

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-300 focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';

function TravelMonitoring() {
  const [activeModule, setActiveModule] = useState('sessions');
  const [sessions, setSessions] = useState(initialSessions);
  const [companions, setCompanions] = useState(initialCompanions);
  const [checkpoints, setCheckpoints] = useState(initialCheckpoints);
  const [selectedSessionId, setSelectedSessionId] = useState(initialSessions[0].id);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [showSessionModal, setShowSessionModal] = useState(false);
  const [showCompanionModal, setShowCompanionModal] = useState(false);
  const [newSession, setNewSession] = useState({ student: '', studentId: '', program: '', destination: '', purpose: '', route: '', scheduledAt: '', travelDate: '2026-07-25' });
  const [newCompanion, setNewCompanion] = useState({ name: '', type: 'Student', relation: '', contact: '', sessionId: initialSessions[0].id });

  const selectedSession = sessions.find((session) => session.id === selectedSessionId) || sessions[0];
  const filteredSessions = useMemo(() => sessions.filter((session) => {
    const term = search.trim().toLowerCase();
    const matchesSearch = !term || `${session.id} ${session.student} ${session.studentId} ${session.destination}`.toLowerCase().includes(term);
    return matchesSearch && (statusFilter === 'all' || session.status === statusFilter);
  }), [search, sessions, statusFilter]);

  const stats = useMemo(() => ({
    active: sessions.filter((session) => ['active', 'delayed'].includes(session.status)).length,
    scheduled: sessions.filter((session) => session.status === 'scheduled').length,
    pending: checkpoints.filter((checkpoint) => ['pending', 'flagged'].includes(checkpoint.status)).length,
    completion: Math.round((sessions.filter((session) => session.status === 'completed').length / sessions.length) * 100),
  }), [checkpoints, sessions]);

  const sessionCompanions = companions.filter((companion) => companion.sessionId === selectedSession.id);
  const sessionCheckpoints = checkpoints.filter((checkpoint) => checkpoint.sessionId === selectedSession.id);
  const verificationQueue = checkpoints.filter((checkpoint) => ['pending', 'flagged'].includes(checkpoint.status));

  const openSession = (sessionId, module = 'routes') => {
    setSelectedSessionId(sessionId);
    setActiveModule(module);
  };

  const createSession = (event) => {
    event.preventDefault();
    const id = `TRV-260725-${String(sessions.length + 19).padStart(3, '0')}`;
    const session = {
      ...newSession,
      id,
      route: newSession.route || `Main Campus → ${newSession.destination}`,
      startedAt: '—',
      eta: 'To be calculated',
      progress: 0,
      status: 'scheduled',
      currentLocation: 'Awaiting departure',
      checkpointsPassed: 0,
      checkpointsTotal: 3,
      companionCount: 0,
      travelDate: new Date(newSession.travelDate).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }),
      verified: true,
    };
    setSessions((current) => [session, ...current]);
    setSelectedSessionId(id);
    setShowSessionModal(false);
    setNewSession({ student: '', studentId: '', program: '', destination: '', purpose: '', route: '', scheduledAt: '', travelDate: '2026-07-25' });
    toast.success(`Travel session ${id} created.`);
  };

  const addCompanion = (event) => {
    event.preventDefault();
    setCompanions((current) => [...current, { ...newCompanion, id: Date.now(), status: 'expected', verified: false }]);
    setSessions((current) => current.map((session) => session.id === newCompanion.sessionId ? { ...session, companionCount: session.companionCount + 1 } : session));
    setShowCompanionModal(false);
    setNewCompanion({ name: '', type: 'Student', relation: '', contact: '', sessionId: selectedSession.id });
    toast.success('Companion added to the manifest.');
  };

  const resolveCheckpoint = (checkpointId, resolution) => {
    setCheckpoints((current) => current.map((checkpoint) => checkpoint.id === checkpointId ? {
      ...checkpoint,
      status: resolution,
      verifiedBy: resolution === 'verified' ? 'Coordinator' : 'For follow-up',
    } : checkpoint));
    toast.success(resolution === 'verified' ? 'Checkpoint evidence verified.' : 'Checkpoint flagged for follow-up.');
  };

  const startSession = (sessionId) => {
    setSessions((current) => current.map((session) => session.id === sessionId ? { ...session, status: 'active', startedAt: new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }), currentLocation: 'Campus Main Gate', progress: 4 } : session));
    toast.success('Travel session is now active.');
  };

  const exportReport = () => {
    const headers = ['Session ID', 'Student', 'Student ID', 'Destination', 'Date', 'Status', 'Progress'];
    const rows = sessions.map((session) => [session.id, session.student, session.studentId, session.destination, session.travelDate, session.status, `${session.progress}%`]);
    const csv = [headers, ...rows].map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'travel-monitoring-report.csv';
    anchor.click();
    URL.revokeObjectURL(url);
    toast.success('Travel report downloaded.');
  };

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#430909] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]">
              <FiActivity className="animate-pulse" /> Operations Center
            </div>
            <h1 className="text-2xl font-black sm:text-3xl">Travel Monitoring</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Coordinate authorized student travel, monitor live routes, and verify every checkpoint from departure to safe return.</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => toast.success('Travel data refreshed.')} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15"><FiRefreshCw /> Refresh</button>
            <button type="button" onClick={() => setShowSessionModal(true)} className="inline-flex items-center gap-2 rounded-xl bg-[#f1c54c] px-4 py-3 text-xs font-black text-[#430909] shadow-lg shadow-black/10 transition hover:bg-[#f7d979]"><FiPlus /> New Travel Session</button>
          </div>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Travelling now" value={stats.active} note="Live and delayed sessions" icon={FiNavigation} tone="green" />
        <MetricCard label="Scheduled today" value={stats.scheduled} note="Awaiting departure" icon={FiCalendar} tone="blue" />
        <MetricCard label="Needs review" value={stats.pending} note="Checkpoint submissions" icon={FiShield} tone="amber" />
        <MetricCard label="Completion rate" value={`${stats.completion}%`} note="For recorded sessions" icon={FiCheckCircle} />
      </section>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-label="Travel monitoring modules">
        <div className="flex min-w-max gap-1">
          {modules.map((module) => (
            <button
              key={module.id}
              type="button"
              onClick={() => setActiveModule(module.id)}
              className={`inline-flex items-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition sm:px-5 ${activeModule === module.id ? 'bg-[#800000] text-white shadow-md shadow-[#800000]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#800000] dark:text-gray-300 dark:hover:bg-gray-700'}`}
            >
              <module.icon className="text-base" />
              <span className="hidden sm:inline">{module.label}</span>
              <span className="sm:hidden">{module.shortLabel}</span>
              {module.id === 'verification' && verificationQueue.length > 0 && <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[9px] ${activeModule === module.id ? 'bg-white text-[#800000]' : 'bg-amber-100 text-amber-700'}`}>{verificationQueue.length}</span>}
            </button>
          ))}
        </div>
      </nav>

      {activeModule === 'sessions' && (
        <Panel
          title="Travel Session Management"
          subtitle="Create, track, and manage every authorized student trip"
          icon={FiNavigation}
          action={<button type="button" onClick={() => setShowSessionModal(true)} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Create Session</button>}
        >
          <div className="mb-5 flex flex-col gap-3 md:flex-row">
            <label className="relative flex-1">
              <FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
              <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search student, session ID, or destination..." className={`${inputClass} pl-10`} />
            </label>
            <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className={`${inputClass} md:w-48`}>
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="delayed">Delayed</option>
              <option value="scheduled">Scheduled</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <div className="space-y-3">
            {filteredSessions.map((session) => (
              <article key={session.id} className="group rounded-2xl border border-slate-100 p-4 transition hover:border-[#800000]/20 hover:shadow-md dark:border-gray-700">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                  <div className="flex min-w-0 flex-1 items-start gap-3">
                    <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-xs font-black text-[#800000] dark:bg-rose-950/50 dark:text-rose-300">
                      {session.student.split(' ').map((name) => name[0]).join('').slice(0, 2)}
                    </div>
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="truncate text-sm font-black text-slate-800 dark:text-white">{session.student}</h3>
                        <StatusBadge status={session.status} />
                      </div>
                      <p className="mt-1 text-[11px] font-bold text-slate-400">{session.studentId} · {session.program}</p>
                      <div className="mt-2 flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-gray-300"><FiMapPin className="shrink-0 text-[#800000]" /><span className="truncate">{session.destination}</span></div>
                    </div>
                  </div>

                  <div className="grid grid-cols-3 gap-4 border-y border-slate-100 py-3 xl:w-[310px] xl:border-x xl:border-y-0 xl:px-5 xl:py-0 dark:border-gray-700">
                    <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Session</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{session.id}</p></div>
                    <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Departure</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{session.startedAt}</p></div>
                    <div><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Companions</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{session.companionCount}</p></div>
                  </div>

                  <div className="xl:w-48">
                    <div className="mb-2 flex items-center justify-between text-[10px] font-black">
                      <span className="text-slate-400">{session.checkpointsPassed}/{session.checkpointsTotal} checkpoints</span>
                      <span className="text-[#800000] dark:text-rose-300">{session.progress}%</span>
                    </div>
                    <ProgressBar value={session.progress} tone={session.status === 'delayed' ? 'amber' : session.status === 'completed' ? 'green' : 'maroon'} />
                  </div>

                  <div className="flex shrink-0 gap-2">
                    {session.status === 'scheduled' && <button type="button" onClick={() => startSession(session.id)} className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-black text-white xl:flex-none"><FiNavigation /> Start</button>}
                    <button type="button" onClick={() => openSession(session.id)} className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black text-[#800000] transition hover:bg-[#800000] hover:text-white dark:border-gray-600 dark:text-rose-300 xl:flex-none"><FiEye /> Monitor</button>
                  </div>
                </div>
              </article>
            ))}
            {filteredSessions.length === 0 && <div className="rounded-2xl border border-dashed border-slate-200 py-12 text-center dark:border-gray-700"><FiSearch className="mx-auto text-3xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No sessions match your filters.</p></div>}
          </div>
        </Panel>
      )}

      {activeModule === 'companions' && (
        <div className="grid gap-6 xl:grid-cols-[1fr_330px]">
          <Panel
            title="Companion Management"
            subtitle="Maintain a verified manifest for each travel session"
            icon={FiUsers}
            action={<button type="button" onClick={() => { setNewCompanion((value) => ({ ...value, sessionId: selectedSession.id })); setShowCompanionModal(true); }} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Add Companion</button>}
          >
            <div className="mb-5 flex gap-3 overflow-x-auto pb-1">
              {sessions.slice(0, 4).map((session) => (
                <button key={session.id} type="button" onClick={() => setSelectedSessionId(session.id)} className={`min-w-[200px] rounded-2xl border p-3 text-left transition ${selectedSession.id === session.id ? 'border-[#800000] bg-[#800000]/5 dark:bg-rose-950/30' : 'border-slate-100 hover:border-slate-200 dark:border-gray-700'}`}>
                  <p className="text-[10px] font-black uppercase tracking-wide text-slate-400">{session.id}</p>
                  <p className="mt-1 truncate text-sm font-black text-slate-700 dark:text-white">{session.student}</p>
                </button>
              ))}
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-left">
                <thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Companion</th><th className="pb-3 font-black">Role</th><th className="pb-3 font-black">Contact</th><th className="pb-3 font-black">Identity</th><th className="pb-3 font-black">Trip status</th></tr></thead>
                <tbody>
                  {sessionCompanions.map((companion) => (
                    <tr key={companion.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700">
                      <td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{companion.name}</p><p className="mt-1 text-[11px] text-slate-400">{companion.type}</p></td>
                      <td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{companion.relation}</td>
                      <td className="py-4"><a href={`tel:${companion.contact.replaceAll(' ', '')}`} className="inline-flex items-center gap-2 text-xs font-black text-[#800000] dark:text-rose-300"><FiPhone /> {companion.contact}</a></td>
                      <td className="py-4">{companion.verified ? <span className="inline-flex items-center gap-1.5 text-xs font-black text-emerald-600"><FiCheckCircle /> Verified</span> : <button type="button" onClick={() => setCompanions((current) => current.map((item) => item.id === companion.id ? { ...item, verified: true } : item))} className="text-xs font-black text-amber-600">Verify identity</button>}</td>
                      <td className="py-4"><StatusBadge status={companion.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {sessionCompanions.length === 0 && <div className="py-12 text-center"><FiUsers className="mx-auto text-3xl text-slate-300" /><p className="mt-2 text-sm font-bold text-slate-400">No companions added to this session.</p></div>}
            </div>
          </Panel>

          <Panel title="Session Manifest" subtitle="Travel party at a glance" icon={FiUserCheck}>
            <div className="rounded-2xl bg-[#430909] p-5 text-white">
              <p className="text-[10px] font-black uppercase tracking-widest text-[#f1c54c]">{selectedSession.id}</p>
              <p className="mt-2 text-lg font-black">{selectedSession.student}</p>
              <p className="mt-1 text-xs leading-5 text-white/60">{selectedSession.destination}</p>
              <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-xl bg-white/10 p-3"><p className="text-2xl font-black">{sessionCompanions.length + 1}</p><p className="text-[9px] font-bold uppercase text-white/55">Total travelers</p></div>
                <div className="rounded-xl bg-white/10 p-3"><p className="text-2xl font-black">{sessionCompanions.filter((item) => item.verified).length}</p><p className="text-[9px] font-bold uppercase text-white/55">Verified companions</p></div>
              </div>
            </div>
            <div className="mt-4 space-y-3 text-xs">
              <div className="flex items-center justify-between"><span className="text-slate-400">Lead traveler</span><span className="font-black text-slate-700 dark:text-gray-200">{selectedSession.student}</span></div>
              <div className="flex items-center justify-between"><span className="text-slate-400">Faculty present</span><span className="font-black text-slate-700 dark:text-gray-200">{sessionCompanions.some((item) => item.type === 'Faculty') ? 'Yes' : 'No'}</span></div>
              <div className="flex items-center justify-between"><span className="text-slate-400">Manifest status</span><span className="font-black text-emerald-600">{sessionCompanions.every((item) => item.verified) ? 'Complete' : 'Needs review'}</span></div>
            </div>
          </Panel>
        </div>
      )}

      {activeModule === 'routes' && (
        <div className="grid gap-6 xl:grid-cols-[310px_1fr]">
          <Panel title="Live Sessions" subtitle="Select a trip to monitor" icon={FiActivity} className="h-fit">
            <div className="space-y-2">
              {sessions.filter((session) => session.status !== 'completed').map((session) => (
                <button key={session.id} type="button" onClick={() => setSelectedSessionId(session.id)} className={`w-full rounded-2xl border p-3.5 text-left transition ${selectedSession.id === session.id ? 'border-[#800000] bg-[#800000]/5 dark:bg-rose-950/30' : 'border-slate-100 hover:border-slate-200 dark:border-gray-700'}`}>
                  <div className="flex items-center justify-between gap-2"><p className="truncate text-sm font-black text-slate-800 dark:text-white">{session.student}</p><StatusBadge status={session.status} /></div>
                  <p className="mt-2 truncate text-[11px] text-slate-400">{session.currentLocation}</p>
                  <div className="mt-3"><ProgressBar value={session.progress} tone={session.status === 'delayed' ? 'amber' : 'maroon'} /></div>
                </button>
              ))}
            </div>
          </Panel>

          <div className="space-y-6">
            <Panel title="Route Monitoring" subtitle={`${selectedSession.id} · ${selectedSession.student}`} icon={FiMap} action={<StatusBadge status={selectedSession.status} />}>
              <RouteMap session={selectedSession} />
              <div className="mt-5 grid gap-4 md:grid-cols-3">
                <div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Destination</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.destination}</p></div>
                <div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Estimated arrival</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.eta}</p></div>
                <div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">Route progress</p><p className="mt-2 text-sm font-black text-slate-700 dark:text-white">{selectedSession.checkpointsPassed} of {selectedSession.checkpointsTotal} checkpoints</p></div>
              </div>
            </Panel>

            <Panel title="Route Timeline" subtitle={selectedSession.route} icon={FiFlag}>
              <div className="relative space-y-0">
                {(sessionCheckpoints.length ? sessionCheckpoints : [
                  { id: 'origin', name: 'Campus Main Gate', expected: selectedSession.scheduledAt, arrived: '—', status: 'upcoming', type: 'Departure' },
                  { id: 'destination', name: selectedSession.destination, expected: selectedSession.eta, arrived: '—', status: 'upcoming', type: 'Destination' },
                ]).map((checkpoint, index, list) => (
                  <div key={checkpoint.id} className="relative flex gap-4 pb-6 last:pb-0">
                    {index < list.length - 1 && <div className="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-0.5 bg-slate-200 dark:bg-gray-700" />}
                    <div className={`relative z-10 mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full border-4 border-white text-xs dark:border-gray-800 ${checkpoint.status === 'verified' ? 'bg-emerald-500 text-white' : checkpoint.status === 'pending' || checkpoint.status === 'flagged' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-500 dark:bg-gray-700'}`}>{checkpoint.status === 'verified' ? <FiCheck /> : index + 1}</div>
                    <div className="flex flex-1 flex-wrap items-start justify-between gap-3">
                      <div><p className="text-sm font-black text-slate-700 dark:text-white">{checkpoint.name}</p><p className="mt-1 text-[11px] text-slate-400">{checkpoint.type}</p></div>
                      <div className="text-right"><p className="text-xs font-black text-slate-600 dark:text-gray-300">{checkpoint.arrived !== '—' ? checkpoint.arrived : checkpoint.expected}</p><p className="mt-1 text-[9px] font-bold uppercase text-slate-400">{checkpoint.arrived !== '—' ? 'Recorded' : 'Expected'}</p></div>
                    </div>
                  </div>
                ))}
              </div>
            </Panel>
          </div>
        </div>
      )}

      {activeModule === 'checkpoints' && (
        <Panel title="Checkpoint Monitoring" subtitle="Review arrivals, evidence, exceptions, and upcoming route markers" icon={FiMapPin}>
          <div className="mb-5 grid gap-3 sm:grid-cols-3">
            {[
              ['Verified', checkpoints.filter((item) => item.status === 'verified').length, FiCheckCircle, 'text-emerald-600'],
              ['Pending review', checkpoints.filter((item) => item.status === 'pending').length, FiClock, 'text-amber-600'],
              ['Flagged', checkpoints.filter((item) => item.status === 'flagged').length, FiAlertTriangle, 'text-rose-600'],
            ].map(([label, value, Icon, color]) => <div key={label} className="flex items-center gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><Icon className={`text-xl ${color}`} /><div><p className={`text-xl font-black ${color}`}>{value}</p><p className="text-[10px] font-bold uppercase text-slate-400">{label}</p></div></div>)}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left">
              <thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Checkpoint</th><th className="pb-3 font-black">Session</th><th className="pb-3 font-black">Expected</th><th className="pb-3 font-black">Recorded</th><th className="pb-3 font-black">Evidence</th><th className="pb-3 font-black">Status</th><th className="pb-3 font-black">Action</th></tr></thead>
              <tbody>
                {checkpoints.map((checkpoint) => (
                  <tr key={checkpoint.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700">
                    <td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{checkpoint.name}</p><p className="mt-1 text-[10px] font-bold uppercase text-[#a8750b]">{checkpoint.type}</p></td>
                    <td className="py-4"><button type="button" onClick={() => openSession(checkpoint.sessionId)} className="text-xs font-black text-[#800000] dark:text-rose-300">{checkpoint.sessionId}</button></td>
                    <td className="py-4 text-xs font-bold text-slate-500">{checkpoint.expected}</td>
                    <td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{checkpoint.arrived}</td>
                    <td className="py-4 text-xs text-slate-500">{checkpoint.proof}</td>
                    <td className="py-4"><StatusBadge status={checkpoint.status} /></td>
                    <td className="py-4">{['pending', 'flagged'].includes(checkpoint.status) ? <button type="button" onClick={() => { setSelectedSessionId(checkpoint.sessionId); setActiveModule('verification'); }} className="inline-flex items-center gap-1.5 text-xs font-black text-[#800000] dark:text-rose-300">Review <FiArrowRight /></button> : <span className="text-[11px] font-bold text-slate-400">{checkpoint.verifiedBy}</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>
      )}

      {activeModule === 'verification' && (
        <div className="grid gap-6 xl:grid-cols-[1fr_330px]">
          <Panel title="Travel Verification" subtitle="Validate checkpoint evidence before it becomes part of the official travel record" icon={FiShield}>
            {verificationQueue.length ? <div className="space-y-4">
              {verificationQueue.map((checkpoint) => {
                const session = sessions.find((item) => item.id === checkpoint.sessionId);
                return (
                  <article key={checkpoint.id} className={`rounded-2xl border p-5 ${checkpoint.status === 'flagged' ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900 dark:bg-rose-950/20' : 'border-amber-200 bg-amber-50/40 dark:border-amber-900 dark:bg-amber-950/20'}`}>
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                      <div className="grid min-h-36 w-full place-items-center rounded-2xl bg-gradient-to-br from-slate-200 to-slate-100 text-center lg:w-44 dark:from-gray-700 dark:to-gray-900">
                        <div><FiMapPin className="mx-auto text-3xl text-[#800000] dark:text-rose-300" /><p className="mt-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Geotag evidence</p></div>
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                          <div><p className="text-[10px] font-black uppercase tracking-wider text-[#a8750b]">{checkpoint.sessionId}</p><h3 className="mt-1 text-base font-black text-slate-800 dark:text-white">{checkpoint.name}</h3></div>
                          <StatusBadge status={checkpoint.status} />
                        </div>
                        <p className="mt-2 text-xs font-bold text-slate-500">{session?.student} · {session?.studentId}</p>
                        <div className="mt-4 grid grid-cols-3 gap-3">
                          <div><p className="text-[9px] font-black uppercase text-slate-400">Expected</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{checkpoint.expected}</p></div>
                          <div><p className="text-[9px] font-black uppercase text-slate-400">Recorded</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{checkpoint.arrived}</p></div>
                          <div><p className="text-[9px] font-black uppercase text-slate-400">Evidence</p><p className="mt-1 text-xs font-black text-slate-700 dark:text-gray-200">{checkpoint.proof}</p></div>
                        </div>
                        {checkpoint.status === 'flagged' && <div className="mt-4 flex items-start gap-2 rounded-xl bg-rose-100 p-3 text-xs font-bold leading-5 text-rose-700 dark:bg-rose-950 dark:text-rose-300"><FiAlertTriangle className="mt-0.5 shrink-0" /> Arrival is 33 minutes later than the planned checkpoint time.</div>}
                        <div className="mt-5 flex flex-wrap gap-2">
                          <button type="button" onClick={() => resolveCheckpoint(checkpoint.id, 'verified')} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white"><FiCheck /> Verify Evidence</button>
                          <button type="button" onClick={() => resolveCheckpoint(checkpoint.id, 'flagged')} className="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-black text-rose-600 dark:border-rose-900 dark:bg-gray-800"><FiFlag /> Flag for Follow-up</button>
                        </div>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div> : <div className="rounded-3xl bg-emerald-50 py-16 text-center dark:bg-emerald-950/30"><FiCheckCircle className="mx-auto text-5xl text-emerald-500" /><h3 className="mt-4 font-black text-emerald-700 dark:text-emerald-300">Verification queue cleared</h3><p className="mt-2 text-xs text-emerald-600/70">All submitted checkpoint evidence has been reviewed.</p></div>}
          </Panel>

          <div className="space-y-6">
            <Panel title="Verification Guide" subtitle="Evidence must satisfy all checks" icon={FiFileText}>
              <div className="space-y-4">
                {['Identity matches travel manifest', 'Timestamp is within route window', 'Location matches checkpoint radius', 'Photo is clear and geotagged'].map((item, index) => <div key={item} className="flex items-start gap-3"><div className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#800000]/10 text-[11px] font-black text-[#800000] dark:bg-rose-950/50 dark:text-rose-300">{index + 1}</div><p className="pt-1 text-xs font-bold leading-5 text-slate-600 dark:text-gray-300">{item}</p></div>)}
              </div>
            </Panel>
            <div className="rounded-3xl bg-gradient-to-br from-[#9d7418] to-[#c3972f] p-6 text-white shadow-sm">
              <FiShield className="text-3xl" />
              <p className="mt-5 text-3xl font-black">{checkpoints.filter((item) => item.status === 'verified').length}</p>
              <p className="mt-1 text-xs font-black uppercase tracking-wider text-white/70">Verified checkpoints</p>
              <p className="mt-4 text-xs leading-5 text-white/75">Evidence decisions are recorded with the reviewer’s identity and review time.</p>
            </div>
          </div>
        </div>
      )}

      {activeModule === 'reports' && (
        <div className="space-y-6">
          <Panel title="Travel Reports" subtitle="Performance, safety, route, and compliance summaries" icon={FiBarChart2} action={<button type="button" onClick={exportReport} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiDownload /> Export CSV</button>}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[
                ['Total sessions', '48', '+12% vs. June', FiNavigation, 'text-[#800000] dark:text-rose-300'],
                ['Safe arrivals', '46', '95.8% completion', FiCheckCircle, 'text-emerald-600'],
                ['Avg. travel time', '1h 42m', '8 min faster', FiClock, 'text-blue-600'],
                ['Route exceptions', '3', '2 resolved', FiAlertTriangle, 'text-amber-600'],
              ].map(([label, value, note, Icon, color]) => <div key={label} className="rounded-2xl bg-slate-50 p-5 dark:bg-gray-900"><Icon className={`text-xl ${color}`} /><p className={`mt-4 text-2xl font-black ${color}`}>{value}</p><p className="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-[11px] font-bold text-slate-500">{note}</p></div>)}
            </div>
          </Panel>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Monthly Travel Activity" subtitle="Sessions recorded during the last six months" icon={FiActivity}>
              <div className="flex h-64 items-end justify-between gap-3 border-b border-slate-200 px-2 pb-1 dark:border-gray-700">
                {[['Feb', 42], ['Mar', 58], ['Apr', 50], ['May', 74], ['Jun', 65], ['Jul', 88]].map(([month, height]) => (
                  <div key={month} className="flex h-full flex-1 flex-col items-center justify-end gap-3">
                    <span className="text-[10px] font-black text-slate-400">{Math.round(height * 0.55)}</span>
                    <div className={`w-full max-w-12 rounded-t-xl ${month === 'Jul' ? 'bg-gradient-to-t from-[#800000] to-[#b82929]' : 'bg-[#800000]/20 dark:bg-rose-900/40'}`} style={{ height: `${height}%` }} />
                    <span className="text-[10px] font-black text-slate-500">{month}</span>
                  </div>
                ))}
              </div>
            </Panel>

            <Panel title="Travel Compliance" subtitle="Completion health across required monitoring steps" icon={FiShield}>
              <div className="space-y-5">
                {[
                  ['Approved travel sessions', 96, 'green'],
                  ['Verified companion manifests', 91, 'blue'],
                  ['Checkpoint evidence submitted', 88, 'maroon'],
                  ['On-time route completion', 83, 'amber'],
                ].map(([label, value, tone]) => <div key={label}><div className="mb-2 flex items-center justify-between"><span className="text-xs font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="text-xs font-black text-slate-800 dark:text-white">{value}%</span></div><ProgressBar value={value} tone={tone} /></div>)}
              </div>
              <div className="mt-6 rounded-2xl border border-[#a8750b]/20 bg-[#f1c54c]/10 p-4">
                <div className="flex items-start gap-3"><FiCheckCircle className="mt-0.5 shrink-0 text-[#a8750b]" /><div><p className="text-sm font-black text-[#6f4b00] dark:text-[#f7d979]">Compliance is above target</p><p className="mt-1 text-xs leading-5 text-[#8a691f] dark:text-[#e7c86e]">Overall travel-monitoring compliance is 89.5%, exceeding the 85% institutional target.</p></div></div>
              </div>
            </Panel>
          </div>

          <Panel title="Recent Travel Records" subtitle="Latest completed and active sessions included in reports" icon={FiFileText}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[760px] text-left">
                <thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Session</th><th className="pb-3 font-black">Traveler</th><th className="pb-3 font-black">Destination</th><th className="pb-3 font-black">Date</th><th className="pb-3 font-black">Checkpoints</th><th className="pb-3 font-black">Status</th></tr></thead>
                <tbody>{sessions.map((session) => <tr key={session.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4 text-xs font-black text-[#800000] dark:text-rose-300">{session.id}</td><td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{session.student}</p><p className="mt-1 text-[11px] text-slate-400">{session.studentId}</p></td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{session.destination}</td><td className="py-4 text-xs text-slate-500">{session.travelDate}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{session.checkpointsPassed}/{session.checkpointsTotal}</td><td className="py-4"><StatusBadge status={session.status} /></td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {showSessionModal && (
        <Modal title="Create Travel Session" subtitle="Register an authorized trip and its planned route" onClose={() => setShowSessionModal(false)}>
          <form onSubmit={createSession} className="space-y-5 p-6">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Student name"><input required value={newSession.student} onChange={(event) => setNewSession((value) => ({ ...value, student: event.target.value }))} placeholder="Full name" className={inputClass} /></Field>
              <Field label="Student ID"><input required value={newSession.studentId} onChange={(event) => setNewSession((value) => ({ ...value, studentId: event.target.value }))} placeholder="e.g. 2022-10001" className={inputClass} /></Field>
            </div>
            <Field label="Program"><input required value={newSession.program} onChange={(event) => setNewSession((value) => ({ ...value, program: event.target.value }))} placeholder="Academic program" className={inputClass} /></Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Destination"><input required value={newSession.destination} onChange={(event) => setNewSession((value) => ({ ...value, destination: event.target.value }))} placeholder="Travel destination" className={inputClass} /></Field>
              <Field label="Purpose"><input required value={newSession.purpose} onChange={(event) => setNewSession((value) => ({ ...value, purpose: event.target.value }))} placeholder="Official travel purpose" className={inputClass} /></Field>
            </div>
            <Field label="Planned route"><input value={newSession.route} onChange={(event) => setNewSession((value) => ({ ...value, route: event.target.value }))} placeholder="Main Campus → Stop → Destination" className={inputClass} /></Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Travel date"><input required type="date" value={newSession.travelDate} onChange={(event) => setNewSession((value) => ({ ...value, travelDate: event.target.value }))} className={inputClass} /></Field>
              <Field label="Scheduled departure"><input required type="time" value={newSession.scheduledAt} onChange={(event) => setNewSession((value) => ({ ...value, scheduledAt: event.target.value }))} className={inputClass} /></Field>
            </div>
            <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-700">
              <button type="button" onClick={() => setShowSessionModal(false)} className="rounded-xl border border-slate-200 px-5 py-3 text-xs font-black text-slate-600 dark:border-gray-600 dark:text-gray-200">Cancel</button>
              <button type="submit" className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-xs font-black text-white"><FiCheckCircle /> Create Session</button>
            </div>
          </form>
        </Modal>
      )}

      {showCompanionModal && (
        <Modal title="Add Travel Companion" subtitle="Add a verified person to a session manifest" onClose={() => setShowCompanionModal(false)}>
          <form onSubmit={addCompanion} className="space-y-5 p-6">
            <Field label="Travel session"><select value={newCompanion.sessionId} onChange={(event) => setNewCompanion((value) => ({ ...value, sessionId: event.target.value }))} className={inputClass}>{sessions.map((session) => <option key={session.id} value={session.id}>{session.id} · {session.student}</option>)}</select></Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Companion name"><input required value={newCompanion.name} onChange={(event) => setNewCompanion((value) => ({ ...value, name: event.target.value }))} placeholder="Full name" className={inputClass} /></Field>
              <Field label="Companion type"><select value={newCompanion.type} onChange={(event) => setNewCompanion((value) => ({ ...value, type: event.target.value }))} className={inputClass}><option>Student</option><option>Faculty</option><option>Parent / Guardian</option><option>Other</option></select></Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Role or relationship"><input required value={newCompanion.relation} onChange={(event) => setNewCompanion((value) => ({ ...value, relation: event.target.value }))} placeholder="e.g. Travel supervisor" className={inputClass} /></Field>
              <Field label="Contact number"><input required value={newCompanion.contact} onChange={(event) => setNewCompanion((value) => ({ ...value, contact: event.target.value }))} placeholder="09XX XXX XXXX" className={inputClass} /></Field>
            </div>
            <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-700">
              <button type="button" onClick={() => setShowCompanionModal(false)} className="rounded-xl border border-slate-200 px-5 py-3 text-xs font-black text-slate-600 dark:border-gray-600 dark:text-gray-200">Cancel</button>
              <button type="submit" className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-xs font-black text-white"><FiUserCheck /> Add Companion</button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default TravelMonitoring;
