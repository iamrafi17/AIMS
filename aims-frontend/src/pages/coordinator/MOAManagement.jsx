import { useCallback, useEffect, useMemo, useState } from 'react';
import { DocumentTextIcon, PlusIcon } from '@heroicons/react/24/outline';
import { FiEdit2, FiEye, FiTrash2 } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';
import MoaReviewModal from '../../components/moa/MoaReviewModal';
import { moaDate, moaDateInput, moaStatus } from '../../components/moa/moaUi';

const initialForm = { hte_id: '', program_id: '', effective_date: '', expiration_date: '', file: null };
const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-[#7D001D] focus:outline-none dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const returnedMoa = (moa) => moa.status === 'returned' || moa.program_status === 'returned';
const unreviewedMoa = (moa) => moa.status === 'pending' && moa.program_status === 'pending' && !moa.program_reviewed_at && !moa.approved_at;
const editableMoa = (moa) => returnedMoa(moa) || unreviewedMoa(moa);
const deletableMoa = (moa) => returnedMoa(moa) || unreviewedMoa(moa);

function EditModal({ moa, onClose, onSaved }) {
  const [effectiveDate, setEffectiveDate] = useState(moaDateInput(moa.effective_date));
  const [expirationDate, setExpirationDate] = useState(moaDateInput(moa.expiration_date));
  const [file, setFile] = useState(null);
  const [saving, setSaving] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    if (expirationDate <= effectiveDate) return toast.error('Expiration date must be later than the effective date.');
    if (file && (file.type !== 'application/pdf' || file.size > 10 * 1024 * 1024)) return toast.error('Choose a PDF file up to 10 MB.');
    setSaving(true);
    try {
      const body = new FormData();
      body.append('_method', 'PUT');
      body.append('effective_date', effectiveDate);
      body.append('expiration_date', expirationDate);
      if (file) body.append('file', file);
      const response = await api.post(`/coordinator/htes/moas/${moa.id}`, body, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message);
      onSaved();
    } catch (error) {
      toast.error(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || 'Unable to update MOA.');
    } finally { setSaving(false); }
  };

  return <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/65 p-3" onMouseDown={(event) => event.target === event.currentTarget && !saving && onClose()}>
    <div role="dialog" aria-modal="true" aria-label="Edit MOA" className="w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
      <header className="border-b border-slate-200 px-6 py-5 dark:border-slate-700"><h2 className="text-xl font-semibold">{returnedMoa(moa) ? 'Revise and resubmit MOA' : 'Edit MOA'}</h2><p className="mt-1 text-sm text-slate-500">{moa.hte?.name} Â· {moa.program?.code}</p></header>
      <form onSubmit={submit}>
        <div className="grid gap-4 p-6 sm:grid-cols-2"><label className="grid gap-1.5 text-sm font-semibold">Effective date<input required type="date" value={effectiveDate} onChange={(event) => setEffectiveDate(event.target.value)} className={fieldClass} /></label><label className="grid gap-1.5 text-sm font-semibold">Expiration date<input required type="date" min={effectiveDate || undefined} value={expirationDate} onChange={(event) => setExpirationDate(event.target.value)} className={fieldClass} /></label><label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Replacement PDF (optional, up to 10 MB)<input type="file" accept=".pdf,application/pdf" onChange={(event) => setFile(event.target.files?.[0] || null)} className={fieldClass} /></label></div>
        <footer className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4 dark:border-slate-700"><button type="button" disabled={saving} onClick={onClose} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-600">Cancel</button><button type="submit" disabled={saving} className="rounded-xl bg-[#7D001D] px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Savingâ€¦' : returnedMoa(moa) ? 'Resubmit to Program Head' : 'Save changes'}</button></footer>
      </form>
    </div>
  </div>;
}

function DeleteModal({ moa, onClose, onDeleted }) {
  const [deleting, setDeleting] = useState(false);
  const remove = async () => {
    setDeleting(true);
    try {
      const response = await api.delete(`/coordinator/htes/moas/${moa.id}`);
      toast.success(response.data.message);
      onDeleted();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to delete MOA.');
    } finally { setDeleting(false); }
  };

  return <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/65 p-3" onMouseDown={(event) => event.target === event.currentTarget && !deleting && onClose()}>
    <div role="alertdialog" aria-modal="true" aria-label="Delete MOA" className="w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
      <header className="border-b border-slate-200 px-6 py-5 dark:border-slate-700"><h2 className="text-xl font-semibold">Delete MOA?</h2></header>
      <p className="px-6 py-5 text-sm text-slate-600 dark:text-slate-300">Remove the MOA for <strong>{moa.hte?.name} Â· {moa.program?.code}</strong> from the active list? The record will be archived.</p>
      <footer className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4 dark:border-slate-700"><button type="button" disabled={deleting} onClick={onClose} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-600">Cancel</button><button type="button" disabled={deleting} onClick={remove} className="rounded-xl bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{deleting ? 'Deletingâ€¦' : 'Delete MOA'}</button></footer>
    </div>
  </div>;
}

function UploadModal({ htes, programs, onClose, onSaved }) {
  const [form, setForm] = useState({ ...initialForm, program_id: String(programs[0]?.id || '') });
  const [saving, setSaving] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    if (!form.file) return toast.error('Choose a PDF document.');
    if (form.expiration_date <= form.effective_date) return toast.error('Expiration date must be later than the effective date.');
    const body = new FormData();
    Object.entries(form).forEach(([key, value]) => body.append(key, value));
    setSaving(true);
    try {
      const response = await api.post('/coordinator/htes/moas', body, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message);
      onSaved();
    } catch (error) {
      const message = Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || 'Unable to upload MOA.';
      toast.error(message);
    } finally { setSaving(false); }
  };

  return <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/65 p-3 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && !saving && onClose()}>
    <div role="dialog" aria-modal="true" aria-label="Upload MOA" className="w-full max-w-xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
      <header className="border-b border-slate-200 px-6 py-5 dark:border-slate-700"><h2 className="text-xl font-semibold text-slate-900 dark:text-white">Upload MOA</h2><p className="mt-1 text-sm text-slate-500">Submit a separate agreement for this HTE and program.</p></header>
      <form onSubmit={submit}>
        <div className="grid gap-4 p-6">
          <label className="grid gap-1.5 text-sm font-semibold">HTE<select required value={form.hte_id} onChange={(event) => setForm({ ...form, hte_id: event.target.value })} className={fieldClass}><option value="">Select HTE</option>{htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select></label>
          <label className="grid gap-1.5 text-sm font-semibold">Program<select required value={form.program_id} onChange={(event) => setForm({ ...form, program_id: event.target.value })} className={fieldClass}><option value="">Select Program</option>{programs.map((program) => <option key={program.id} value={program.id}>{program.code} Â· {program.name}</option>)}</select></label>
          <div className="grid gap-4 sm:grid-cols-2"><label className="grid gap-1.5 text-sm font-semibold">Effective date<input required type="date" value={form.effective_date} onChange={(event) => setForm({ ...form, effective_date: event.target.value })} className={fieldClass} /></label><label className="grid gap-1.5 text-sm font-semibold">Expiration date<input required type="date" min={form.effective_date || undefined} value={form.expiration_date} onChange={(event) => setForm({ ...form, expiration_date: event.target.value })} className={fieldClass} /></label></div>
          <label className="grid gap-1.5 text-sm font-semibold">MOA document (PDF, up to 10 MB)<input required type="file" accept=".pdf,application/pdf" onChange={(event) => setForm({ ...form, file: event.target.files?.[0] || null })} className={fieldClass} /></label>
        </div>
        <footer className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4 dark:border-slate-700"><button type="button" disabled={saving} onClick={onClose} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-600">Cancel</button><button type="submit" disabled={saving} className="rounded-xl bg-[#7D001D] px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Submittingâ€¦' : 'Submit MOA'}</button></footer>
      </form>
    </div>
  </div>;
}

export default function MOAManagement() {
  const [data, setData] = useState({ moas: [], htes: [], programs: [] });
  const [loading, setLoading] = useState(true);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [selected, setSelected] = useState(null);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [validity, setValidity] = useState(new URLSearchParams(window.location.search).get('validity') || 'all');

  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get('/coordinator/htes')).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load MOAs.'); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const moas = data.moas;
  const shown = useMemo(() => moas.filter((moa) => {
    const label = moaStatus(moa).toLowerCase();
    return `${moa.hte?.name} ${moa.program?.code} ${moa.program?.name}`.toLowerCase().includes(search.toLowerCase())
      && (status === 'all' || label.includes(status))
      && (validity === 'all' || moa.validity === validity);
  }), [moas, search, status, validity]);
  if (loading && !data.moas?.length) return <LoadingPanel />;

  return <div className="space-y-6">
    <PageIntro eyebrow="Coordinator workspace" title="MOA Management" description="Upload and manage Memorandum of Agreement documents for your program." icon={DocumentTextIcon} actions={<button type="button" onClick={() => setUploadOpen(true)} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-semibold text-white"><PlusIcon className="h-4 w-4" />Upload MOA</button>} />
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Total MOAs" value={moas.length} icon={DocumentTextIcon} /><MetricCard label="Pending Program Head" value={moas.filter((moa) => moa.program_status === 'pending').length} icon={DocumentTextIcon} tone="gold" /><MetricCard label="Approved" value={moas.filter((moa) => moa.status === 'approved').length} icon={DocumentTextIcon} tone="green" /><MetricCard label="Expired" value={moas.filter((moa) => moa.validity === 'expired').length} icon={DocumentTextIcon} /></div>
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
      <div className="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 md:grid-cols-[minmax(0,1fr)_12rem_12rem]"><input aria-label="Search MOAs" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search HTE or program" className={fieldClass} /><select aria-label="Approval status" value={status} onChange={(event) => setStatus(event.target.value)} className={fieldClass}><option value="all">All statuses</option><option value="pending program head">Pending Program Head</option><option value="pending dean">Pending Dean</option><option value="approved">Dean approved</option><option value="vpaa reviewed">VPAA reviewed</option><option value="returned">Returned</option></select><select aria-label="Validity" value={validity} onChange={(event) => setValidity(event.target.value)} className={fieldClass}><option value="all">All validity</option><option value="upcoming">Upcoming</option><option value="valid">Valid</option><option value="expiring soon">Expiring Soon</option><option value="expired">Expired</option></select></div>
      {shown.length ? <div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th className="px-5 py-3">HTE</th><th className="px-4 py-3">Program</th><th className="px-4 py-3">Effective date</th><th className="px-4 py-3">Expiration date</th><th className="px-4 py-3">Approval status</th><th className="px-4 py-3">Validity</th><th className="px-5 py-3 text-right">Actions</th></tr></thead><tbody>{shown.map((moa) => <tr key={moa.id} className="border-t border-slate-100 dark:border-slate-700"><td className="px-5 py-4 font-semibold text-slate-900 dark:text-white">{moa.hte?.name}</td><td className="px-4 py-4">{moa.program?.code}</td><td className="px-4 py-4">{moaDate(moa.effective_date)}</td><td className="px-4 py-4">{moaDate(moa.expiration_date)}</td><td className="px-4 py-4">{moaStatus(moa)}</td><td className="px-4 py-4 capitalize">{moa.validity}</td><td className="px-5 py-4 text-right"><div className="flex justify-end gap-1.5">
        <button type="button" onClick={() => setSelected(moa)} aria-label={`View MOA for ${moa.hte?.name}`} className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-blue-100 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60"><FiEye aria-hidden="true" />View</button>
        <button type="button" onClick={() => setEditing(moa)} disabled={!editableMoa(moa)} title={editableMoa(moa) ? (returnedMoa(moa) ? 'Revise and resubmit MOA' : 'Edit MOA') : 'Available before review or after return'} aria-label={`Edit MOA for ${moa.hte?.name}`} className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-amber-100 px-3 text-xs font-semibold text-amber-700 hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60"><FiEdit2 aria-hidden="true" />Edit</button>
        <button type="button" onClick={() => setDeleting(moa)} disabled={!deletableMoa(moa)} title={deletableMoa(moa) ? 'Delete MOA' : 'Available before review or after return'} aria-label={`Delete MOA for ${moa.hte?.name}`} className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-rose-100 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-rose-900/40 dark:text-rose-300 dark:hover:bg-rose-900/60"><FiTrash2 aria-hidden="true" />Delete</button>
      </div></td></tr>)}</tbody></table></div> : <EmptyPanel icon={DocumentTextIcon} title="No MOAs found" description="Upload a MOA or adjust your filters." />}
    </section>
    {uploadOpen && <UploadModal htes={data.htes || []} programs={data.programs || []} onClose={() => setUploadOpen(false)} onSaved={() => { setUploadOpen(false); load(); }} />}
    {selected && <MoaReviewModal moa={selected} url={`/coordinator/htes/moas/${selected.id}/download`} title="MOA Details" onClose={() => setSelected(null)}>
      <p className="mr-auto text-xs text-slate-500">{returnedMoa(selected) ? 'Returned MOA: revise or delete this upload.' : unreviewedMoa(selected) ? 'Pending MOA: you can still edit or delete this upload.' : 'Under review or approved: editing and deletion are unavailable until the MOA is returned.'}</p>
      {deletableMoa(selected) && <button type="button" onClick={() => { setDeleting(selected); setSelected(null); }} className="inline-flex items-center gap-2 rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-700"><FiTrash2 />Delete</button>}
      {editableMoa(selected) && <button type="button" onClick={() => { setEditing(selected); setSelected(null); }} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-semibold text-white"><FiEdit2 />{returnedMoa(selected) ? 'Revise MOA' : 'Edit MOA'}</button>}
    </MoaReviewModal>}
    {editing && <EditModal moa={editing} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); load(); }} />}
    {deleting && <DeleteModal moa={deleting} onClose={() => setDeleting(null)} onDeleted={() => { setDeleting(null); load(); }} />}
  </div>;
}
