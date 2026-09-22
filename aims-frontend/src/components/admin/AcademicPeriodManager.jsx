import { useCallback, useEffect, useState } from 'react';
import { CalendarDaysIcon, PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';

const semesters = ['1st Semester', '2nd Semester', 'Summer'];
const statusLabels = { draft: 'Draft', active: 'Active', completed: 'Completed' };
const statusColors = {
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  active: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
  completed: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
};
const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const buttonClass = 'rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800';

function displayYear(year) {
  return year.replace('-', '–');
}

function displayDate(date) {
  return new Intl.DateTimeFormat('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
}

function linkedCount(period) {
  return (period.students_count || 0) + (period.enrollments_count || 0)
    + (period.sections_count || 0) + (period.report_templates_count || 0) + (period.generated_reports_count || 0);
}

function errorMessage(error) {
  return Object.values(error.response?.data?.errors || {}).flat()[0]
    || error.response?.data?.message || 'Unable to save the academic period.';
}

function PeriodForm({ period, activePeriod, onClose, onSaved }) {
  const year = new Date().getFullYear();
  const [form, setForm] = useState(period ? {
    academic_year: period.academic_year,
    semester: period.semester,
    start_date: period.start_date,
    end_date: period.end_date,
    status: period.status,
  } : { academic_year: `${year}-${year + 1}`, semester: '2nd Semester', start_date: '', end_date: '', status: 'draft' });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [failure, setFailure] = useState('');

  const change = (key, value) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: undefined }));
    setFailure('');
  };
  const close = () => { if (!saving) onClose(); };
  const submit = async (event) => {
    event.preventDefault();
    if (saving) return;
    setSaving(true);
    setErrors({});
    setFailure('');
    try {
      const response = period
        ? await api.put(`/admin/academic-periods/${period.id}`, form)
        : await api.post('/admin/academic-periods', form);
      toast.success(response.data.message);
      onSaved();
    } catch (error) {
      setErrors(error.response?.data?.errors || {});
      setFailure(errorMessage(error));
    } finally {
      setSaving(false);
    }
  };
  const help = (key) => errors[key] && <span id={`period-${key}-error`} className="mt-1 block text-xs text-red-600 dark:text-red-400">{errors[key][0]}</span>;
  const inputProps = (key) => ({
    id: `period-${key}`,
    value: form[key],
    onChange: (event) => change(key, event.target.value),
    'aria-invalid': Boolean(errors[key]),
    'aria-describedby': errors[key] ? `period-${key}-error` : undefined,
    className: inputClass,
    required: true,
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm" onMouseDown={close}>
      <form onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} role="dialog" aria-modal="true" aria-labelledby="period-form-title" className="flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
        <div className="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-700">
          <div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Academic calendar</p><h2 id="period-form-title" className="mt-1 text-xl font-black">{period ? 'Edit academic period' : 'Create academic period'}</h2></div>
          <button type="button" onClick={close} disabled={saving} aria-label="Close academic period form" className="rounded-xl p-2 text-slate-500"><XMarkIcon className="h-5 w-5" /></button>
        </div>
        <fieldset disabled={saving} className="min-h-0 space-y-5 overflow-y-auto p-5">
          {failure && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{failure}</p>}
          <div className="grid gap-4 sm:grid-cols-2">
            <label htmlFor="period-academic_year" className="block"><span className="mb-2 block text-sm font-bold">Academic year</span><input {...inputProps('academic_year')} placeholder="2026-2027" pattern="\d{4}[-–]\d{4}" maxLength={9} />{help('academic_year')}</label>
            <label htmlFor="period-semester" className="block"><span className="mb-2 block text-sm font-bold">Semester</span><select {...inputProps('semester')}>{semesters.map((semester) => <option key={semester}>{semester}</option>)}</select>{help('semester')}</label>
            <label htmlFor="period-start_date" className="block"><span className="mb-2 block text-sm font-bold">Start date</span><input {...inputProps('start_date')} type="date" />{help('start_date')}</label>
            <label htmlFor="period-end_date" className="block"><span className="mb-2 block text-sm font-bold">End date</span><input {...inputProps('end_date')} type="date" min={form.start_date || undefined} />{help('end_date')}</label>
          </div>
          <label htmlFor="period-status" className="block"><span className="mb-2 block text-sm font-bold">Status</span><select {...inputProps('status')} disabled={period?.status === 'completed'}>{period?.status === 'completed' ? <option value="completed">Completed</option> : <><option value="draft">Draft</option><option value="active">Active</option></>}</select>{help('status')}</label>
          <p className="rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            {form.status === 'active' && activePeriod && activePeriod.id !== period?.id
              ? `Only one period can be active. ${displayYear(activePeriod.academic_year)} · ${activePeriod.semester} will become Draft when you save.`
              : 'Only one academic period can be active. Completed periods stay archived, and their linked records are preserved.'}
          </p>
        </fieldset>
        <div className="flex shrink-0 justify-end gap-3 border-t border-slate-200 p-5 dark:border-slate-700">
          <button type="button" onClick={close} disabled={saving} className={buttonClass}>Cancel</button>
          <button type="submit" disabled={saving} className="rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Saving...' : period ? 'Save period' : 'Create period'}</button>
        </div>
      </form>
    </div>
  );
}

function PeriodAction({ action, activePeriod, onClose, onSaved }) {
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState('');
  const { period, type } = action;
  const title = type === 'delete' ? 'Delete academic period?' : type === 'complete' ? 'Complete / archive period?' : 'Set this period as active?';
  const description = type === 'delete'
    ? 'This will permanently delete the period. Deletion is allowed only when no student or other record is linked to it.'
    : type === 'complete'
      ? 'The period will become Completed and move to the archive. Student and other linked records will be preserved.'
      : activePeriod && activePeriod.id !== period.id
        ? `${displayYear(activePeriod.academic_year)} · ${activePeriod.semester} will become Draft. This period will be the only active period.`
        : 'This will become the active academic period.';
  const close = () => { if (!busy) onClose(); };
  const confirm = async () => {
    if (busy) return;
    setBusy(true);
    try {
      const response = type === 'delete'
        ? await api.delete(`/admin/academic-periods/${period.id}`)
        : await api.put(`/admin/academic-periods/${period.id}/${type}`);
      toast.success(response.data.message);
      onSaved();
    } catch (error) {
      setFailure(errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm" onMouseDown={close}>
      <section role="dialog" aria-modal="true" aria-labelledby="period-action-title" onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
        <h2 id="period-action-title" className="text-xl font-black">{title}</h2>
        <p className="mt-2 font-bold text-[#7D001D] dark:text-rose-300">{displayYear(period.academic_year)} · {period.semester}</p>
        <p className="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">{description}</p>
        {failure && <p role="alert" className="mt-3 text-sm text-red-600 dark:text-red-400">{failure}</p>}
        <div className="mt-6 flex justify-end gap-3"><button type="button" onClick={close} disabled={busy} className={buttonClass}>Cancel</button><button type="button" onClick={confirm} disabled={busy} className="rounded-xl bg-[#7D001D] px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{busy ? 'Saving...' : type === 'delete' ? 'Delete period' : type === 'complete' ? 'Complete / archive' : 'Set as active'}</button></div>
      </section>
    </div>
  );
}

export default function AcademicPeriodManager() {
  const [periods, setPeriods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [failure, setFailure] = useState('');
  const [filter, setFilter] = useState('all');
  const [formPeriod, setFormPeriod] = useState(null);
  const [formOpen, setFormOpen] = useState(false);
  const [action, setAction] = useState(null);
  const load = useCallback(async () => {
    setLoading(true);
    setFailure('');
    try {
      const response = await api.get('/admin/academic-periods');
      setPeriods(response.data.periods || []);
    } catch (error) {
      setFailure(error.response?.data?.message || 'Unable to load academic periods.');
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => { load(); }, [load]);
  const activePeriod = periods.find((period) => period.status === 'active');
  const visiblePeriods = periods.filter((period) => filter === 'all' || (filter === 'archive' ? period.status === 'completed' : period.status !== 'completed'));
  const saved = () => { setFormOpen(false); setAction(null); load(); };
  const edit = (period = null) => { setFormPeriod(period); setFormOpen(true); };

  return (
    <section aria-labelledby="academic-periods-title" className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
      <div className="flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 dark:border-slate-800 sm:flex-row sm:items-start">
        <div className="flex items-start gap-4"><div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#7D001D]/10 text-[#7D001D] dark:bg-red-400/10 dark:text-red-300"><CalendarDaysIcon className="h-6 w-6" /></div><div><h2 id="academic-periods-title" className="text-xl font-black">Academic Periods</h2><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage academic years, semesters, dates, and the active period.</p></div></div>
        <button type="button" onClick={() => edit()} disabled={loading || Boolean(failure)} className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-3 text-sm font-bold text-white disabled:opacity-50"><PlusIcon className="h-5 w-5" />Create academic period</button>
      </div>
      {loading ? <p role="status" className="py-8 text-sm text-slate-500">Loading academic periods...</p> : failure ? <div role="alert" className="py-6"><p className="text-sm text-red-600 dark:text-red-400">{failure}</p><button type="button" onClick={load} className={`${buttonClass} mt-3`}>Reload periods</button></div> : <>
        <div className="mt-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div className="text-sm"><p className="font-bold text-slate-800 dark:text-white">{activePeriod ? `${displayYear(activePeriod.academic_year)} · ${activePeriod.semester}` : 'No active academic period'}</p><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{activePeriod ? `${displayDate(activePeriod.start_date)} – ${displayDate(activePeriod.end_date)}` : 'Create a period or set a draft period as active.'}</p></div>
          <label className="flex items-center gap-2 text-xs font-bold text-slate-500"><span>Show</span><select value={filter} onChange={(event) => setFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="all">All periods</option><option value="current">Active & drafts</option><option value="archive">Completed / archive</option></select></label>
        </div>
        {visiblePeriods.length === 0 ? <p className="mt-5 rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-700">{periods.length === 0 ? 'No academic periods yet. Create your first academic period.' : 'No periods in this view.'}</p> : <div className="mt-5 grid gap-4 xl:grid-cols-2">
          {visiblePeriods.map((period) => <article key={period.id} className={`rounded-2xl border p-4 ${period.status === 'active' ? 'border-[#7D001D]/30 bg-[#7D001D]/[0.02] dark:bg-rose-950/10' : 'border-slate-200 dark:border-slate-700'}`}>
            <div className="flex items-start justify-between gap-3"><h3 className="text-lg font-black">{displayYear(period.academic_year)}</h3><span className={`rounded-full px-3 py-1 text-xs font-bold ${statusColors[period.status]}`}>{statusLabels[period.status]}</span></div>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt className="text-xs text-slate-500">Semester</dt><dd className="mt-1 font-bold">{period.semester}</dd></div><div><dt className="text-xs text-slate-500">Status</dt><dd className="mt-1 font-bold">{statusLabels[period.status]}</dd></div><div><dt className="text-xs text-slate-500">Start date</dt><dd className="mt-1">{displayDate(period.start_date)}</dd></div><div><dt className="text-xs text-slate-500">End date</dt><dd className="mt-1">{displayDate(period.end_date)}</dd></div></dl>
            <p className="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400">{period.students_count || 0} students · {period.enrollments_count || 0} enrollments · {period.sections_count || 0} sections · {period.report_templates_count || 0} report templates · {period.generated_reports_count || 0} reports</p>
            <div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={() => edit(period)} className={buttonClass} aria-label={`Edit ${period.academic_year} ${period.semester}`}>Edit</button>{period.status === 'draft' && <button type="button" onClick={() => setAction({ period, type: 'activate' })} className={`${buttonClass} text-[#7D001D] dark:text-rose-300`}>Set as active</button>}{period.status !== 'completed' && <button type="button" onClick={() => setAction({ period, type: 'complete' })} className={buttonClass}>Complete / archive</button>}<button type="button" onClick={() => setAction({ period, type: 'delete' })} disabled={linkedCount(period) > 0} title={linkedCount(period) > 0 ? 'This period has linked records. Complete / archive it instead.' : undefined} className={`${buttonClass} text-red-600 dark:text-red-400`}>Delete</button></div>
            {linkedCount(period) > 0 && <p className="mt-2 text-xs text-slate-500">Deletion is blocked because this period has linked records.</p>}
          </article>)}
        </div>}
      </>}
      {formOpen && <PeriodForm period={formPeriod} activePeriod={activePeriod} onClose={() => setFormOpen(false)} onSaved={saved} />}
      {action && <PeriodAction action={action} activePeriod={activePeriod} onClose={() => setAction(null)} onSaved={saved} />}
    </section>
  );
}
