import { useEffect, useState } from 'react';
import {
  FiActivity,
  FiAlertTriangle,
  FiBarChart2,
  FiCheckCircle,
  FiClock,
  FiDownload,
  FiFileText,
  FiGrid,
  FiPieChart,
  FiPrinter,
  FiRefreshCw,
  FiShield,
  FiTrendingUp,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyReport = {
  overview: { total_students: 0, active: 0, completed: 0, pending: 0, deployed: 0, average_progress: 0 },
  attendance: { present: 0, late: 0, absent: 0, holiday: 0, total: 0, rate: 0, verified: 0, total_hours: 0, trend: [] },
  requirements: { total: 0, submitted: 0, approved: 0, pending: 0, rejected: 0, missing: 0, completion_rate: 0 },
  travel: { active: 0, total_this_month: 0, completed_this_month: 0, cancelled_this_month: 0, completion_rate: 0 },
  pending_reviews: { registrations: 0, requirements: 0, attendance: 0, journals: 0, total: 0 },
  analytics: { average_progress: 0, internship_status: [], program_performance: [] },
};

const tabs = [
  { id: 'compliance', label: 'Compliance Analytics', icon: FiShield },
  { id: 'performance', label: 'Internship Performance Analytics', icon: FiTrendingUp },
];

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
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-lg text-[#800000] dark:bg-rose-950/50 dark:text-rose-300 print:hidden"><Icon /></div>
          <div><h2 className="font-black text-[#430909] dark:text-white print:text-black">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div>
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

function Ring({ value, label, color = '#800000' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return (
    <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${safeValue * 3.6}deg, #e8e4df 0deg)` }}>
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800 print:bg-white">
        <div><p className="text-2xl font-black text-[#430909] dark:text-white print:text-black">{safeValue.toFixed(1)}%</p><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p></div>
      </div>
    </div>
  );
}

function downloadBlob(content, type, filename) {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function csvCell(value) {
  return `"${String(value ?? '').replaceAll('"', '""')}"`;
}

function xmlCell(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;');
}

function ProgramHeadReports() {
  const [report, setReport] = useState(emptyReport);
  const [activeTab, setActiveTab] = useState('compliance');
  const [programFilter, setProgramFilter] = useState('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    let mounted = true;
    api.get('/program-head/dashboard')
      .then((response) => {
        if (mounted) setReport({ ...emptyReport, ...response.data });
      })
      .catch((error) => {
        if (mounted) toast.error(error.response?.data?.message || 'Unable to load analytics.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => { mounted = false; };
  }, []);

  const refreshReport = async () => {
    setRefreshing(true);
    try {
      const response = await api.get('/program-head/dashboard');
      setReport({ ...emptyReport, ...response.data });
      toast.success('Analytics refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to refresh analytics.');
    } finally {
      setRefreshing(false);
    }
  };

  const overview = report.overview || emptyReport.overview;
  const attendance = report.attendance || emptyReport.attendance;
  const requirements = report.requirements || emptyReport.requirements;
  const travel = report.travel || emptyReport.travel;
  const reviews = report.pending_reviews || emptyReport.pending_reviews;
  const analytics = report.analytics || emptyReport.analytics;
  const programOptions = analytics.program_performance?.map((item) => item.program) || [];
  const filteredPrograms = (analytics.program_performance || []).filter((item) => programFilter === 'all' || item.program === programFilter);
  const complianceScore = Math.round(((Number(attendance.rate) || 0) + (Number(requirements.completion_rate) || 0) + (Number(travel.completion_rate) || 0)) / 3);
  const verificationRate = attendance.total ? Math.round((attendance.verified / attendance.total) * 100) : 0;
  const deploymentRate = overview.total_students ? Math.round((overview.deployed / overview.total_students) * 100) : 0;
  const reportPeriod = new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });

  const complianceRows = [
    { Category: 'Attendance', Metric: 'Attendance compliance rate', Value: `${attendance.rate || 0}%`, Target: '90%', Status: attendance.rate >= 90 ? 'On target' : 'Below target' },
    { Category: 'Attendance', Metric: 'Verified attendance logs', Value: `${verificationRate}%`, Target: '95%', Status: verificationRate >= 95 ? 'On target' : 'Needs review' },
    { Category: 'Requirements', Metric: 'Requirement approval completion', Value: `${requirements.completion_rate || 0}%`, Target: '90%', Status: requirements.completion_rate >= 90 ? 'On target' : 'Below target' },
    { Category: 'Requirements', Metric: 'Pending requirement decisions', Value: requirements.pending || 0, Target: '0', Status: requirements.pending ? 'Action required' : 'Clear' },
    { Category: 'Travel', Metric: 'Safe travel completion', Value: `${travel.completion_rate || 0}%`, Target: '95%', Status: travel.completion_rate >= 95 ? 'On target' : 'Below target' },
    { Category: 'Review Queue', Metric: 'Total pending reviews', Value: reviews.total || 0, Target: '0', Status: reviews.total ? 'Action required' : 'Clear' },
  ];
  const performanceRows = filteredPrograms.map((item) => ({
    Program: item.program,
    'Total Students': item.students,
    'Active Interns': item.active,
    'Average OJT Progress': `${item.progress}%`,
    'Attendance Rate': `${item.attendance_rate}%`,
  }));
  const exportRows = activeTab === 'compliance' ? complianceRows : performanceRows;
  const reportName = activeTab === 'compliance' ? 'compliance-analytics' : 'internship-performance-analytics';

  const exportCsv = () => {
    if (!exportRows.length) {
      toast.error('There is no report data to export.');
      return;
    }
    const headers = Object.keys(exportRows[0]);
    const content = `\uFEFF${headers.map(csvCell).join(',')}\n${exportRows.map((row) => headers.map((header) => csvCell(row[header])).join(',')).join('\n')}`;
    downloadBlob(content, 'text/csv;charset=utf-8', `${reportName}-${new Date().toISOString().slice(0, 10)}.csv`);
    toast.success('CSV report downloaded.');
  };

  const exportExcel = () => {
    if (!exportRows.length) {
      toast.error('There is no report data to export.');
      return;
    }
    const headers = Object.keys(exportRows[0]);
    const headerCells = headers.map((header) => `<Cell ss:StyleID="Header"><Data ss:Type="String">${xmlCell(header)}</Data></Cell>`).join('');
    const bodyRows = exportRows.map((row) => `<Row>${headers.map((header) => `<Cell><Data ss:Type="String">${xmlCell(row[header])}</Data></Cell>`).join('')}</Row>`).join('');
    const workbook = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Styles>
    <Style ss:ID="Default"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>
    <Style ss:ID="Header"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#800000" ss:Pattern="Solid"/></Style>
  </Styles>
  <Worksheet ss:Name="${activeTab === 'compliance' ? 'Compliance Analytics' : 'Performance Analytics'}">
    <Table><Row>${headerCells}</Row>${bodyRows}</Table>
  </Worksheet>
</Workbook>`;
    downloadBlob(workbook, 'application/vnd.ms-excel;charset=utf-8', `${reportName}-${new Date().toISOString().slice(0, 10)}.xls`);
    toast.success('Excel report downloaded.');
  };

  const exportPdf = () => {
    const originalTitle = document.title;
    document.title = `AIMS ${activeTab === 'compliance' ? 'Compliance Analytics' : 'Internship Performance Analytics'} - ${reportPeriod}`;
    toast.success('Preparing the print-ready PDF report.');
    window.print();
    document.title = originalTitle;
  };

  if (loading) {
    return <div className="grid min-h-[420px] place-items-center"><div className="text-center"><div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#800000]/15 border-b-[#800000]" /><p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading analytics</p></div></div>;
  }

  const complianceIndicators = [
    { label: 'Attendance compliance', value: attendance.rate || 0, target: 90, note: `${attendance.present || 0} present · ${attendance.late || 0} late`, color: 'bg-emerald-500' },
    { label: 'Attendance verification', value: verificationRate, target: 95, note: `${attendance.verified || 0} of ${attendance.total || 0} logs verified`, color: 'bg-blue-500' },
    { label: 'Requirement completion', value: requirements.completion_rate || 0, target: 90, note: `${requirements.approved || 0} of ${requirements.total || 0} approved`, color: 'bg-[#800000]' },
    { label: 'Safe travel completion', value: travel.completion_rate || 0, target: 95, note: `${travel.completed_this_month || 0} completed this month`, color: 'bg-violet-500' },
  ];
  const statusTotal = analytics.internship_status?.reduce((sum, item) => sum + Number(item.value || 0), 0) || 0;

  return (
    <div className="space-y-6 print:space-y-4">
      <div className="hidden border-b-2 border-[#800000] pb-4 print:block">
        <div className="flex items-end justify-between gap-4">
          <div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000]">Marinduque State University · AIMS</p><h1 className="mt-1 text-2xl font-black text-black">{activeTab === 'compliance' ? 'Compliance Analytics' : 'Internship Performance Analytics'}</h1><p className="mt-1 text-xs text-slate-500">Program Head analytical report</p></div>
          <div className="text-right text-xs"><p className="font-bold text-black">{reportPeriod}</p><p className="mt-1 text-slate-500">{programFilter === 'all' ? 'All academic programs' : programFilter}</p></div>
        </div>
      </div>

      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#430909] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8 print:hidden">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiBarChart2 /> Program Analytics</div>
            <h1 className="text-2xl font-black sm:text-3xl">Program Head Reports</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Evaluate internship compliance and program performance, then export decision-ready institutional reports.</p>
          </div>
          <button type="button" onClick={refreshReport} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Analytics</button>
        </div>
      </header>

      <div className="grid gap-3 sm:grid-cols-3 print:hidden">
        <button type="button" onClick={exportPdf} className="inline-flex items-center justify-center gap-3 rounded-2xl border border-rose-100 bg-white p-4 text-sm font-black text-[#800000] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-rose-950 dark:bg-gray-800 dark:text-rose-300"><span className="grid h-9 w-9 place-items-center rounded-xl bg-rose-100 dark:bg-rose-950"><FiPrinter /></span> Export PDF</button>
        <button type="button" onClick={exportExcel} className="inline-flex items-center justify-center gap-3 rounded-2xl border border-emerald-100 bg-white p-4 text-sm font-black text-emerald-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-950 dark:bg-gray-800 dark:text-emerald-300"><span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-100 dark:bg-emerald-950"><FiGrid /></span> Export Excel</button>
        <button type="button" onClick={exportCsv} className="inline-flex items-center justify-center gap-3 rounded-2xl border border-blue-100 bg-white p-4 text-sm font-black text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-blue-950 dark:bg-gray-800 dark:text-blue-300"><span className="grid h-9 w-9 place-items-center rounded-xl bg-blue-100 dark:bg-blue-950"><FiDownload /></span> Export CSV</button>
      </div>

      <div className="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center print:hidden">
        <nav className="flex flex-1 gap-1 overflow-x-auto" aria-label="Program Head analytics reports">
          {tabs.map((tab) => <button key={tab.id} type="button" onClick={() => setActiveTab(tab.id)} className={`inline-flex min-w-max flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition ${activeTab === tab.id ? 'bg-[#800000] text-white shadow-md shadow-[#800000]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#800000] dark:text-gray-300 dark:hover:bg-gray-700'}`}><tab.icon className="text-base" /> {tab.label}</button>)}
        </nav>
        {activeTab === 'performance' && <select value={programFilter} onChange={(event) => setProgramFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-xs font-black text-slate-600 outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"><option value="all">All programs</option>{programOptions.map((program) => <option key={program} value={program}>{program}</option>)}</select>}
      </div>

      {activeTab === 'compliance' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Compliance score" value={`${complianceScore}%`} note="Average of core indicators" icon={FiShield} tone="green" />
            <MetricCard label="Attendance rate" value={`${attendance.rate || 0}%`} note={`${attendance.total || 0} monthly records`} icon={FiClock} tone="blue" />
            <MetricCard label="Requirements approved" value={`${requirements.completion_rate || 0}%`} note={`${requirements.pending || 0} pending decisions`} icon={FiFileText} />
            <MetricCard label="Pending reviews" value={reviews.total || 0} note="Across all review queues" icon={FiAlertTriangle} tone="amber" />
          </div>

          <div className="grid gap-6 xl:grid-cols-[380px_1fr]">
            <Panel title="Overall Compliance" subtitle="Combined attendance, requirement, and travel compliance" icon={FiPieChart}>
              <div className="flex justify-center"><Ring value={complianceScore} label="compliant" color={complianceScore >= 90 ? '#059669' : '#d97706'} /></div>
              <div className={`mt-6 rounded-2xl p-4 ${complianceScore >= 90 ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'bg-amber-50 dark:bg-amber-950/30'}`}>
                <div className="flex items-start gap-3">{complianceScore >= 90 ? <FiCheckCircle className="mt-0.5 shrink-0 text-emerald-600" /> : <FiAlertTriangle className="mt-0.5 shrink-0 text-amber-600" />}<div><p className={`text-sm font-black ${complianceScore >= 90 ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'}`}>{complianceScore >= 90 ? 'Compliance target achieved' : 'Compliance action needed'}</p><p className="mt-1 text-xs leading-5 text-slate-500">Institutional target is 90% across monitored internship controls.</p></div></div>
              </div>
            </Panel>

            <Panel title="Compliance Indicators" subtitle="Current results measured against institutional targets" icon={FiActivity}>
              <div className="space-y-6">
                {complianceIndicators.map((item) => <div key={item.label}><div className="mb-2 flex flex-wrap items-end justify-between gap-2"><div><p className="text-xs font-black text-slate-700 dark:text-white print:text-black">{item.label}</p><p className="mt-1 text-[10px] text-slate-400">{item.note}</p></div><div className="text-right"><p className={`text-sm font-black ${item.value >= item.target ? 'text-emerald-600' : 'text-amber-600'}`}>{item.value}%</p><p className="text-[8px] font-bold uppercase text-slate-400">Target {item.target}%</p></div></div><ProgressBar value={item.value} color={item.value >= item.target ? item.color : 'bg-amber-500'} /></div>)}
              </div>
            </Panel>
          </div>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Document Compliance" subtitle="Internship requirement submission and decision status" icon={FiFileText}>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[['Approved', requirements.approved, 'text-emerald-600', 'bg-emerald-50 dark:bg-emerald-950/30'], ['Pending', requirements.pending, 'text-amber-600', 'bg-amber-50 dark:bg-amber-950/30'], ['Rejected', requirements.rejected, 'text-rose-600', 'bg-rose-50 dark:bg-rose-950/30'], ['Missing', requirements.missing, 'text-slate-600 dark:text-gray-300', 'bg-slate-50 dark:bg-gray-900']].map(([label, value, color, background]) => <div key={label} className={`rounded-2xl p-4 text-center ${background}`}><p className={`text-2xl font-black ${color}`}>{value || 0}</p><p className="mt-1 text-[9px] font-black uppercase text-slate-400">{label}</p></div>)}
              </div>
              <div className="mt-5"><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-500">Approved completion</span><span className="font-black text-[#800000] dark:text-rose-300">{requirements.completion_rate || 0}%</span></div><ProgressBar value={requirements.completion_rate} /></div>
            </Panel>

            <Panel title="Review Queue Compliance" subtitle="Outstanding academic decisions by review category" icon={FiAlertTriangle}>
              <div className="space-y-4">
                {[['Registrations', reviews.registrations, 'bg-violet-500'], ['Requirements', reviews.requirements, 'bg-blue-500'], ['Attendance', reviews.attendance, 'bg-amber-500'], ['Journals', reviews.journals, 'bg-rose-500']].map(([label, value, color]) => <div key={label} className="flex items-center gap-3"><div className={`h-2.5 w-2.5 rounded-full ${color}`} /><span className="flex-1 text-xs font-bold text-slate-600 dark:text-gray-300">{label}</span><span className={`grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-black ${value ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'}`}>{value || 0}</span></div>)}
              </div>
            </Panel>
          </div>

          <Panel title="Compliance Gap Analysis" subtitle="Detailed indicator results for audit and follow-up" icon={FiShield}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[700px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Category</th><th className="pb-3 font-black">Metric</th><th className="pb-3 font-black">Current</th><th className="pb-3 font-black">Target</th><th className="pb-3 font-black">Standing</th></tr></thead>
                <tbody>{complianceRows.map((row) => <tr key={row.Metric} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-slate-700 dark:text-white print:text-black">{row.Category}</td><td className="py-4 text-xs text-slate-500">{row.Metric}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.Value}</td><td className="py-4 text-xs font-bold text-slate-500">{row.Target}</td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${row.Status === 'On target' || row.Status === 'Clear' ? 'bg-emerald-100 text-emerald-700' : row.Status === 'Action required' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>{row.Status}</span></td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'performance' && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Total interns" value={overview.total_students || 0} note={`${overview.deployed || 0} deployed to HTEs`} icon={FiUsers} />
            <MetricCard label="Active interns" value={overview.active || 0} note={`${deploymentRate}% deployment coverage`} icon={FiActivity} tone="green" />
            <MetricCard label="Average OJT progress" value={`${overview.average_progress || 0}%`} note="Across active interns" icon={FiTrendingUp} tone="blue" />
            <MetricCard label="Completed internships" value={overview.completed || 0} note={`${overview.pending || 0} pending deployment`} icon={FiCheckCircle} tone="violet" />
          </div>

          <div className="grid gap-6 xl:grid-cols-[370px_1fr]">
            <Panel title="Internship Status Distribution" subtitle="Students grouped by current internship stage" icon={FiPieChart}>
              <div className="flex justify-center"><Ring value={statusTotal ? ((overview.active || 0) / statusTotal) * 100 : 0} label="active interns" color="#059669" /></div>
              <div className="mt-6 space-y-3">{(analytics.internship_status || []).map((item) => <div key={item.label} className="flex items-center gap-3"><span className="h-2.5 w-2.5 rounded-full" style={{ background: item.color }} /><span className="flex-1 text-xs font-bold text-slate-500">{item.label}</span><span className="text-xs font-black text-slate-700 dark:text-gray-200">{item.value}</span></div>)}</div>
            </Panel>

            <Panel title="Program Performance Comparison" subtitle="OJT progress and attendance across academic programs" icon={FiBarChart2}>
              {filteredPrograms.length ? <div className="space-y-6">{filteredPrograms.map((program) => <div key={program.program}><div className="mb-2 flex flex-wrap items-end justify-between gap-2"><div><p className="text-sm font-black text-slate-700 dark:text-white print:text-black">{program.program}</p><p className="mt-1 text-[10px] text-slate-400">{program.active} active of {program.students} students</p></div><div className="flex gap-4 text-right"><div><p className="text-xs font-black text-[#800000] dark:text-rose-300">{program.progress}%</p><p className="text-[8px] uppercase text-slate-400">Progress</p></div><div><p className="text-xs font-black text-emerald-600">{program.attendance_rate}%</p><p className="text-[8px] uppercase text-slate-400">Attendance</p></div></div></div><div className="grid grid-cols-2 gap-2"><ProgressBar value={program.progress} /><ProgressBar value={program.attendance_rate} color="bg-emerald-500" /></div></div>)}</div> : <div className="py-16 text-center text-sm font-bold text-slate-400">No program performance records are available.</div>}
            </Panel>
          </div>

          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Seven-Day Attendance Performance" subtitle="Daily attendance rate across internship programs" icon={FiActivity}>
              {attendance.trend?.length ? <div className="flex h-56 items-end justify-between gap-3 border-b border-slate-200 px-2 pb-1 dark:border-gray-700">{attendance.trend.map((day) => <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[9px] font-black text-slate-400">{day.rate}%</span><div className={`w-full max-w-12 rounded-t-xl ${day.rate >= 90 ? 'bg-gradient-to-t from-emerald-700 to-emerald-400' : day.rate > 0 ? 'bg-gradient-to-t from-[#800000] to-[#b82929]' : 'bg-slate-200 dark:bg-gray-700'}`} style={{ height: `${Math.max(day.rate, 4)}%` }} /><span className="text-[9px] font-black text-slate-500">{day.label}</span></div>)}</div> : <div className="py-16 text-center text-sm font-bold text-slate-400">No recent attendance trend is available.</div>}
            </Panel>

            <Panel title="Performance Outcomes" subtitle="Key internship productivity and completion indicators" icon={FiTrendingUp}>
              <div className="space-y-5">
                {[['Average OJT progress', overview.average_progress, 'bg-[#800000]'], ['Attendance performance', attendance.rate, 'bg-emerald-500'], ['Requirement completion', requirements.completion_rate, 'bg-blue-500'], ['Deployment coverage', deploymentRate, 'bg-violet-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value || 0}%</span></div><ProgressBar value={value} color={color} /></div>)}
              </div>
            </Panel>
          </div>

          <Panel title="Program Performance Detail" subtitle={`${filteredPrograms.length} academic programs shown`} icon={FiUsers}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Program</th><th className="pb-3 font-black">Students</th><th className="pb-3 font-black">Active Interns</th><th className="pb-3 font-black">OJT Progress</th><th className="pb-3 font-black">Attendance Rate</th><th className="pb-3 font-black">Performance Standing</th></tr></thead>
                <tbody>{filteredPrograms.map((program) => { const score = Math.round((Number(program.progress) + Number(program.attendance_rate)) / 2); return <tr key={program.program} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-sm font-black text-slate-800 dark:text-white print:text-black">{program.program}</td><td className="py-4 text-xs font-black text-slate-600 dark:text-gray-300">{program.students}</td><td className="py-4 text-xs font-black text-emerald-600">{program.active}</td><td className="w-40 py-4"><div className="mb-1 text-right text-[9px] font-black text-[#800000] dark:text-rose-300">{program.progress}%</div><ProgressBar value={program.progress} /></td><td className="w-40 py-4"><div className="mb-1 text-right text-[9px] font-black text-emerald-600">{program.attendance_rate}%</div><ProgressBar value={program.attendance_rate} color="bg-emerald-500" /></td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${score >= 85 ? 'bg-emerald-100 text-emerald-700' : score >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>{score >= 85 ? 'High performing' : score >= 70 ? 'Developing' : 'Needs support'}</span></td></tr>; })}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      <footer className="hidden border-t border-slate-300 pt-3 text-[9px] text-slate-500 print:flex print:items-center print:justify-between"><span>Generated from the AIMS Program Head Analytics Center</span><span>Generated {new Date().toLocaleString('en-PH')}</span></footer>
    </div>
  );
}

export default ProgramHeadReports;
