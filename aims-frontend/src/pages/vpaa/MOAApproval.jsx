import { useCallback, useEffect, useMemo, useState } from 'react';
import { CheckBadgeIcon, ClockIcon, DocumentTextIcon, ShieldCheckIcon, XCircleIcon } from '@heroicons/react/24/outline';
import { FiEye } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';
import MoaReviewModal from '../../components/moa/MoaReviewModal';
import { moaDate, moaStatus } from '../../components/moa/moaUi';

const controlClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';

export default function MOAApproval({ apiBase = '/vpaa/moas' }) {
  const isDean = apiBase.startsWith('/dean');
  const [data, setData] = useState({ pending: [], history: [], summary: {} });
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('pending');
  const [search, setSearch] = useState('');
  const [program, setProgram] = useState('all');
  const [status, setStatus] = useState('all');
  const [validity, setValidity] = useState('all');
  const [selected, setSelected] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get(apiBase)).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load MOAs.'); }
    finally { setLoading(false); }
  }, [apiBase]);
  useEffect(() => { load(); }, [load]);

  const programs = useMemo(() => [...new Map([...data.pending, ...data.history]
    .map((moa) => [moa.program_id, { id: moa.program_id, label: moa.program }])).values()], [data]);
  const items = useMemo(() => data[tab].filter((moa) => {
    return `${moa.hte} ${moa.program} ${moa.college}`.toLowerCase().includes(search.toLowerCase())
      && (program === 'all' || String(moa.program_id) === program)
      && (status === 'all' || (status === 'reviewed' ? Boolean(moa.vpaa_reviewed_at) : moa.approval_status === status))
      && (validity === 'all' || moa.validity === validity);
  }), [data, tab, search, program, status, validity]);

  const review = async (decision) => {
    if (!selected || busy) return;
    setBusy(true);
    try {
      if (decision === 'returned' && !remarks.trim()) { toast.error('Enter a reason for returning this MOA.'); setBusy(false); return; }
      const response = isDean
        ? await api.put(`${apiBase}/${selected.approval_id}`, { decision, remarks: remarks.trim() || null })
        : await api.post(`${apiBase}/${selected.approval_id}/review`);
      toast.success(response.data.message);
      setSelected(null);
      setRemarks('');
      await load();
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to record the review.'); }
    finally { setBusy(false); }
  };

  if (loading && !data.pending.length && !data.history.length) return <LoadingPanel />;

  return <div className="space-y-6">
    <PageIntro eyebrow={isDean ? 'Dean workspace' : 'VPAA workspace'} title={isDean ? 'MOA Final Approval' : 'MOA Review'} description={isDean ? 'Make the final decision on Program Head-endorsed MOAs.' : 'Acknowledge MOAs after final Dean approval.'} icon={ShieldCheckIcon} />
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label={isDean ? 'Pending final approval' : 'Awaiting review'} value={data.summary.pending} icon={ClockIcon} tone="gold" /><MetricCard label={isDean ? 'Approved' : 'Reviewed'} value={isDean ? data.summary.approved : data.summary.reviewed} icon={CheckBadgeIcon} tone="green" /><MetricCard label="Expiring soon" value={data.summary.expiring} icon={DocumentTextIcon} tone="violet" /><MetricCard label="Expired" value={data.summary.expired} icon={XCircleIcon} /></div>
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
      <div className="flex gap-2 border-b border-slate-200 p-4 dark:border-slate-700"><button type="button" onClick={() => { setTab('pending'); setStatus('all'); }} className={`rounded-lg px-4 py-2 text-sm font-semibold ${tab === 'pending' ? 'bg-[#7D001D] text-white' : 'text-slate-600 dark:text-slate-300'}`}>{isDean ? 'Pending Final Approval' : 'Awaiting Review'}</button><button type="button" onClick={() => { setTab('history'); setStatus('all'); }} className={`rounded-lg px-4 py-2 text-sm font-semibold ${tab === 'history' ? 'bg-[#7D001D] text-white' : 'text-slate-600 dark:text-slate-300'}`}>History</button></div>
      <div className="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 md:grid-cols-[minmax(0,1fr)_repeat(3,10rem)]"><input aria-label="Search MOAs" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search HTE or program" className={controlClass} /><select aria-label="Program" value={program} onChange={(event) => setProgram(event.target.value)} className={controlClass}><option value="all">All programs</option>{programs.map((entry) => <option key={entry.id} value={entry.id}>{entry.label}</option>)}</select><select aria-label="Approval status" value={status} onChange={(event) => setStatus(event.target.value)} className={controlClass}><option value="all">All statuses</option>{isDean ? <><option value="pending">Pending</option><option value="approved">Approved</option><option value="returned">Returned</option></> : <><option value="approved">Dean approved</option><option value="reviewed">VPAA reviewed</option></>}</select><select aria-label="Validity" value={validity} onChange={(event) => setValidity(event.target.value)} className={controlClass}><option value="all">All validity</option><option value="upcoming">Upcoming</option><option value="valid">Valid</option><option value="expiring soon">Expiring Soon</option><option value="expired">Expired</option></select></div>
      {items.length ? <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th className="px-5 py-3">HTE</th><th className="px-4 py-3">Program</th><th className="px-4 py-3">College</th><th className="px-4 py-3">Expiration date</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Validity</th><th className="px-5 py-3 text-right">Action</th></tr></thead><tbody>{items.map((moa) => <tr key={moa.approval_id} className="border-t border-slate-100 dark:border-slate-700"><td className="px-5 py-4 font-semibold text-slate-900 dark:text-white">{moa.hte}</td><td className="px-4 py-4">{moa.program}</td><td className="px-4 py-4">{moa.college_code}</td><td className="px-4 py-4">{moaDate(moa.expiration_date)}</td><td className="px-4 py-4">{moaStatus(moa)}</td><td className="px-4 py-4 capitalize">{moa.validity}</td><td className="px-5 py-4 text-right"><button type="button" onClick={() => setSelected(moa)} title="View MOA" aria-label={`View MOA for ${moa.hte}`} className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60"><FiEye aria-hidden="true" /></button></td></tr>)}</tbody></table></div> : <EmptyPanel icon={DocumentTextIcon} title="No MOAs found" description={tab === 'pending' ? (isDean ? 'Program Head-endorsed MOAs will appear here.' : 'Dean-approved MOAs will appear here.') : 'Completed MOA records will appear here.'} />}
    </section>
    {selected && <MoaReviewModal moa={selected} url={`${apiBase}/${selected.approval_id}/download`} title={isDean ? 'Dean MOA Final Approval' : 'VPAA MOA Review'} onClose={() => { if (!busy) { setSelected(null); setRemarks(''); } }}>
      {tab === 'pending' && (isDean ? selected.approval_status === 'pending' : selected.approval_status === 'approved') && (isDean ? <><textarea aria-label="Decision remarks" value={remarks} onChange={(event) => setRemarks(event.target.value)} rows={2} placeholder="Reason required when returning this MOA" className="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-600 dark:bg-slate-800" /><button type="button" disabled={busy || !remarks.trim()} onClick={() => review('returned')} className="rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-700 disabled:opacity-50 dark:text-rose-300">Return</button><button type="button" disabled={busy || selected.validity === 'expired'} onClick={() => review('approved')} className="rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Final Approve</button></> : <button type="button" disabled={busy} onClick={() => review('reviewed')} className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{busy ? 'Saving...' : 'Mark as Reviewed'}</button>)}
    </MoaReviewModal>}
  </div>;
}
