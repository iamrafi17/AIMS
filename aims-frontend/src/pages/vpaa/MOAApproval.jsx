import { useCallback, useEffect, useState } from 'react';
import {
  ArrowDownTrayIcon,
  CheckBadgeIcon,
  CheckCircleIcon,
  ClockIcon,
  DocumentTextIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  ShieldCheckIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

function format(value) {
  return value ? new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Not set';
}

function DecisionModal({ item, onClose, onSaved }) {
  const [decision, setDecision] = useState('approved');
  const [remarks, setRemarks] = useState('');
  const [saving, setSaving] = useState(false);
  const submit = async () => {
    if (decision === 'rejected' && !remarks.trim()) return toast.error('Explain why the MOA is being rejected.');
    if (!window.confirm(`${decision === 'approved' ? 'Approve' : 'Reject'} this MOA as the final decision?`)) return;
    setSaving(true);
    try { const response = await api.put(`/vpaa/moas/${item.approval_id}`, { decision, remarks }); toast.success(response.data.message); onSaved(); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to record the MOA decision.'); }
    finally { setSaving(false); }
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}><div onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8"><div className="flex items-start justify-between"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Final MOA approval</p><h2 className="mt-1 text-2xl font-black text-slate-900 dark:text-white">{item.hte}</h2><p className="mt-1 text-sm text-slate-500">{item.college}</p></div><button onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button></div><div className="mt-6 grid gap-3 sm:grid-cols-2">{[['approved', CheckCircleIcon, 'Approve MOA', 'Authorize this HTE partnership.'], ['rejected', XCircleIcon, 'Reject MOA', 'Return it with final remarks.']].map(([value, Icon, title, note]) => <button key={value} onClick={() => setDecision(value)} className={`rounded-2xl border p-4 text-left ${decision === value ? value === 'approved' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : 'border-rose-500 bg-rose-50 dark:bg-rose-950/30' : 'border-slate-200 dark:border-slate-700'}`}><div className="flex items-center gap-2"><Icon className={`h-5 w-5 ${value === 'approved' ? 'text-emerald-600' : 'text-rose-600'}`} /><span className="font-black">{title}</span></div><p className="mt-1 text-xs text-slate-500">{note}</p></button>)}</div><label className="mt-5 block"><span className="mb-2 block text-sm font-bold">Decision remarks {decision === 'rejected' && '*'}</span><textarea required={decision === 'rejected'} value={remarks} onChange={(event) => setRemarks(event.target.value)} className="min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Record the basis, conditions, or corrective action..." /></label><div className="mt-6 flex justify-end gap-3"><button onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button onClick={submit} disabled={saving} className="rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white">{saving ? 'Recording...' : 'Record final decision'}</button></div></div></div>;
}

export default function VPAAMOA() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('pending');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [decision, setDecision] = useState(null);
  const load = useCallback(async () => { setLoading(true); try { setData((await api.get('/vpaa/moas')).data); } catch { toast.error('Unable to load MOA approvals.'); } finally { setLoading(false); } }, []);
  useEffect(() => { load(); }, [load]);
  const items = (data?.[tab] || []).filter((item) => `${item.hte} ${item.college} ${item.endorsed_by}`.toLowerCase().includes(search.toLowerCase()));
  const download = async (item) => { try { const response = await api.get(`/vpaa/moas/${item.approval_id}/download`, { responseType: 'blob' }); const url = URL.createObjectURL(response.data); const link = document.createElement('a'); link.href = url; link.download = `MOA-${item.hte}.pdf`; link.click(); URL.revokeObjectURL(url); } catch { toast.error('The MOA file could not be downloaded.'); } };
  if (loading && !data) return <LoadingPanel />;
  return <div className="space-y-6">
    <PageIntro eyebrow="Executive approval workspace" title="MOA Final Approval" description="Review Program Head-endorsed HTE agreements, inspect partnership validity, and record the final university decision." icon={ShieldCheckIcon} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Pending final review" value={data?.summary.pending} icon={ClockIcon} tone="gold" /><MetricCard label="Approved MOAs" value={data?.summary.approved} icon={CheckBadgeIcon} tone="green" /><MetricCard label="Rejected MOAs" value={data?.summary.rejected} icon={XCircleIcon} /><MetricCard label="Expiring in 60 days" value={data?.summary.expiring} icon={DocumentTextIcon} tone="violet" /></div>
    <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex flex-col gap-3 md:flex-row"><div className="flex rounded-2xl bg-slate-100 p-1 dark:bg-slate-800">{['pending', 'history'].map((name) => <button key={name} onClick={() => setTab(name)} className={`rounded-xl px-4 py-2.5 text-sm font-black capitalize ${tab === name ? 'bg-white text-[#800000] shadow-sm dark:bg-slate-700 dark:text-red-300' : 'text-slate-500'}`}>{name}</button>)}</div><label className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" placeholder="Search HTE, college, or endorser..." /></label></div></section>
    {items.length === 0 ? <EmptyPanel icon={DocumentTextIcon} title={`No ${tab} MOA records`} description={tab === 'pending' ? 'Program Head-endorsed MOAs will appear here for final review.' : 'Final approval decisions will appear here.'} /> : <div className="grid gap-5 xl:grid-cols-2">{items.map((item) => <article key={item.approval_id} className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{item.college_code} · HTE partnership</p><h2 className="mt-1 text-xl font-black text-slate-900 dark:text-white">{item.hte}</h2><p className="mt-1 text-sm text-slate-500">{item.hte_address}</p></div><span className={`rounded-full px-3 py-1 text-xs font-black capitalize ${item.approval_status === 'approved' ? 'bg-emerald-100 text-emerald-700' : item.approval_status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>{item.approval_status}</span></div><div className="mt-5 grid grid-cols-2 gap-3"><div className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p className="text-xs text-slate-500">Effective</p><p className="mt-1 text-sm font-black">{format(item.effective_date)}</p></div><div className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p className="text-xs text-slate-500">Expiration</p><p className="mt-1 text-sm font-black">{format(item.expiration_date)}</p></div></div><p className="mt-4 text-xs text-slate-500">Endorsed by <span className="font-bold text-slate-700 dark:text-slate-200">{item.endorsed_by || 'Program Head'}</span> on {format(item.endorsed_at)}</p>{item.remarks && <p className="mt-3 rounded-2xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">{item.remarks}</p>}<div className="mt-5 flex flex-wrap gap-2"><button onClick={() => setSelected(item)} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-slate-700"><EyeIcon className="h-4 w-4" />Details</button><button onClick={() => download(item)} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-slate-700"><ArrowDownTrayIcon className="h-4 w-4" />Download MOA</button>{tab === 'pending' && <button onClick={() => setDecision(item)} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2 text-sm font-bold text-white"><ShieldCheckIcon className="h-4 w-4" />Review</button>}</div></article>)}</div>}
    {selected && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" onMouseDown={() => setSelected(null)}><div onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-xl rounded-3xl bg-white p-7 shadow-2xl dark:bg-slate-900"><div className="flex justify-between"><h2 className="text-2xl font-black">MOA details</h2><button onClick={() => setSelected(null)}><XMarkIcon className="h-6 w-6" /></button></div><dl className="mt-6 grid grid-cols-2 gap-4">{[['HTE', selected.hte], ['College', selected.college], ['Effective date', format(selected.effective_date)], ['Expiration date', format(selected.expiration_date)], ['Endorsed by', selected.endorsed_by], ['Final reviewer', selected.decided_by || 'Pending']].map(([label, value]) => <div key={label} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 font-black">{value || '—'}</dd></div>)}</dl></div></div>}
    {decision && <DecisionModal item={decision} onClose={() => setDecision(null)} onSaved={() => { setDecision(null); load(); }} />}
  </div>;
}
