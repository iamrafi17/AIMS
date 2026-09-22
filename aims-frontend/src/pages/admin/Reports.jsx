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

function Bars({ rows, labelKey = 'label', valueKey = 'total', color = '#7D001D' }) {
  const max = Math.max(...rows.map((row) => Number(row[valueKey]) || 0), 1);

  return (
    <div className="space-y-3 sm:space-y-4">
      {rows.map((row) => (
        <div key={row[labelKey]}>
          <div className="mb-1.5 flex items-center justify-between gap-3 text-xs sm:text-sm">
            <span className="min-w-0 truncate font-bold capitalize text-slate-700 dark:text-slate-200">{String(row[labelKey]).replaceAll('_', ' ')}</span>
            <span className="shrink-0 font-black text-slate-900 dark:text-white">{row[valueKey]}</span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700 sm:h-2.5">
            <div className="h-full rounded-full transition-all duration-700" style={{ width: `${(Number(row[valueKey]) / max) * 100}%`, backgroundColor: color }} />
          </div>
        </div>
      ))}
    </div>
  );
}

function ReportCard({ title, description, rows, color, compact = false }) {
  return (
    <section className={`rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 ${compact ? 'p-4' : 'p-6'}`}>
      <h2 className={`${compact ? 'text-base' : 'text-xl'} font-black text-slate-900 dark:text-white`}>{title}</h2>
      <p className={`${compact ? 'mb-4 text-xs' : 'mb-6 text-sm'} mt-1 text-slate-500`}>{description}</p>
      <Bars rows={rows} color={color} />
    </section>
  );
}

function AttendanceActivity({ data, compact = false }) {
  const months = data?.monthly_attendance || [];
  const max = Math.max(...months.map((entry) => Number(entry.total) || 0), 1);

  return (
    <section className={`rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 ${compact ? 'p-4' : 'p-6'}`}>
      <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
        <div>
          <h2 className={`${compact ? 'text-base' : 'text-xl'} font-black text-slate-900 dark:text-white`}>Attendance activity — last six months</h2>
          <p className={`${compact ? 'text-xs' : 'text-sm'} mt-1 text-slate-500`}>Monthly volume of saved attendance records.</p>
        </div>
        <p className="text-[10px] text-slate-400 sm:text-xs">Generated {new Date(data.generated_at).toLocaleString()}</p>
      </div>
      <div className={`${compact ? 'mt-5 h-40 gap-2' : 'mt-7 h-56 gap-3'} grid grid-cols-6 items-end`}>
        {months.map((month) => (
          <div key={month.label} className="flex h-full min-w-0 flex-col justify-end text-center">
            <span className="mb-2 text-[10px] font-black text-slate-700 dark:text-slate-200 sm:text-xs">{month.total}</span>
            <div className="mx-auto w-full max-w-16 rounded-t-xl bg-gradient-to-t from-[#7D001D] to-[#b33434] sm:rounded-t-2xl" style={{ height: `${Math.max((month.total / max) * 100, 3)}%` }} />
            <span className="mt-2 truncate text-[10px] font-bold text-slate-500 sm:text-xs">{month.label}</span>
          </div>
        ))}
      </div>
    </section>
  );
}

export default function AdminReports() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [mobileView, setMobileView] = useState('roles');

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
    const link = document.createElement('a');
    link.href = url;
    link.download = `aims-system-report-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  if (loading) return <LoadingPanel />;

  const overview = data?.overview || {};
  const programRows = (data?.students_by_program || []).map((row) => ({ label: row.code, total: row.students_count }));
  const mobileViews = [
    { id: 'roles', label: 'Users by role' },
    { id: 'attendance', label: 'Attendance distribution' },
    { id: 'programs', label: 'Students by program' },
    { id: 'requirements', label: 'Requirement workflow' },
    { id: 'activity', label: 'Six-month activity' },
  ];

  return (
    <div className="space-y-6">
      <PageIntro
        eyebrow="System-wide analytics"
        title="Consolidated Reports"
        description="A university-level view of users, internships, HTE partnerships, requirements, attendance, and operational activity."
        icon={ChartBarIcon}
        actions={(
          <>
            <button onClick={() => window.print()} className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 font-bold text-[#7D001D] shadow-sm transition hover:bg-slate-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"><PrinterIcon className="h-5 w-5" />Print</button>
            <button onClick={exportCsv} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-4 py-3 font-black text-[#690016]"><ArrowDownTrayIcon className="h-5 w-5" />Export CSV</button>
          </>
        )}
      />

      <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard label="All users" value={overview.users} icon={UsersIcon} />
        <MetricCard label="Student interns" value={overview.students} icon={UserGroupIcon} tone="blue" />
        <MetricCard label="Active internships" value={overview.active_interns} icon={ClockIcon} tone="green" />
        <MetricCard label="Partner HTEs" value={overview.partner_htes} icon={BuildingOffice2Icon} tone="gold" />
        <MetricCard label="Approved MOAs" value={overview.approved_moas} icon={DocumentCheckIcon} tone="violet" />
        <MetricCard label="Attendance records" value={overview.attendance_records} icon={ChartBarIcon} tone="maroon" />
      </div>

      <section className="touch-layout-only rounded-3xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:hidden">
        <label className="block">
          <span className="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">Detailed report</span>
          <select value={mobileView} onChange={(event) => setMobileView(event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950">
            {mobileViews.map((view) => <option key={view.id} value={view.id}>{view.label}</option>)}
          </select>
        </label>
      </section>

      <div className="touch-layout-only lg:hidden">
        {mobileView === 'roles' && <ReportCard compact title="Users by role" description="Active and inactive registered accounts." rows={roleRows} />}
        {mobileView === 'attendance' && <ReportCard compact title="Attendance distribution" description="All recorded attendance statuses." rows={attendanceRows} color="#0f766e" />}
        {mobileView === 'programs' && <ReportCard compact title="Students by program" description="Programs with the largest internship population." rows={programRows} color="#a8750b" />}
        {mobileView === 'requirements' && <ReportCard compact title="Requirement workflow" description="Submission and approval status across student files." rows={requirementRows} color="#6d28d9" />}
        {mobileView === 'activity' && <AttendanceActivity compact data={data} />}
      </div>

      <div className="desktop-layout-only hidden gap-6 lg:grid xl:grid-cols-2">
        <ReportCard title="Users by role" description="Active and inactive registered accounts." rows={roleRows} />
        <ReportCard title="Attendance distribution" description="All recorded attendance statuses." rows={attendanceRows} color="#0f766e" />
        <ReportCard title="Students by program" description="Programs with the largest internship population." rows={programRows} color="#a8750b" />
        <ReportCard title="Requirement workflow" description="Submission and approval status across student files." rows={requirementRows} color="#6d28d9" />
      </div>

      <div className="desktop-layout-only hidden lg:block"><AttendanceActivity data={data} /></div>
    </div>
  );
}
