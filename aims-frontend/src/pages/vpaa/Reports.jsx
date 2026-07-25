import { useEffect, useMemo, useState } from 'react';
import {
  ArrowDownTrayIcon,
  BuildingOffice2Icon,
  ChartBarIcon,
  CheckBadgeIcon,
  ClipboardDocumentCheckIcon,
  MapPinIcon,
  PrinterIcon,
  UserGroupIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

function HorizontalBars({ rows, color = '#800000' }) {
  const max = Math.max(...rows.map((row) => Number(row.value) || 0), 1);
  return <div className="space-y-4">{rows.map((row) => <div key={row.label}><div className="mb-1.5 flex justify-between text-sm"><span className="font-bold text-slate-700 dark:text-slate-200">{row.label}</span><span className="font-black">{row.value}</span></div><div className="h-2.5 rounded-full bg-slate-100 dark:bg-slate-700"><div className="h-full rounded-full" style={{ width: `${(Number(row.value) / max) * 100}%`, backgroundColor: color }} /></div></div>)}</div>;
}

export default function VPAAReports() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => { api.get('/vpaa/dashboard').then((response) => setData(response.data)).catch(() => toast.error('Unable to generate executive reports.')).finally(() => setLoading(false)); }, []);
  const statusRows = useMemo(() => (data?.analytics?.internship_status || []).map((row) => ({ label: row.label || row.status, value: row.value ?? row.total })), [data]);
  const programRows = useMemo(() => (data?.analytics?.program_performance || []).slice(0, 8).map((row) => ({ label: row.program || row.code || 'Program', value: row.average_progress ?? row.progress ?? 0 })), [data]);
  const exportCsv = () => {
    const overview = data?.overview || {};
    const rows = [['AIMS VPAA Executive Report'], ['Generated', new Date().toLocaleString()], [], ['Metric', 'Value'], ...Object.entries(overview).map(([key, value]) => [key.replaceAll('_', ' '), value]), [], ['Compliance metric', 'Percent'], ...Object.entries(data?.compliance || {}).filter(([, value]) => typeof value === 'number').map(([key, value]) => [key.replaceAll('_', ' '), value])];
    const csv = rows.map((row) => row.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); const link = document.createElement('a'); link.href = url; link.download = `vpaa-executive-report-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url);
  };
  if (loading) return <LoadingPanel />;
  const overview = data?.overview || {}; const compliance = data?.compliance || {}; const travel = data?.travel || {};
  return <div className="space-y-6">
    <PageIntro eyebrow="Executive decision support" title="University Internship Reports" description="Consolidated participation, compliance, attendance, MOA, travel, and performance information for executive review." icon={ChartBarIcon} actions={<><button onClick={() => window.print()} className="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 font-bold"><PrinterIcon className="h-5 w-5" />Print</button><button onClick={exportCsv} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-4 py-3 font-black text-[#430909]"><ArrowDownTrayIcon className="h-5 w-5" />Export CSV</button></>} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Student interns" value={overview.total_students} icon={UserGroupIcon} /><MetricCard label="Active internships" value={overview.active} icon={ChartBarIcon} tone="blue" /><MetricCard label="Partner HTEs" value={overview.partner_htes} icon={BuildingOffice2Icon} tone="gold" /><MetricCard label="Approved MOAs" value={overview.approved_moas} icon={CheckBadgeIcon} tone="green" /></div>
    <div className="grid gap-6 xl:grid-cols-2">
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black">University compliance</h2><p className="mb-6 mt-1 text-sm text-slate-500">Cross-workflow completion and verification rates.</p><HorizontalBars rows={[['Overall compliance', compliance.overall_score], ['Requirements approved', compliance.requirements_rate], ['Attendance verified', compliance.attendance_verification_rate], ['MOA coverage', compliance.moa_coverage_rate], ['Travel checkpoints verified', compliance.travel_verification_rate]].map(([label, value]) => ({ label, value: Number(value || 0) }))} color="#0f766e" /></section>
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black">Internship status</h2><p className="mb-6 mt-1 text-sm text-slate-500">Current distribution of all student internships.</p><HorizontalBars rows={statusRows} color="#800000" /></section>
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black">Program performance</h2><p className="mb-6 mt-1 text-sm text-slate-500">Average internship progress by program.</p><HorizontalBars rows={programRows} color="#a8750b" /></section>
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200"><MapPinIcon className="h-6 w-6" /></div><div><h2 className="text-xl font-black">Travel monitoring</h2><p className="mt-1 text-sm text-slate-500">University-issued student travel sessions.</p></div></div><div className="mt-7 grid grid-cols-2 gap-4">{[['Active now', travel.active], ['This month', travel.total_this_month], ['Completed', travel.completed_this_month], ['Completion rate', `${Number(travel.completion_rate || 0).toFixed(1)}%`]].map(([label, value]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><p className="text-xs font-bold text-slate-500">{label}</p><p className="mt-1 text-2xl font-black">{value || 0}</p></div>)}</div></section>
    </div>
    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-center gap-3"><ClipboardDocumentCheckIcon className="h-6 w-6 text-[#800000] dark:text-red-300" /><h2 className="text-xl font-black">Pending executive action</h2></div><div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">{Object.entries(data?.pending_approvals || {}).filter(([key]) => key !== 'total').map(([key, value]) => <div key={key} className="rounded-2xl border border-slate-200 p-4 text-center dark:border-slate-700"><p className="text-2xl font-black text-[#800000] dark:text-red-300">{value}</p><p className="mt-1 text-xs font-bold capitalize text-slate-500">{key.replaceAll('_', ' ')}</p></div>)}</div></section>
  </div>;
}
