import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AcademicCapIcon,
  BuildingLibraryIcon,
  PencilSquareIcon,
  PlusIcon,
  RectangleGroupIcon,
  TrashIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const baseForms = {
  college: { name: '', code: '' },
  program: { college_id: '', name: '', code: '' },
  section: { program_id: '', name: '', year_level: '', academic_period_id: '' },
};

function AcademicModal({ type, item, colleges, programs, academicPeriods, onClose, onSaved }) {
  const [form, setForm] = useState(() => item ? {
    ...item,
    college_id: item.college_id || '',
    program_id: item.program_id || '',
    academic_period_id: item.academic_period_id || '',
  } : {
    ...baseForms[type],
    ...(type === 'section' ? { academic_period_id: academicPeriods.find((period) => period.status === 'active')?.id || '' } : {}),
  });
  const [saving, setSaving] = useState(false);
  const title = `${item ? 'Edit' : 'Add'} ${type}`;

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const endpoint = `/admin/academic/${type === 'college' ? 'colleges' : type === 'program' ? 'programs' : 'sections'}`;
      const payload = type === 'college' ? { name: form.name, code: form.code } : form;
      const response = item ? await api.put(`${endpoint}/${item.id}`, payload) : await api.post(endpoint, payload);
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
          {type === 'section' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <label><span className="mb-2 block text-sm font-bold">Year level</span><input type="number" required min="1" max="6" step="1" value={form.year_level} onChange={(event) => setForm({ ...form, year_level: event.target.value })} placeholder="Enter year level" className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" /></label>
              <label>
                <span className="mb-2 block text-sm font-bold">Academic year / semester</span>
                <select required value={form.academic_period_id} onChange={(event) => setForm({ ...form, academic_period_id: event.target.value })} disabled={academicPeriods.length === 0} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                  <option value="">Select academic period</option>
                  {academicPeriods.map((period) => <option key={period.id} value={period.id}>{period.academic_year.replace('-', '–')} · {period.semester}{period.status === 'active' ? ' (Active)' : period.status === 'completed' ? ' (Completed)' : ' (Draft)'}</option>)}
                </select>
                <span className="mt-1 block text-xs text-slate-500">{academicPeriods.length === 0 ? <>Create an academic period in <Link to="/admin/system" className="font-bold text-[#7D001D] underline dark:text-rose-300">System Settings</Link> first.</> : 'Choose from the Academic Periods in System Settings.'}</span>
              </label>
            </div>
          )}
        </div>
        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-5 dark:border-slate-700">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button>
          <button disabled={saving || (type === 'section' && !form.academic_period_id)} className="rounded-2xl bg-[#7D001D] px-6 py-3 font-bold text-white disabled:opacity-60">{saving ? 'Saving...' : 'Save record'}</button>
        </div>
      </form>
    </div>
  );
}

function AcademicActionModal({ type, item, onClose, onCompleted }) {
  const [busy, setBusy] = useState(false);
  const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);

  const confirmAction = async () => {
    setBusy(true);

    try {
      const plural = type === 'college' ? 'colleges' : type === 'program' ? 'programs' : 'sections';
      const endpoint = `/admin/academic/${plural}/${item.id}`;
      const response = await api.delete(endpoint);
      toast.success(response.data.message);
      onCompleted();
    } catch (error) {
      toast.error(error.response?.data?.message || `Unable to delete this ${type}.`);
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
        <div className="border-b border-rose-100 bg-rose-50 p-6 dark:border-rose-950 dark:bg-rose-950/30">
          <div className="flex items-start gap-4">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
              <TrashIcon className="h-6 w-6" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-black uppercase tracking-widest text-rose-700 dark:text-rose-300">Delete confirmation</p>
              <h2 id="academic-action-title" className="mt-1 text-2xl font-black">Delete {typeLabel}?</h2>
              <p id="academic-action-description" className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                This permanently deletes the {type}. This action cannot be undone and is blocked when connected records exist.
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
            className="rounded-2xl bg-rose-700 px-5 py-3 font-black text-white transition hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-40"
          >
            {busy ? 'Deleting...' : `Delete ${typeLabel}`}
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
      <div className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Colleges" value={data?.overview.colleges} icon={BuildingLibraryIcon} />
        <MetricCard label="Programs" value={data?.overview.programs} icon={AcademicCapIcon} tone="gold" />
        <MetricCard label="Sections" value={data?.overview.sections} icon={RectangleGroupIcon} tone="blue" />
        <MetricCard label="Student records" value={data?.overview.enrolled_students} icon={UsersIcon} tone="green" />
      </div>
      <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="portal-mobile-tabs portal-mobile-tabs--three grid grid-cols-3 gap-1 border-b border-slate-200 p-2 dark:border-slate-700 lg:flex lg:p-3">
          {tabs.map(([name, Icon]) => <button key={name} onClick={() => setTab(name)} className={`inline-flex min-w-0 flex-col items-center justify-center gap-1 rounded-2xl px-2 py-2 text-xs font-black capitalize transition lg:flex-row lg:gap-2 lg:px-4 lg:py-2.5 lg:text-sm ${tab === name ? 'bg-[#7D001D] text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'}`}><Icon className="h-4 w-4 lg:h-5 lg:w-5" />{name}</button>)}
        </div>

        <div className="touch-layout-only divide-y divide-slate-100 dark:divide-slate-800 lg:hidden">
          {records.map((item) => (
            <article key={item.id} className="p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="break-words text-sm font-black text-slate-900 dark:text-white">{item.name}</p>
                  <p className="mt-1 text-xs leading-5 text-slate-500">
                    {type === 'college' ? 'College record' : type === 'program' ? item.college?.name || 'Academic program' : item.program?.name || 'Official academic record'}
                  </p>
                </div>
                <div className="flex shrink-0 gap-2">
                  <button onClick={() => setModal({ type, item })} className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300" title={`Edit ${item.name}`} aria-label={`Edit ${item.name}`}><PencilSquareIcon className="h-5 w-5" /></button>
                  <button onClick={() => setActionTarget({ type, item })} className="grid h-10 w-10 place-items-center rounded-xl border border-rose-200 text-rose-700 dark:border-rose-900 dark:text-rose-300" title={`Delete ${item.name}`} aria-label={`Delete ${item.name}`}><TrashIcon className="h-5 w-5" /></button>
                </div>
              </div>
              <dl className="mt-3 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                <div className="min-w-0">
                  <dt className="text-[10px] font-black uppercase tracking-wider text-slate-400">{type === 'section' ? 'Academic details' : 'Code / Parent'}</dt>
                  <dd className="mt-1 break-words font-bold text-slate-700 dark:text-slate-200">{item.code || (item.program ? `${item.program.code} · Year ${item.year_level} · ${item.academic_year}${item.academic_period ? ` · ${item.academic_period.semester}` : ''}` : item.college?.code) || '—'}</dd>
                </div>
                <div className="min-w-0">
                  <dt className="text-[10px] font-black uppercase tracking-wider text-slate-400">Connected</dt>
                  <dd className="mt-1 break-words font-bold text-slate-700 dark:text-slate-200">{type === 'college' ? `${item.programs_count} programs · ${item.students_count} students` : type === 'program' ? `${item.students_count} students` : item.program?.code || '—'}</dd>
                </div>
              </dl>
            </article>
          ))}
          {records.length === 0 && <p className="px-4 py-12 text-center text-sm text-slate-500">No {tab} have been configured.</p>}
        </div>

        <div className="desktop-layout-only hidden overflow-x-auto lg:block">
          <table className="w-full min-w-[720px]">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/70"><tr><th className="px-6 py-4">Record</th><th className="px-6 py-4">{type === 'section' ? 'Academic details' : 'Code / Parent'}</th><th className="px-6 py-4">Connected</th><th className="px-6 py-4 text-right">Actions</th></tr></thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {records.map((item) => <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td className="px-6 py-4"><p className="font-black text-slate-900 dark:text-white">{item.name}</p><p className="mt-1 max-w-xs truncate text-xs text-slate-500">{type === 'college' ? 'College record' : type === 'program' ? item.college?.name || 'Academic program' : item.program?.name || 'Official academic record'}</p></td>
                <td className="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{item.code || (item.program ? `${item.program.code} · Year ${item.year_level} · ${item.academic_year}${item.academic_period ? ` · ${item.academic_period.semester}` : ''}` : item.college?.code)}</td>
                <td className="px-6 py-4 text-sm font-bold text-slate-700 dark:text-slate-200">{type === 'college' ? `${item.programs_count} programs · ${item.students_count} students` : type === 'program' ? `${item.students_count} students` : item.program?.code}</td>
                <td className="px-6 py-4"><div className="flex justify-end gap-2"><button onClick={() => setModal({ type, item })} className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:text-[#7D001D] dark:border-slate-700 dark:text-slate-300"><PencilSquareIcon className="h-5 w-5" /></button><button onClick={() => setActionTarget({ type, item })} className="rounded-xl border border-rose-200 p-2 text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30" title={`Delete ${item.name}`} aria-label={`Delete ${item.name}`}><TrashIcon className="h-5 w-5" /></button></div></td>
              </tr>)}
              {records.length === 0 && <tr><td colSpan="4" className="px-6 py-14 text-center text-slate-500">No {tab} have been configured.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
      {modal && <AcademicModal type={modal.type} item={modal.item} colleges={data?.colleges || []} programs={data?.programs || []} academicPeriods={data?.academic_periods || []} onClose={() => setModal(null)} onSaved={() => { setModal(null); load(); }} />}
      {actionTarget && <AcademicActionModal type={actionTarget.type} item={actionTarget.item} onClose={() => setActionTarget(null)} onCompleted={() => { setActionTarget(null); load(); }} />}
    </div>
  );
}
