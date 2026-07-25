import { useEffect, useMemo, useState } from 'react';
import {
  ArrowDownTrayIcon,
  BuildingOffice2Icon,
  ChartBarIcon,
  ClockIcon,
  DocumentCheckIcon,
  PrinterIcon,
  UserGroupIcon,
  UsersIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

function Bars({ rows, labelKey = 'label', valueKey = 'total', color = '#800000' }) {
  const max = Math.max(...rows.map((row) => Number(row[valueKey]) || 0), 1);
  return <div className="space-y-4">{rows.map((row) => <div key={row[labelKey]}><div className="mb-1.5 flex items-center justify-between text-sm"><span className="font-bold capitalize text-slate-700 dark:text-slate-200">{String(row[labelKey]).replaceAll('_', ' ')}</span><span className="font-black text-slate-900 dark:text-white">{row[valueKey]}</span></div><div className="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700"><div className="h-full rounded-full transition-all duration-700" style={{ width: `${(Number(row[valueKey]) / max) * 100}%`, backgroundColor: color }} /></div></div>)}</div>;
}

export default function AdminReports() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/admin/reports').then((response) => setData(response.data)).catch(() => toast.error('Unable to generate system reports.')).finally(() => setLoading(false));
  }, []);

  const roleRows = useMemo(() => Object.entries(data?.users_by_role || {}).map(([label, total]) => ({ label, total })), [data]);
  const attendanceRows = useMemo(() => Object.entries(data?.attendance_by_status || {}).map(([label, total]) => ({ label, total })), [data]);
  const requirementRows = useMemo(() => Object.entries(data?.requirements_by_status || {}).map(([label, total]) => ({ label, total })), [data]);

  const exportCsv = () => {
    const lines = [
      ['AIMS System Report', new Date(data.generated_at).toLocaleString()],
      [],
      ['Metric', 'Total'],
      ...Object.entries(data.overview).map(([key, value]) => [key.replaceAll('_', ' '), value]),
      [],
      ['Users by role', 'Total'],
      ...roleRows.map((row) => [row.label, row.total]),
      [],
      ['Attendance status', 'Total'],
      ...attendanceRows.map((row) => [row.label, row.total]),
    ];
    const csv = lines.map((line) => line.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = `aims-system-report-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url);
  };

  if (loading) return <LoadingPanel />;
  const overview = data?.overview || {};
  return (
    <div className="space-y-6">
      <PageIntro eyebrow="System-wide analytics" title="Consolidated Reports" description="A university-level view of users, internships, HTE partnerships, requirements, attendance, and operational activity." icon={ChartBarIcon} actions={<><button onClick={() => window.print()} className="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 font-bold text-white"><PrinterIcon className="h-5 w-5" />Print</button><button onClick={exportCsv} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-4 py-3 font-black text-[#430909]"><ArrowDownTrayIcon className="h-5 w-5" />Export CSV</button></>} />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard label="All users" value={overview.users} icon={UsersIcon} />
        <MetricCard label="Student interns" value={overview.students} icon={UserGroupIcon} tone="blue" />
        <MetricCard label="Active internships" value={overview.active_interns} icon={ClockIcon} tone="green" />
        <MetricCard label="Partner HTEs" value={overview.partner_htes} icon={BuildingOffice2Icon} tone="gold" />
        <MetricCard label="Approved MOAs" value={overview.approved_moas} icon={DocumentCheckIcon} tone="violet" />
        <MetricCard label="Attendance records" value={overview.attendance_records} icon={ChartBarIcon} tone="maroon" />
      </div>
      <div className="grid gap-6 xl:grid-cols-2">
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black text-slate-900 dark:text-white">Users by role</h2><p className="mb-6 mt-1 text-sm text-slate-500">Active and inactive registered accounts.</p><Bars rows={roleRows} /></section>
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black text-slate-900 dark:text-white">Attendance distribution</h2><p className="mb-6 mt-1 text-sm text-slate-500">All recorded attendance statuses.</p><Bars rows={attendanceRows} color="#0f766e" /></section>
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black text-slate-900 dark:text-white">Students by program</h2><p className="mb-6 mt-1 text-sm text-slate-500">Programs with the largest internship population.</p><Bars rows={(data?.students_by_program || []).map((row) => ({ label: row.code, total: row.students_count }))} color="#a8750b" /></section>
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 className="text-xl font-black text-slate-900 dark:text-white">Requirement workflow</h2><p className="mb-6 mt-1 text-sm text-slate-500">Submission and approval status across student files.</p><Bars rows={requirementRows} color="#6d28d9" /></section>
      </div>
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"><div><h2 className="text-xl font-black text-slate-900 dark:text-white">Attendance activity — last six months</h2><p className="mt-1 text-sm text-slate-500">Monthly volume of saved attendance records.</p></div><p className="text-xs text-slate-400">Generated {new Date(data.generated_at).toLocaleString()}</p></div><div className="mt-7 grid h-56 grid-cols-6 items-end gap-3">{(data?.monthly_attendance || []).map((month) => { const max = Math.max(...data.monthly_attendance.map((entry) => entry.total), 1); return <div key={month.label} className="flex h-full flex-col justify-end text-center"><span className="mb-2 text-xs font-black text-slate-700 dark:text-slate-200">{month.total}</span><div className="mx-auto w-full max-w-16 rounded-t-2xl bg-gradient-to-t from-[#800000] to-[#b33434]" style={{ height: `${Math.max((month.total / max) * 100, 3)}%` }} /><span className="mt-2 text-xs font-bold text-slate-500">{month.label}</span></div>; })}</div></section>
    </div>
  );
}
