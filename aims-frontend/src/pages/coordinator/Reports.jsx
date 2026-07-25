import { useState } from 'react';
import {
  FiBarChart2,
  FiBookOpen,
  FiBriefcase,
  FiCalendar,
  FiCheckCircle,
  FiClock,
  FiDownload,
  FiFileText,
  FiFilter,
  FiMapPin,
  FiPieChart,
  FiPrinter,
  FiSearch,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';

const reportModules = [
  { id: 'attendance', label: 'Attendance Reports', shortLabel: 'Attendance', icon: FiClock, description: 'Daily attendance, rendered hours, and work arrangements' },
  { id: 'journals', label: 'Journal Reports', shortLabel: 'Journals', icon: FiBookOpen, description: 'Submission completion, reviews, and daily accomplishment reports' },
  { id: 'students', label: 'Student Reports', shortLabel: 'Students', icon: FiUsers, description: 'Intern progress, requirements, and placement status' },
  { id: 'deployments', label: 'HTE Deployment Reports', shortLabel: 'HTE Deployment', icon: FiBriefcase, description: 'Partner deployment, capacity, and MOA coverage' },
  { id: 'travel', label: 'Travel Reports', shortLabel: 'Travel', icon: FiMapPin, description: 'Travel sessions, route completion, and safety compliance' },
];

const attendanceRows = [
  { id: 1, date: '2026-07-25', student: 'Alyssa Reyes', studentId: '2022-10482', program: 'BSIT', hte: 'Marinduque Tech Solutions', mode: 'WFO', timeIn: '7:52 AM', timeOut: '5:06 PM', hours: 8.1, status: 'present', verified: true },
  { id: 2, date: '2026-07-25', student: 'Marco Villanueva', studentId: '2022-11806', program: 'BSBA', hte: 'Provincial Capitol', mode: 'Field', timeIn: '8:14 AM', timeOut: '5:02 PM', hours: 7.7, status: 'late', verified: true },
  { id: 3, date: '2026-07-25', student: 'Sofia Mendoza', studentId: '2022-10931', program: 'BSHM', hte: 'Bellavista Hotel', mode: 'WFO', timeIn: '7:45 AM', timeOut: '4:58 PM', hours: 8.2, status: 'present', verified: true },
  { id: 4, date: '2026-07-25', student: 'Daniel Cruz', studentId: '2021-09218', program: 'BSIS', hte: 'DICT Marinduque', mode: 'WFH', timeIn: '8:00 AM', timeOut: '5:00 PM', hours: 8, status: 'present', verified: false },
  { id: 5, date: '2026-07-24', student: 'Lea Dela Cruz', studentId: '2022-11342', program: 'BSIT', hte: 'LGU Boac MIS', mode: 'WFO', timeIn: '—', timeOut: '—', hours: 0, status: 'absent', verified: true },
  { id: 6, date: '2026-07-24', student: 'Kenneth Flores', studentId: '2022-10155', program: 'BSBA', hte: 'First Consolidated Bank', mode: 'WFO', timeIn: '8:09 AM', timeOut: '5:12 PM', hours: 8, status: 'late', verified: false },
];

const journalRows = [
  { id: 1, student: 'Alyssa Reyes', studentId: '2022-10482', program: 'BSIT', submitted: 21, expected: 22, approved: 20, pending: 1, missing: 1, lastSubmission: 'Jul 25, 2026', status: 'on-track' },
  { id: 2, student: 'Marco Villanueva', studentId: '2022-11806', program: 'BSBA', submitted: 18, expected: 22, approved: 16, pending: 2, missing: 4, lastSubmission: 'Jul 24, 2026', status: 'attention' },
  { id: 3, student: 'Sofia Mendoza', studentId: '2022-10931', program: 'BSHM', submitted: 22, expected: 22, approved: 22, pending: 0, missing: 0, lastSubmission: 'Jul 25, 2026', status: 'complete' },
  { id: 4, student: 'Daniel Cruz', studentId: '2021-09218', program: 'BSIS', submitted: 20, expected: 22, approved: 18, pending: 2, missing: 2, lastSubmission: 'Jul 25, 2026', status: 'on-track' },
  { id: 5, student: 'Lea Dela Cruz', studentId: '2022-11342', program: 'BSIT', submitted: 15, expected: 22, approved: 14, pending: 1, missing: 7, lastSubmission: 'Jul 22, 2026', status: 'attention' },
];

const studentRows = [
  { id: 1, name: 'Alyssa Reyes', studentId: '2022-10482', program: 'BSIT', section: '4A', hte: 'Marinduque Tech Solutions', rendered: 386, required: 486, attendance: 96, requirements: 100, journals: 95, status: 'active' },
  { id: 2, name: 'Marco Villanueva', studentId: '2022-11806', program: 'BSBA', section: '4B', hte: 'Provincial Capitol', rendered: 318, required: 486, attendance: 88, requirements: 83, journals: 82, status: 'active' },
  { id: 3, name: 'Sofia Mendoza', studentId: '2022-10931', program: 'BSHM', section: '4A', hte: 'Bellavista Hotel', rendered: 462, required: 486, attendance: 98, requirements: 100, journals: 100, status: 'active' },
  { id: 4, name: 'Daniel Cruz', studentId: '2021-09218', program: 'BSIS', section: '4A', hte: 'DICT Marinduque', rendered: 486, required: 486, attendance: 97, requirements: 100, journals: 96, status: 'completed' },
  { id: 5, name: 'Lea Dela Cruz', studentId: '2022-11342', program: 'BSIT', section: '4B', hte: 'LGU Boac MIS', rendered: 247, required: 486, attendance: 79, requirements: 75, journals: 68, status: 'active' },
  { id: 6, name: 'Kenneth Flores', studentId: '2022-10155', program: 'BSBA', section: '4A', hte: 'First Consolidated Bank', rendered: 0, required: 486, attendance: 0, requirements: 67, journals: 0, status: 'pending' },
];

const deploymentRows = [
  { id: 1, hte: 'Marinduque Tech Solutions', sector: 'Information Technology', location: 'Boac', deployed: 14, capacity: 18, active: 13, completed: 1, programs: ['BSIT', 'BSIS'], moa: 'Valid', moaExpiry: 'Dec 18, 2027', performance: 96 },
  { id: 2, hte: 'Provincial Capitol', sector: 'Government', location: 'Boac', deployed: 22, capacity: 25, active: 20, completed: 2, programs: ['BSBA', 'BPA'], moa: 'Valid', moaExpiry: 'Mar 30, 2027', performance: 91 },
  { id: 3, hte: 'Bellavista Hotel', sector: 'Hospitality', location: 'Gasan', deployed: 11, capacity: 15, active: 11, completed: 0, programs: ['BSHM'], moa: 'Expiring', moaExpiry: 'Aug 28, 2026', performance: 94 },
  { id: 4, hte: 'DICT Marinduque', sector: 'Government / ICT', location: 'Boac', deployed: 8, capacity: 10, active: 6, completed: 2, programs: ['BSIT', 'BSIS'], moa: 'Valid', moaExpiry: 'Jan 15, 2028', performance: 98 },
  { id: 5, hte: 'First Consolidated Bank', sector: 'Banking', location: 'Boac', deployed: 7, capacity: 12, active: 7, completed: 0, programs: ['BSBA'], moa: 'Pending', moaExpiry: 'Under review', performance: 87 },
];

const travelRows = [
  { id: 'TRV-260725-014', student: 'Alyssa Reyes', studentId: '2022-10482', program: 'BSIT', destination: 'Marinduque Provincial Capitol', date: '2026-07-25', companions: 2, checkpoints: '3/5', duration: '1h 12m', status: 'active', compliance: 92 },
  { id: 'TRV-260725-011', student: 'Marco Villanueva', studentId: '2022-11806', program: 'BSBA', destination: 'Torrijos Municipal Hall', date: '2026-07-25', companions: 1, checkpoints: '2/6', duration: '2h 04m', status: 'delayed', compliance: 78 },
  { id: 'TRV-260725-018', student: 'Sofia Mendoza', studentId: '2022-10931', program: 'BSHM', destination: 'Bellavista Hotel', date: '2026-07-25', companions: 3, checkpoints: '0/4', duration: '—', status: 'scheduled', compliance: 100 },
  { id: 'TRV-260724-027', student: 'Daniel Cruz', studentId: '2021-09218', program: 'BSIS', destination: 'DICT Marinduque', date: '2026-07-24', companions: 1, checkpoints: '5/5', duration: '1h 01m', status: 'completed', compliance: 100 },
  { id: 'TRV-260723-009', student: 'Lea Dela Cruz', studentId: '2022-11342', program: 'BSIT', destination: 'LGU Boac MIS', date: '2026-07-23', companions: 0, checkpoints: '4/4', duration: '48m', status: 'completed', compliance: 96 },
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
  Valid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  Expiring: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  Pending: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
};

const inputClass = 'rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-600 outline-none transition focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200';

function StatusBadge({ status }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${statusStyles[status] || statusStyles.pending}`}>{String(status).replace('-', ' ')}</span>;
}

function ProgressBar({ value, color = 'bg-[#800000]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
  };
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:border-slate-200 print:shadow-none">
      <div className="flex items-start justify-between gap-3">
        <div><p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#430909] dark:text-white print:text-black">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div>
        <div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl print:hidden ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return (
    <section className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 print:break-inside-avoid print:rounded-none print:border-0 print:p-0 print:shadow-none ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3 print:mb-3">
        <div className="flex items-start gap-3">
          {Icon && <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300 print:hidden"><Icon /></div>}
          <div><h2 className="font-black text-[#430909] dark:text-white print:text-black">{title}</h2>{subtitle && <p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p>}</div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function Donut({ value, label, color = '#800000' }) {
  return (
    <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${value * 3.6}deg, #e8e4df 0deg)` }}>
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800 print:bg-white">
        <div><p className="text-2xl font-black text-[#430909] dark:text-white print:text-black">{value}%</p><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p></div>
      </div>
    </div>
  );
}

function ReportFilters({ dateFrom, dateTo, program, search, onDateFrom, onDateTo, onProgram, onSearch }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
        <div className="flex items-center gap-2 text-xs font-black text-slate-500 dark:text-gray-300"><FiFilter className="text-[#800000] dark:text-rose-300" /> Report filters</div>
        <div className="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="relative"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Search report records..." className={`${inputClass} w-full pl-9`} /></label>
          <select value={program} onChange={(event) => onProgram(event.target.value)} className={inputClass}><option value="all">All programs</option><option value="BSIT">BS Information Technology</option><option value="BSIS">BS Information Systems</option><option value="BSBA">BS Business Administration</option><option value="BSHM">BS Hospitality Management</option></select>
          <label className="flex items-center gap-2"><span className="shrink-0 text-[10px] font-black uppercase text-slate-400">From</span><input type="date" value={dateFrom} onChange={(event) => onDateFrom(event.target.value)} className={`${inputClass} min-w-0 flex-1`} /></label>
          <label className="flex items-center gap-2"><span className="shrink-0 text-[10px] font-black uppercase text-slate-400">To</span><input type="date" value={dateTo} onChange={(event) => onDateTo(event.target.value)} className={`${inputClass} min-w-0 flex-1`} /></label>
        </div>
      </div>
    </div>
  );
}

function CoordinatorReports() {
  const [activeReport, setActiveReport] = useState('attendance');
  const [dateFrom, setDateFrom] = useState('2026-07-01');
  const [dateTo, setDateTo] = useState('2026-07-25');
  const [program, setProgram] = useState('all');
  const [search, setSearch] = useState('');

  const activeModule = reportModules.find((module) => module.id === activeReport);
  const normalizedSearch = search.trim().toLowerCase();
  const matchesCommon = (record, fields) => {
    const matchesProgram = program === 'all' || record.program === program || record.programs?.includes(program);
    const matchesSearch = !normalizedSearch || fields.some((field) => String(record[field] || '').toLowerCase().includes(normalizedSearch));
    return matchesProgram && matchesSearch;
  };

  const filteredAttendance = attendanceRows.filter((row) => matchesCommon(row, ['student', 'studentId', 'hte', 'status']));
  const filteredJournals = journalRows.filter((row) => matchesCommon(row, ['student', 'studentId', 'status']));
  const filteredStudents = studentRows.filter((row) => matchesCommon(row, ['name', 'studentId', 'hte', 'status']));
  const filteredDeployments = deploymentRows.filter((row) => matchesCommon(row, ['hte', 'sector', 'location', 'moa']));
  const filteredTravel = travelRows.filter((row) => matchesCommon(row, ['id', 'student', 'studentId', 'destination', 'status']));

  const reportPeriod = `${new Date(`${dateFrom}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })} – ${new Date(`${dateTo}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}`;

  const exportPdf = () => {
    const originalTitle = document.title;
    document.title = `AIMS ${activeModule.label} - ${reportPeriod}`;
    toast.success('Preparing the print-ready PDF report.');
    window.print();
    document.title = originalTitle;
  };

  return (
    <div className="space-y-6 print:space-y-4">
      <div className="hidden border-b-2 border-[#800000] pb-4 print:block">
        <div className="flex items-end justify-between gap-4">
          <div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000]">Marinduque State University · AIMS</p><h1 className="mt-1 text-2xl font-black text-black">{activeModule.label}</h1><p className="mt-1 text-xs text-slate-500">{activeModule.description}</p></div>
          <div className="text-right text-xs"><p className="font-bold text-black">{reportPeriod}</p><p className="mt-1 text-slate-500">{program === 'all' ? 'All academic programs' : program}</p></div>
        </div>
      </div>

      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#430909] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8 print:hidden">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiBarChart2 /> Reporting Center</div>
            <h1 className="text-2xl font-black sm:text-3xl">Coordinator Reports</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Generate clear, filtered summaries for internship operations, compliance reviews, and institutional decision-making.</p>
          </div>
          <button type="button" onClick={exportPdf} className="inline-flex items-center gap-2 rounded-xl bg-[#f1c54c] px-5 py-3 text-xs font-black text-[#430909] shadow-lg shadow-black/10 transition hover:bg-[#f7d979]"><FiDownload /> Export PDF</button>
        </div>
      </header>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden" aria-label="Coordinator report types">
        <div className="flex min-w-max gap-1">
          {reportModules.map((module) => (
            <button key={module.id} type="button" onClick={() => setActiveReport(module.id)} className={`inline-flex items-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition sm:px-5 ${activeReport === module.id ? 'bg-[#800000] text-white shadow-md shadow-[#800000]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#800000] dark:text-gray-300 dark:hover:bg-gray-700'}`}>
              <module.icon className="text-base" /><span>{module.shortLabel}</span>
            </button>
          ))}
        </div>
      </nav>

      <ReportFilters dateFrom={dateFrom} dateTo={dateTo} program={program} search={search} onDateFrom={setDateFrom} onDateTo={setDateTo} onProgram={setProgram} onSearch={setSearch} />

      <div className="flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div><p className="text-[10px] font-black uppercase tracking-[0.16em] text-[#a8750b]">{activeModule.shortLabel}</p><h2 className="mt-1 text-xl font-black text-[#430909] dark:text-white">{activeModule.label}</h2><p className="mt-1 text-xs text-slate-400">{activeModule.description} · {reportPeriod}</p></div>
        <button type="button" onClick={exportPdf} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#800000] shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiPrinter /> Print / Save PDF</button>
      </div>

      {activeReport === 'attendance' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Attendance rate" value="92.4%" note="Present and late records" icon={FiTrendingUp} tone="green" />
            <MetricCard label="Rendered hours" value="8,462" note="Across active interns" icon={FiClock} tone="blue" />
            <MetricCard label="Late records" value="18" note="3.8% of workdays" icon={FiCalendar} tone="amber" />
            <MetricCard label="Pending review" value="7" note="Unverified attendance logs" icon={FiFileText} />
          </div>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Attendance Distribution" subtitle="Record status for the selected reporting period" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row">
                <Donut value={92} label="attendance" color="#059669" />
                <div className="w-full flex-1 space-y-4">
                  {[['Present', 412, 84, 'bg-emerald-500'], ['Late', 41, 8, 'bg-amber-500'], ['Absent', 37, 8, 'bg-rose-500']].map(([label, count, percent, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count} records</span></div><ProgressBar value={percent} color={color} /></div>)}
                </div>
              </div>
            </Panel>

            <Panel title="Daily Rendered Hours" subtitle="Consolidated hours for the last seven workdays" icon={FiBarChart2}>
              <div className="flex h-52 items-end justify-between gap-3 border-b border-slate-200 pb-1 dark:border-gray-700">
                {[['Jul 17', 72, 1034], ['Jul 18', 88, 1258], ['Jul 21', 81, 1162], ['Jul 22', 94, 1341], ['Jul 23', 86, 1227], ['Jul 24', 90, 1289], ['Jul 25', 77, 1101]].map(([day, height, hours]) => <div key={day} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[9px] font-black text-slate-400">{hours}</span><div className="w-full max-w-10 rounded-t-lg bg-gradient-to-t from-[#800000] to-[#b82929]" style={{ height: `${height}%` }} /><span className="text-[8px] font-bold text-slate-400">{day}</span></div>)}
              </div>
            </Panel>
          </div>

          <Panel title="Attendance Records" subtitle={`${filteredAttendance.length} records shown`} icon={FiClock}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[900px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Date</th><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">HTE</th><th className="pb-3 font-black">Mode</th><th className="pb-3 font-black">Time In</th><th className="pb-3 font-black">Time Out</th><th className="pb-3 font-black">Hours</th><th className="pb-3 font-black">Status</th><th className="pb-3 font-black">Verified</th></tr></thead>
                <tbody>{filteredAttendance.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-3 text-xs font-bold text-slate-500">{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</td><td className="py-3"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}</p></td><td className="py-3 text-xs text-slate-500">{row.hte}</td><td className="py-3 text-xs font-bold text-slate-600 dark:text-gray-300">{row.mode}</td><td className="py-3 text-xs text-slate-500">{row.timeIn}</td><td className="py-3 text-xs text-slate-500">{row.timeOut}</td><td className="py-3 text-xs font-black text-slate-700 dark:text-gray-200 print:text-black">{row.hours}h</td><td className="py-3"><StatusBadge status={row.status} /></td><td className="py-3">{row.verified ? <FiCheckCircle className="text-emerald-500" /> : <FiClock className="text-amber-500" />}</td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'journals' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Expected entries" value="1,024" note="AM and PM journals" icon={FiBookOpen} />
            <MetricCard label="Submitted" value="926" note="90.4% submission rate" icon={FiCheckCircle} tone="green" />
            <MetricCard label="Pending review" value="31" note="Awaiting coordinator action" icon={FiClock} tone="amber" />
            <MetricCard label="Missing entries" value="98" note="From 17 active interns" icon={FiFileText} tone="violet" />
          </div>

          <div className="grid gap-6 xl:grid-cols-[380px_1fr]">
            <Panel title="Submission Completion" subtitle="Overall journal compliance" icon={FiPieChart}>
              <div className="flex justify-center"><Donut value={90} label="submitted" /></div>
              <div className="mt-6 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">895</p><p className="text-[8px] font-black uppercase text-slate-400">Approved</p></div>
                <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p className="text-xl font-black text-amber-600">31</p><p className="text-[8px] font-black uppercase text-slate-400">Pending</p></div>
                <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">98</p><p className="text-[8px] font-black uppercase text-slate-400">Missing</p></div>
              </div>
            </Panel>

            <Panel title="Weekly Submission Trend" subtitle="Submitted journal entries compared with expectations" icon={FiTrendingUp}>
              <div className="space-y-5">
                {[['Week 1', 186, 194, 96], ['Week 2', 181, 194, 93], ['Week 3', 174, 194, 90], ['Week 4', 165, 194, 85], ['Current week', 92, 108, 85]].map(([week, submitted, expected, percent]) => <div key={week}><div className="mb-2 flex justify-between text-xs"><span className="font-black text-slate-600 dark:text-gray-300">{week}</span><span className="font-bold text-slate-400">{submitted}/{expected} entries · {percent}%</span></div><ProgressBar value={percent} color={percent >= 90 ? 'bg-emerald-500' : 'bg-amber-500'} /></div>)}
              </div>
            </Panel>
          </div>

          <Panel title="Student Journal Summary" subtitle={`${filteredJournals.length} student records shown`} icon={FiBookOpen}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[800px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Submitted</th><th className="pb-3 font-black">Approved</th><th className="pb-3 font-black">Pending</th><th className="pb-3 font-black">Missing</th><th className="pb-3 font-black">Completion</th><th className="pb-3 font-black">Latest</th><th className="pb-3 font-black">Standing</th></tr></thead>
                <tbody>{filteredJournals.map((row) => { const completion = Math.round((row.submitted / row.expected) * 100); return <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}</p></td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.submitted}/{row.expected}</td><td className="py-4 text-xs font-black text-emerald-600">{row.approved}</td><td className="py-4 text-xs font-black text-amber-600">{row.pending}</td><td className="py-4 text-xs font-black text-rose-600">{row.missing}</td><td className="w-36 py-4"><div className="mb-1.5 text-right text-[9px] font-black text-slate-500">{completion}%</div><ProgressBar value={completion} color={completion >= 90 ? 'bg-emerald-500' : 'bg-amber-500'} /></td><td className="py-4 text-xs text-slate-500">{row.lastSubmission}</td><td className="py-4"><StatusBadge status={row.status} /></td></tr>; })}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'students' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Total interns" value="126" note="Registered this semester" icon={FiUsers} />
            <MetricCard label="Active" value="104" note="Currently deployed" icon={FiUserCheck} tone="green" />
            <MetricCard label="Completed" value="14" note="OJT requirements fulfilled" icon={FiCheckCircle} tone="blue" />
            <MetricCard label="Pending deployment" value="8" note="Awaiting placement" icon={FiClock} tone="amber" />
          </div>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Internship Status" subtitle="Student distribution by current OJT stage" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row"><Donut value={83} label="active" /><div className="w-full flex-1 space-y-4">{[['Active', 104, 83, 'bg-emerald-500'], ['Completed', 14, 11, 'bg-blue-500'], ['Pending', 8, 6, 'bg-amber-500']].map(([label, count, percent, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count}</span></div><ProgressBar value={percent} color={color} /></div>)}</div></div>
            </Panel>
            <Panel title="Average Student Readiness" subtitle="Progress across core internship indicators" icon={FiTrendingUp}>
              <div className="space-y-5">{[['OJT hours completion', 72, 'bg-[#800000]'], ['Attendance compliance', 92, 'bg-emerald-500'], ['Requirement completion', 88, 'bg-blue-500'], ['Journal completion', 90, 'bg-amber-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value}%</span></div><ProgressBar value={value} color={color} /></div>)}</div>
            </Panel>
          </div>

          <Panel title="Student Internship Progress" subtitle={`${filteredStudents.length} student records shown`} icon={FiUsers}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[980px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">HTE Placement</th><th className="pb-3 font-black">OJT Hours</th><th className="pb-3 font-black">Attendance</th><th className="pb-3 font-black">Requirements</th><th className="pb-3 font-black">Journals</th><th className="pb-3 font-black">Status</th></tr></thead>
                <tbody>{filteredStudents.map((row) => { const progress = Math.round((row.rendered / row.required) * 100); return <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.name}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}-{row.section}</p></td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.hte}</td><td className="w-40 py-4"><div className="mb-1.5 flex justify-between text-[9px] font-bold"><span className="text-slate-500">{row.rendered}/{row.required}h</span><span className="text-[#800000]">{progress}%</span></div><ProgressBar value={progress} /></td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.attendance}%</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.requirements}%</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.journals}%</td><td className="py-4"><StatusBadge status={row.status} /></td></tr>; })}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'deployments' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Partner HTEs" value="28" note="24 currently active" icon={FiBriefcase} />
            <MetricCard label="Deployed interns" value="104" note="Across all active HTEs" icon={FiUsers} tone="green" />
            <MetricCard label="Open capacity" value="37" note="Available internship slots" icon={FiTrendingUp} tone="blue" />
            <MetricCard label="MOA attention" value="4" note="Expiring or pending" icon={FiFileText} tone="amber" />
          </div>

          <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
            <Panel title="HTE Capacity Utilization" subtitle="Deployed interns compared with partner capacity" icon={FiBarChart2}>
              <div className="space-y-5">{filteredDeployments.map((row) => { const utilization = Math.round((row.deployed / row.capacity) * 100); return <div key={row.id}><div className="mb-2 flex flex-wrap justify-between gap-2"><div><p className="text-xs font-black text-slate-700 dark:text-white print:text-black">{row.hte}</p><p className="mt-0.5 text-[9px] text-slate-400">{row.location} · {row.sector}</p></div><p className="text-xs font-black text-slate-600 dark:text-gray-300">{row.deployed}/{row.capacity} interns · {utilization}%</p></div><ProgressBar value={utilization} color={utilization >= 90 ? 'bg-amber-500' : 'bg-[#800000]'} /></div>; })}</div>
            </Panel>
            <Panel title="Deployment Health" subtitle="Current placement indicators" icon={FiUserCheck}>
              <div className="flex justify-center"><Donut value={88} label="deployed" color="#059669" /></div>
              <div className="mt-6 space-y-3 text-xs"><div className="flex justify-between"><span className="text-slate-400">Students needing placement</span><span className="font-black text-amber-600">8</span></div><div className="flex justify-between"><span className="text-slate-400">HTEs at full capacity</span><span className="font-black text-slate-700 dark:text-gray-200">6</span></div><div className="flex justify-between"><span className="text-slate-400">Valid MOA coverage</span><span className="font-black text-emerald-600">92%</span></div><div className="flex justify-between"><span className="text-slate-400">Average performance</span><span className="font-black text-blue-600">93.2%</span></div></div>
            </Panel>
          </div>

          <Panel title="HTE Deployment Summary" subtitle={`${filteredDeployments.length} partner establishments shown`} icon={FiBriefcase}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[940px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Host Training Establishment</th><th className="pb-3 font-black">Programs</th><th className="pb-3 font-black">Deployed</th><th className="pb-3 font-black">Active</th><th className="pb-3 font-black">Completed</th><th className="pb-3 font-black">MOA Status</th><th className="pb-3 font-black">Expiry</th><th className="pb-3 font-black">Performance</th></tr></thead>
                <tbody>{filteredDeployments.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.hte}</p><p className="mt-1 text-[9px] text-slate-400">{row.location} · {row.sector}</p></td><td className="py-4"><div className="flex flex-wrap gap-1">{row.programs.map((item) => <span key={item} className="rounded-md bg-slate-100 px-1.5 py-1 text-[8px] font-black text-slate-500 dark:bg-gray-700">{item}</span>)}</div></td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.deployed}/{row.capacity}</td><td className="py-4 text-xs font-black text-emerald-600">{row.active}</td><td className="py-4 text-xs font-black text-blue-600">{row.completed}</td><td className="py-4"><StatusBadge status={row.moa} /></td><td className="py-4 text-xs text-slate-500">{row.moaExpiry}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.performance}%</td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeReport === 'travel' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Travel sessions" value="48" note="For the selected period" icon={FiMapPin} />
            <MetricCard label="Completed safely" value="43" note="95.6% safe arrival rate" icon={FiCheckCircle} tone="green" />
            <MetricCard label="Avg. duration" value="1h 42m" note="8 minutes faster than June" icon={FiClock} tone="blue" />
            <MetricCard label="Route exceptions" value="3" note="2 resolved, 1 active" icon={FiFileText} tone="amber" />
          </div>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Travel Status Breakdown" subtitle="Session outcomes for the selected period" icon={FiPieChart}>
              <div className="flex flex-col items-center gap-7 sm:flex-row"><Donut value={90} label="completed" color="#059669" /><div className="w-full flex-1 space-y-4">{[['Completed', 43, 90, 'bg-emerald-500'], ['Active', 2, 4, 'bg-blue-500'], ['Scheduled', 2, 4, 'bg-violet-500'], ['Delayed', 1, 2, 'bg-rose-500']].map(([label, count, percent, color]) => <div key={label}><div className="mb-1.5 flex justify-between text-xs"><span className="font-bold text-slate-500">{label}</span><span className="font-black text-slate-700 dark:text-gray-200 print:text-black">{count}</span></div><ProgressBar value={percent} color={color} /></div>)}</div></div>
            </Panel>
            <Panel title="Safety & Compliance" subtitle="Travel monitoring requirement completion" icon={FiTrendingUp}>
              <div className="space-y-5">{[['Approved travel sessions', 100, 'bg-emerald-500'], ['Verified companion manifests', 94, 'bg-blue-500'], ['Checkpoint evidence submitted', 91, 'bg-[#800000]'], ['On-time route completion', 86, 'bg-amber-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value}%</span></div><ProgressBar value={value} color={color} /></div>)}</div>
            </Panel>
          </div>

          <Panel title="Travel Session Records" subtitle={`${filteredTravel.length} session records shown`} icon={FiMapPin}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[960px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Session</th><th className="pb-3 font-black">Traveler</th><th className="pb-3 font-black">Destination</th><th className="pb-3 font-black">Date</th><th className="pb-3 font-black">Companions</th><th className="pb-3 font-black">Checkpoints</th><th className="pb-3 font-black">Duration</th><th className="pb-3 font-black">Compliance</th><th className="pb-3 font-black">Status</th></tr></thead>
                <tbody>{filteredTravel.map((row) => <tr key={row.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-[#800000] dark:text-rose-300">{row.id}</td><td className="py-4"><p className="text-xs font-black text-slate-800 dark:text-white print:text-black">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.studentId} · {row.program}</p></td><td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{row.destination}</td><td className="py-4 text-xs text-slate-500">{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.companions}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.checkpoints}</td><td className="py-4 text-xs text-slate-500">{row.duration}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.compliance}%</td><td className="py-4"><StatusBadge status={row.status} /></td></tr>)}</tbody>
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
