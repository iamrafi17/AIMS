import { useEffect, useState } from 'react';
import {
  FiActivity,
  FiAlertTriangle,
  FiBarChart2,
  FiCheckCircle,
  FiClock,
  FiFileText,
  FiPieChart,
  FiRefreshCw,
  FiShield,
  FiTrendingUp,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import ReportTemplateControls from '../../components/reports/ReportTemplateControls';
import api from '../../services/api';

const emptyReport = {
  overview: { total_students: 0, active: 0, completed: 0, pending: 0, deployed: 0, average_progress: 0 },
  attendance: { present: 0, late: 0, absent: 0, holiday: 0, total: 0, rate: 0, verified: 0, total_hours: 0, trend: [] },
  requirements: { total: 0, submitted: 0, approved: 0, pending: 0, rejected: 0, missing: 0, completion_rate: 0 },
  travel: { active: 0, total_this_month: 0, completed_this_month: 0, cancelled_this_month: 0, completion_rate: 0 },
  pending_reviews: { requirements: 0, attendance: 0, journals: 0, total: 0 },
  analytics: { average_progress: 0, internship_status: [], program_performance: [], section_performance: [] },
};

const tabs = [
  { id: 'program_summary', label: 'Program Internship Summary', icon: FiTrendingUp },
  { id: 'hte_deployment_review', label: 'HTE Deployment Review', icon: FiUsers },
  { id: 'travel_monitoring_review', label: 'Travel Monitoring Review', icon: FiShield },
];

function StatusPill({ value }) {
  const label = String(value || 'not submitted').replaceAll('_', ' ');
  return <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase text-slate-600 dark:bg-slate-700 dark:text-slate-200">{label}</span>;
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
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-lg text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300 print:hidden"><Icon /></div>
          <div><h2 className="font-black text-[#690016] dark:text-white print:text-black">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function ProgressBar({ value, color = 'bg-[#7D001D]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function Ring({ value, label, color = '#7D001D' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return (
    <div className="relative grid h-36 w-36 shrink-0 place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${safeValue * 3.6}deg, #e8e4df 0deg)` }}>
      <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center dark:bg-gray-800 print:bg-white">
        <div><p className="text-2xl font-black text-[#690016] dark:text-white print:text-black">{safeValue.toFixed(1)}%</p><p className="text-[9px] font-black uppercase tracking-wider text-slate-400">{label}</p></div>
      </div>
    </div>
  );
}

function ProgramHeadReports() {
  const [report, setReport] = useState(emptyReport);
  const [activeTab, setActiveTab] = useState('program_summary');
  const [mobileView, setMobileView] = useState('compliance');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [reviewingDeployment, setReviewingDeployment] = useState(null);

  useEffect(() => {
    let mounted = true;
    api.get('/program-head/reports')
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
      const response = await api.get('/program-head/reports');
      setReport({ ...emptyReport, ...response.data });
      toast.success('Analytics refreshed.');
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to refresh analytics.');
    } finally {
      setRefreshing(false);
    }
  };

  const reviewDeployment = async (row, decision) => {
    if (!row.approval_id) return toast.error('This deployment has not been submitted for review.');
    const remarks = decision === 'returned'
      ? window.prompt('Enter the required revision remarks for the Coordinator:')
      : 'Requirements and working schedule reviewed by the Program Head.';
    if (decision === 'returned' && !remarks?.trim()) return;
    if (decision === 'forwarded' && !window.confirm('Forward this deployment request to the VPAA for final validation?')) return;
    setReviewingDeployment(row.approval_id);
    try {
      const response = await api.put(`/program-head/deployments/${row.approval_id}/review`, { decision, remarks });
      toast.success(response.data.message);
      await refreshReport();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to review the deployment request.');
    } finally {
      setReviewingDeployment(null);
    }
  };

  const overview = report.overview || emptyReport.overview;
  const attendance = report.attendance || emptyReport.attendance;
  const requirements = report.requirements || emptyReport.requirements;
  const travel = report.travel || emptyReport.travel;
  const reviews = report.pending_reviews || emptyReport.pending_reviews;
  const analytics = report.analytics || emptyReport.analytics;
  const filteredPrograms = (analytics.section_performance || []).map((item) => ({ ...item, program: item.section }));
  const assignedProgram = report.scope?.program;
  const sectionLabel = 'All sections';
  const complianceScore = Math.round(((Number(attendance.rate) || 0) + (Number(requirements.completion_rate) || 0) + (Number(travel.completion_rate) || 0)) / 3);
  const verificationRate = attendance.total ? Math.round((attendance.verified / attendance.total) * 100) : 0;
  const deploymentRate = overview.total_students ? Math.round((overview.deployed / overview.total_students) * 100) : 0;
  const reportPeriod = 'All available records';

  const complianceRows = [
    { Category: 'Attendance', Metric: 'Attendance compliance rate', Value: `${attendance.rate || 0}%`, Target: '90%', Status: attendance.rate >= 90 ? 'On target' : 'Below target' },
    { Category: 'Attendance', Metric: 'Verified attendance logs', Value: `${verificationRate}%`, Target: '95%', Status: verificationRate >= 95 ? 'On target' : 'Needs review' },
    { Category: 'Requirements', Metric: 'Requirement approval completion', Value: `${requirements.completion_rate || 0}%`, Target: '90%', Status: requirements.completion_rate >= 90 ? 'On target' : 'Below target' },
    { Category: 'Requirements', Metric: 'Pending requirement decisions', Value: requirements.pending || 0, Target: '0', Status: requirements.pending ? 'Action required' : 'Clear' },
    { Category: 'Travel', Metric: 'Safe travel completion', Value: `${travel.completion_rate || 0}%`, Target: '95%', Status: travel.completion_rate >= 95 ? 'On target' : 'Below target' },
    { Category: 'Review Queue', Metric: 'Total pending reviews', Value: reviews.total || 0, Target: '0', Status: reviews.total ? 'Action required' : 'Clear' },
  ];

  if (loading) {
    return <div className="grid min-h-[420px] place-items-center"><div className="text-center"><div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#7D001D]/15 border-b-[#7D001D]" /><p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading analytics</p></div></div>;
  }

  const complianceIndicators = [
    { label: 'Attendance compliance', value: attendance.rate || 0, target: 90, note: `${attendance.present || 0} present · ${attendance.late || 0} late`, color: 'bg-emerald-500' },
    { label: 'Attendance verification', value: verificationRate, target: 95, note: `${attendance.verified || 0} of ${attendance.total || 0} logs verified`, color: 'bg-blue-500' },
    { label: 'Requirement completion', value: requirements.completion_rate || 0, target: 90, note: `${requirements.approved || 0} of ${requirements.total || 0} approved`, color: 'bg-[#7D001D]' },
    { label: 'Safe travel completion', value: travel.completion_rate || 0, target: 95, note: `${travel.completed_this_month || 0} completed records`, color: 'bg-violet-500' },
  ];
  const statusTotal = analytics.internship_status?.reduce((sum, item) => sum + Number(item.value || 0), 0) || 0;

  return (
    <div className="program-head-reports portal-card-tables space-y-6 print:space-y-4">
      <div className="hidden border-b-2 border-[#7D001D] pb-4 print:block">
        <div className="flex items-end justify-between gap-4">
          <div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#7D001D]">Marinduque State University · AIMS</p><h1 className="mt-1 text-2xl font-black text-black">{tabs.find((tab) => tab.id === activeTab)?.label}</h1><p className="mt-1 text-xs text-slate-500">Program Head summary and review report</p></div>
          <div className="text-right text-xs"><p className="font-bold text-black">{reportPeriod}</p><p className="mt-1 text-slate-500">{assignedProgram ? `${assignedProgram.code} · ${assignedProgram.name}` : 'Assigned program'}</p><p className="mt-1 text-slate-500">{sectionLabel}</p></div>
        </div>
      </div>

      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#690016] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8 print:hidden">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiBarChart2 /> Program Analytics</div>
            <h1 className="text-2xl font-black sm:text-3xl">Program Head Reports</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Evaluate internship compliance and program performance, then export decision-ready institutional reports.</p>
          </div>
          <div className="flex flex-wrap gap-2"><ReportTemplateControls reportType={activeTab} reportLabel={tabs.find((tab) => tab.id === activeTab)?.label} /><button type="button" onClick={refreshReport} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Analytics</button></div>
        </div>
      </header>

      <div className="rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden">
        <nav className="portal-mobile-tabs flex flex-1 gap-1 overflow-x-auto" aria-label="Program Head analytics reports">
          {tabs.map((tab) => <button key={tab.id} type="button" onClick={() => { setActiveTab(tab.id); setMobileView('compliance'); }} className={`inline-flex min-w-max flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3 text-xs font-black transition ${activeTab === tab.id ? 'bg-[#7D001D] text-white shadow-md shadow-[#7D001D]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#7D001D] dark:text-gray-300 dark:hover:bg-gray-700'}`}><tab.icon className="text-base" /> {tab.label}</button>)}
        </nav>
      </div>

      {activeTab === 'program_summary' && <section className="touch-layout-only rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:hidden print:hidden"><label className="block"><span className="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">Report section</span><select value={mobileView} onChange={(event) => setMobileView(event.target.value)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold dark:border-slate-700 dark:bg-slate-950"><option value="compliance">Compliance overview</option><option value="decisions">Documents and review queues</option><option value="compliance_detail">Compliance details</option><option value="performance">Performance overview</option><option value="performance_detail">Performance details</option></select></label></section>}

      {activeTab === 'program_summary' && (
        <div data-touch-active={['compliance', 'decisions', 'compliance_detail'].includes(mobileView)} className="space-y-6">
          <div data-touch-active={mobileView === 'compliance'} className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Compliance score" value={`${complianceScore}%`} note="Average of core indicators" icon={FiShield} tone="green" />
            <MetricCard label="Attendance rate" value={`${attendance.rate || 0}%`} note={`${attendance.total || 0} monthly records`} icon={FiClock} tone="blue" />
            <MetricCard label="Requirements approved" value={`${requirements.completion_rate || 0}%`} note={`${requirements.pending || 0} pending decisions`} icon={FiFileText} />
            <MetricCard label="Pending reviews" value={reviews.total || 0} note="Across all review queues" icon={FiAlertTriangle} tone="amber" />
          </div>

          <div data-touch-active={mobileView === 'compliance'} className="grid gap-6 xl:grid-cols-[380px_1fr]">
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

          <div data-touch-active={mobileView === 'decisions'} className="grid gap-6 xl:grid-cols-2">
            <Panel title="Document Compliance" subtitle="Internship requirement submission and decision status" icon={FiFileText}>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[['Approved', requirements.approved, 'text-emerald-600', 'bg-emerald-50 dark:bg-emerald-950/30'], ['Pending', requirements.pending, 'text-amber-600', 'bg-amber-50 dark:bg-amber-950/30'], ['Rejected', requirements.rejected, 'text-rose-600', 'bg-rose-50 dark:bg-rose-950/30'], ['Missing', requirements.missing, 'text-slate-600 dark:text-gray-300', 'bg-slate-50 dark:bg-gray-900']].map(([label, value, color, background]) => <div key={label} className={`rounded-2xl p-4 text-center ${background}`}><p className={`text-2xl font-black ${color}`}>{value || 0}</p><p className="mt-1 text-[9px] font-black uppercase text-slate-400">{label}</p></div>)}
              </div>
              <div className="mt-5"><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-500">Approved completion</span><span className="font-black text-[#7D001D] dark:text-rose-300">{requirements.completion_rate || 0}%</span></div><ProgressBar value={requirements.completion_rate} /></div>
            </Panel>

            <Panel title="Review Queue Compliance" subtitle="Outstanding academic decisions by review category" icon={FiAlertTriangle}>
              <div className="space-y-4">
                {[['Requirements', reviews.requirements, 'bg-blue-500'], ['Attendance', reviews.attendance, 'bg-amber-500'], ['Journals', reviews.journals, 'bg-rose-500']].map(([label, value, color]) => <div key={label} className="flex items-center gap-3"><div className={`h-2.5 w-2.5 rounded-full ${color}`} /><span className="flex-1 text-xs font-bold text-slate-600 dark:text-gray-300">{label}</span><span className={`grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-black ${value ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'}`}>{value || 0}</span></div>)}
              </div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'compliance_detail'} title="Compliance Gap Analysis" subtitle="Detailed indicator results for audit and follow-up" icon={FiShield}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[700px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Category</th><th className="pb-3 font-black">Metric</th><th className="pb-3 font-black">Current</th><th className="pb-3 font-black">Target</th><th className="pb-3 font-black">Standing</th></tr></thead>
                <tbody>{complianceRows.map((row) => <tr key={row.Metric} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-xs font-black text-slate-700 dark:text-white print:text-black">{row.Category}</td><td className="py-4 text-xs text-slate-500">{row.Metric}</td><td className="py-4 text-xs font-black text-slate-700 dark:text-gray-200">{row.Value}</td><td className="py-4 text-xs font-bold text-slate-500">{row.Target}</td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${row.Status === 'On target' || row.Status === 'Clear' ? 'bg-emerald-100 text-emerald-700' : row.Status === 'Action required' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>{row.Status}</span></td></tr>)}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'program_summary' && (
        <div data-touch-active={mobileView === 'performance' || mobileView === 'performance_detail'} className="space-y-6">
          <div data-touch-active={mobileView === 'performance'} className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Total interns" value={overview.total_students || 0} note={`${overview.deployed || 0} deployed to HTEs`} icon={FiUsers} />
            <MetricCard label="Active interns" value={overview.active || 0} note={`${deploymentRate}% deployment coverage`} icon={FiActivity} tone="green" />
            <MetricCard label="Average OJT progress" value={`${overview.average_progress || 0}%`} note="Across active interns" icon={FiTrendingUp} tone="blue" />
            <MetricCard label="Completed internships" value={overview.completed || 0} note={`${overview.pending || 0} pending deployment`} icon={FiCheckCircle} tone="violet" />
          </div>

          <div data-touch-active={mobileView === 'performance'} className="grid gap-6 xl:grid-cols-[370px_1fr]">
            <Panel title="Internship Status Distribution" subtitle="Students grouped by current internship stage" icon={FiPieChart}>
              <div className="flex justify-center"><Ring value={statusTotal ? ((overview.active || 0) / statusTotal) * 100 : 0} label="active interns" color="#059669" /></div>
              <div className="mt-6 space-y-3">{(analytics.internship_status || []).map((item) => <div key={item.label} className="flex items-center gap-3"><span className="h-2.5 w-2.5 rounded-full" style={{ background: item.color }} /><span className="flex-1 text-xs font-bold text-slate-500">{item.label}</span><span className="text-xs font-black text-slate-700 dark:text-gray-200">{item.value}</span></div>)}</div>
            </Panel>

            <Panel title="Section Performance Comparison" subtitle={`OJT progress and attendance across ${sectionLabel.toLowerCase()}`} icon={FiBarChart2}>
              {filteredPrograms.length ? <div className="space-y-6">{filteredPrograms.map((program) => <div key={program.program}><div className="mb-2 flex flex-wrap items-end justify-between gap-2"><div><p className="text-sm font-black text-slate-700 dark:text-white print:text-black">{program.program}</p><p className="mt-1 text-[10px] text-slate-400">{program.active} active of {program.students} students</p></div><div className="flex gap-4 text-right"><div><p className="text-xs font-black text-[#7D001D] dark:text-rose-300">{program.progress}%</p><p className="text-[8px] uppercase text-slate-400">Progress</p></div><div><p className="text-xs font-black text-emerald-600">{program.attendance_rate}%</p><p className="text-[8px] uppercase text-slate-400">Attendance</p></div></div></div><div className="grid grid-cols-2 gap-2"><ProgressBar value={program.progress} /><ProgressBar value={program.attendance_rate} color="bg-emerald-500" /></div></div>)}</div> : <div className="py-16 text-center text-sm font-bold text-slate-400">No section performance records are available.</div>}
            </Panel>
          </div>

          <div data-touch-active={mobileView === 'performance_detail'} className="grid gap-6 xl:grid-cols-2">
            <Panel title="Seven-Day Attendance Performance" subtitle="Daily attendance rate across internship programs" icon={FiActivity}>
              {attendance.trend?.length ? <div className="flex h-56 items-end justify-between gap-3 border-b border-slate-200 px-2 pb-1 dark:border-gray-700">{attendance.trend.map((day) => <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2"><span className="text-[9px] font-black text-slate-400">{day.rate}%</span><div className={`w-full max-w-12 rounded-t-xl ${day.rate >= 90 ? 'bg-gradient-to-t from-emerald-700 to-emerald-400' : day.rate > 0 ? 'bg-gradient-to-t from-[#7D001D] to-[#b82929]' : 'bg-slate-200 dark:bg-gray-700'}`} style={{ height: `${Math.max(day.rate, 4)}%` }} /><span className="text-[9px] font-black text-slate-500">{day.label}</span></div>)}</div> : <div className="py-16 text-center text-sm font-bold text-slate-400">No recent attendance trend is available.</div>}
            </Panel>

            <Panel title="Performance Outcomes" subtitle="Key internship productivity and completion indicators" icon={FiTrendingUp}>
              <div className="space-y-5">
                {[['Average OJT progress', overview.average_progress, 'bg-[#7D001D]'], ['Attendance performance', attendance.rate, 'bg-emerald-500'], ['Requirement completion', requirements.completion_rate, 'bg-blue-500'], ['Deployment coverage', deploymentRate, 'bg-violet-500']].map(([label, value, color]) => <div key={label}><div className="mb-2 flex justify-between text-xs"><span className="font-bold text-slate-600 dark:text-gray-300">{label}</span><span className="font-black text-slate-800 dark:text-white print:text-black">{value || 0}%</span></div><ProgressBar value={value} color={color} /></div>)}
              </div>
            </Panel>
          </div>

          <Panel data-touch-active={mobileView === 'performance_detail'} title="Section Performance Detail" subtitle={`${filteredPrograms.length} academic sections shown`} icon={FiUsers}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700 print:border-slate-300"><th className="pb-3 font-black">Section</th><th className="pb-3 font-black">Students</th><th className="pb-3 font-black">Active Interns</th><th className="pb-3 font-black">OJT Progress</th><th className="pb-3 font-black">Attendance Rate</th><th className="pb-3 font-black">Performance Standing</th></tr></thead>
                <tbody>{filteredPrograms.map((program) => { const score = Math.round((Number(program.progress) + Number(program.attendance_rate)) / 2); return <tr key={program.program} className="border-b border-slate-100 last:border-0 dark:border-gray-700 print:border-slate-200"><td className="py-4 text-sm font-black text-slate-800 dark:text-white print:text-black">{program.program}</td><td className="py-4 text-xs font-black text-slate-600 dark:text-gray-300">{program.students}</td><td className="py-4 text-xs font-black text-emerald-600">{program.active}</td><td className="w-40 py-4"><div className="mb-1 text-right text-[9px] font-black text-[#7D001D] dark:text-rose-300">{program.progress}%</div><ProgressBar value={program.progress} /></td><td className="w-40 py-4"><div className="mb-1 text-right text-[9px] font-black text-emerald-600">{program.attendance_rate}%</div><ProgressBar value={program.attendance_rate} color="bg-emerald-500" /></td><td className="py-4"><span className={`rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${score >= 85 ? 'bg-emerald-100 text-emerald-700' : score >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>{score >= 85 ? 'High performing' : score >= 70 ? 'Developing' : 'Needs support'}</span></td></tr>; })}</tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'hte_deployment_review' && (
        <Panel title="HTE Deployment Review Report" subtitle={`${report.deployment_reviews?.length || 0} deployment records in the selected program scope`} icon={FiUsers}>
          <div className="overflow-x-auto"><table className="w-full min-w-[1180px] text-left"><thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3">Student</th><th className="pb-3">HTE / Batch</th><th className="pb-3">Proposed Date</th><th className="pb-3">Working Schedule</th><th className="pb-3">Requirements</th><th className="pb-3">Coordinator Submission</th><th className="pb-3">Review Status</th><th className="pb-3">Actions</th></tr></thead><tbody>{report.deployment_reviews?.length ? report.deployment_reviews.map((row) => <tr key={row.student_id} className="border-b border-slate-100 text-xs last:border-0 dark:border-gray-700"><td className="py-4"><p className="font-black text-slate-800 dark:text-white">{row.student}</p><p className="mt-1 text-[9px] text-slate-400">{row.student_id}</p></td><td className="py-4"><p className="font-bold">{row.hte}</p><p className="mt-1 text-[9px] text-slate-400">{row.batch}</p></td><td className="py-4">{row.proposed_date || 'Not set'}</td><td className="max-w-72 py-4">{row.working_schedule}</td><td className="py-4 font-bold">{row.requirements_readiness}</td><td className="py-4">{row.submitted_at ? new Date(row.submitted_at).toLocaleString('en-PH') : 'Not submitted'}</td><td className="py-4"><StatusPill value={row.review_status} /></td><td className="py-4">{row.review_status === 'pending' && row.approval_id ? <div className="flex gap-2"><button disabled={reviewingDeployment === row.approval_id} onClick={() => reviewDeployment(row, 'forwarded')} className="rounded-lg bg-emerald-600 px-3 py-2 text-[9px] font-black uppercase text-white disabled:opacity-50">Forward</button><button disabled={reviewingDeployment === row.approval_id} onClick={() => reviewDeployment(row, 'returned')} className="rounded-lg bg-rose-100 px-3 py-2 text-[9px] font-black uppercase text-rose-700 disabled:opacity-50">Return</button></div> : <span className="text-[9px] font-bold text-slate-400">No action available</span>}</td></tr>) : <tr><td colSpan="8" className="py-14 text-center font-bold text-slate-400">No deployment records are available.</td></tr>}</tbody></table></div>
        </Panel>
      )}

      {activeTab === 'travel_monitoring_review' && (
        <Panel title="Travel Monitoring Review Report" subtitle={`${report.travel_reviews?.length || 0} submitted and generated travel reports`} icon={FiShield}>
          <div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left"><thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3">Reference</th><th className="pb-3">Students</th><th className="pb-3">Destination</th><th className="pb-3">Departure / Arrival</th><th className="pb-3">Checkpoints</th><th className="pb-3">Photo</th><th className="pb-3">Status</th></tr></thead><tbody>{report.travel_reviews?.length ? report.travel_reviews.map((row) => <tr key={row.report_id} className="border-b border-slate-100 text-xs last:border-0 dark:border-gray-700"><td className="py-4 font-black text-[#7D001D] dark:text-rose-300">{row.reference}</td><td className="max-w-56 py-4">{row.students}</td><td className="py-4 font-bold">{row.destination}</td><td className="py-4"><p>{row.departure || 'Not recorded'}</p><p className="mt-1 text-slate-400">{row.arrival || 'Not recorded'}</p></td><td className="max-w-64 py-4">{row.checkpoints || 'No checkpoints'}</td><td className="py-4">{row.photo_evidence ? 'Available' : 'None'}</td><td className="py-4"><StatusPill value={row.review_status} /></td></tr>) : <tr><td colSpan="7" className="py-14 text-center font-bold text-slate-400">No travel reports are available.</td></tr>}</tbody></table></div>
        </Panel>
      )}

      <footer className="hidden border-t border-slate-300 pt-3 text-[9px] text-slate-500 print:flex print:items-center print:justify-between"><span>Generated from the AIMS Program Head Analytics Center</span><span>Generated {new Date().toLocaleString('en-PH')}</span></footer>
    </div>
  );
}

export default ProgramHeadReports;
