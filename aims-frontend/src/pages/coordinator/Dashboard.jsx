import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiAlertTriangle,
  FiArrowRight,
  FiBarChart2,
  FiBookOpen,
  FiBriefcase,
  FiCheckCircle,
  FiClock,
  FiFileText,
  FiMapPin,
  FiRefreshCw,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const colors = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };

  return <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#430909] dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div><div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl ${colors[tone]}`}><Icon /></div></div></div>;
}

function Section({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return <section className={`rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 ${className}`}><div className="mb-6 flex items-start justify-between gap-4"><div className="flex items-start gap-3"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div><div><h2 className="font-black text-[#430909] dark:text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div></div>{action}</div>{children}</section>;
}

function Progress({ value, color = 'bg-[#800000]' }) {
  const safe = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all duration-700 ${color}`} style={{ width: `${safe}%` }} /></div>;
}

function Ring({ value, label }) {
  const safe = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(#800000 ${safe * 3.6}deg, var(--ring-track) 0deg)` }}><div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800"><div><p className="text-2xl font-black text-[#800000] dark:text-rose-300">{safe.toFixed(1)}%</p><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p></div></div></div>;
}

const statusColors = {
  present: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  late: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  absent: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  no_record: 'bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-300',
};

function CoordinatorDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboard = async (quiet = false) => {
    if (quiet) setRefreshing(true); else setLoading(true);
    try {
      const response = await api.get('/coordinator/dashboard');
      setData(response.data);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load the coordinator dashboard.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchDashboard(); }, []);

  if (loading) return <div className="grid h-64 place-items-center"><div className="h-12 w-12 animate-spin rounded-full border-b-2 border-[#800000]" /></div>;

  const overview = data?.overview || {};
  const attendance = data?.attendance || {};
  const journals = data?.journals || {};
  const requirements = data?.requirements || {};
  const hteMoa = data?.hte_moa || {};
  const travel = data?.travel || {};
  const totalAttendance = (attendance.present || 0) + (attendance.late || 0) + (attendance.absent || 0) + (attendance.holiday || 0);
  const attendanceParts = [
    { label: 'Present', value: attendance.present || 0, color: 'bg-emerald-500' },
    { label: 'Late', value: attendance.late || 0, color: 'bg-amber-500' },
    { label: 'Absent', value: attendance.absent || 0, color: 'bg-rose-500' },
    { label: 'Holiday', value: attendance.holiday || 0, color: 'bg-violet-500' },
  ];

  return <div className="space-y-6">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Internship Management</p><h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">Coordinator Dashboard</h1><p className="mt-1 text-sm text-slate-400">Campus-wide internship monitoring and operational overview</p></div><button onClick={() => fetchDashboard(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#800000] shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Data</button></div>

    <section><div className="mb-4"><p className="text-xs font-extrabold uppercase tracking-widest text-slate-400">Internship Overview</p></div><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><MetricCard label="Total Interns" value={overview.total_students || 0} note="Registered students" icon={FiUsers} /><MetricCard label="Active" value={overview.active || 0} note="Currently deployed" icon={FiUserCheck} tone="green" /><MetricCard label="Completed" value={overview.completed || 0} note="Finished internships" icon={FiCheckCircle} tone="blue" /><MetricCard label="Pending" value={overview.pending || 0} note="Awaiting deployment" icon={FiClock} tone="amber" /><MetricCard label="For Approval" value={overview.pending_registrations || 0} note="Registration requests" icon={FiAlertTriangle} tone="violet" /></div></section>

    <Section title="Active Intern Monitoring" subtitle="Progress, placement, attendance, and requirement completion" icon={FiTrendingUp} action={<Link to="/coordinator/students" className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">Manage Students <FiArrowRight /></Link>}>
      {data?.active_interns?.length ? <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-left"><thead><tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-extrabold">Student</th><th className="pb-3 font-extrabold">HTE</th><th className="pb-3 font-extrabold">OJT Progress</th><th className="pb-3 font-extrabold">Requirements</th><th className="pb-3 font-extrabold">Latest Attendance</th></tr></thead><tbody>{data.active_interns.map((intern) => <tr key={intern.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{intern.name}</p><p className="mt-1 text-xs text-slate-400">{intern.student_id} · {intern.program}</p></td><td className="py-4 text-sm font-semibold text-slate-600 dark:text-gray-300">{intern.hte}</td><td className="w-48 py-4"><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{intern.rendered_hours}/{intern.required_hours}h</span><span className="font-black text-[#800000] dark:text-rose-300">{intern.progress_percent}%</span></div><Progress value={intern.progress_percent} /></td><td className="w-36 py-4"><p className="mb-1.5 text-xs font-black text-slate-600 dark:text-gray-300">{intern.requirements_percent}%</p><Progress value={intern.requirements_percent} color="bg-blue-500" /></td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${statusColors[intern.last_attendance_status] || statusColors.no_record}`}>{intern.last_attendance_status.replace('_', ' ')}</span><p className="mt-1.5 text-[11px] text-slate-400">{intern.last_attendance_date || 'No attendance yet'}</p></td></tr>)}</tbody></table></div> : <div className="py-10 text-center text-sm text-slate-400">No active interns found.</div>}
    </Section>

    <div className="grid gap-6 xl:grid-cols-2">
      <Section title="Attendance Summary" subtitle={`${new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })} attendance records`} icon={FiBarChart2} action={<Link to="/coordinator/attendance" className="text-xs font-black text-[#800000] dark:text-rose-300">View Attendance</Link>}>
        <div className="flex flex-col items-center gap-7 sm:flex-row"><Ring value={attendance.rate} label="attendance" /><div className="w-full flex-1"><div className="mb-5 flex items-end justify-between"><div><p className="text-3xl font-black text-[#430909] dark:text-white">{attendance.total_hours || 0}<span className="ml-1 text-sm text-slate-400">hrs</span></p><p className="text-xs text-slate-400">Total rendered this month</p></div><p className="text-sm font-black text-slate-500">{totalAttendance} records</p></div><div className="space-y-3">{attendanceParts.map((part) => <div key={part.label}><div className="mb-1 flex justify-between text-xs"><span className="font-bold text-slate-500">{part.label}</span><span className="font-black text-slate-700 dark:text-gray-200">{part.value}</span></div><Progress value={totalAttendance ? (part.value / totalAttendance) * 100 : 0} color={part.color} /></div>)}</div></div></div>
      </Section>

      <Section title="Journal Submission Statistics" subtitle="AM and PM accomplishment reports for the current month" icon={FiBookOpen}>
        <div className="flex flex-col items-center gap-7 sm:flex-row"><Ring value={journals.completion_rate} label="submitted" /><div className="grid w-full flex-1 grid-cols-2 gap-3">{[
          ['Submitted', journals.submitted, 'text-emerald-600'], ['Expected', journals.expected, 'text-blue-600'], ['Missing', journals.missing, 'text-rose-600'], ['Students', journals.students_with_submissions, 'text-violet-600'],
        ].map(([label, value, color]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className={`text-2xl font-black ${color}`}>{value || 0}</p><p className="mt-1 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p></div>)}</div></div>
      </Section>
    </div>

    <div className="grid gap-6 xl:grid-cols-2">
      <Section title="Requirement Completion Status" subtitle="Document review and approval pipeline" icon={FiFileText}>
        <div className="mb-6 flex items-end justify-between"><div><p className="text-4xl font-black text-[#800000] dark:text-rose-300">{requirements.completion_rate || 0}%</p><p className="mt-1 text-xs text-slate-400">Overall requirement approval</p></div><p className="text-sm font-black text-slate-500">{requirements.approved || 0}/{requirements.total || 0} approved</p></div><Progress value={requirements.completion_rate} /><div className="mt-6 grid grid-cols-3 gap-3">{[['Approved', requirements.approved, 'text-emerald-600'], ['Pending', requirements.pending, 'text-amber-600'], ['Rejected', requirements.rejected, 'text-rose-600']].map(([label, value, color]) => <div key={label} className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className={`text-xl font-black ${color}`}>{value || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">{label}</p></div>)}</div>
      </Section>

      <Section title="HTE and MOA Status" subtitle="Partner establishments and agreement validity" icon={FiBriefcase} action={<Link to="/coordinator/htes" className="text-xs font-black text-[#800000] dark:text-rose-300">Manage HTEs</Link>}>
        <div className="grid grid-cols-3 gap-3"><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-[#800000] dark:text-rose-300">{hteMoa.total_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">Partners</p></div><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-emerald-600">{hteMoa.active_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">Active</p></div><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-blue-600">{hteMoa.assigned_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">With Interns</p></div></div><div className="mt-5 grid grid-cols-4 gap-2 text-center">{[['Approved', hteMoa.moa_approved, 'text-emerald-600'], ['Pending', hteMoa.moa_pending, 'text-amber-600'], ['Rejected', hteMoa.moa_rejected, 'text-rose-600'], ['Expired', hteMoa.moa_expired, 'text-slate-500']].map(([label, value, color]) => <div key={label}><p className={`text-lg font-black ${color}`}>{value || 0}</p><p className="text-[9px] font-bold uppercase text-slate-400">{label} MOA</p></div>)}</div>{hteMoa.expiring_soon?.length > 0 && <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-bold text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">⚠ {hteMoa.expiring_soon.length} MOA(s) expire within 30 days.</div>}
      </Section>
    </div>

    <Section title="Travel Monitoring Summary" subtitle="Student travel sessions for the current month" icon={FiMapPin} action={<Link to="/coordinator/travel" className="text-xs font-black text-[#800000] dark:text-rose-300">Open Travel Monitor</Link>}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><MetricCard label="Active Now" value={travel.active || 0} note="Ongoing sessions" icon={FiMapPin} tone="maroon" /><MetricCard label="This Month" value={travel.total_this_month || 0} note="All travel logs" icon={FiBarChart2} tone="blue" /><MetricCard label="Completed" value={travel.completed_this_month || 0} note="Closed safely" icon={FiCheckCircle} tone="green" /><MetricCard label="Cancelled" value={travel.cancelled_this_month || 0} note="Cancelled sessions" icon={FiAlertTriangle} tone="amber" /></div>
    </Section>

    <div className="grid gap-6 xl:grid-cols-3">
      <Section title="Notifications and Alerts" subtitle="Items requiring coordinator attention" icon={FiAlertTriangle} className="xl:col-span-2">
        {data?.alerts?.length ? <div className="space-y-3">{data.alerts.map((alert) => <Link key={alert.title} to={alert.link} className={`flex items-start gap-3 rounded-2xl border p-4 transition hover:-translate-y-0.5 ${alert.type === 'danger' ? 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/40' : alert.type === 'warning' ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40' : 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40'}`}><FiAlertTriangle className="mt-0.5 shrink-0" /><div className="flex-1"><p className="text-sm font-black text-slate-800 dark:text-white">{alert.title}</p><p className="mt-1 text-xs text-slate-500 dark:text-gray-300">{alert.message}</p></div><FiArrowRight className="mt-1 shrink-0" /></Link>)}</div> : <div className="rounded-2xl bg-emerald-50 p-6 text-center dark:bg-emerald-950/40"><FiCheckCircle className="mx-auto text-3xl text-emerald-600" /><p className="mt-2 text-sm font-black text-emerald-700 dark:text-emerald-300">No urgent alerts</p></div>}
      </Section>

      <Section title="University Notices" subtitle="Latest coordinator announcements" icon={FiBookOpen}>
        <div className="space-y-4">{data?.announcements?.length ? data.announcements.map((item) => <div key={item.id} className="border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-gray-700"><p className="text-sm font-black text-slate-800 dark:text-white">{item.title}</p><p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">{item.content}</p><p className="mt-2 text-[10px] font-bold text-[#a8750b]">{new Date(item.published_at || item.created_at).toLocaleDateString()}</p></div>) : <p className="py-8 text-center text-sm text-slate-400">No notices available.</p>}</div>
      </Section>
    </div>

    <Section title="Quick Reports" subtitle="Open frequently used monitoring and reporting views" icon={FiFileText}>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{[
        { label: 'Student Progress', note: `${overview.active || 0} active interns`, path: '/coordinator/students', icon: FiTrendingUp },
        { label: 'Attendance Report', note: `${attendance.rate || 0}% attendance rate`, path: '/coordinator/attendance', icon: FiClock },
        { label: 'Travel Report', note: `${travel.total_this_month || 0} logs this month`, path: '/coordinator/travel', icon: FiMapPin },
        { label: 'Consolidated Reports', note: 'Open reporting center', path: '/coordinator/reports', icon: FiBarChart2 },
      ].map((report) => <Link key={report.label} to={report.path} className="group flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-[#800000]/30 hover:bg-[#800000]/[0.02] dark:border-gray-700 dark:hover:bg-gray-900"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-[#800000] group-hover:bg-[#800000] group-hover:text-white dark:bg-gray-700 dark:text-rose-300"><report.icon /></div><div className="min-w-0 flex-1"><p className="text-sm font-black text-slate-800 dark:text-white">{report.label}</p><p className="mt-0.5 truncate text-[11px] text-slate-400">{report.note}</p></div><FiArrowRight className="text-slate-300 group-hover:text-[#800000]" /></Link>)}</div>
    </Section>
  </div>;
}

export default CoordinatorDashboard;
