import { useEffect, useState } from 'react';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import api from '../../services/api';
import PdfPages from '../PdfPages';
import DocumentPreviewModal, { DocumentDetail } from '../common/DocumentPreviewModal';
import { moaDate, moaStatus } from './moaUi';

async function downloadMoa(url, name) {
  const response = await api.get(url, { responseType: 'blob' });
  const objectUrl = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = `MOA-${name || 'document'}.pdf`;
  link.click();
  setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
}

export default function MoaReviewModal({ moa, url, title = 'MOA Details', onClose, children }) {
  const [file, setFile] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    api.get(url, { responseType: 'blob', signal: controller.signal })
      .then((response) => setFile(new File([response.data], `moa-${moa.moa_id || moa.id}.pdf`, { type: 'application/pdf' })))
      .catch((requestError) => {
        if (requestError.code !== 'ERR_CANCELED') setError('The MOA PDF could not be opened.');
      });
    return () => controller.abort();
  }, [url, moa.id, moa.moa_id]);

  const hte = typeof moa.hte === 'string' ? moa.hte : moa.hte?.name;
  const program = typeof moa.program === 'string' ? moa.program : moa.program?.code;
  const college = typeof moa.college === 'string' ? moa.college : moa.college?.code;
  const validity = moa.validity || (moa.computed_status === 'expired' ? 'expired' : 'valid');
  const returned = moa.status === 'returned' || moa.approval_status === 'returned' || moa.program_status === 'returned';
  const programReviewed = moa.program_status === 'endorsed' || Boolean(moa.reviewed_at || moa.program_reviewed_at);
  const deanApproved = Boolean(moa.dean_approved_at || (moa.status === 'approved' && (moa.dean_approved_by || moa.dean_endorsed_at)));
  const stages = [
    { label: 'Coordinator', detail: 'Uploaded', done: true },
    { label: 'Program Head', detail: moa.program_status === 'returned' ? 'Returned' : programReviewed ? 'Endorsed' : 'For review', done: programReviewed && moa.program_status !== 'returned' },
    { label: 'Dean', detail: moa.status === 'returned' ? 'Returned' : deanApproved ? 'Final approved' : moa.program_status === 'endorsed' ? 'For final approval' : 'Awaiting Program Head', done: deanApproved },
    { label: 'VPAA', detail: moa.vpaa_reviewed_at ? 'Reviewed' : deanApproved ? 'For review' : 'Awaiting Dean', done: Boolean(moa.vpaa_reviewed_at) },
  ];

  return <DocumentPreviewModal
    compact
    kicker="Memorandum of Agreement"
    title={title}
    subtitle={`${hte || ''} · ${program || ''}`}
    onClose={onClose}
    preview={file ? <PdfPages file={file} /> : <p className="py-20 text-center text-sm text-slate-500" role={error ? 'alert' : undefined}>{error || 'Loading MOA PDF…'}</p>}
    details={<><div className="mb-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"><p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">MOA flow</p><ol className="mt-3 space-y-2">{stages.map((stage, index) => <li key={stage.label} className="flex items-center gap-3"><span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold ${stage.done ? 'bg-emerald-100 text-emerald-700' : returned && ((index === 1 && moa.program_status === 'returned') || (index === 2 && moa.status === 'returned')) ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'}`}>{stage.done ? '✓' : index + 1}</span><span className="min-w-0"><span className="block text-xs font-semibold text-slate-900 dark:text-white">{stage.label}</span><span className="block text-[11px] text-slate-500">{stage.detail}</span></span></li>)}</ol></div><dl className="grid grid-cols-2 gap-3 lg:grid-cols-1">
      <DocumentDetail label="HTE" value={hte} /><DocumentDetail label="Program" value={program} /><DocumentDetail label="College" value={college} />
      <DocumentDetail label="Effective date" value={moaDate(moa.effective_date)} /><DocumentDetail label="Expiration date" value={moaDate(moa.expiration_date)} />
      <DocumentDetail label="Approval status" value={moaStatus(moa)} /><DocumentDetail label="Validity" value={validity} />
      {moa.dean_approved_at && <DocumentDetail label="Dean final approved" value={`${moa.dean_approved_by || 'Dean'} · ${moaDate(moa.dean_approved_at)}`} />}
      {moa.vpaa_reviewed_at && <DocumentDetail label="VPAA reviewed" value={`${moa.vpaa_reviewed_by || 'VPAA'} · ${moaDate(moa.vpaa_reviewed_at)}`} />}
      {moa.dean_remarks && <DocumentDetail label="Dean remarks" value={moa.dean_remarks} />}
      {moa.program_feedback && <DocumentDetail label="Program Head feedback" value={moa.program_feedback} />}
      {moa.remarks && <DocumentDetail label="Final decision remarks" value={moa.remarks} />}
    </dl>
    {validity === 'expired' && <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">This MOA has expired. A renewed MOA is required before it can be endorsed or approved.</p>}
    <button type="button" onClick={() => downloadMoa(url, `${program || 'program'}-${hte || 'hte'}`).catch(() => setError('The MOA PDF could not be downloaded.'))} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200"><ArrowDownTrayIcon className="h-4 w-4" />Download PDF</button></>}
    footer={children}
  />;
}
