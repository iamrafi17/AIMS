import { useEffect, useState } from 'react';
import {
  FiBarChart2,
  FiBookOpen,
  FiBriefcase,
  FiCalendar,
  FiCheckCircle,
  FiClock,
  FiFileText,
  FiMapPin,
  FiPieChart,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import ReportTemplateControls from '../../components/reports/ReportTemplateControls';
import api from '../../services/api';

const reportModules = [
  { id: 'student_internship', label: 'Student Internship Report', shortLabel: 'Student Internship', icon: FiUsers, description: 'Student information, HTE assignment, attendance summary, requirements, hours, and internship progress' },
  { id: 'hte_deployment', label: 'Host Training Establishment Report', shortLabel: 'HTE Report', icon: FiBriefcase, description: 'Host training establishments, departments, student counts, work schedules, and supervisors' },
  { id: 'travel_monitoring', label: 'Travel Monitoring Report', shortLabel: 'Travel Monitoring', icon: FiMapPin, description: 'Travel participants, route evidence, checkpoints, remarks, and validation status' },
];

const statusStyles = {
  present: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  late: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  absent: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  completed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  delayed: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  scheduled: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
  complete: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  'on-track': 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  attention: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  not_submitted: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
  near_completion: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  Valid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  Expiring: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  Pending: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
};

function StatusBadge({ status }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${statusStyles[status] || statusStyles.pending}`}>{String(status).replace('-', ' ')}</span>;
}

function ProgressBar({ value, color = 'bg-[#7D001D]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };
  return (
    <div className="portal-metric-card rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:border-slate-200 print:shadow-none">
      <div className="flex items-start justify-between gap-3">
        <div><p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#690016] dark:text-white print:text-black">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div>
        <div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl print:hidden ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '', ...sectionProps }) {
  return (
    <section {...sectionProps} className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 print:break-inside-avoid print:rounded-none print:border-0 print:p-0 print:shadow-none ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3 print:mb-3">
        <div className="flex items-start gap-3">
          {Icon && <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-lg text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300 print:hidden"><Icon /></div>}
          <div><h2 className="font-black text-[#690016] dark:text-white print:text-black">{title}</h2>{subtitle && <p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p>}</div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function Donut({ value, label, color = '#7D001D' }) {
  return (
    <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${value * 3.6}deg, #e8e4df 0deg)` }}>
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800 print:bg-white">
        <div><p className="text-2xl font-black text-[#690016] dark:text-white print:text-black">{value}%</p><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p></div>
      </div>
    </div>
  );
}

function CoordinatorReports() {
  const [activeReport, setActiveReport] = useState('student_internship');
  const [mobileView, setMobileView] = useState('analysis');
  const [records, setRecords] = useState({ program: null, sections: [], selected_section: null, attendance: [], journals: [], requirements: [], students: [], deployments: [], travel: [] });

  useEffect(() => {
    let active = true;
    api.get('/coordinator/reports')
      .then((response) => { if (active) setRecords(response.data); })
      .catch(() => { if (active) toast.error('Unable to load coordinator report data.'); });
    return () => { active = false; };
  }, []);

  const activeModule = reportModules.find((module) => module.id === activeReport);
  const filteredAttendance = records.attendance;
  const filteredJournals = records.journals;
  const filteredRequirements = records.requirements || [];
  const filteredStudents = records.students;
  const filteredDeployments = records.deployments;
  const filteredTravel = records.travel;
  const assignedProgram = records.program ? `${records.program.code} · ${records.program.name}` : '';
  const sectionLabel = 'All sections';

  const sum = (items, key) => items.reduce((total, item) => total + (Number(item[key]) || 0), 0);
  const percent = (part, total) => total > 0 ? Math.round((part / total) * 100) : 0;
  const average = (items, key) => items.length > 0 ? Math.round(sum(items, key) / items.length) : 0;
  const attendancePresent = filteredAttendance.filter((row) => row.status === 'present').length;
  const attendanceLate = filteredAttendance.filter((row) => row.status === 'late').length;
  const attendanceAbsent = filteredAttendance.filter((row) => row.status === 'absent').length;
  const attendanceRate = percent(attendancePresent + attendanceLate, filteredAttendance.length);
  const attendanceHours = sum(filteredAttendance, 'hours');
  const unverifiedAttendance = filteredAttendance.filter((row) => !row.verified).length;
  const dailyHours = Object.values(filteredAttendance.reduce((days, row) => {
    const entry = days[row.date] || { date: row.date, hours: 0 };
    entry.hours += Number(row.hours) || 0;
    days[row.date] = entry;
    return days;
  }, {})).sort((a, b) => a.date.localeCompare(b.date)).slice(-7);
  const maximumDailyHours = Math.max(...dailyHours.map((row) => row.hours), 1);

  const journalExpected = sum(filteredJournals, 'expected');
  const journalSubmitted = sum(filteredJournals, 'submitted');
  const journalApproved = sum(filteredJournals, 'approved');
  const journalPending = sum(filteredJournals, 'pending');
  const journalMissing = sum(filteredJournals, 'missing');
  const journalCompletion = percent(journalSubmitted, journalExpected);

  const studentActive = filteredStudents.filter((row) => row.status === 'active').length;
  const studentCompleted = filteredStudents.filter((row) => row.status === 'completed').length;
  const studentPending = filteredStudents.filter((row) => row.status === 'pending').length;
  const studentHoursCompletion = percent(sum(filteredStudents, 'rendered'), sum(filteredStudents, 'required'));
  const studentAttendanceAverage = average(filteredStudents, 'attendance');
  const studentRequirementsAverage = average(filteredStudents, 'requirements');
  const studentJournalsAverage = average(filteredStudents, 'journals');

  const deployedInterns = sum(filteredDeployments, 'deployed');
  const activeInterns = sum(filteredDeployments, 'active');
  const completedInterns = sum(filteredDeployments, 'completed');
  const deploymentRate = percent(activeInterns + completedInterns, deployedInterns);
  const moaAttention = filteredDeployments.filter((row) => ['Expiring', 'Pending', 'Expired', 'Rejected'].includes(row.moa)).length;
  const validMoaRate = percent(filteredDeployments.filter((row) => row.moa === 'Valid').length, filteredDeployments.length);

  const travelCompleted = filteredTravel.filter((row) => row.status === 'completed').length;
  const travelActive = filteredTravel.filter((row) => row.status === 'active').length;
  const travelScheduled = filteredTravel.filter((row) => row.status === 'scheduled').length;
  const travelDelayed = filteredTravel.filter((row) => row.status === 'delayed').length;
  const travelCompletionRate = percent(travelCompleted, filteredTravel.length);
  const travelCompliance = average(filteredTravel, 'compliance');

  const reportPeriod = 'All available records';

  return (
    <div className="coordinator-reports portal-card-tables space-y-6 print:space-y-4">
      <div className="hidden border-b-2 border-[#7D001D] pb-4 print:block">
        <div className="flex items-end justify-between gap-4">
          <div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#7D001D]">Marinduque State University · AIMS</p><h1 className="mt-1 text-2xl font-black text-black">{activeModule.label}</h1><p className="mt-1 text-xs text-slate-500">{activeModule.description}</p></div>
          <div className="text-right text-xs"><p className="font-bold text-black">{reportPeriod}</p><p className="mt-1 text-slate-500">{assignedProgram || 'Assigned academic program'}</p><p className="mt-1 text-slate-500">{sectionLabel}</p></div>
        </div>
      </div>

      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#690016] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8 print:hidden">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiBarChart2 /> Reporting Center</div>
            <h1 className="text-2xl font-black sm:text-3xl">Coordinator Reports</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Generate clear, filtered summaries for internship operations, compliance reviews, and institutional decision-making.</p>
          </div>
          <ReportTemplateControls reportType={activeReport} reportLabel={activeModule.label} />
        </div>
      </header>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden" aria-label="Coordinator report types">
        <div className="portal-mobile-tabs flex min-w-max gap-1">
          {reportModules.map((module) => (
            <button key={module.id} type="button" onClick={() => { setActiveReport(module.id); setMobileView('analysis'); }} className={`inline-flex items-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition sm:px-5 ${activeReport === module.id ? 'bg-[#7D001D] text-white shadow-md shadow-[#7D001D]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#7D001D] dark:text-gray-300 dark:hover:bg-gray-700'}`}>
              <module.icon className="text-base" /><span>{module.shortLabel}</span>
            </button>
          ))}
        </div>
      </nav>

      <div className="flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#a8750b]">{activeModule.shortLabel}</p><h2 className="mt-1 text-xl font-black text-[#690016] dark:text-white">{activeModule.label}</h2><p className="mt-1 text-xs text-slate-400">{activeModule.description} · {sectionLabel} · {reportPeriod}</p></div>
      </div>

      <section className="touch-layout-only rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:hidden print:hidden">
        <label className="block"><span className="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">Report section</span><select value={mobileView} onChange={(event) => setMobileView(event.target.value)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold dark:border-slate-700 dark:bg-slate-950"><option value="analysis">Summary and analysis</option><option value="records">Detailed records</option></select></label>
      </section>

      {activeReport === 'attendance' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Attendance rate" value={`${attendanceRate}%`} note={`${attendancePresent + attendanceLate}/${filteredAttendance.length} present or late`} icon={FiTrendingUp} tone="green" />
            <MetricCard label="Rendered hours" value={attendanceHours.toFixed(2)} note="For the selected records" icon={FiClock} tone="blue" />
            <MetricCard label="Late records" value={attendanceLate} note={`${percent(attendanceLate, filteredAttendance.length)}% of displayed records`} icon={FiCalendar} tone="amber" />
            <MetricCard label="Pending review" value={unverifiedAttendance} note="Unverified attendance logs" icon={FiFileText} />
          </div>

          <div data-touch-active={mobileView === 'analysis'} className="grid gap-6 xl:grid-cols-2">
            <Panel title="Attendance Distribution" subtitle="Record status for the selected reporting period" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row">
                <Donut value={attendanceRate} label="attendance" color="#059669" />
                <div className="w-full flex-1 space-y-4">
                  {[['Present', attendancePresent, 'bg-emerald-500'], ['Late', attendanceLate, 'bg-amber-500'], ['Absent', attendanceAbsent, 'bg-rose-500']].map(([label, count, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count} records</span></div><ProgressBar value={percent(count, filteredAttendance.length)} color={color} /></div>)}
                </div>
              </div>
            </Panel>

            <Panel title="Daily Rendered Hours" subtitle="Consolidated hours for the last seven recorded days" icon={FiBarChart2}>
              <div className="flex h-52 items-end justify-between gap-3 border-b border-slate-200 pb-1 dark:border-gray-700">
                {dailyHours.map((row) => <div key={row.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[9px] font-black text-slate-400">{row.hours.toFixed(2)}h</span><div className="w-full max-w-10 rounded-t-lg bg-gradient-to-t from-[#7D001D] to-[#b82929]" style={{ height: `${(row.hours / maximumDailyHours) * 100}%` }} /><span className="text-[8px] font-bold text-slate-400">{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</span></div>)}
                {dailyHours.length === 0 && <p className="m-auto text-xs font-bold text-slate-400">No attendance hours for the selected period.</p>}
              </div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'records'} title="Attendance Records" subtitle={`${filteredAttendance.length} records shown`} icon={FiClock}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[900px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Date / Student</th><th className="pb-3 font-black">HTE / Mode</th><th className="pb-3 font-black">AM In / Out</th><th className="pb-3 font-black">PM In / Out</th><th className="pb-3 font-black">Hours / OT</th><th className="pb-3 font-black">GPS / Geofence</th><th className="pb-3 font-black">Status</th><th className="pb-3 font-black">Verified</th></tr></thead>
                <tbody>{filteredAttendance.length ? filteredAttendance.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-3"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-PH')} · {row.studentId}</p></td><td className="py-3"><p className="text-xs text-slate-600 dark:text-gray-300">{row.hte}</p><p className="mt-1 text-[9px] font-black text-slate-400">{row.mode} · {String(row.sessionType || '').replaceAll('_', ' ')}</p></td><td className="py-3 text-xs text-slate-500">{row.amTimeIn || '—'} / {row.amTimeOut || '—'}</td><td className="py-3 text-xs text-slate-500">{row.pmTimeIn || '—'} / {row.pmTimeOut || '—'}</td><td className="py-3"><p className="text-xs font-black text-slate-700 dark:text-gray-200">{row.hours}h</p><p className="mt-1 text-[9px] text-slate-400">{row.overtimeHours || 0}h OT</p></td><td className="py-3"><p className="text-[10px] text-slate-500">{row.gps || 'No GPS'}</p><p className="mt-1 text-[9px] font-black uppercase text-slate-400">{String(row.geofenceStatus || '').replaceAll('_', ' ')}</p></td><td className="py-3"><StatusBadge status={row.status} /></td><td className="py-3">{row.verified ? <FiCheckCircle className="text-emerald-500" /> : <FiClock className="text-amber-500" />}</td></tr>) : <tr><td colSpan="8" className="py-16 text-center text-sm font-bold text-slate-400">No attendance records are available.</td></tr>}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'journals' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Expected entries" value={journalExpected} note="AM and PM journals" icon={FiBookOpen} />
            <MetricCard label="Submitted" value={journalSubmitted} note={`${journalCompletion}% submission rate`} icon={FiCheckCircle} tone="green" />
            <MetricCard label="Pending review" value={journalPending} note="Awaiting coordinator review" icon={FiClock} tone="amber" />
            <MetricCard label="Missing entries" value={journalMissing} note={`${filteredJournals.length} student records`} icon={FiFileText} tone="violet" />
          </div>

          <div data-touch-active={mobileView === 'analysis'} className="grid gap-6 xl:grid-cols-[380px_1fr]">
            <Panel title="Submission Completion" subtitle="Overall journal compliance" icon={FiPieChart}>
              <div className="flex justify-center"><Donut value={journalCompletion} label="submitted" /></div>
              <div className="mt-6 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">{journalApproved}</p><p className="text-[8px] font-black uppercase text-slate-400">Approved</p></div>
                <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p className="text-xl font-black text-amber-600">{journalPending}</p><p className="text-[8px] font-black uppercase text-slate-400">Pending</p></div>
                <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">{journalMissing}</p><p className="text-[8px] font-black uppercase text-slate-400">Missing</p></div>
              </div>
            </Panel>

            <Panel title="Journal Completion by Student" subtitle="Submitted entries compared with expected entries" icon={FiTrendingUp}>
              <div className="space-y-5">
                {filteredJournals.map((row) => { const completion = percent(row.submitted, row.expected); return <div key={row.id}><div className="mb-2 flex justify-between text-xs"><span className="font-black text-slate-600 dark:text-gray-300">{row.student}</span><span className="font-bold text-slate-400">{row.submitted}/{row.expected} entries · {completion}%</span></div><ProgressBar value={completion} color={completion >= 90 ? 'bg-emerald-500' : 'bg-amber-500'} /></div>; })}
                {filteredJournals.length === 0 && <p className="py-10 text-center text-xs font-bold text-slate-400">No journal records are available.</p>}
              </div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'records'} title="Student Journal Summary" subtitle={`${filteredJournals.length} student records shown`} icon={FiBookOpen}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[800px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Submitted</th><th className="pb-3 font-black">Approved</th><th className="pb-3 font-black">Pending</th><th className="pb-3 font-black">Missing</th><th className="pb-3 font-black">Completion</th><th className="pb-3 font-black">Latest</th><th className="pb-3 font-black">Standing</th></tr></thead>
                <tbody>{filteredJournals.map((row) => { const completion = Math.round((row.submitted / row.expected) * 100); return <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}</p></td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.submitted}/{row.expected}</td><td className="py-4 text-xs font-black text-emerald-600">{row.approved}</td><td className="py-4 text-xs font-black text-amber-600">{row.pending}</td><td className="py-4 text-xs font-black text-rose-600">{row.missing}</td><td className="w-36 py-4"><div className="mb-1.5 text-right text-[9px] font-black text-slate-500">{completion}%</div><ProgressBar value={completion} color={completion >= 90 ? 'bg-emerald-500' : 'bg-amber-500'} /></td><td className="py-4 text-xs text-slate-500">{row.lastSubmission}</td><td className="py-4"><StatusBadge status={row.status} /></td></tr>; })}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'requirements' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Checklist items" value={filteredRequirements.length} note="For all authorized students" icon={FiFileText} />
            <MetricCard label="Approved" value={filteredRequirements.filter((row) => row.status === 'approved').length} note="Accepted requirement files" icon={FiCheckCircle} tone="green" />
            <MetricCard label="Pending review" value={filteredRequirements.filter((row) => row.status === 'pending').length} note="Submitted files awaiting a decision" icon={FiClock} tone="amber" />
            <MetricCard label="Needs action" value={filteredRequirements.filter((row) => ['not_submitted', 'rejected'].includes(row.status)).length} note="Missing, rejected, or for correction" icon={FiUserCheck} tone="violet" />
          </div>
          <Panel data-touch-active={mobileView === 'records'} title="Student Requirements Status" subtitle={`${filteredRequirements.length} checklist records shown`} icon={FiFileText}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[940px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Requirement</th><th className="pb-3 font-black">Submitted</th><th className="pb-3 font-black">Status</th><th className="pb-3 font-black">Reviewed By</th><th className="pb-3 font-black">Remarks</th></tr></thead>
                <tbody>{filteredRequirements.length ? filteredRequirements.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}-{row.section}</p></td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.requirement}</td><td className="py-4 text-xs text-slate-500">{row.submittedAt ? new Date(row.submittedAt).toLocaleDateString('en-PH') : 'Not submitted'}</td><td className="py-4"><StatusBadge status={row.status} /></td><td className="py-4 text-xs text-slate-500">{row.reviewedBy || '—'}</td><td className="max-w-xs py-4 text-xs text-slate-500">{row.remarks || '—'}</td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-sm font-bold text-slate-400">No requirement records are available.</td></tr>}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'student_internship' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Total interns" value={filteredStudents.length} note="All authorized records" icon={FiUsers} />
            <MetricCard label="Active" value={studentActive} note="Currently on internship" icon={FiUserCheck} tone="green" />
            <MetricCard label="Completed" value={studentCompleted} note="OJT requirements fulfilled" icon={FiCheckCircle} tone="blue" />
            <MetricCard label="Pending deployment" value={studentPending} note="Awaiting placement" icon={FiClock} tone="amber" />
          </div>

          <div data-touch-active={mobileView === 'analysis'} className="grid gap-6 xl:grid-cols-2">
            <Panel title="Internship Status" subtitle="Student distribution by current OJT stage" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row"><Donut value={percent(studentActive, filteredStudents.length)} label="active" /><div className="w-full flex-1 space-y-4">{[['Active', studentActive, 'bg-emerald-500'], ['Completed', studentCompleted, 'bg-blue-500'], ['Pending', studentPending, 'bg-amber-500']].map(([label, count, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count}</span></div><ProgressBar value={percent(count, filteredStudents.length)} color={color} /></div>)}</div></div>
            </Panel>
            <Panel title="Average Student Readiness" subtitle="Progress across core internship indicators" icon={FiTrendingUp}>
              <div className="space-y-5">{[['OJT hours completion', studentHoursCompletion, 'bg-[#7D001D]'], ['Attendance compliance', studentAttendanceAverage, 'bg-emerald-500'], ['Requirement completion', studentRequirementsAverage, 'bg-blue-500'], ['Journal completion', studentJournalsAverage, 'bg-amber-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value}%</span></div><ProgressBar value={value} color={color} /></div>)}</div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'records'} title="Student Internship Report" subtitle={`${filteredStudents.length} student records shown`} icon={FiUsers}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1000px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Student Number</th><th className="pb-3 font-black">Student Name</th><th className="pb-3 font-black">Academic Year</th><th className="pb-3 font-black">Semester</th><th className="pb-3 font-black">HTE</th><th className="pb-3 font-black">Working Schedule</th><th className="pb-3 font-black">Supervisor</th></tr></thead>
                <tbody>{filteredStudents.length ? filteredStudents.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-slate-800 dark:text-white print:text-black">{row.studentId}</td><td className="py-4 text-xs font-bold text-slate-700 dark:text-gray-200">{row.name}</td><td className="py-4 text-xs text-slate-500">{row.academicYear || 'Not set'}</td><td className="py-4 text-xs text-slate-500">{row.semester || 'Not set'}</td><td className="max-w-48 py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.hte}</td><td className="max-w-64 py-4 text-xs leading-5 text-slate-500">{row.workingSchedule}</td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.supervisor}</td></tr>) : <tr><td colSpan="7" className="py-16 text-center text-sm font-bold text-slate-400">No student internship records are available.</td></tr>}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'hte_deployment' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Partner HTEs" value={filteredDeployments.length} note="All authorized records" icon={FiBriefcase} />
            <MetricCard label="Deployed interns" value={deployedInterns} note="Across listed partner HTEs" icon={FiUsers} tone="green" />
            <MetricCard label="Active interns" value={activeInterns} note={`${completedInterns} completed internship`} icon={FiTrendingUp} tone="blue" />
            <MetricCard label="MOA attention" value={moaAttention} note="Expiring, pending, expired, or rejected" icon={FiFileText} tone="amber" />
          </div>

          <div data-touch-active={mobileView === 'analysis'} className="grid gap-6 xl:grid-cols-[1fr_360px]">
            <Panel title="HTE Assignment Distribution" subtitle="Share of deployed interns by partner establishment" icon={FiBarChart2}>
              <div className="space-y-5">{filteredDeployments.map((row) => { const share = percent(row.deployed, deployedInterns); return <div key={row.id}><div className="mb-2 flex flex-wrap justify-between gap-2"><div><p className="text-xs font-black text-slate-700 dark:text-white print:text-black">{row.hte}</p><p className="mt-0.5 text-[9px] text-slate-400">{row.location} · {row.sector}</p></div><p className="text-xs font-black text-slate-600 dark:text-gray-300">{row.deployed} interns · {share}%</p></div><ProgressBar value={share} color="bg-[#7D001D]" /></div>; })}</div>
            </Panel>
            <Panel title="Deployment Health" subtitle="Current placement indicators" icon={FiUserCheck}>
              <div className="flex justify-center"><Donut value={deploymentRate} label="deployed" color="#059669" /></div>
              <div className="mt-6 space-y-3 text-xs"><div className="flex justify-between"><span className="text-slate-400">Students needing placement</span><span className="font-black text-amber-600">{studentPending}</span></div><div className="flex justify-between"><span className="text-slate-400">Active interns</span><span className="font-black text-slate-700 dark:text-gray-200">{activeInterns}</span></div><div className="flex justify-between"><span className="text-slate-400">Valid MOA coverage</span><span className="font-black text-emerald-600">{validMoaRate}%</span></div><div className="flex justify-between"><span className="text-slate-400">Completed internships</span><span className="font-black text-blue-600">{completedInterns}</span></div></div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'records'} title="Host Training Establishment Report" subtitle={`${filteredDeployments.length} partner establishments shown`} icon={FiBriefcase}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[940px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">HTE</th><th className="pb-3 font-black">HTE Address</th><th className="pb-3 font-black">Department</th><th className="pb-3 font-black">Student Count</th><th className="pb-3 font-black">Working Schedule</th><th className="pb-3 font-black">Supervisor</th></tr></thead>
                <tbody>{filteredDeployments.length ? filteredDeployments.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-slate-800 dark:text-white print:text-black">{row.hte}</td><td className="max-w-56 py-4 text-xs leading-5 text-slate-500">{row.location || 'Not set'}</td><td className="max-w-48 py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.departments?.join(', ') || 'Not assigned'}</td><td className="py-4 text-center text-xs font-black text-slate-700 dark:text-gray-200">{row.deployed}</td><td className="max-w-64 py-4 text-xs leading-5 text-slate-500">{row.workingSchedules?.join(', ') || 'Not configured'}</td><td className="max-w-48 py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.supervisors?.join(', ') || 'Not assigned'}</td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-sm font-bold text-slate-400">No host training establishment records are available.</td></tr>}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'travel_monitoring' && (
        <div className="space-y-6">
          <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Travel sessions" value={filteredTravel.length} note="For the selected period" icon={FiMapPin} />
            <MetricCard label="Completed" value={travelCompleted} note={`${travelCompletionRate}% of displayed sessions`} icon={FiCheckCircle} tone="green" />
            <MetricCard label="Active sessions" value={travelActive} note={`${travelScheduled} scheduled`} icon={FiClock} tone="blue" />
            <MetricCard label="Route exceptions" value={travelDelayed} note="Sessions marked delayed" icon={FiFileText} tone="amber" />
          </div>

          <div data-touch-active={mobileView === 'analysis'} className="grid gap-6 xl:grid-cols-2">
            <Panel title="Travel Status Breakdown" subtitle="Session outcomes for the selected period" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row"><Donut value={travelCompletionRate} label="completed" color="#059669" /><div className="w-full flex-1 space-y-4">{[['Completed', travelCompleted, 'bg-emerald-500'], ['Active', travelActive, 'bg-blue-500'], ['Scheduled', travelScheduled, 'bg-violet-500'], ['Delayed', travelDelayed, 'bg-rose-500']].map(([label, count, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count}</span></div><ProgressBar value={percent(count, filteredTravel.length)} color={color} /></div>)}</div></div>
            </Panel>
            <Panel title="Safety & Compliance" subtitle="Values available from recorded travel sessions" icon={FiTrendingUp}>
              <div className="space-y-5">{[['Session compliance', travelCompliance, 'bg-emerald-500'], ['Completed sessions', travelCompletionRate, 'bg-blue-500'], ['Active or scheduled sessions', percent(travelActive + travelScheduled, filteredTravel.length), 'bg-[#7D001D]'], ['Delayed sessions', percent(travelDelayed, filteredTravel.length), 'bg-amber-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value}%</span></div><ProgressBar value={value} color={color} /></div>)}</div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'records'} title="Travel Session Records" subtitle={`${filteredTravel.length} session records shown`} icon={FiMapPin}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[860px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Session</th><th className="pb-3 font-black">Traveler</th><th className="pb-3 font-black">Destination</th><th className="pb-3 font-black">Date</th><th className="pb-3 font-black">Checkpoints</th><th className="pb-3 font-black">Duration</th><th className="pb-3 font-black">Compliance</th><th className="pb-3 font-black">Status</th></tr></thead>
                <tbody>{filteredTravel.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-[#7D001D] dark:text-rose-300">{row.id}</td><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}</p></td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.destination}</td><td className="py-4 text-xs text-slate-500">{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.checkpoints}</td><td className="py-4 text-xs text-slate-500">{row.duration}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.compliance}%</td><td className="py-4"><StatusBadge status={row.status} /></td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      <footer className="hidden border-t border-slate-300 pt-3 text-[9px] text-slate-500 print:flex print:items-center print:justify-between">
        <span>Generated from the AIMS Coordinator Reporting Center</span>
        <span>Generated {new Date().toLocaleString('en-PH')}</span>
      </footer>
    </div>
  );
}

export default CoordinatorReports;
