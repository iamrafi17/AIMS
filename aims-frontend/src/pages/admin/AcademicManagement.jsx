import { useCallback, useEffect, useState } from 'react';
import {
  AcademicCapIcon,
  BuildingLibraryIcon,
  PencilSquareIcon,
  PlusIcon,
  PowerIcon,
  RectangleGroupIcon,
  TrashIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const baseForms = {
  college: { name: '', code: '', required_ojt_hours: 486, is_active: true },
  program: { college_id: '', name: '', code: '', is_active: true },
  section: { program_id: '', name: '', year_level: 4, academic_year: '2026-2027', is_active: true },
};

function AcademicModal({ type, item, colleges, programs, onClose, onSaved }) {
  const [form, setForm] = useState(() => item ? {
    ...item,
    college_id: item.college_id || '',
    program_id: item.program_id || '',
  } : { ...baseForms[type] });
  const [saving, setSaving] = useState(false);
  const title = `${item ? 'Edit' : 'Add'} ${type}`;

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const endpoint = `/admin/academic/${type === 'college' ? 'colleges' : type === 'program' ? 'programs' : 'sections'}`;
      const response = item ? await api.put(`${endpoint}/${item.id}`, form) : await api.post(endpoint, form);
      toast.success(response.data.message);
      onSaved();
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || `Unable to save ${type}.`);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4" onMouseDown={onClose}>
      <form role="dialog" aria-modal="true" aria-label={`${item ? 'Edit' : 'Add'} ${type}`} onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} className="flex max-h-[calc(100dvh-1rem)] w-full max-w-xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5 dark:border-slate-700">
          <div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Academic structure</p><h2 className="mt-1 text-2xl font-black capitalize text-slate-900 dark:text-white">{title}</h2></div>
          <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"><XMarkIcon className="h-6 w-6" /></button>
        </div>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
          {type === 'program' && (
            <label className="block"><span className="mb-2 block text-sm font-bold">College</span>
              <select required value={form.college_id} onChange={(event) => setForm({ ...form, college_id: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                <option value="">Select college</option>{colleges.map((college) => <option key={college.id} value={college.id}>{college.code} — {college.name}</option>)}
              </select>
            </label>
          )}
          {type === 'section' && (
            <label className="block"><span className="mb-2 block text-sm font-bold">Program</span>
              <select required value={form.program_id} onChange={(event) => setForm({ ...form, program_id: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                <option value="">Select program</option>{programs.map((program) => <option key={program.id} value={program.id}>{program.code} — {program.name}</option>)}
              </select>
            </label>
          )}
          <div className="grid gap-4 sm:grid-cols-3">
            <label className="sm:col-span-2"><span className="mb-2 block text-sm font-bold">{type === 'section' ? 'Section name' : `${type} name`}</span><input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></label>
            {type !== 'section' && <label><span className="mb-2 block text-sm font-bold">Code</span><input required value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 uppercase dark:border-slate-700" /></label>}
          </div>
          {type === 'college' && (
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Required Internship Hours</span>
              <input type="number" min="1" max="2000" step="0.5" required value={form.required_ojt_hours} onChange={(event) => setForm({ ...form, required_ojt_hours: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" />
              <span className="mt-1 block text-xs text-slate-500">New students enrolled under this college will automatically inherit this requirement.</span>
            </label>
          )}
          {type === 'section' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <label><span className="mb-2 block text-sm font-bold">Year level</span><select value={form.year_level} onChange={(event) => setForm({ ...form, year_level: Number(event.target.value) })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="4">4th year</option><option value="5">5th year</option></select></label>
              <label><span className="mb-2 block text-sm font-bold">Academic year</span><input required pattern="\d{4}-\d{4}" value={form.academic_year} onChange={(event) => setForm({ ...form, academic_year: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></label>
            </div>
          )}
          <label className="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800"><input type="checkbox" checked={Boolean(form.is_active)} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} className="h-5 w-5 accent-[#7D001D]" /><span className="font-bold">Active and available for enrollment</span></label>
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-5 dark:border-slate-700">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button>
          <button disabled={saving} className="rounded-2xl bg-[#7D001D] px-6 py-3 font-bold text-white disabled:opacity-60">{saving ? 'Saving...' : 'Save record'}</button>
        </div>
      </form>
    </div>
  );
}

function AcademicActionModal({ type, item, action, onClose, onCompleted }) {
  const [busy, setBusy] = useState(false);
  const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
  const deleting = action === 'delete';

  const confirmAction = async () => {
    setBusy(true);

    try {
      const plural = type === 'college' ? 'colleges' : type === 'program' ? 'programs' : 'sections';
      const endpoint = `/admin/academic/${plural}/${item.id}${deleting ? '/permanent' : ''}`;
      const response = await api.delete(endpoint);
      toast.success(response.data.message);
      onCompleted();
    } catch (error) {
      toast.error(error.response?.data?.message || `Unable to ${deleting ? 'delete' : 'deactivate'} this ${type}.`);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
      onMouseDown={busy ? undefined : onClose}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="academic-action-title"
        aria-describedby="academic-action-description"
        onMouseDown={(event) => event.stopPropagation()}
        className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
      >
        <div className={`border-b p-6 ${deleting ? 'border-rose-100 bg-rose-50 dark:border-rose-950 dark:bg-rose-950/30' : 'border-amber-100 bg-amber-50 dark:border-amber-950 dark:bg-amber-950/30'}`}>
          <div className="flex items-start gap-4">
            <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-2xl ${deleting ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'}`}>
              {deleting ? <TrashIcon className="h-6 w-6" /> : <PowerIcon className="h-6 w-6" />}
            </div>
            <div className="min-w-0 flex-1">
              <p className={`text-xs font-black uppercase tracking-widest ${deleting ? 'text-rose-700 dark:text-rose-300' : 'text-amber-700 dark:text-amber-300'}`}>{deleting ? 'Permanent delete confirmation' : 'Deactivate confirmation'}</p>
              <h2 id="academic-action-title" className="mt-1 text-2xl font-black">{deleting ? 'Delete' : 'Deactivate'} {typeLabel}?</h2>
              <p id="academic-action-description" className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {deleting
                  ? `This permanently deletes the ${type}. This action cannot be undone and is blocked when connected records exist.`
                  : `This removes the ${type} from active academic workflows while preserving connected and historical records.`}
              </p>
            </div>
            <button
              type="button"
              onClick={onClose}
              disabled={busy}
              className="rounded-xl p-2 text-slate-500 transition hover:bg-white/70 disabled:opacity-40 dark:hover:bg-slate-800"
              aria-label="Close delete confirmation"
            >
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="p-6">
          <div className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <p className="text-xs font-black uppercase tracking-wider text-slate-500">{typeLabel}</p>
            <p className="mt-1 break-words text-lg font-black text-slate-900 dark:text-white">{item.name}</p>
            {item.code && <p className="mt-1 text-sm font-bold text-[#7D001D] dark:text-red-300">{item.code}</p>}
          </div>
        </div>

        <div className="flex flex-col-reverse gap-3 border-t border-slate-200 p-6 dark:border-slate-700 sm:flex-row sm:justify-end">
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-2xl border border-slate-200 px-5 py-3 font-bold disabled:opacity-40 dark:border-slate-700"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={confirmAction}
            disabled={busy}
            className={`rounded-2xl px-5 py-3 font-black text-white transition disabled:cursor-not-allowed disabled:opacity-40 ${deleting ? 'bg-rose-700 hover:bg-rose-800' : 'bg-amber-600 hover:bg-amber-700'}`}
          >
            {busy ? `${deleting ? 'Deleting' : 'Deactivating'}...` : `${deleting ? 'Delete' : 'Deactivate'} ${typeLabel}`}
          </button>
        </div>
      </section>
    </div>
  );
}

export default function AdminAcademic() {
  const [data, setData] = useState(null);
  const [tab, setTab] = useState('colleges');
  const [modal, setModal] = useState(null);
  const [actionTarget, setActionTarget] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get('/admin/academic')).data); }
    catch (error) { toast.error(error.response?.data?.message || 'Unable to load academic structures.'); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  if (loading && !data) return <LoadingPanel />;
  const records = data?.[tab] || [];
  const type = tab.slice(0, -1);
  const tabs = [
    ['colleges', BuildingLibraryIcon],
    ['programs', AcademicCapIcon],
    ['sections', RectangleGroupIcon],
  ];

  return (
    <div className="space-y-6">
      <PageIntro eyebrow="System administration" title="Academic Management" description="Maintain colleges, degree programs, sections, and academic-year structures used by enrollment and internship workflows." icon={AcademicCapIcon} actions={
        <button onClick={() => setModal({ type, item: null })} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#690016]"><PlusIcon className="h-5 w-5" />Add {type}</button>
      } />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Active colleges" value={data?.overview.colleges} icon={BuildingLibraryIcon} />
        <MetricCard label="Active programs" value={data?.overview.programs} icon={AcademicCapIcon} tone="gold" />
        <MetricCard label="Active sections" value={data?.overview.sections} icon={RectangleGroupIcon} tone="blue" />
        <MetricCard label="Student records" value={data?.overview.enrolled_students} icon={UsersIcon} tone="green" />
      </div>
      <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="flex gap-1 overflow-x-auto border-b border-slate-200 p-3 dark:border-slate-700">
          {tabs.map(([name, Icon]) => <button key={name} onClick={() => setTab(name)} className={`inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black capitalize transition ${tab === name ? 'bg-[#7D001D] text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'}`}><Icon className="h-5 w-5" />{name}</button>)}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px]">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/70"><tr><th className="px-6 py-4">Record</th><th className="px-6 py-4">{type === 'section' ? 'Academic details' : 'Code / Parent'}</th><th className="px-6 py-4">Connected</th><th className="px-6 py-4">Status</th><th className="px-6 py-4 text-right">Actions</th></tr></thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {records.map((item) => <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td className="px-6 py-4"><p className="font-black text-slate-900 dark:text-white">{item.name}</p><p className="mt-1 max-w-xs truncate text-xs text-slate-500">{type === 'college' ? `${Number(item.required_ojt_hours || 486)} required internship hours` : type === 'program' ? item.college?.name || 'Academic program' : item.program?.name || 'Official academic record'}</p></td>
                <td className="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{item.code || (item.program ? `${item.program.code} · Year ${item.year_level} · ${item.academic_year}` : item.college?.code)}</td>
                <td className="px-6 py-4 text-sm font-bold text-slate-700 dark:text-slate-200">{type === 'college' ? `${item.programs_count} programs · ${item.students_count} students` : type === 'program' ? `${item.students_count} students` : item.program?.code}</td>
                <td className="px-6 py-4"><span className={`rounded-full px-3 py-1 text-xs font-bold ${item.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300'}`}>{item.is_active ? 'Active' : 'Inactive'}</span></td>
                <td className="px-6 py-4"><div className="flex justify-end gap-2"><button onClick={() => setModal({ type, item })} className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:text-[#7D001D] dark:border-slate-700 dark:text-slate-300"><PencilSquareIcon className="h-5 w-5" /></button>{item.is_active && <button onClick={() => setActionTarget({ type, item, action: 'deactivate' })} className="rounded-xl border border-slate-200 p-2 text-amber-600 dark:border-slate-700" title={`Deactivate ${item.name}`}><PowerIcon className="h-5 w-5" /></button>}<button onClick={() => setActionTarget({ type, item, action: 'delete' })} className="rounded-xl border border-rose-200 p-2 text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30" title={`Permanently delete ${item.name}`} aria-label={`Permanently delete ${item.name}`}><TrashIcon className="h-5 w-5" /></button></div></td>
              </tr>)}
              {records.length === 0 && <tr><td colSpan="5" className="px-6 py-14 text-center text-slate-500">No {tab} have been configured.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
      {modal && <AcademicModal type={modal.type} item={modal.item} colleges={data?.colleges || []} programs={data?.programs || []} onClose={() => setModal(null)} onSaved={() => { setModal(null); load(); }} />}
      {actionTarget && <AcademicActionModal type={actionTarget.type} item={actionTarget.item} action={actionTarget.action} onClose={() => setActionTarget(null)} onCompleted={() => { setActionTarget(null); load(); }} />}
    </div>
  );
}
