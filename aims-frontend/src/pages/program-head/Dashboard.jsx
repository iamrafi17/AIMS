import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiActivity,
  FiAlertCircle,
  FiArrowRight,
  FiBarChart2,
  FiBookOpen,
  FiCheckCircle,
  FiClock,
  FiFileText,
  FiMapPin,
  FiRefreshCw,
  FiShield,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyDashboard = {
  overview: { total_students: 0, active: 0, completed: 0, pending: 0, deployed: 0, average_progress: 0 },
  attendance: { present: 0, late: 0, absent: 0, holiday: 0, total: 0, rate: 0, verified: 0, total_hours: 0, trend: [] },
  requirements: { total: 0, submitted: 0, approved: 0, pending: 0, rejected: 0, missing: 0, completion_rate: 0 },
  travel: { active: 0, total_this_month: 0, completed_this_month: 0, cancelled_this_month: 0, completion_rate: 0 },
  pending_reviews: { registrations: 0, requirements: 0, attendance: 0, journals: 0, total: 0 },
  analytics: { average_progress: 0, internship_status: [], program_performance: [] },
};

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase text-slate-500">{label}</p>
          <p className="mt-2 text-2xl font-bold text-[#690016] dark:text-white">{value}</p>
          <p className="mt-1 text-xs text-slate-400">{note}</p>
        </div>
        <div className={`grid h-10 w-10 place-items-center rounded-lg text-lg ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return (
    <section className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-lg text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div>
          <div><h2 className="font-black text-[#690016] dark:text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function ProgressBar({ value, color = 'bg-[#7D001D]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all duration-500 ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function Ring({ value, label, color = '#7D001D' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return (
    <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${safeValue * 3.6}deg, var(--ring-track) 0deg)` }}>
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800">
        <div><p className="text-2xl font-black text-[#690016] dark:text-white">{safeValue.toFixed(1)}%</p><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p></div>
      </div>
    </div>
  );
}

function ProgramHeadDashboard() {
  const [dashboard, setDashboard] = useState(emptyDashboard);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    let mounted = true;

    api.get('/program-head/dashboard')
      .then((response) => {
        if (mounted) setDashboard({ ...emptyDashboard, ...response.data });
      })
      .catch((error) => {
        if (mounted) toast.error(error.response?.data?.message || 'Unable to load the Program Head dashboard.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });

    return () => { mounted = false; };
  }, []);

  const refreshDashboard = async () => {
    setRefreshing(true);
    try {
      const response = await api.get('/program-head/dashboard');
      setDashboard({ ...emptyDashboard, ...response.data });
      toast.success('Dashboard data refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to refresh dashboard data.');
    } finally {
      setRefreshing(false);
    }
  };

  if (loading) {
    return (
      <div className="grid min-h-[420px] place-items-center">
        <div className="text-center">
          <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#7D001D]/15 border-b-[#7D001D]" />
          <p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading program overview</p>
        </div>
      </div>
    );
  }

  const overview = dashboard.overview || emptyDashboard.overview;
  const attendance = dashboard.attendance || emptyDashboard.attendance;
  const requirements = dashboard.requirements || emptyDashboard.requirements;
  const travel = dashboard.travel || emptyDashboard.travel;
  const reviews = dashboard.pending_reviews || emptyDashboard.pending_reviews;
  const analytics = dashboard.analytics || emptyDashboard.analytics;
  const attendanceTotal = (attendance.present || 0) + (attendance.late || 0) + (attendance.absent || 0) + (attendance.holiday || 0);
  const statusTotal = analytics.internship_status?.reduce((sum, item) => sum + Number(item.value || 0), 0) || 0;

  const reviewItems = [
    { label: 'Registration reviews', count: reviews.registrations || 0, note: 'New student registrations awaiting decision', icon: FiUsers, tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300', path: '/program-head/documents' },
    { label: 'Requirement reviews', count: reviews.requirements || 0, note: 'Submitted internship documents to evaluate', icon: FiFileText, tone: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300', path: '/program-head/documents' },
    { label: 'Attendance reviews', count: reviews.attendance || 0, note: 'Attendance logs pending verification', icon: FiClock, tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', path: '/program-head/reports' },
    { label: 'Journal reviews', count: reviews.journals || 0, note: 'Daily accomplishment reports to review', icon: FiBookOpen, tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300', path: '/program-head/reports' },
  ];

  return (
    <div className="space-y-6">
      <header className="border-b border-slate-200 pb-5 dark:border-slate-700">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <div className="mb-1.5 inline-flex items-center gap-2 text-xs font-bold uppercase text-[#7D001D] dark:text-rose-300"><FiShield /> Academic Oversight</div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Program Head Dashboard</h1>
            <p className="mt-1.5 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">Monitor internship performance, compliance, reviews, and student safety from one academic oversight center.</p>
          </div>
          <button type="button" onClick={refreshDashboard} disabled={refreshing} className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-[#7D001D] transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-rose-300"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Data</button>
        </div>
      </header>

      <section>
        <div className="mb-4 flex items-end justify-between gap-3">
          <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#a8750b]">Program Internship Overview</p><h2 className="mt-1 text-lg font-black text-[#690016] dark:text-white">Internship population and progress</h2></div>
          <p className="hidden text-xs font-bold text-slate-400 sm:block">{overview.deployed || 0} students deployed to an HTE</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <MetricCard label="Total students" value={overview.total_students || 0} note="Registered interns" icon={FiUsers} />
          <MetricCard label="Active interns" value={overview.active || 0} note="Currently deployed" icon={FiUserCheck} tone="green" />
          <MetricCard label="Completed" value={overview.completed || 0} note="OJT requirements fulfilled" icon={FiCheckCircle} tone="blue" />
          <MetricCard label="Pending" value={overview.pending || 0} note="Awaiting deployment" icon={FiClock} tone="amber" />
          <MetricCard label="Average progress" value={`${overview.average_progress || 0}%`} note="Across active interns" icon={FiTrendingUp} tone="violet" />
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-2">
        <Panel title="Attendance Summary" subtitle={`${new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })} attendance performance`} icon={FiClock} action={<Link to="/program-head/reports" className="inline-flex items-center gap-1 text-xs font-black text-[#7D001D] dark:text-rose-300">Open report <FiArrowRight /></Link>}>
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={attendance.rate} label="attendance" color="#059669" />
            <div className="w-full flex-1">
              <div className="mb-5 flex items-end justify-between gap-3"><div><p className="text-3xl font-black text-[#690016] dark:text-white">{attendance.total_hours || 0}<span className="ml-1 text-sm text-slate-400">hrs</span></p><p className="mt-1 text-xs text-slate-400">Rendered this month</p></div><div className="text-right"><p className="text-lg font-black text-emerald-600">{attendance.verified || 0}</p><p className="text-[9px] font-bold uppercase text-slate-400">Verified logs</p></div></div>
              <div className="space-y-3">
                {[
                  ['Present', attendance.present, 'bg-emerald-500'],
                  ['Late', attendance.late, 'bg-amber-500'],
                  ['Absent', attendance.absent, 'bg-rose-500'],
                  ['Holiday', attendance.holiday, 'bg-violet-500'],
                ].map(([label, value, color]) => <div key={label}><div className="mb-1 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200">{value || 0}</span></div><ProgressBar value={attendanceTotal ? ((value || 0) / attendanceTotal) * 100 : 0} color={color} /></div>)}
              </div>
            </div>
          </div>
        </Panel>

        <Panel title="Requirement Completion Summary" subtitle="Official internship document submission and approval" icon={FiFileText} action={<Link to="/program-head/documents" className="inline-flex items-center gap-1 text-xs font-black text-[#7D001D] dark:text-rose-300">Review documents <FiArrowRight /></Link>}>
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={requirements.completion_rate} label="approved" />
            <div className="w-full flex-1">
              <div className="mb-5"><p className="text-3xl font-black text-[#690016] dark:text-white">{requirements.approved || 0}<span className="ml-1 text-sm text-slate-400">of {requirements.total || 0}</span></p><p className="mt-1 text-xs text-slate-400">Approved requirement checklists</p></div>
              <div className="grid grid-cols-2 gap-3">
                {[
                  ['Submitted', requirements.submitted, 'text-blue-600', 'bg-blue-50 dark:bg-blue-950/30'],
                  ['Pending', requirements.pending, 'text-amber-600', 'bg-amber-50 dark:bg-amber-950/30'],
                  ['Rejected', requirements.rejected, 'text-rose-600', 'bg-rose-50 dark:bg-rose-950/30'],
                  ['Missing', requirements.missing, 'text-slate-600 dark:text-slate-300', 'bg-slate-50 dark:bg-gray-900'],
                ].map(([label, value, color, background]) => <div key={label} className={`rounded-xl p-3 ${background}`}><p className={`text-xl font-black ${color}`}>{value || 0}</p><p className="mt-1 text-[9px] font-black uppercase tracking-wide text-slate-400">{label}</p></div>)}
              </div>
            </div>
          </div>
        </Panel>
      </div>

      <Panel title="Travel Monitoring Summary" subtitle="Student travel activity and safe completion for the current month" icon={FiMapPin} action={<Link to="/program-head/travel" className="inline-flex items-center gap-1 text-xs font-black text-[#7D001D] dark:text-rose-300">Open monitor <FiArrowRight /></Link>}>
        <div className="grid gap-4 lg:grid-cols-[1.25fr_1fr]">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-2xl bg-gradient-to-br from-[#690016] to-[#7D001D] p-5 text-white"><FiActivity className="text-2xl text-[#f1c54c]" /><p className="mt-5 text-3xl font-black">{travel.active || 0}</p><p className="mt-1 text-[10px] font-black uppercase tracking-wider text-white/55">Active travel sessions</p><p className="mt-3 text-xs text-white/60">Students currently travelling under an authorized session.</p></div>
            <div className="rounded-2xl bg-slate-50 p-5 dark:bg-gray-900"><FiMapPin className="text-2xl text-blue-600" /><p className="mt-5 text-3xl font-black text-[#690016] dark:text-white">{travel.total_this_month || 0}</p><p className="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Sessions this month</p><p className="mt-3 text-xs text-slate-400">All recorded program travel activity.</p></div>
          </div>
          <div className="rounded-2xl border border-slate-100 p-5 dark:border-gray-700">
            <div className="mb-4 flex items-center justify-between"><div><p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Safe completion</p><p className="mt-1 text-2xl font-black text-emerald-600">{travel.completion_rate || 0}%</p></div><FiShield className="text-3xl text-emerald-500" /></div>
            <ProgressBar value={travel.completion_rate} color="bg-emerald-500" />
            <div className="mt-5 grid grid-cols-2 gap-3">
              <div className="rounded-xl bg-emerald-50 p-3 text-center dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">{travel.completed_this_month || 0}</p><p className="text-[9px] font-black uppercase text-slate-400">Completed</p></div>
              <div className="rounded-xl bg-rose-50 p-3 text-center dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">{travel.cancelled_this_month || 0}</p><p className="text-[9px] font-black uppercase text-slate-400">Cancelled</p></div>
            </div>
          </div>
        </div>
      </Panel>

      <Panel title="Pending Reviews" subtitle="Items requiring academic review or oversight" icon={FiAlertCircle} action={<div className="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase text-amber-700 dark:bg-amber-950 dark:text-amber-300">{reviews.total || 0} total</div>}>
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {reviewItems.map((item) => (
            <Link key={item.label} to={item.path} className="group rounded-2xl border border-slate-100 p-4 transition hover:-translate-y-0.5 hover:border-[#7D001D]/20 hover:shadow-md dark:border-gray-700">
              <div className="flex items-start justify-between gap-3"><div className={`grid h-10 w-10 place-items-center rounded-xl text-lg ${item.tone}`}><item.icon /></div><span className={`text-2xl font-black ${item.count > 0 ? 'text-[#7D001D] dark:text-rose-300' : 'text-emerald-600'}`}>{item.count}</span></div>
              <p className="mt-4 text-sm font-black text-slate-800 dark:text-white">{item.label}</p>
              <p className="mt-1 min-h-10 text-[11px] leading-5 text-slate-400">{item.note}</p>
              <div className="mt-3 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-[#7D001D] opacity-70 group-hover:opacity-100 dark:text-rose-300">Review queue <FiArrowRight /></div>
            </Link>
          ))}
        </div>
      </Panel>

      <div className="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
        <Panel title="Internship Analytics" subtitle="Distribution of students by current internship status" icon={FiBarChart2}>
          {statusTotal > 0 ? (
            <div>
              <div className="mb-7 flex justify-center"><Ring value={statusTotal ? ((overview.active || 0) / statusTotal) * 100 : 0} label="active interns" color="#059669" /></div>
              <div className="space-y-4">
                {analytics.internship_status.map((item) => <div key={item.label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{item.label}</span><span className="font-black text-slate-700 dark:text-gray-200">{item.value}</span></div><ProgressBar value={statusTotal ? (item.value / statusTotal) * 100 : 0} color={item.label === 'Active' ? 'bg-emerald-500' : item.label === 'Completed' ? 'bg-blue-500' : item.label === 'Pending' ? 'bg-amber-500' : 'bg-rose-500'} /></div>)}
              </div>
            </div>
          ) : <div className="py-12 text-center text-sm font-bold text-slate-400">No internship analytics are available yet.</div>}
        </Panel>

        <Panel title="Program Performance Analytics" subtitle="Internship progress and attendance by academic program" icon={FiTrendingUp} action={<Link to="/program-head/reports" className="text-xs font-black text-[#7D001D] dark:text-rose-300">View reports</Link>}>
          {analytics.program_performance?.length ? (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[590px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Program</th><th className="pb-3 font-black">Students</th><th className="pb-3 font-black">Active</th><th className="pb-3 font-black">OJT Progress</th><th className="pb-3 font-black">Attendance</th></tr></thead>
                <tbody>{analytics.program_performance.map((item) => <tr key={item.program} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4 text-sm font-black text-slate-800 dark:text-white">{item.program}</td><td className="py-4 text-xs font-black text-slate-600 dark:text-gray-300">{item.students}</td><td className="py-4 text-xs font-black text-emerald-600">{item.active}</td><td className="w-40 py-4"><div className="mb-1.5 flex justify-between text-[9px] font-black"><span className="text-slate-400">Average</span><span className="text-[#7D001D] dark:text-rose-300">{item.progress}%</span></div><ProgressBar value={item.progress} /></td><td className="w-36 py-4"><div className="mb-1.5 text-right text-[9px] font-black text-emerald-600">{item.attendance_rate}%</div><ProgressBar value={item.attendance_rate} color="bg-emerald-500" /></td></tr>)}</tbody>
              </table>
            </div>
          ) : <div className="py-12 text-center text-sm font-bold text-slate-400">Program performance will appear when student records are available.</div>}
        </Panel>
      </div>

      <Panel title="Seven-Day Attendance Trend" subtitle="Daily verified and unverified attendance performance" icon={FiActivity}>
        {attendance.trend?.length ? (
          <div className="flex h-52 items-end justify-between gap-3 border-b border-slate-200 px-2 pb-1 dark:border-gray-700">
            {attendance.trend.map((day) => <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[9px] font-black text-slate-400">{day.rate}%</span><div className={`w-full max-w-14 rounded-t-xl ${day.rate >= 90 ? 'bg-gradient-to-t from-emerald-700 to-emerald-400' : day.rate > 0 ? 'bg-gradient-to-t from-[#7D001D] to-[#b82929]' : 'bg-slate-200 dark:bg-gray-700'}`} style={{ height: `${Math.max(day.rate, 4)}%` }} /><span className="text-[9px] font-black text-slate-500">{day.label}</span></div>)}
          </div>
        ) : <div className="py-12 text-center text-sm font-bold text-slate-400">No attendance trend is available for the past seven days.</div>}
      </Panel>
    </div>
  );
}

export default ProgramHeadDashboard;
