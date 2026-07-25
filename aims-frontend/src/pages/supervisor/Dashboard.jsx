import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiAlertTriangle,
  FiArrowRight,
  FiAward,
  FiBarChart2,
  FiCheckCircle,
  FiClipboard,
  FiClock,
  FiRefreshCw,
  FiSearch,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyDashboard = {
  overview: {
    assigned: 0,
    active: 0,
    completed: 0,
    pending_evaluations: 0,
    average_progress: 0,
  },
  assigned_interns: [],
  attendance: {
    present: 0,
    late: 0,
    absent: 0,
    holiday: 0,
    total: 0,
    rate: 0,
    verified: 0,
    total_hours: 0,
    trend: [],
  },
  progress: {
    average: 0,
    on_track: 0,
    progressing: 0,
    needs_attention: 0,
    total_rendered_hours: 0,
    total_required_hours: 0,
  },
  pending_evaluations: {
    total: 0,
    midterm: 0,
    final: 0,
    items: [],
  },
  completion: {
    completed: 0,
    near_completion: 0,
    in_progress: 0,
    not_started: 0,
    dropped: 0,
    completion_rate: 0,
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
      className="grid h-36 w-36 shrink-0 place-items-center rounded-full"
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

function statusStyle(status) {
  const styles = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    completed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    dropped: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  };

  return styles[status] || 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300';
}

function SupervisorDashboard() {
  const [dashboard, setDashboard] = useState(emptyDashboard);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [query, setQuery] = useState('');

  const setResponse = (data) => {
    setDashboard({
      ...emptyDashboard,
      ...data,
      overview: { ...emptyDashboard.overview, ...data?.overview },
      attendance: { ...emptyDashboard.attendance, ...data?.attendance },
      progress: { ...emptyDashboard.progress, ...data?.progress },
      pending_evaluations: { ...emptyDashboard.pending_evaluations, ...data?.pending_evaluations },
      completion: { ...emptyDashboard.completion, ...data?.completion },
      assigned_interns: data?.assigned_interns || [],
    });
  };

  const loadDashboard = async (showSuccess = false) => {
    try {
      const response = await api.get('/supervisor/dashboard');
      setResponse(response.data);
      if (showSuccess) toast.success('Supervisor dashboard refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load the supervisor dashboard.');
    }
  };

  useEffect(() => {
    let mounted = true;

    api.get('/supervisor/dashboard')
      .then((response) => {
        if (mounted) setResponse(response.data);
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
  }, []);

  const refreshDashboard = async () => {
    setRefreshing(true);
    await loadDashboard(true);
    setRefreshing(false);
  };

  const filteredInterns = useMemo(() => {
    const search = query.trim().toLowerCase();
    if (!search) return dashboard.assigned_interns;

    return dashboard.assigned_interns.filter((intern) => (
      intern.name?.toLowerCase().includes(search)
      || intern.student_id?.toLowerCase().includes(search)
      || intern.program?.toLowerCase().includes(search)
      || intern.hte?.toLowerCase().includes(search)
    ));
  }, [dashboard.assigned_interns, query]);

  if (loading) {
    return (
      <div className="grid min-h-[420px] place-items-center">
        <div className="text-center">
          <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#800000]/15 border-b-[#800000]" />
          <p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading assigned interns</p>
        </div>
      </div>
    );
  }

  const { overview, attendance, progress, completion } = dashboard;
  const evaluations = dashboard.pending_evaluations;
  const attendanceTotal = attendance.present + attendance.late + attendance.absent + attendance.holiday;
  const completionTotal = completion.completed + completion.in_progress + completion.not_started + completion.dropped;

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#360707] via-[#650d0d] to-[#8b1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-24 h-32 w-32 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]">
              <FiUserCheck /> HTE Supervisor Workspace
            </div>
            <h1 className="text-2xl font-black sm:text-3xl">Intern Supervision Dashboard</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-white/65">
              Monitor your assigned interns, attendance, OJT progress, evaluation responsibilities, and completion status.
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
        <div className="mb-4">
          <p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#a8750b]">Supervisor Overview</p>
          <h2 className="mt-1 text-lg font-black text-[#430909] dark:text-white">Your internship supervision workload</h2>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <MetricCard label="Assigned interns" value={overview.assigned} note="Students under your supervision" icon={FiUsers} />
          <MetricCard label="Active interns" value={overview.active} note="Currently completing OJT" icon={FiUserCheck} tone="green" />
          <MetricCard label="Pending evaluations" value={overview.pending_evaluations} note="Midterm or final actions" icon={FiClipboard} tone="amber" />
          <MetricCard label="Average progress" value={`${overview.average_progress}%`} note="Rendered against required hours" icon={FiTrendingUp} tone="violet" />
          <MetricCard label="Completed" value={overview.completed} note="Internships successfully finished" icon={FiAward} tone="blue" />
        </div>
      </section>

      <Panel
        title="Assigned Interns"
        subtitle="Interns linked to your evaluation assignments"
        icon={FiUsers}
        action={(
          <div className="relative">
            <FiSearch className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search interns"
              className="w-56 rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-xs font-bold text-slate-700 outline-none transition focus:border-[#800000]/40 focus:ring-4 focus:ring-[#800000]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            />
          </div>
        )}
      >
        {filteredInterns.length ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-left">
              <thead>
                <tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700">
                  <th className="pb-3 font-black">Intern</th>
                  <th className="pb-3 font-black">Deployment</th>
                  <th className="pb-3 font-black">Progress</th>
                  <th className="pb-3 font-black">Attendance</th>
                  <th className="pb-3 font-black">Evaluations</th>
                  <th className="pb-3 text-right font-black">Status</th>
                </tr>
              </thead>
              <tbody>
                {filteredInterns.map((intern) => (
                  <tr key={intern.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700">
                    <td className="py-4">
                      <div className="flex items-center gap-3">
                        {intern.avatar_url ? (
                          <img src={intern.avatar_url} alt="" className="h-10 w-10 rounded-xl object-cover" />
                        ) : (
                          <div className="grid h-10 w-10 rounded-xl bg-[#800000] text-xs font-black text-white">
                            <span className="m-auto">{intern.name?.split(' ').map((part) => part[0]).slice(0, 2).join('')}</span>
                          </div>
                        )}
                        <div>
                          <p className="text-sm font-black text-slate-800 dark:text-white">{intern.name}</p>
                          <p className="mt-0.5 text-[10px] font-bold text-slate-400">{intern.student_id} · {intern.program}</p>
                        </div>
                      </div>
                    </td>
                    <td className="max-w-44 py-4 text-xs font-bold text-slate-500">{intern.hte}</td>
                    <td className="w-40 py-4">
                      <div className="mb-1.5 flex justify-between text-[9px] font-black">
                        <span className="text-slate-400">{intern.rendered_hours} / {intern.required_hours} hrs</span>
                        <span className="text-[#800000] dark:text-rose-300">{intern.progress}%</span>
                      </div>
                      <ProgressBar value={intern.progress} />
                    </td>
                    <td className="py-4 text-xs font-black text-emerald-600">{intern.attendance_rate}%</td>
                    <td className="py-4">
                      <div className="flex gap-1.5">
                        {[
                          ['Mid', intern.midterm_status],
                          ['Final', intern.final_status],
                        ].map(([label, status]) => (
                          <span key={label} title={`${label}: ${status}`} className={`rounded-lg px-2 py-1 text-[8px] font-black uppercase ${['submitted', 'finalized'].includes(status) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'}`}>
                            {label}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="py-4 text-right">
                      <span className={`rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${statusStyle(intern.internship_status)}`}>
                        {intern.internship_status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="py-14 text-center">
            <FiUsers className="mx-auto text-4xl text-slate-200 dark:text-gray-600" />
            <p className="mt-3 text-sm font-black text-slate-500">No assigned interns found</p>
            <p className="mt-1 text-xs text-slate-400">{query ? 'Try a different search.' : 'Interns appear after an evaluation is assigned to you.'}</p>
          </div>
        )}
      </Panel>

      <div className="grid gap-6 xl:grid-cols-2">
        <Panel
          title="Attendance Summary"
          subtitle={`${new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })} attendance for assigned interns`}
          icon={FiClock}
          action={<Link to="/supervisor/attendance" className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">View attendance <FiArrowRight /></Link>}
        >
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={attendance.rate} label="attendance" color="#059669" />
            <div className="w-full flex-1">
              <div className="mb-5 flex items-end justify-between">
                <div>
                  <p className="text-3xl font-black text-[#430909] dark:text-white">{attendance.total_hours}<span className="ml-1 text-sm text-slate-400">hrs</span></p>
                  <p className="mt-1 text-xs text-slate-400">Rendered this month</p>
                </div>
                <div className="text-right">
                  <p className="text-xl font-black text-emerald-600">{attendance.verified}</p>
                  <p className="text-[9px] font-black uppercase text-slate-400">Verified logs</p>
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
                    <div className="mb-1 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200">{value}</span></div>
                    <ProgressBar value={attendanceTotal ? (value / attendanceTotal) * 100 : 0} color={color} />
                  </div>
                ))}
              </div>
            </div>
          </div>
          <div className="mt-7 flex h-28 items-end justify-between gap-2 border-b border-slate-200 px-1 pb-1 dark:border-gray-700">
            {attendance.trend.map((day) => (
              <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                <span className="text-[8px] font-black text-slate-400">{day.rate}%</span>
                <div className={`w-full max-w-10 rounded-t-lg ${day.rate >= 90 ? 'bg-emerald-500' : day.rate > 0 ? 'bg-[#800000]' : 'bg-slate-200 dark:bg-gray-700'}`} style={{ height: `${Math.max(day.rate, 4)}%` }} />
                <span className="text-[8px] font-black text-slate-500">{day.label}</span>
              </div>
            ))}
          </div>
        </Panel>

        <Panel
          title="Progress Overview"
          subtitle="OJT hours rendered by your assigned interns"
          icon={FiBarChart2}
          action={<Link to="/supervisor/progress" className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">Open progress <FiArrowRight /></Link>}
        >
          <div className="flex flex-col items-center gap-7 sm:flex-row">
            <Ring value={progress.average} label="average progress" />
            <div className="w-full flex-1">
              <p className="text-3xl font-black text-[#430909] dark:text-white">{progress.total_rendered_hours}<span className="ml-1 text-sm text-slate-400">of {progress.total_required_hours} hrs</span></p>
              <p className="mt-1 text-xs text-slate-400">Combined supervised internship hours</p>
              <div className="mt-5 grid grid-cols-3 gap-2">
                <div className="rounded-xl bg-emerald-50 p-3 text-center dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">{progress.on_track}</p><p className="mt-1 text-[8px] font-black uppercase text-slate-400">On track</p></div>
                <div className="rounded-xl bg-blue-50 p-3 text-center dark:bg-blue-950/30"><p className="text-xl font-black text-blue-600">{progress.progressing}</p><p className="mt-1 text-[8px] font-black uppercase text-slate-400">Progressing</p></div>
                <div className="rounded-xl bg-rose-50 p-3 text-center dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">{progress.needs_attention}</p><p className="mt-1 text-[8px] font-black uppercase text-slate-400">Needs attention</p></div>
              </div>
            </div>
          </div>
        </Panel>
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <Panel
          title="Pending Evaluations"
          subtitle="Midterm and final evaluations requiring your action"
          icon={FiClipboard}
          action={(
            <Link to="/supervisor/evaluations" className="inline-flex items-center gap-1 text-xs font-black text-[#800000] dark:text-rose-300">
              Evaluation workspace <FiArrowRight />
            </Link>
          )}
        >
          <div className="mb-4 grid grid-cols-3 gap-3">
            <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p className="text-xl font-black text-amber-600">{evaluations.total}</p><p className="text-[9px] font-black uppercase text-slate-400">Total pending</p></div>
            <div className="rounded-xl bg-violet-50 p-3 dark:bg-violet-950/30"><p className="text-xl font-black text-violet-600">{evaluations.midterm}</p><p className="text-[9px] font-black uppercase text-slate-400">Midterm</p></div>
            <div className="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/30"><p className="text-xl font-black text-blue-600">{evaluations.final}</p><p className="text-[9px] font-black uppercase text-slate-400">Final</p></div>
          </div>
          {evaluations.items.length ? (
            <div className="space-y-2">
              {evaluations.items.slice(0, 6).map((item) => (
                <Link key={`${item.student_id}-${item.evaluation_type}`} to="/supervisor/evaluations" className="group flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-[#800000]/20 hover:bg-[#800000]/[0.02] dark:border-gray-700">
                  <div className="flex min-w-0 items-center gap-3">
                    <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"><FiAlertTriangle /></div>
                    <div className="min-w-0">
                      <p className="truncate text-xs font-black text-slate-800 dark:text-white">{item.student_name}</p>
                      <p className="mt-0.5 text-[9px] font-bold text-slate-400">{item.student_number} · {item.program} · {item.progress}% complete</p>
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <span className="rounded-lg bg-slate-100 px-2 py-1 text-[8px] font-black uppercase text-slate-600 dark:bg-gray-700 dark:text-gray-200">{item.evaluation_type}</span>
                    <FiArrowRight className="text-slate-300 transition group-hover:text-[#800000]" />
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <div className="py-10 text-center">
              <FiCheckCircle className="mx-auto text-4xl text-emerald-400" />
              <p className="mt-3 text-sm font-black text-slate-600 dark:text-gray-200">All evaluations are up to date</p>
            </div>
          )}
        </Panel>

        <Panel title="Completion Status" subtitle="Internship completion across assigned interns" icon={FiAward}>
          <div className="flex flex-col items-center gap-6 sm:flex-row xl:flex-col 2xl:flex-row">
            <Ring value={completion.completion_rate} label="completed" color="#2563eb" />
            <div className="w-full flex-1 space-y-3">
              {[
                ['Completed', completion.completed, 'bg-blue-500'],
                ['Near completion', completion.near_completion, 'bg-emerald-500'],
                ['In progress', completion.in_progress, 'bg-violet-500'],
                ['Not started', completion.not_started, 'bg-amber-500'],
                ['Dropped', completion.dropped, 'bg-rose-500'],
              ].map(([label, value, color]) => (
                <div key={label}>
                  <div className="mb-1 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200">{value}</span></div>
                  <ProgressBar value={completionTotal ? (value / completionTotal) * 100 : 0} color={color} />
                </div>
              ))}
            </div>
          </div>
        </Panel>
      </div>
    </div>
  );
}

export default SupervisorDashboard;
