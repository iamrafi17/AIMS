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
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import api from '../../services/api';

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon', to, onClick }) {
  const colors = {
    maroon: 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };

  const content = <div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="text-xs font-semibold uppercase text-slate-500">{label}</p><p className="mt-2 text-2xl font-bold text-[#690016] dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div><div className={`grid h-10 w-10 shrink-0 place-items-center rounded-lg text-lg ${colors[tone]}`}><Icon /></div></div>;
  const className = 'portal-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800';
  return to ? <Link to={to} className={className}>{content}</Link> : onClick ? <button type="button" onClick={onClick} className={`${className} text-left`}>{content}</button> : <div className={className}>{content}</div>;
}

function Section({ title, subtitle, icon: Icon, action, children, className = '', ...sectionProps }) {
  return <section {...sectionProps} className={`portal-content-panel rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5 ${className}`}><div className="mb-4 flex flex-col items-start justify-between gap-3 sm:mb-5 sm:flex-row sm:gap-4"><div className="flex min-w-0 items-start gap-3"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-[#7D001D]/10 text-lg text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div><div className="min-w-0"><h2 className="font-bold text-[#690016] dark:text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div></div>{action}</div>{children}</section>;
}

function Progress({ value, color = 'bg-[#7D001D]' }) {
  const safe = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all duration-700 ${color}`} style={{ width: `${safe}%` }} /></div>;
}

function Ring({ value, label }) {
  const safe = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="relative grid h-32 w-32 shrink-0 place-items-center rounded-full sm:h-36 sm:w-36" style={{ background: `conic-gradient(#7D001D ${safe * 3.6}deg, var(--ring-track) 0deg)` }}><div className="grid h-24 w-24 place-items-center rounded-full bg-white text-center dark:bg-gray-800 sm:h-28 sm:w-28"><div><p className="text-xl font-black text-[#7D001D] dark:text-rose-300 sm:text-2xl">{safe.toFixed(1)}%</p><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p></div></div></div>;
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
  const [mobileView, setMobileView] = useState('interns');

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

  if (loading) return <div className="grid h-64 place-items-center"><div className="h-12 w-12 animate-spin rounded-full border-b-2 border-[#7D001D]" /></div>;

  const overview = data?.overview || {};
  const requirementSummary = data?.requirement_summary || {};
  const period = data?.academic_period;
  const attendance = data?.attendance || {};
  const journals = data?.journals || {};
  const hteMoa = data?.hte_moa || {};
  const travel = data?.travel || {};
  const totalAttendance = (attendance.present || 0) + (attendance.late || 0) + (attendance.absent || 0) + (attendance.holiday || 0);
  const attendanceParts = [
    { label: 'Present', value: attendance.present || 0, color: 'bg-emerald-500' },
    { label: 'Late', value: attendance.late || 0, color: 'bg-amber-500' },
    { label: 'Absent', value: attendance.absent || 0, color: 'bg-rose-500' },
    { label: 'Holiday', value: attendance.holiday || 0, color: 'bg-violet-500' },
  ];

  return <div className="coordinator-dashboard space-y-6">
    <div className="portal-page-heading flex flex-wrap items-end justify-between gap-4"><div className="min-w-0"><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Internship Management</p><h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">Coordinator Dashboard</h1><p className="mt-1 text-sm text-slate-400">Internship monitoring and operational overview</p><p className="mt-1 text-sm font-bold text-[#7D001D]">{period ? `AY ${period.academic_year} · ${period.semester}` : 'No active academic period'}</p></div><button onClick={() => fetchDashboard(true)} disabled={refreshing} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#7D001D] shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Data</button></div>

    <section><div className="mb-3 sm:mb-4"><p className="text-xs font-extrabold uppercase tracking-widest text-slate-400">Internship Overview</p></div><div className="portal-metric-grid grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-5"><MetricCard label="Total Interns" value={overview.total_students || 0} note="Registered students" icon={FiUsers} to="/coordinator/students" /><MetricCard label="Active OJT" value={overview.active || 0} note="Currently deployed" icon={FiUserCheck} tone="green" to="/coordinator/students?internship_status=active" /><MetricCard label="Completed" value={overview.completed || 0} note="Finished internships" icon={FiCheckCircle} tone="blue" to="/coordinator/students?internship_status=completed" /><MetricCard label="Needs Attention" value={overview.needs_attention || 0} note="Open action center" icon={FiAlertTriangle} tone="amber" onClick={() => document.getElementById('action-center')?.scrollIntoView({ behavior: 'smooth' })} /><MetricCard label="Pending Registration" value={overview.awaiting_registration || 0} note="Eligible students without accounts" icon={FiClock} tone="violet" to="/coordinator/students?registration=pending#ojt-enrollments" /></div></section>

    <Section id="action-center" title="Action Center" subtitle="Records requiring coordinator attention" icon={FiAlertTriangle} action={<Link to="/coordinator/internship-requirements?status=for_review" className="text-xs font-black text-[#7D001D]">View reviews</Link>}>
      {data?.action_center?.length ? <div className="grid gap-2 sm:grid-cols-2">{data.action_center.map((item) => <Link key={item.title} to={item.link} className="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><span><b className="mr-2 text-[#7D001D]">{item.count}</b>{item.title}</span><FiArrowRight /></Link>)}</div> : <p className="text-sm text-emerald-700">No items need attention.</p>}
    </Section>

    <div className="grid gap-6 xl:grid-cols-2">
      <Section title="Internship Status" subtitle="Current student internship states" icon={FiBarChart2} action={<Link to="/coordinator/students" className="text-xs font-black text-[#7D001D]">View Students</Link>}><div className="h-64"><ResponsiveContainer width="100%" height="100%"><BarChart data={[["Active OJT", data?.internship_status?.active], ["Completed", data?.internship_status?.completed], ["Pending Internship", data?.internship_status?.pending], ["Dropped", data?.internship_status?.dropped]].map(([name, value]) => ({ name, value: value || 0 }))} layout="vertical" margin={{ left: 26 }}><CartesianGrid strokeDasharray="3 3" /><XAxis type="number" allowDecimals={false} /><YAxis type="category" dataKey="name" width={126} tick={{ fontSize: 11 }} /><Tooltip /><Bar dataKey="value" fill="#7D001D" /></BarChart></ResponsiveContainer></div></Section>
      <Section title="Today's Attendance" subtitle="Attendance status for active interns" icon={FiClock} action={<Link to="/coordinator/attendance?date=today" className="text-xs font-black text-[#7D001D]">View Attendance</Link>}><div className="h-64"><ResponsiveContainer width="100%" height="100%"><BarChart data={[["Present", data?.attendance_today?.present], ["Late", data?.attendance_today?.late], ["Absent", data?.attendance_today?.absent], ["No Log", data?.attendance_today?.no_log]].map(([name, value]) => ({ name, value: value || 0 }))} layout="vertical" margin={{ left: 10 }}><CartesianGrid strokeDasharray="3 3" /><XAxis type="number" allowDecimals={false} /><YAxis type="category" dataKey="name" width={65} /><Tooltip /><Bar dataKey="value" fill="#a8750b" /></BarChart></ResponsiveContainer></div></Section>
      <Section title="OJT Progress" subtitle="Active interns by completed hours" icon={FiTrendingUp} action={<Link to="/coordinator/students?internship_status=active" className="text-xs font-black text-[#7D001D]">View Interns</Link>}><div className="h-64"><ResponsiveContainer width="100%" height="100%"><BarChart data={[["0–25%", data?.ojt_progress?.['0_25']], ["26–50%", data?.ojt_progress?.['26_50']], ["51–75%", data?.ojt_progress?.['51_75']], ["76–99%", data?.ojt_progress?.['76_99']], ["100%", data?.ojt_progress?.['100']]].map(([name, value]) => ({ name, value: value || 0 }))} layout="vertical" margin={{ left: 10 }}><CartesianGrid strokeDasharray="3 3" /><XAxis type="number" allowDecimals={false} /><YAxis type="category" dataKey="name" width={70} /><Tooltip /><Bar dataKey="value" fill="#7D001D" /></BarChart></ResponsiveContainer></div></Section>
      <Section title="Internship Requirements" subtitle="Student document completion" icon={FiFileText} action={<Link to="/coordinator/internship-requirements?status=for_review" className="text-xs font-black text-[#7D001D]">Review Requirements</Link>}><Ring value={overview.total_students ? requirementSummary.completed / overview.total_students * 100 : 0} label="complete" /><div className="mt-4 grid grid-cols-3 gap-2 text-center text-sm">{[['Complete', requirementSummary.completed], ['Incomplete', requirementSummary.incomplete], ['For Review', requirementSummary.for_review]].map(([name, value]) => <div key={name} className="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"><b className="block text-xl">{value || 0}</b>{name}</div>)}</div></Section>
    </div>

    <section className="touch-layout-only rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:hidden"><label className="block"><span className="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">Dashboard section</span><select value={mobileView} onChange={(event) => setMobileView(event.target.value)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold dark:border-slate-700 dark:bg-slate-950"><option value="interns">Active intern monitoring</option><option value="performance">Attendance & compliance</option><option value="alerts">Alerts, notices & quick reports</option></select></label></section>

    <Section data-touch-active={mobileView === 'interns'} title="Active Intern Monitoring" subtitle="Progress, placement, attendance, and requirement completion" icon={FiTrendingUp} action={<Link to="/coordinator/students" className="inline-flex items-center gap-1 text-xs font-black text-[#7D001D] dark:text-rose-300">Manage Students <FiArrowRight /></Link>}>
      {data?.active_interns?.length ? <>
        <div className="touch-layout-only grid gap-3 sm:grid-cols-2 lg:hidden">
          {data.active_interns.map((intern) => (
            <article key={intern.id} className="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-gray-700 dark:bg-gray-900/60">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-black text-slate-800 dark:text-white">{intern.name}</p>
                  <p className="mt-0.5 truncate text-xs text-slate-400">{intern.student_id} · {intern.program}</p>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${statusColors[intern.last_attendance_status] || statusColors.no_record}`}>{String(intern.last_attendance_status || 'no record').replace('_', ' ')}</span>
              </div>
              <p className="mt-3 truncate text-xs font-semibold text-slate-600 dark:text-gray-300">{intern.hte || 'HTE not assigned'}</p>
              <div className="mt-3 grid grid-cols-2 gap-3">
                <div>
                  <div className="mb-1.5 flex justify-between gap-2 text-xs"><span className="text-slate-500">OJT</span><span className="font-black text-[#7D001D] dark:text-rose-300">{intern.progress_percent}%</span></div>
                  <Progress value={intern.progress_percent} />
                  <p className="mt-1 text-[10px] text-slate-400">{intern.rendered_hours}/{intern.required_hours}h</p>
                </div>
                <div>
                  <div className="mb-1.5 flex justify-between gap-2 text-xs"><span className="text-slate-500">Requirements</span><span className="font-black text-blue-600">{intern.requirements_percent}%</span></div>
                  <Progress value={intern.requirements_percent} color="bg-blue-500" />
                  <p className="mt-1 truncate text-[10px] text-slate-400">{intern.last_attendance_date || 'No attendance yet'}</p>
                </div>
              </div>
            </article>
          ))}
        </div>
        <div className="hidden overflow-x-auto lg:block"><table className="w-full min-w-[780px] text-left"><thead><tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-extrabold">Student</th><th className="pb-3 font-extrabold">HTE</th><th className="pb-3 font-extrabold">OJT Progress</th><th className="pb-3 font-extrabold">Requirements</th><th className="pb-3 font-extrabold">Latest Attendance</th></tr></thead><tbody>{data.active_interns.map((intern) => <tr key={intern.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700"><td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{intern.name}</p><p className="mt-1 text-xs text-slate-400">{intern.student_id} · {intern.program}</p></td><td className="py-4 text-sm font-semibold text-slate-600 dark:text-gray-300">{intern.hte}</td><td className="w-48 py-4"><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{intern.rendered_hours}/{intern.required_hours}h</span><span className="font-black text-[#7D001D] dark:text-rose-300">{intern.progress_percent}%</span></div><Progress value={intern.progress_percent} /></td><td className="w-36 py-4"><p className="mb-1.5 text-xs font-black text-slate-600 dark:text-gray-300">{intern.requirements_percent}%</p><Progress value={intern.requirements_percent} color="bg-blue-500" /></td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${statusColors[intern.last_attendance_status] || statusColors.no_record}`}>{intern.last_attendance_status.replace('_', ' ')}</span><p className="mt-1.5 text-[11px] text-slate-400">{intern.last_attendance_date || 'No attendance yet'}</p></td></tr>)}</tbody></table></div>
      </> : <div className="py-10 text-center text-sm text-slate-400">No active interns found.</div>}
    </Section>

    <div data-touch-active={mobileView === 'performance'} className="grid gap-6 xl:grid-cols-2">
      <Section title="Attendance Summary" subtitle={`${new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })} attendance records`} icon={FiBarChart2} action={<Link to="/coordinator/attendance" className="text-xs font-black text-[#7D001D] dark:text-rose-300">View Attendance</Link>}>
        <div className="flex flex-col items-center gap-7 sm:flex-row"><Ring value={attendance.rate} label="attendance" /><div className="w-full flex-1"><div className="mb-5 flex items-end justify-between"><div><p className="text-3xl font-black text-[#690016] dark:text-white">{attendance.total_hours || 0}<span className="ml-1 text-sm text-slate-400">hrs</span></p><p className="text-xs text-slate-400">Total rendered this month</p></div><p className="text-sm font-black text-slate-500">{totalAttendance} records</p></div><div className="space-y-3">{attendanceParts.map((part) => <div key={part.label}><div className="mb-1 flex justify-between text-xs"><span className="font-bold text-slate-500">{part.label}</span><span className="font-black text-slate-700 dark:text-gray-200">{part.value}</span></div><Progress value={totalAttendance ? (part.value / totalAttendance) * 100 : 0} color={part.color} /></div>)}</div></div></div>
      </Section>

      <Section title="Journal Submission Statistics" subtitle="AM and PM accomplishment reports for the current month" icon={FiBookOpen}>
        <div className="flex flex-col items-center gap-7 sm:flex-row"><Ring value={journals.completion_rate} label="submitted" /><div className="grid w-full flex-1 grid-cols-2 gap-3">{[
          ['Submitted', journals.submitted, 'text-emerald-600'], ['Expected', journals.expected, 'text-blue-600'], ['Missing', journals.missing, 'text-rose-600'], ['Students', journals.students_with_submissions, 'text-violet-600'],
        ].map(([label, value, color]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className={`text-2xl font-black ${color}`}>{value || 0}</p><p className="mt-1 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p></div>)}</div></div>
      </Section>
    </div>

    <div data-touch-active={mobileView === 'performance'} className="grid gap-6 xl:grid-cols-2">
      <Section title="HTE and MOA Status" subtitle="Partner establishments and agreement validity" icon={FiBriefcase} action={<div className="flex gap-3"><Link to="/coordinator/htes" className="text-xs font-black text-[#7D001D] dark:text-rose-300">Manage HTE</Link><Link to="/coordinator/moas" className="text-xs font-black text-[#7D001D] dark:text-rose-300">Manage MOA</Link></div>}>
        <div className="grid grid-cols-3 gap-3"><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-[#7D001D] dark:text-rose-300">{hteMoa.total_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">Partners</p></div><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-emerald-600">{hteMoa.active_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">Active</p></div><div className="rounded-xl bg-slate-50 p-3 text-center dark:bg-gray-900"><p className="text-2xl font-black text-blue-600">{hteMoa.assigned_htes || 0}</p><p className="text-[10px] font-bold uppercase text-slate-400">With Interns</p></div></div><div className="mt-5 grid grid-cols-4 gap-2 text-center">{[['Approved', hteMoa.moa_approved, 'text-emerald-600'], ['Pending', hteMoa.moa_pending, 'text-amber-600'], ['Rejected', hteMoa.moa_rejected, 'text-rose-600'], ['Expired', hteMoa.moa_expired, 'text-slate-500']].map(([label, value, color]) => <div key={label}><p className={`text-lg font-black ${color}`}>{value || 0}</p><p className="text-[9px] font-bold uppercase text-slate-400">{label} MOA</p></div>)}</div>{hteMoa.expiring_soon?.length > 0 && <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-bold text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">⚠ {hteMoa.expiring_soon.length} MOA(s) expire within 30 days.</div>}
      </Section>
    </div>

    <Section data-touch-active={mobileView === 'performance'} title="Travel Monitoring Summary" subtitle="Student travel sessions for the current month" icon={FiMapPin} action={<Link to={travel.current_session_id ? `/coordinator/travel?session=${travel.current_session_id}` : '/coordinator/travel'} className="text-xs font-black text-[#7D001D] dark:text-rose-300">Open Travel Monitor</Link>}>
      <div className="portal-metric-grid grid gap-4 sm:grid-cols-3"><MetricCard label="Active Now" value={travel.active || 0} note="Ongoing sessions" icon={FiMapPin} tone="maroon" to="/coordinator/travel" /><MetricCard label="Scheduled" value={travel.scheduled || 0} note="Upcoming sessions" icon={FiClock} tone="blue" to="/coordinator/travel" /><MetricCard label="Completed" value={travel.completed_this_month || 0} note="Closed this month" icon={FiCheckCircle} tone="green" to="/coordinator/travel" /></div><p className="mt-3 text-xs text-slate-500">{travel.active ? 'Active travel session in progress.' : `No active travel session. ${travel.scheduled || 0} upcoming sessions scheduled.`}</p>
    </Section>

    <div data-touch-active={mobileView === 'alerts'} className="grid gap-6">
      <Section title="Announcements" subtitle="Latest coordinator announcements" icon={FiBookOpen} action={<Link to="/coordinator/announcements" className="text-xs font-black text-[#7D001D]">View All</Link>}>
        <div className="space-y-4">{data?.announcements?.length ? data.announcements.map((item) => <div key={item.id} className="border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-gray-700"><p className="text-sm font-black text-slate-800 dark:text-white">{item.title}</p><p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">{item.content}</p><p className="mt-2 text-[10px] font-bold text-[#a8750b]">{new Date(item.published_at || item.created_at).toLocaleDateString()}</p></div>) : <p className="py-8 text-center text-sm text-slate-400">No notices available.</p>}</div>
      </Section>
    </div>

    <Section data-touch-active={mobileView === 'alerts'} title="Quick Reports" subtitle="Open frequently used monitoring and reporting views" icon={FiFileText}>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{[
        { label: 'Student Internship Report', note: `${overview.active || 0} active interns`, path: '/coordinator/reports?report=student_internship', icon: FiTrendingUp },
        { label: 'HTE Deployment Report', note: `${hteMoa.assigned_htes || 0} partner sites with interns`, path: '/coordinator/reports?report=hte_deployment', icon: FiBriefcase },
        { label: 'Travel Monitoring Report', note: `${travel.total_this_month || 0} logs this month`, path: '/coordinator/reports?report=travel_monitoring', icon: FiMapPin },
        { label: 'View All Reports', note: 'Open reporting center', path: '/coordinator/reports', icon: FiBarChart2 },
      ].map((report) => <Link key={report.label} to={report.path} className="group flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-[#7D001D]/30 hover:bg-[#7D001D]/[0.02] dark:border-gray-700 dark:hover:bg-gray-900"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-[#7D001D] group-hover:bg-[#7D001D] group-hover:text-white dark:bg-gray-700 dark:text-rose-300"><report.icon /></div><div className="min-w-0 flex-1"><p className="text-sm font-black text-slate-800 dark:text-white">{report.label}</p><p className="mt-0.5 truncate text-[11px] text-slate-400">{report.note}</p></div><FiArrowRight className="text-slate-300 group-hover:text-[#7D001D]" /></Link>)}</div>
    </Section>
  </div>;
}

export default CoordinatorDashboard;
