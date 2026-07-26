import { useEffect, useState } from 'react';
import {
  FiArchive,
  FiArrowDown,
  FiArrowUp,
  FiCheck,
  FiEdit2,
  FiFilePlus,
  FiRefreshCw,
  FiX,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyForm = { name: '', instructions: '' };

function RequirementManagerModal({ onClose, onChanged }) {
  const [requirements, setRequirements] = useState([]);
  const [program, setProgram] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/requirements');
      setRequirements(response.data.requirements || []);
      setProgram(response.data.program || null);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load program requirements.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    load();
    return () => { document.body.style.overflow = previousOverflow; };
  }, []);

  const errorMessage = (error, fallback) => {
    const errors = error.response?.data?.errors;
    return errors ? Object.values(errors).flat()[0] : error.response?.data?.message || fallback;
  };

  const addRequirement = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      const response = await api.post('/coordinator/requirements', form);
      toast.success(response.data.message);
      setForm(emptyForm);
      await load();
      onChanged?.();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to add requirement.'));
    } finally {
      setBusy(false);
    }
  };

  const saveEdit = async (requirement) => {
    setBusy(true);
    try {
      const response = await api.put(`/coordinator/requirements/${requirement.id}`, {
        name: editing.name,
        instructions: editing.instructions,
        is_active: requirement.is_active,
      });
      toast.success(response.data.message);
      setEditing(null);
      await load();
      onChanged?.();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update requirement.'));
    } finally {
      setBusy(false);
    }
  };

  const toggleRequirement = async (requirement) => {
    setBusy(true);
    try {
      const response = await api.put(`/coordinator/requirements/${requirement.id}`, {
        name: requirement.name,
        instructions: requirement.instructions,
        is_active: !requirement.is_active,
      });
      toast.success(response.data.message);
      await load();
      onChanged?.();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to change requirement status.'));
    } finally {
      setBusy(false);
    }
  };

  const archiveRequirement = async (requirement) => {
    if (!window.confirm(`Remove "${requirement.name}" from the active student checklist? Existing uploaded files will be preserved.`)) return;
    setBusy(true);
    try {
      const response = await api.delete(`/coordinator/requirements/${requirement.id}`);
      toast.success(response.data.message);
      await load();
      onChanged?.();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to archive requirement.'));
    } finally {
      setBusy(false);
    }
  };

  const move = async (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= requirements.length) return;
    const reordered = [...requirements];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
    setRequirements(reordered);
    try {
      await api.put('/coordinator/requirements/order', {
        requirement_ids: reordered.map((requirement) => requirement.id),
      });
      onChanged?.();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to change requirement order.'));
      await load();
    }
  };

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/65 p-3 backdrop-blur-sm sm:p-5">
      <section className="flex max-h-[calc(100vh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-white/20 bg-[#f7f3ee] shadow-2xl dark:border-slate-700 dark:bg-slate-950">
        <header className="flex shrink-0 items-start justify-between border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-7">
          <div>
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#a8750b]">Program upload settings</p>
            <h2 className="mt-1 text-xl font-black text-[#430909] dark:text-white">Customize Student Requirements</h2>
            <p className="mt-1 text-xs text-slate-500">{program ? `${program.code} — ${program.name}` : 'Your assigned academic program'}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close requirement manager"><FiX className="text-xl" /></button>
        </header>

        <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-7">
          <div className="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <form onSubmit={addRequirement} className="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
              <div className="flex items-center gap-3">
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-[#800000]/10 text-[#800000] dark:text-red-300"><FiFilePlus /></span>
                <div><h3 className="font-black">Add requirement</h3><p className="text-xs text-slate-500">Added to every student in this program.</p></div>
              </div>
              <label className="mt-5 block">
                <span className="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-500">Document name</span>
                <input required maxLength="255" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="e.g. Barangay Clearance" className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 dark:border-slate-700 dark:bg-slate-950" />
              </label>
              <label className="mt-4 block">
                <span className="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-500">Upload instructions</span>
                <textarea rows="5" maxLength="2000" value={form.instructions} onChange={(event) => setForm((current) => ({ ...current, instructions: event.target.value }))} placeholder="Describe the document or signing requirements..." className="w-full resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 dark:border-slate-700 dark:bg-slate-950" />
              </label>
              <button disabled={busy} className="mt-5 w-full rounded-xl bg-[#800000] px-4 py-3 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : 'Add to Checklist'}</button>
              <p className="mt-4 text-[11px] leading-5 text-slate-500">Archived items disappear from student upload pages, while files already submitted remain available for historical review.</p>
            </form>

            <div>
              <div className="mb-3 flex items-center justify-between">
                <div><h3 className="font-black">Program checklist</h3><p className="text-xs text-slate-500">{requirements.filter((item) => item.is_active).length} active · {requirements.length} total</p></div>
                <button type="button" onClick={load} disabled={loading} className="rounded-xl border border-slate-200 p-2.5 text-slate-500 dark:border-slate-700" title="Refresh"><FiRefreshCw className={loading ? 'animate-spin' : ''} /></button>
              </div>

              {loading ? <div className="rounded-2xl bg-white py-16 text-center text-sm text-slate-400 dark:bg-slate-900">Loading requirements...</div> : (
                <div className="space-y-3">
                  {requirements.map((requirement, index) => (
                    <article key={requirement.id} className={`rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-900 ${requirement.is_active ? 'border-slate-200 dark:border-slate-700' : 'border-dashed border-slate-300 opacity-70 dark:border-slate-700'}`}>
                      {editing?.id === requirement.id ? (
                        <div>
                          <input autoFocus value={editing.name} onChange={(event) => setEditing((current) => ({ ...current, name: event.target.value }))} className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold dark:border-slate-700 dark:bg-slate-950" />
                          <textarea rows="3" value={editing.instructions || ''} onChange={(event) => setEditing((current) => ({ ...current, instructions: event.target.value }))} className="mt-2 w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Upload instructions (optional)" />
                          <div className="mt-3 flex justify-end gap-2"><button type="button" onClick={() => setEditing(null)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-slate-700">Cancel</button><button type="button" onClick={() => saveEdit(requirement)} disabled={busy || !editing.name.trim()} className="inline-flex items-center gap-1 rounded-lg bg-[#800000] px-3 py-2 text-xs font-black text-white disabled:opacity-50"><FiCheck /> Save</button></div>
                        </div>
                      ) : (
                        <div className="flex gap-3">
                          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#800000]/8 text-xs font-black text-[#800000] dark:text-red-300">{index + 1}</div>
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2"><h4 className="font-black text-slate-800 dark:text-white">{requirement.name}</h4><span className={`rounded-full px-2 py-0.5 text-[9px] font-black uppercase ${requirement.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'}`}>{requirement.is_active ? 'Active' : 'Archived'}</span></div>
                            <p className="mt-1 text-xs leading-5 text-slate-500">{requirement.instructions || 'No special upload instructions.'}</p>
                            <p className="mt-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">{requirement.uploaded_count || 0} uploaded · {requirement.requirements_count || 0} student records</p>
                          </div>
                          <div className="flex shrink-0 flex-col gap-1">
                            <button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="rounded-lg border border-slate-200 p-1.5 disabled:opacity-25 dark:border-slate-700" title="Move up"><FiArrowUp /></button>
                            <button type="button" onClick={() => move(index, 1)} disabled={index === requirements.length - 1} className="rounded-lg border border-slate-200 p-1.5 disabled:opacity-25 dark:border-slate-700" title="Move down"><FiArrowDown /></button>
                          </div>
                        </div>
                      )}
                      {editing?.id !== requirement.id && <div className="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <button type="button" onClick={() => setEditing({ id: requirement.id, name: requirement.name, instructions: requirement.instructions || '' })} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-slate-700"><FiEdit2 /> Edit</button>
                        <button type="button" onClick={() => toggleRequirement(requirement)} disabled={busy} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-black ${requirement.is_active ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>{requirement.is_active ? <><FiArchive /> Disable</> : <><FiRefreshCw /> Restore</>}</button>
                        {requirement.is_active && <button type="button" onClick={() => archiveRequirement(requirement)} disabled={busy} className="rounded-lg bg-rose-100 px-3 py-2 text-xs font-black text-rose-700">Remove</button>}
                      </div>}
                    </article>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

export default RequirementManagerModal;
