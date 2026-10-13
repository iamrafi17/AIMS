import { useCallback, useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';

export default function DeanApprovals() {
  const [data, setData] = useState({ deployments: [], history: [], summary: {} });
  const [selected, setSelected] = useState(null);
  const [decision, setDecision] = useState('approved');
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);
  const load = useCallback(async () => {
    try { setData((await api.get('/dean/approvals')).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load deployments.'); }
  }, []);
  useEffect(() => { load(); }, [load]);
  const submit = async () => {
    if (decision === 'rejected' && !remarks.trim()) return toast.error('Enter a reason for returning this deployment.');
    setBusy(true);
    try {
      await api.put(`/dean/approvals/${selected.approval_id}`, { decision, remarks: remarks.trim() || null });
      toast.success('Dean decision recorded.');
      setSelected(null); setRemarks(''); await load();
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to record the decision.'); }
    finally { setBusy(false); }
  };
  return <div className="space-y-6">
    <header className="rounded-3xl bg-[#690016] p-6 text-white"><p className="text-xs font-bold uppercase tracking-widest text-amber-200">Dean workspace</p><h1 className="mt-2 text-3xl font-black">Deployment Final Approval</h1><p className="mt-2 text-sm text-white/75">Program Head-forwarded requests from your college.</p></header>
    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><h2 className="font-black">Pending deployments ({data.deployments.length})</h2>{data.deployments.length ? <div className="mt-4 space-y-3">{data.deployments.map((item) => <button key={item.approval_id} type="button" onClick={() => setSelected(item)} className="w-full rounded-xl border p-4 text-left hover:border-[#7D001D]"><p className="font-bold">{item.student?.name} · {item.student?.student_id}</p><p className="text-sm text-slate-500">{item.student?.program} · {item.hte?.name} · {item.schedule?.start_date || 'No start date'}</p></button>)}</div> : <p className="mt-3 text-sm text-slate-500">No requests awaiting final approval.</p>}</div>
    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><h2 className="font-black">Decision history</h2><div className="mt-3 space-y-2">{data.history.filter((item) => item.type === 'deployment').map((item) => <div key={item.id} className="rounded-lg border p-3 text-sm"><b>{item.student?.name}</b> · {item.status} · {item.decided_by}{item.remarks && <p className="text-slate-500">{item.remarks}</p>}</div>)}</div></div>
    {selected && <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-3"><div role="dialog" aria-modal="true" aria-label="Review deployment" className="w-full max-w-lg rounded-2xl bg-white p-6 dark:bg-slate-900"><h2 className="text-xl font-black">{selected.student?.name}</h2><p className="mt-2 text-sm">{selected.student?.program} · {selected.hte?.name}</p><p className="mt-2 text-sm">Schedule: {selected.schedule?.start_date} to {selected.schedule?.end_date} · {selected.schedule?.required_hours} hours</p><label className="mt-4 block text-sm font-bold">Decision<select value={decision} onChange={(event) => setDecision(event.target.value)} className="mt-1 w-full rounded-lg border p-2"><option value="approved">Approve</option><option value="rejected">Return / reject</option></select></label><label className="mt-3 block text-sm font-bold">Remarks<textarea value={remarks} onChange={(event) => setRemarks(event.target.value)} rows={3} className="mt-1 w-full rounded-lg border p-2" placeholder={decision === 'rejected' ? 'Reason required' : 'Optional'} /></label><div className="mt-4 flex justify-end gap-2"><button type="button" onClick={() => setSelected(null)} className="rounded-lg border px-4 py-2">Cancel</button><button type="button" disabled={busy} onClick={submit} className="rounded-lg bg-[#7D001D] px-4 py-2 font-bold text-white disabled:opacity-50">Save decision</button></div></div></div>}
  </div>;
}
