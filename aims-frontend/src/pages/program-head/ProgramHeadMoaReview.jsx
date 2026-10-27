import { useMemo, useState } from 'react';
import { DocumentTextIcon } from '@heroicons/react/24/outline';
import { FiEye } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, MetricCard } from '../../components/common/PortalUI';
import MoaReviewModal from '../../components/moa/MoaReviewModal';
import { moaDate, moaStatus } from '../../components/moa/moaUi';

const controlClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';

export default function ProgramHeadMoaReview({ moas, onChanged }) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [validity, setValidity] = useState('all');
  const [selected, setSelected] = useState(null);
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState('');

  const activeMoas = useMemo(() => moas.filter((moa) => moa.program_status !== 'returned'), [moas]);
  const shown = useMemo(() => activeMoas.filter((moa) => {
    const reviewStatus = moaStatus(moa).toLowerCase();
    return `${moa.hte} ${moa.program} ${moa.college}`.toLowerCase().includes(search.toLowerCase())
      && (status === 'all' || (status === 'endorsed' ? moa.program_status === 'endorsed' && moa.status === 'pending' : reviewStatus.includes(status)))
      && (validity === 'all' || moa.validity === validity);
  }), [activeMoas, search, status, validity]);

  const review = async (decision) => {
    if (!selected || busy) return;
    setBusy(true);
    try {
      const response = await api.post(`/program-head/documents/moas/${selected.id}/review`, { decision, feedback: feedback.trim() || null });
      toast.success(response.data.message);
      setSelected(null);
      setFeedback('');
      await onChanged();
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to review MOA.'); }
    finally { setBusy(false); }
  };

  const pendingReview = selected?.program_status === 'pending' && selected?.status === 'pending';
  const completedReview = selected?.program_status === 'endorsed' && selected?.status === 'approved' && Boolean(selected?.vpaa_reviewed_at);

  return <div className="space-y-5">
    <div><h2 className="text-xl font-semibold text-slate-900 dark:text-white">MOA Review</h2><p className="mt-1 text-sm text-slate-500">Review agreements for your program and endorse valid documents to the Dean.</p></div>
    <div className="grid gap-3 sm:grid-cols-3"><MetricCard label="Pending review" value={activeMoas.filter((moa) => moa.program_status === 'pending').length} icon={DocumentTextIcon} tone="gold" /><MetricCard label="Endorsed" value={activeMoas.filter((moa) => moa.program_status === 'endorsed').length} icon={DocumentTextIcon} tone="blue" /><MetricCard label="Expired" value={activeMoas.filter((moa) => moa.validity === 'expired').length} icon={DocumentTextIcon} /></div>
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
      <div className="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 md:grid-cols-[minmax(0,1fr)_12rem_12rem]"><input aria-label="Search MOAs" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search HTE or program" className={controlClass} /><select aria-label="Review status" value={status} onChange={(event) => setStatus(event.target.value)} className={controlClass}><option value="all">All statuses</option><option value="pending program head">Pending</option><option value="endorsed">Endorsed, awaiting decision</option><option value="approved">Approved</option><option value="returned">Returned</option></select><select aria-label="MOA validity" value={validity} onChange={(event) => setValidity(event.target.value)} className={controlClass}><option value="all">All validity</option><option value="upcoming">Upcoming</option><option value="valid">Valid</option><option value="expiring soon">Expiring Soon</option><option value="expired">Expired</option></select></div>
      {shown.length ? <div className="overflow-x-auto"><table className="w-full min-w-[750px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th className="px-5 py-3">HTE</th><th className="px-4 py-3">Program</th><th className="px-4 py-3">Effective date</th><th className="px-4 py-3">Expiration date</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Validity</th><th className="px-5 py-3 text-right">Action</th></tr></thead><tbody>{shown.map((moa) => <tr key={moa.id} className="border-t border-slate-100 dark:border-slate-700"><td className="px-5 py-4 font-semibold text-slate-900 dark:text-white">{moa.hte}</td><td className="px-4 py-4">{moa.program}</td><td className="px-4 py-4">{moaDate(moa.effective_date)}</td><td className="px-4 py-4">{moaDate(moa.expiration_date)}</td><td className="px-4 py-4">{moaStatus(moa)}</td><td className="px-4 py-4 capitalize">{moa.validity}</td><td className="px-5 py-4 text-right"><button type="button" onClick={() => setSelected(moa)} title="View MOA" aria-label={`View MOA for ${moa.hte}`} className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60"><FiEye aria-hidden="true" /></button></td></tr>)}</tbody></table></div> : <EmptyPanel icon={DocumentTextIcon} title="No MOAs found" description="Submitted MOAs will appear here." />}
    </section>
    {selected && <MoaReviewModal moa={selected} url={`/program-head/documents/moas/${selected.id}/download`} title="MOA Review" onClose={() => { if (!busy) { setSelected(null); setFeedback(''); } }}>
      {(pendingReview || completedReview) && <><textarea aria-label="Return reason" value={feedback} onChange={(event) => setFeedback(event.target.value)} rows={2} placeholder="Reason required when returning this MOA" className="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-600 dark:bg-slate-800" /><button type="button" disabled={busy || !feedback.trim()} onClick={() => review('returned')} className="rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-700 disabled:opacity-50 dark:text-rose-300">Return to Coordinator</button>{pendingReview && <button type="button" disabled={busy || selected.validity === 'expired'} onClick={() => review('endorsed')} className="rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Endorse to Dean</button>}</>}
    </MoaReviewModal>}
  </div>;
}
