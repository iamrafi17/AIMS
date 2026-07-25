import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiActivity,
  FiAlertCircle,
  FiArrowRight,
  FiBarChart2,
  FiBriefcase,
  FiCheckCircle,
  FiClipboard,
  FiClock,
  FiFileText,
  FiMapPin,
  FiRefreshCw,
  FiShield,
  FiStar,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyDashboard = {
  overview: {
    total_students: 0,
    active: 0,
    completed: 0,
    pending: 0,
    deployed: 0,
    partner_htes: 0,
    average_progress: 0,
  },
  analytics: {
    average_progress: 0,
    average_rating: 0,
    evaluations_completed: 0,
    internship_status: [],
    program_performance: [],
  },
  attendance: {
    present: 0,
    late: 0,
    absent: 0,
    holiday: 0,
    total: 0,
    rate: 0,
    verified: 0,
    verification_rate: 0,
    total_hours: 0,
    trend: [],
  },
  compliance: {
    overall_score: 0,
    requirements_rate: 0,
    requirements_approved: 0,
    requirements_total: 0,
    attendance_verification_rate: 0,
    moa_coverage_rate: 0,
    valid_moa_students: 0,
    travel_verification_rate: 0,
  },
  travel: {
    active: 0,
    total_this_month: 0,
    completed_this_month: 0,
    cancelled_this_month: 0,
    completion_rate: 0,
    verified_checkpoints: 0,
    checkpoint_total: 0,
  },
  pending_approvals: {
    evaluations: 0,
    attendance: 0,
    requirements: 0,
    registrations: 0,
    travel_checkpoints: 0,
    moas: 0,
    total: 0,
  },
};

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };

  return (
    <article className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-black text-[#430909] dark:text-white">{value}</p>
          <p className="mt-1 text-xs text-slate-400">{note}</p>
        </div>
        <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-xl ${tones[tone]}`}>
          <Icon />
        </div>
      </div>
    </article>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return (
    <section className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300">
            <Icon />
          </div>
          <div>
            <h2 className="font-black text-[#430909] dark:text-white">{title}</h2>
            <p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p>
          </div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function ProgressBar({ value, color = 'bg-[#800000]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return (
    <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700">
      <div className={`h-full rounded-full transition-all duration-500 ${color}`} style={{ width: `${safeValue}%` }} />
    </div>
  );
}

function Ring({ value, label, color = '#800000' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);

  return (
    <div
      className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full"
      style={{ background: `conic-gradient(${color} ${safeValue * 3.6}deg, #e2e8f0 0deg)` }}
    >
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800">
        <div>
          <p className="text-2xl font-black text-[#430909] dark:text-white">{safeValue.toFixed(1)}%</p>
          <p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p>
        </div>
      </div>
    </div>
  );
}

function UniversityInternshipDashboard({
  apiEndpoint = '/supervisor/dashboard',
  audienceLabel = 'University Oversight Center',
  dashboardTitle = 'University Internship Dashboard',
  dashboardSubtitle = 'Monitor internship participation, performance, compliance, attendance, and student travel across the university.',
  executive = false,
  showMoaApprovals = false,
  pageRoutes = {},
}) {
  const routes = {
    analytics: '/supervisor/progress',
    attendance: '/supervisor/attendance',
    evaluations: '/supervisor/evaluations',
    approvals: '/supervisor/progress',
    moas: '/supervisor/progress',
    ...pageRoutes,
  };
  const [dashboard, setDashboard] = useState(emptyDashboard);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadDashboard = async (showSuccess = false) => {
    try {
      const response = await api.get(apiEndpoint);
      setDashboard({
        ...emptyDashboard,
        ...response.data,
        overview: { ...emptyDashboard.overview, ...response.data?.overview },
        analytics: { ...emptyDashboard.analytics, ...response.data?.analytics },
        attendance: { ...emptyDashboard.attendance, ...response.data?.attendance },
        compliance: { ...emptyDashboard.compliance, ...response.data?.compliance },
        travel: { ...emptyDashboard.travel, ...response.data?.travel },
        pending_approvals: { ...emptyDashboard.pending_approvals, ...response.data?.pending_approvals },
      });
      if (showSuccess) toast.success('Dashboard data refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load the supervisor dashboard.');
    }
  };

  useEffect(() => {
    let mounted = true;

    api.get(apiEndpoint)
      .then((response) => {
        if (!mounted) return;
        setDashboard({
          ...emptyDashboard,
          ...response.data,
          overview: { ...emptyDashboard.overview, ...response.data?.overview },
          analytics: { ...emptyDashboard.analytics, ...response.data?.analytics },
          attendance: { ...emptyDashboard.attendance, ...response.data?.attendance },
          compliance: { ...emptyDashboard.compliance, ...response.data?.compliance },
          travel: { ...emptyDashboard.travel, ...response.data?.travel },
          pending_approvals: { ...emptyDashboard.pending_approvals, ...response.data?.pending_approvals },
        });
      })
      .catch((error) => {
        if (mounted) toast.error(error.response?.data?.message || 'Unable to load the supervisor dashboard.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, [apiEndpoint]);

  const refreshDashboard = async () => {
    setRefreshing(true);
    await loadDashboard(true);
    setRefreshing(false);
  };

  if (loading) {
    return (
      <div className="grid min-h-[420px] place-items-center">
        <div className="text-center">
          <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#800000]/15 border-b-[#800000]" />
          <p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading university overview</p>
        </div>
      </div>
    );
  }

  const { overview, analytics, attendance, compliance, travel } = dashboard;
  const approvals = dashboard.pending_approvals;
  const attendanceTotal = attendance.present + attendance.late + attendance.absent + attendance.holiday;
  const statusTotal = analytics.internship_status.reduce((sum, item) => sum + Number(item.value || 0), 0);

  const approvalItems = [
    {
      label: 'Evaluation drafts',
      count: approvals.evaluations,
      note: 'Performance evaluations awaiting submission',
      icon: FiClipboard,
      tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
      path: routes.evaluations,
    },
    {
      label: 'Attendance logs',
      count: approvals.attendance,
      note: 'Current-month records awaiting verification',
      icon: FiClock,
      tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
      path: routes.attendance,
    },
    {
      label: 'Requirements',
      count: approvals.requirements,
      note: 'Submitted documents pending academic review',
      icon: FiFileText,
      tone: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
      path: routes.approvals,
    },
    {
      label: 'Registrations',
      count: approvals.registrations,
      note: 'Student registrations awaiting approval',
      icon: FiUsers,
      tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
      path: routes.approvals,
    },
    {
      label: 'Travel checkpoints',
      count: approvals.travel_checkpoints,
      note: 'Location and photo records awaiting review',
      icon: FiMapPin,
      tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
      path: routes.approvals,
    },
  ];

  if (showMoaApprovals) {
    approvalItems.unshift({
      label: 'MOA approvals',
      count: approvals.moas,
      note: 'Partnership agreements awaiting executive decision',
      icon: FiBriefcase,
      tone: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300',
      path: routes.moas,
    });
  }

  const complianceItems = [
    ['Requirements approved', compliance.requirements_rate, `${compliance.requirements_approved} of ${compliance.requirements_total}`, 'bg-[#800000]'],
    ['Attendance verified', compliance.attendance_verification_rate, 'Current month', 'bg-emerald-500'],
    ['Valid MOA coverage', compliance.moa_coverage_rate, `${compliance.valid_moa_students} deployed students`, 'bg-blue-500'],
    ['Travel checkpoints', compliance.travel_verification_rate, 'Verified records', 'bg-violet-500'],
  ];

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#360707] via-[#650d0d] to-[#8b1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-24 h-32 w-32 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]">
              <FiShield /> {audienceLabel}
            </div>
            <h1 className="text-2xl font-black sm:text-3xl">{dashboardTitle}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-white/65">
              {dashboardSubtitle}
            </p>
          </div>
          <button
            type="button"
            onClick={refreshDashboard}
            disabled={refreshing}
            className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50"
          >
            <FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Data
          </button>
        </div>
      </header>

      <section>
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#a8750b]">University Internship Overview</p>
            <h2 className="mt-1 text-lg font-black text-[#430909] dark:text-white">University-wide internship status</h2>
          </div>
          {executive ? (
            <p className="text-xs font-bold text-slate-400">
              {overview.colleges || 0} colleges · {overview.active_programs || 0} active programs · {overview.approved_moas || 0} approved MOAs
            </p>
          ) : (
            <p className="text-xs font-bold text-slate-400">
              {overview.deployed} deployed across {overview.partner_htes} partner HTEs
            </p>
          )}
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <MetricCard label="Total interns" value={overview.total_students} note="Registered university interns" icon={FiUsers} />
          <MetricCard label="Active interns" value={overview.active} note="Currently completing OJT" icon={FiUserCheck} tone="green" />
          <MetricCard label="Completed" value={overview.completed} note="Internships successfully finished" icon={FiCheckCircle} tone="blue" />
          <MetricCard label="Awaiting deployment" value={overview.pending} note="Pending internship start" icon={FiClock} tone="amber" />
          <MetricCard label="Average progress" value={`${overview.average_progress}%`} note="Rendered against required hours" icon={FiTrendingUp} tone="violet" />
        </div>
      </section>

      <Panel
        title="Internship Analytics"
        subtitle="Internship status, evaluation results, and program-level performance"
        icon={FiBarChart2}
        action={(
          <Link to={routes.analytics} className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">
            View progress <FiArrowRight />
          </Link>
        )}
      >
        <div className="grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
          <div>
            <div className="grid grid-cols-2 gap-3">
              <div className="rounded-2xl bg-[#800000]/5 p-4 dark:bg-rose-950/20">
                <FiTrendingUp className="text-xl text-[#800000] dark:text-rose-300" />
                <p className="mt-3 text-2xl font-black text-[#430909] dark:text-white">{analytics.average_progress}%</p>
                <p className="mt-1 text-[9px] font-black uppercase tracking-wide text-slate-400">Average OJT progress</p>
              </div>
              <div className="rounded-2xl bg-amber-50 p-4 dark:bg-amber-950/20">
                <FiStar className="text-xl text-amber-600" />
                <p className="mt-3 text-2xl font-black text-[#430909] dark:text-white">{analytics.average_rating}<span className="text-xs text-slate-400"> / 5</span></p>
                <p className="mt-1 text-[9px] font-black uppercase tracking-wide text-slate-400">{analytics.evaluations_completed} reviewed evaluations</p>
              </div>
            </div>
            <div className="mt-5 space-y-3">
              {statusTotal ? analytics.internship_status.map((item) => (
                <div key={item.label}>
                  <div className="mb-1 flex justify-between text-xs">
                    <span className="font-bold text-slate-500">{item.label}</span>
                    <span className="font-black text-slate-700 dark:text-gray-200">{item.value}</span>
                  </div>
                  <ProgressBar
                    value={(item.value / statusTotal) * 100}
                    color={item.label === 'Active' ? 'bg-emerald-500' : item.label === 'Completed' ? 'bg-blue-500' : item.label === 'Pending' ? 'bg-amber-500' : 'bg-rose-500'}
                  />
                </div>
              )) : <p className="py-8 text-center text-sm font-bold text-slate-400">No internship status data is available yet.</p>}
            </div>
          </div>

          {analytics.program_performance.length ? (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[650px] text-left">
                <thead>
                  <tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700">
                    <th className="pb-3 font-black">Program</th>
                    <th className="pb-3 font-black">Interns</th>
                    <th className="pb-3 font-black">OJT progress</th>
                    <th className="pb-3 font-black">Attendance</th>
                    <th className="pb-3 text-right font-black">Rating</th>
                  </tr>
                </thead>
                <tbody>
                  {analytics.program_performance.map((item) => (
                    <tr key={item.program} className="border-b border-slate-100 last:border-0 dark:border-gray-700">
                      <td className="py-4 text-sm font-black text-slate-800 dark:text-white">{item.program}</td>
                      <td className="py-4 text-xs font-black text-slate-600 dark:text-gray-300">{item.students}</td>
                      <td className="w-36 py-4">
                        <div className="mb-1 text-right text-[9px] font-black text-[#800000] dark:text-rose-300">{item.progress}%</div>
                        <ProgressBar value={item.progress} />
                      </td>
                      <td className="w-36 py-4">
                        <div className="mb-1 text-right text-[9px] font-black text-emerald-600">{item.attendance_rate}%</div>
                        <ProgressBar value={item.attendance_rate} color="bg-emerald-500" />
                      </td>
                      <td className="py-4 text-right text-xs font-black text-amber-600">{item.average_rating} / 5</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : <div className="grid min-h-52 place-items-center rounded-2xl bg-slate-50 text-sm font-bold text-slate-400 dark:bg-gray-900">Program analytics will appear when student data is available.</div>}
        </div>
      </Panel>

      <div className="grid gap-6 xl:grid-cols-2">
        <Panel
          title="Attendance Summary"
          subtitle={`${new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })} university attendance`}
          icon={FiClock}
          action={(
            <Link to={routes.attendance} className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">
              Open attendance <FiArrowRight />
            </Link>
          )}
        >
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={attendance.rate} label="attendance" color="#059669" />
            <div className="w-full flex-1">
              <div className="mb-5 flex items-end justify-between gap-3">
                <div>
                  <p className="text-3xl font-black text-[#430909] dark:text-white">{attendance.total_hours}<span className="ml-1 text-sm text-slate-400">hrs</span></p>
                  <p className="mt-1 text-xs text-slate-400">Rendered this month</p>
                </div>
                <div className="text-right">
                  <p className="text-lg font-black text-emerald-600">{attendance.verification_rate}%</p>
                  <p className="text-[9px] font-bold uppercase text-slate-400">Verified</p>
                </div>
              </div>
              <div className="space-y-3">
                {[
                  ['Present', attendance.present, 'bg-emerald-500'],
                  ['Late', attendance.late, 'bg-amber-500'],
                  ['Absent', attendance.absent, 'bg-rose-500'],
                  ['Holiday', attendance.holiday, 'bg-violet-500'],
                ].map(([label, value, color]) => (
                  <div key={label}>
                    <div className="mb-1 flex justify-between text-xs">
                      <span className="font-bold text-slate-500">{label}</span>
                      <span className="font-black text-slate-700 dark:text-gray-200">{value}</span>
                    </div>
                    <ProgressBar value={attendanceTotal ? (value / attendanceTotal) * 100 : 0} color={color} />
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="mt-7 flex h-32 items-end justify-between gap-2 border-b border-slate-200 px-1 pb-1 dark:border-gray-700">
            {attendance.trend.map((day) => (
              <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                <span className="text-[8px] font-black text-slate-400">{day.rate}%</span>
                <div
                  className={`w-full max-w-10 rounded-t-lg ${day.rate >= 90 ? 'bg-emerald-500' : day.rate > 0 ? 'bg-[#800000]' : 'bg-slate-200 dark:bg-gray-700'}`}
                  style={{ height: `${Math.max(day.rate, 4)}%` }}
                />
                <span className="text-[8px] font-black text-slate-500">{day.label}</span>
              </div>
            ))}
          </div>
        </Panel>

        <Panel title="Compliance Summary" subtitle="University compliance across documents, attendance, MOAs, and travel" icon={FiShield}>
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={compliance.overall_score} label="overall compliance" />
            <div className="w-full flex-1 space-y-4">
              {complianceItems.map(([label, value, note, color]) => (
                <div key={label}>
                  <div className="mb-1.5 flex items-end justify-between gap-3">
                    <div>
                      <p className="text-xs font-black text-slate-600 dark:text-gray-200">{label}</p>
                      <p className="text-[9px] text-slate-400">{note}</p>
                    </div>
                    <span className="text-xs font-black text-[#800000] dark:text-rose-300">{value}%</span>
                  </div>
                  <ProgressBar value={value} color={color} />
                </div>
              ))}
            </div>
          </div>
        </Panel>
      </div>

      <Panel title="Travel Monitoring Summary" subtitle="Read-only university travel activity and verification status" icon={FiMapPin}>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div className="rounded-2xl bg-gradient-to-br from-[#430909] to-[#800000] p-5 text-white">
            <FiActivity className="text-2xl text-[#f1c54c]" />
            <p className="mt-5 text-3xl font-black">{travel.active}</p>
            <p className="mt-1 text-[10px] font-black uppercase tracking-wider text-white/55">Active sessions</p>
            <p className="mt-3 text-xs leading-5 text-white/60">Students currently travelling under recorded sessions.</p>
          </div>
          <div className="rounded-2xl bg-slate-50 p-5 dark:bg-gray-900">
            <FiBriefcase className="text-2xl text-blue-600" />
            <p className="mt-5 text-3xl font-black text-[#430909] dark:text-white">{travel.total_this_month}</p>
            <p className="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Sessions this month</p>
            <p className="mt-3 text-xs leading-5 text-slate-400">All recorded university travel activity.</p>
          </div>
          <div className="rounded-2xl border border-slate-100 p-5 dark:border-gray-700">
            <FiCheckCircle className="text-2xl text-emerald-600" />
            <p className="mt-5 text-3xl font-black text-emerald-600">{travel.completion_rate}%</p>
            <p className="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Completion rate</p>
            <ProgressBar value={travel.completion_rate} color="mt-4 bg-emerald-500" />
            <p className="mt-3 text-xs text-slate-400">{travel.completed_this_month} completed · {travel.cancelled_this_month} cancelled</p>
          </div>
          <div className="rounded-2xl border border-slate-100 p-5 dark:border-gray-700">
            <FiShield className="text-2xl text-violet-600" />
            <p className="mt-5 text-3xl font-black text-violet-600">{travel.verified_checkpoints}<span className="text-sm text-slate-400"> / {travel.checkpoint_total}</span></p>
            <p className="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Verified checkpoints</p>
            <p className="mt-3 text-xs leading-5 text-slate-400">Location and verification-photo records reviewed.</p>
          </div>
        </div>
      </Panel>

      <Panel
        title="Pending Approvals"
        subtitle="University records that still require verification, review, or completion"
        icon={FiAlertCircle}
        action={<div className="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase text-amber-700 dark:bg-amber-950 dark:text-amber-300">{approvals.total} total</div>}
      >
        <div className={`grid gap-3 sm:grid-cols-2 ${showMoaApprovals ? 'xl:grid-cols-3 2xl:grid-cols-6' : 'xl:grid-cols-5'}`}>
          {approvalItems.map((item) => (
            <Link
              key={item.label}
              to={item.path}
              className="group rounded-2xl border border-slate-100 p-4 transition hover:-translate-y-0.5 hover:border-[#800000]/20 hover:shadow-md dark:border-gray-700"
            >
              <div className="flex items-start justify-between gap-3">
                <div className={`grid h-10 w-10 place-items-center rounded-xl text-lg ${item.tone}`}><item.icon /></div>
                <span className={`text-2xl font-black ${item.count > 0 ? 'text-[#800000] dark:text-rose-300' : 'text-emerald-600'}`}>{item.count}</span>
              </div>
              <p className="mt-4 text-sm font-black text-slate-800 dark:text-white">{item.label}</p>
              <p className="mt-1 min-h-10 text-[11px] leading-5 text-slate-400">{item.note}</p>
              <div className="mt-3 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-[#800000] opacity-70 group-hover:opacity-100 dark:text-rose-300">
                Open queue <FiArrowRight />
              </div>
            </Link>
          ))}
        </div>
      </Panel>
    </div>
  );
}

export default UniversityInternshipDashboard;
