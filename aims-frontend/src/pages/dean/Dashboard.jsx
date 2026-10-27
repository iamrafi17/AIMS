import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ArrowPathIcon, CheckCircleIcon, ClockIcon, DocumentCheckIcon, MapIcon, UserGroupIcon } from '@heroicons/react/24/outline';
import api from '../../services/api';

const colors = { active: '#800020', pending: '#d4af37', completed: '#16856a', dropped: '#64748b', other: '#94a3b8' };
const card = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900';
const actions = [
  { key: 'pending_deployments', label: 'Pending Deployments', note: 'Ready to forward to VPAA', path: '/dean/deployments', icon: CheckCircleIcon },
  { key: 'pending_moas', label: 'Pending MOAs', note: 'Awaiting Dean final approval', path: '/dean/moas', icon: DocumentCheckIcon },
  { key: 'pending_travel_reports', label: 'Pending Travel Reports', note: 'Awaiting validation', path: '/dean/travel', icon: MapIcon },
];
function Panel({ title, note, children, className = '' }) {
  return <section className={`${card} ${className}`}><h2 className="font-extrabold text-slate-900 dark:text-white">{title}</h2><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{note}</p>{children}</section>;
}
function Empty({ children = 'No data available for this selection.' }) {
  return <div className="mt-4 flex items-center justify-center rounded-xl border border-dashed border-slate-200 px-4 text-center text-sm text-slate-500 dark:border-slate-700" style={{ minHeight: 170 }}>{children}</div>;
}
function StatusDonut({ data, selected, onSelect }) {
  let offset = 0;
  return <div className="relative mx-auto mt-4 h-52 w-52" role="img" aria-label="Internship status distribution">
    <svg viewBox="0 0 120 120" className="h-full w-full -rotate-90">
      <circle cx="60" cy="60" r="44" fill="none" stroke="#e2e8f0" strokeWidth="18" />
      {data.map((item) => {
        const start = offset;
        offset += item.percentage;
        return <circle key={item.status} cx="60" cy="60" r="44" fill="none" stroke={colors[item.status] || colors.other} strokeWidth="18"
          strokeDasharray={`${item.percentage} ${100 - item.percentage}`} strokeDashoffset={-start} pathLength="100"
          opacity={selected && selected !== item.status ? 0.25 : 1} className="cursor-pointer transition-opacity"
          onClick={() => onSelect(item.status)}><title>{item.status}: {item.count} students ({item.percentage}%)</title></circle>;
      })}
    </svg>
    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"><strong className="text-3xl text-[#690016] dark:text-white">{selected ? data.find((item) => item.status === selected)?.count : data.reduce((sum, item) => sum + item.count, 0)}</strong><span className="text-xs font-semibold capitalize text-slate-500">{selected || 'Students'}</span></div>
  </div>;
}

export default function DeanDashboard() {
  const [params, setParams] = useSearchParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('');
  const requestId = useRef(0);
  const periodId = params.get('academic_period_id') || '';
  const programId = params.get('program_id') || '';
  const load = useCallback(async (refresh = false) => {
    const id = ++requestId.current;
    if (refresh) setRefreshing(true); else { setLoading(true); setData(null); }
    setError('');
    try {
      const response = await api.get('/dean/dashboard', { params: {
        ...(periodId ? { academic_period_id: periodId } : {}),
        ...(programId ? { program_id: programId } : {}),
      } });
      if (id === requestId.current) { setData(response.data); setSelectedStatus(''); }
    } catch (requestError) {
      if (id === requestId.current) setError(requestError.response?.data?.message || 'Unable to load the Dean dashboard.');
    } finally { if (id === requestId.current) { setLoading(false); setRefreshing(false); } }
  }, [periodId, programId]);
  useEffect(() => { load(); }, [load]);
  const changeFilter = (key, value) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value); else next.delete(key);
    setParams(next);
  };
  const filters = data?.filters;
  const summary = data?.summary || {};
  const charts = data?.charts || {};
  const period = filters?.academic_periods?.find((item) => item.id === filters.academic_period_id);
  const statusData = charts.internship_status || [];
  const totalPending = actions.reduce((sum, item) => sum + (summary[item.key] || 0), 0);
  const metrics = [
    { label: 'Total OJT Students', value: summary.total_students, note: 'College internship records', icon: UserGroupIcon },
    { label: 'Active Interns', value: summary.active_interns, note: 'Currently undergoing OJT', icon: ClockIcon },
    ...actions.map((item) => ({ ...item, value: summary[item.key] })),
  ];
  return <div className="dean-dashboard-page space-y-5 pb-8">
    <header className="dean-dashboard-header rounded-3xl bg-[#690016] px-5 py-5 text-white sm:px-7">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div><p className="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-200">Dean workspace</p><h1 className="mt-1 text-2xl font-black text-white sm:text-3xl">{data?.college?.name || 'College Overview'}</h1><p className="mt-1 text-sm text-white/80">Monitor internship performance, attendance, and pending college decisions.</p>{period && <p className="mt-2 text-xs font-bold text-amber-100">AY {period.academic_year} · {period.semester} · {period.status === 'active' ? 'Active' : 'Historical view'}</p>}</div>
        <button type="button" onClick={() => load(true)} disabled={loading || refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-2 text-sm font-bold text-white hover:bg-white/10 disabled:opacity-50"><ArrowPathIcon className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />Refresh</button>
      </div>
      <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
        <label className="text-xs font-bold text-white/85">Academic Period<select aria-label="Academic Period" value={periodId || filters?.academic_period_id || ''} onChange={(event) => changeFilter('academic_period_id', event.target.value)} className="mt-1 block w-full rounded-xl border border-white/30 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900"><option value="">Default period</option>{filters?.academic_periods?.map((item) => <option key={item.id} value={item.id}>AY {item.academic_year} · {item.semester}{item.status === 'active' ? ' (Active)' : ''}</option>)}</select></label>
        <label className="text-xs font-bold text-white/85">Program<select aria-label="Program" value={programId} onChange={(event) => changeFilter('program_id', event.target.value)} className="mt-1 block w-full rounded-xl border border-white/30 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900"><option value="">All Programs</option>{filters?.programs?.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label>
      </div>
    </header>
    {error && <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><span>{error}</span><button type="button" onClick={() => load(true)} className="rounded-lg bg-red-800 px-3 py-2 font-bold text-white">Retry</button></div>}
    {loading && !data ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Loading dashboard">{metrics.map((item) => <div key={item.label} className="h-32 animate-pulse rounded-2xl bg-slate-200 dark:bg-slate-800" />)}</div> : data && <>
      {!filters.can_review && <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Approval actions are available only for the active academic period.</p>}
      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="College metrics">{metrics.map((item) => {
        const Icon = item.icon;
        const content = <><div className="flex items-start justify-between gap-2"><p className="text-xs font-bold text-slate-500 dark:text-slate-400">{item.label}</p><Icon className="h-5 w-5 text-[#800020] dark:text-rose-300" /></div><p className="mt-3 text-3xl font-black text-[#690016] dark:text-white">{item.value ?? 0}</p><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{item.note}</p></>;
        return item.path && item.value > 0 && filters.can_review ? <Link key={item.label} to={item.path} className={`${card} block transition hover:border-[#800020] hover:shadow-md`}>{content}</Link> : <div key={item.label} className={card}>{content}</div>;
      })}</section>
      <div className="grid gap-4 xl:grid-cols-2">
        <Panel title="Internship Status" note="Students by internship stage in the selected period">
          {statusData.length ? <><StatusDonut data={statusData} selected={selectedStatus} onSelect={(status) => setSelectedStatus((current) => current === status ? '' : status)} /><div className="mt-4 flex flex-wrap justify-center gap-2">{statusData.map((item) => <button type="button" key={item.status} onClick={() => setSelectedStatus((current) => current === item.status ? '' : item.status)} aria-pressed={selectedStatus === item.status} className={`rounded-full border px-3 py-1.5 text-xs font-bold capitalize ${selectedStatus === item.status ? 'border-[#800020] bg-rose-50 text-[#800020]' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'}`}><span className="mr-1.5 inline-block h-2 w-2 rounded-full" style={{ backgroundColor: colors[item.status] || colors.other }} />{item.status}: {item.count} ({item.percentage}%)</button>)}</div></> : <Empty />}
        </Panel>
        <Panel title="OJT Progress by Program" note="Average verified hours completed against required OJT hours">
          {charts.program_progress?.length ? <div className="mt-6 space-y-5" style={{ minHeight: 190 }}>{charts.program_progress.map((item) => <div key={item.program_id} title={`${item.name}: ${item.average_progress}% average, ${item.interns} interns`}><div className="mb-2 flex items-center justify-between gap-3 text-sm"><span className="font-bold text-slate-800 dark:text-white">{item.program}</span><span className="font-bold text-[#800020] dark:text-rose-300">{item.average_progress}% <span className="font-normal text-slate-500">· {item.interns} intern{item.interns === 1 ? '' : 's'}</span></span></div><div className="h-4 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700"><div className="h-full rounded-full bg-[#800020]" style={{ width: `${item.average_progress}%` }} /></div></div>)}</div> : <Empty />}
        </Panel>
      </div>
      <Panel title="Monthly Attendance Trend" note="Unique verified intern attendance days per month">
        {charts.monthly_attendance?.length ? <div className="mt-4 h-64"><ResponsiveContainer width="100%" height="100%"><AreaChart data={charts.monthly_attendance} margin={{ left: 0, right: 18 }}><defs><linearGradient id="deanAttendance" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="#800020" stopOpacity={0.28} /><stop offset="95%" stopColor="#800020" stopOpacity={0} /></linearGradient></defs><CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="month" tickFormatter={(value) => new Date(`${value}-01T00:00:00`).toLocaleDateString('en-PH', { month: 'short', year: '2-digit' })} /><YAxis allowDecimals={false} /><Tooltip formatter={(value) => [`${value} attendance days`, 'Verified days']} /><Area type="monotone" dataKey="days" stroke="#800020" strokeWidth={3} fill="url(#deanAttendance)" /></AreaChart></ResponsiveContainer></div> : <Empty>No verified attendance days for this selection.</Empty>}
      </Panel>
      <Panel title="Action Center" note="Current items requiring Dean review">
        <div className="mt-4 flex flex-wrap items-center gap-2 text-sm font-bold text-[#800020] dark:text-rose-300"><span className="rounded-full bg-rose-50 px-3 py-1 dark:bg-rose-950/30">{totalPending} pending</span>{totalPending === 0 && <span className="font-normal text-slate-500">No pending actions for this selection.</span>}</div>
        <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-700">{data.pending_actions?.map((item) => <div key={item.key} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="text-sm font-bold text-slate-900 dark:text-white">{item.label}</p><p className="text-xs text-slate-500">{item.count} awaiting review</p></div>{item.count > 0 && filters.can_review ? <Link to={item.path} className="rounded-lg bg-[#800020] px-3 py-2 text-xs font-bold text-white">Review</Link> : <span className="text-xs text-slate-400">{item.count} pending</span>}</div>)}</div>
      </Panel>
      <Panel title="Quick Access" note="Open Dean tools and approval pages">
        <div className="mt-4 grid gap-3 sm:grid-cols-3">{[['Live Map', '/dean/live-map', 'Timed-in interns sharing fresh GPS locations'], ['MOA Approvals', '/dean/moas', 'College MOA decisions'], ['Travel Reports', '/dean/travel', 'Travel validation records']].map(([label, path, note]) => <Link key={path} to={path} className="rounded-xl border border-slate-200 p-4 transition hover:border-[#800020] dark:border-slate-700"><p className="text-sm font-extrabold text-[#800020] dark:text-rose-300">{label} →</p><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{note}</p></Link>)}</div>
      </Panel>
    </>}
  </div>;
}
