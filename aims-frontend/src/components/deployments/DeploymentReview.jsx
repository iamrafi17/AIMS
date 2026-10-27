import { useCallback, useEffect, useMemo, useState } from 'react';
import { FiEye, FiRefreshCw, FiX } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { deploymentStageLabel } from '../../utils/deployment';
const date = (value) => value ? new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : 'Pending';
const fieldClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900';

export default function DeploymentReview({ role = 'program_head' }) {
  const base = role === 'program_head' ? '/program-head' : `/${role}`;
  const [data, setData] = useState({ deployments: [], summary: {} });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [stage, setStage] = useState('');
  const [selected, setSelected] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);
  const load = useCallback(async () => {
    setLoading(true); setError('');
    try { setData((await api.get(`${base}/deployments`)).data); }
    catch (err) { setError(err.response?.data?.message || 'Unable to load deployments.'); }
    finally { setLoading(false); }
  }, [base]);
  useEffect(() => { load(); }, [load]);
  const records = useMemo(() => data.deployments.filter((item) => (!stage || item.stage === stage)
    && `${item.student?.name} ${item.student?.student_id} ${item.hte?.name} ${item.department?.name}`.toLowerCase().includes(search.toLowerCase())), [data.deployments, search, stage]);
  const canAct = selected?.is_current && ((role === 'program_head' && selected.stage === 'for_program_head_review')
    || (role === 'dean' && selected.stage === 'for_dean_forwarding'));
  const act = async () => {
    if (!canAct || busy) return;
    setBusy(true);
    try {
      const response = role === 'program_head'
        ? await api.put(`${base}/deployments/${selected.approval_id}/review`, { decision: 'approved', remarks: remarks.trim() || null })
        : await api.post(`${base}/deployments/${selected.approval_id}/forward`);
      toast.success(response.data.message); setSelected(null); setRemarks(''); await load();
    } catch (err) { toast.error(err.response?.data?.message || 'Unable to save the deployment handoff.'); }
    finally { setBusy(false); }
  };
  const title = role === 'program_head' ? 'Deployment Approval' : role === 'dean' ? 'Deployment Review' : 'Student Deployments';
  return <section className="space-y-5">
    <header className="flex flex-wrap items-start justify-between gap-3 rounded-2xl bg-[#690016] p-6 text-white"><div><h2 className="text-2xl font-black">{title}</h2><p className="mt-2 text-sm text-white/75">{role === 'program_head'
      ? 'Review Coordinator-approved HTE and department assignments, then approve and forward to the Dean.'
      : role === 'dean' ? 'View Program Head-approved deployments from your college and forward them to VPAA.'
        : 'View student HTE and department assignments forwarded by the Dean.'}</p></div><button type="button" disabled={loading} onClick={load} className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-2 text-sm disabled:opacity-50"><FiRefreshCw /> Refresh</button></header>
    <div className="grid gap-3 sm:grid-cols-2">{[['Records', data.summary.total], [role === 'vpaa' ? 'Dean-forwarded records' : 'Awaiting your action', role === 'vpaa' ? data.summary.forwarded : data.summary.pending]].map(([label, value]) => <div key={label} className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-2xl font-black">{value ?? 0}</p></div>)}</div>
    <div className="flex flex-wrap gap-3"><input aria-label="Search deployments" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search student, HTE or department..." className={`${fieldClass} flex-1`} /><select aria-label="Deployment stage" value={stage} onChange={(event) => setStage(event.target.value)} className={`${fieldClass} sm:w-64`}><option value="">All stages</option>{['for_program_head_review', 'for_dean_forwarding', 'forwarded_to_vpaa', 'superseded'].map((value) => <option key={value} value={value}>{deploymentStageLabel(value)}</option>)}</select></div>
    {error ? <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{error} <button onClick={load} className="ml-2 underline">Retry</button></div> : loading ? <p role="status" className="p-8 text-center text-slate-500">Loading deployments...</p>
      : <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800"><tr>{['Student', 'HTE / Department', 'Coordinator', 'Stage', 'Action'].map((label) => <th key={label} className="p-4">{label}</th>)}</tr></thead><tbody>{records.length ? records.map((item) => <tr key={item.approval_id} className="border-t border-slate-100 dark:border-slate-800"><td className="p-4"><p className="font-bold">{item.student?.name}</p><p className="text-xs text-slate-500">{item.student?.student_id} · {item.student?.program} {item.student?.section}</p></td><td className="p-4"><p className="font-bold">{item.hte?.name}</p><p className="text-xs text-slate-500">{item.department?.name || 'No department assigned'}</p></td><td className="p-4">{item.coordinator}</td><td className="p-4"><p>{deploymentStageLabel(item.stage)}</p>{!item.is_current && <p className="text-xs text-slate-500">Historical assignment</p>}</td><td className="p-4"><button type="button" onClick={() => { setSelected(item); setRemarks(''); }} className="inline-flex items-center gap-2 rounded-lg bg-[#7D001D] px-3 py-2 font-bold text-white"><FiEye /> View Details</button></td></tr>) : <tr><td colSpan="5" className="p-10 text-center text-slate-500">No deployments match this view.</td></tr>}</tbody></table></div>}
    {selected && <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-3" onMouseDown={(event) => event.target === event.currentTarget && !busy && setSelected(null)}><div role="dialog" aria-modal="true" aria-label="Deployment details" className="max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900"><div className="flex justify-between gap-3"><div><h3 className="text-xl font-black">{selected.student?.name}</h3><p className="mt-1 text-sm text-slate-500">{selected.student?.student_id} · {deploymentStageLabel(selected.stage)}</p></div><button type="button" aria-label="Close deployment details" disabled={busy} onClick={() => setSelected(null)}><FiX /></button></div>
      <dl className="mt-5 grid gap-3 sm:grid-cols-2">{[['Program / Section', `${selected.student?.program || ''} ${selected.student?.section || ''}`], ['Final HTE', selected.hte?.name], ['Final Department', selected.department?.name], ['Supervisor', selected.supervisor?.name], ['Student preferred HTE', selected.preference?.hte], ['Student preferred department', selected.preference?.department], ['OJT start date', selected.schedule?.start_date], ['Required OJT hours', selected.schedule?.required_hours]].map(([label, value]) => <div key={label} className="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 font-bold">{value ?? 'Not assigned'}</dd></div>)}</dl>
      <h4 className="mt-5 font-bold">Working schedule</h4><div className="mt-2 grid gap-2 text-sm sm:grid-cols-2">{Object.entries(selected.schedule?.days || {}).filter(([, day]) => day.is_working).map(([key, day]) => <p key={key} className="rounded-lg border border-slate-200 p-2 dark:border-slate-700"><b className="uppercase">{key}</b>: {day.am_start?.slice(0, 5)}–{day.am_end?.slice(0, 5)} / {day.pm_start?.slice(0, 5)}–{day.pm_end?.slice(0, 5)}</p>)}</div>
      <h4 className="mt-5 font-bold">Handoff history</h4><ol className="mt-2 space-y-2 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800"><li>Coordinator approved: <b>{selected.coordinator}</b> · {date(selected.coordinator_approved_at)}</li><li>Program Head approved: <b>{selected.program_head || 'Pending'}</b> · {date(selected.program_head_approved_at)}</li><li>Dean forwarded to VPAA: <b>{selected.dean || 'Pending'}</b> · {date(selected.dean_forwarded_at)}</li></ol>
      {selected.remarks && <p className="mt-3 whitespace-pre-wrap text-sm">Program Head remarks: {selected.remarks}</p>}
      {canAct && <div className="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">{role === 'program_head' && <label className="mb-4 block text-sm font-bold">Review remarks (optional)<textarea maxLength={2000} value={remarks} onChange={(event) => setRemarks(event.target.value)} className={`${fieldClass} mt-2`} /></label>}<button type="button" disabled={busy} onClick={act} className="rounded-xl bg-[#7D001D] px-5 py-3 font-bold text-white disabled:opacity-50">{busy ? 'Saving...' : role === 'program_head' ? 'Review, Approve & Forward to Dean' : 'Forward to VPAA'}</button></div>}
    </div></div>}
  </section>;
}
