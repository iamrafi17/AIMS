import { useEffect, useMemo, useState } from 'react';
import {
  CheckCircleIcon,
  ClockIcon,
  ComputerDesktopIcon,
  ShieldCheckIcon,
  UserGroupIcon,
  UsersIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import UserAvatar from '../../components/UserAvatar';
import { LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

export default function AdminDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => { api.get('/admin/dashboard').then((response) => setData(response.data)).catch(() => toast.error('Unable to load the administration dashboard.')).finally(() => setLoading(false)); }, []);
  const roleRows = useMemo(() => Object.entries(data?.user_statistics || {}), [data]);
  const totalUsers = roleRows.reduce((total, [, value]) => total + Number(value), 0);
  if (loading) return <LoadingPanel />;
  return <div className="space-y-6">
    <PageIntro eyebrow="System administration center" title="Administration Dashboard" description="Monitor platform access, role distribution, student records, attendance activity, and recent account usage across AIMS." icon={ShieldCheckIcon} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Registered users" value={totalUsers} icon={UsersIcon} /><MetricCard label="Active interns" value={data?.student_statistics?.active} icon={UserGroupIcon} tone="green" /><MetricCard label="Pending registrations" value={data?.student_statistics?.pending} icon={ClockIcon} tone="gold" /><MetricCard label="Completed internships" value={data?.student_statistics?.completed} icon={CheckCircleIcon} tone="blue" /></div>
    <div className="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#800000]/10 text-[#800000] dark:text-red-300"><UsersIcon className="h-6 w-6" /></div><div><h2 className="text-xl font-black">Accounts by role</h2><p className="mt-1 text-sm text-slate-500">Current role-based access distribution.</p></div></div><div className="mt-6 grid gap-3 sm:grid-cols-2">{roleRows.map(([role, count]) => <div key={role} className="flex items-center justify-between rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><span className="text-sm font-bold capitalize">{role.replace('_', ' ')}</span><span className="text-2xl font-black text-[#800000] dark:text-red-300">{count}</span></div>)}</div></section>
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200"><ClockIcon className="h-6 w-6" /></div><div><h2 className="text-xl font-black">Monthly attendance</h2><p className="mt-1 text-sm text-slate-500">System attendance records this month.</p></div></div><div className="mt-6 space-y-4">{[['Present', data?.attendance_statistics?.present, 'bg-emerald-500'], ['Late', data?.attendance_statistics?.late, 'bg-amber-500'], ['Absent', data?.attendance_statistics?.absent, 'bg-rose-500']].map(([label, count, color]) => { const max = Math.max(data?.attendance_statistics?.present || 0, data?.attendance_statistics?.late || 0, data?.attendance_statistics?.absent || 0, 1); return <div key={label}><div className="mb-2 flex justify-between text-sm"><span className="font-bold">{label}</span><span className="font-black">{count || 0}</span></div><div className="h-3 rounded-full bg-slate-100 dark:bg-slate-700"><div className={`h-full rounded-full ${color}`} style={{ width: `${((count || 0) / max) * 100}%` }} /></div></div>; })}</div></section>
    </div>
    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start gap-3 border-b border-slate-200 p-6 dark:border-slate-700"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200"><ComputerDesktopIcon className="h-6 w-6" /></div><div><h2 className="text-xl font-black">Recent account activity</h2><p className="mt-1 text-sm text-slate-500">Most recent authenticated user sessions.</p></div></div><div className="divide-y divide-slate-100 dark:divide-slate-800">{(data?.recent_activity || []).length ? data.recent_activity.map((user) => <div key={user.id} className="flex items-center justify-between gap-4 px-6 py-4"><div className="flex items-center gap-3"><UserAvatar user={user} className="h-10 w-10" /><div><p className="font-black">{user.name}</p><p className="text-xs capitalize text-slate-500">{user.role.replace('_', ' ')} · {user.email}</p></div></div><p className="text-xs font-semibold text-slate-400">{new Date(user.last_login_at).toLocaleString()}</p></div>) : <p className="p-10 text-center text-sm text-slate-500">No recent login activity.</p>}</div></section>
  </div>;
}
