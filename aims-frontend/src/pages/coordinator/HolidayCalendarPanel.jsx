import { useEffect, useMemo, useState } from 'react';
import { FiChevronLeft, FiChevronRight, FiEdit2, FiTrash2 } from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const styles = {
  national_holiday: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
  local_holiday: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  special_non_working: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
};
const labels = {
  national_holiday: 'National',
  local_holiday: 'Local',
  special_non_working: 'Special non-working',
};
const dateKey = (year, month, day) => `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
const datePart = (value) => String(value || '').slice(0, 10);

export function HolidayCalendarPanel({ holidays, htes, onEdit, onDelete, focusDate }) {
  const today = new Date();
  const [month, setMonth] = useState(() => new Date(today.getFullYear(), today.getMonth(), 1));
  const [selected, setSelected] = useState(() => dateKey(today.getFullYear(), today.getMonth(), today.getDate()));
  const [type, setType] = useState('all');
  const [hteId, setHteId] = useState('all');
  useEffect(() => {
    if (!focusDate) return;
    const date = new Date(`${focusDate}T00:00:00`);
    setMonth(new Date(date.getFullYear(), date.getMonth(), 1));
    setSelected(focusDate);
    setType('all');
    setHteId('all');
  }, [focusDate]);
  const year = month.getFullYear();
  const monthIndex = month.getMonth();
  const dayCount = new Date(year, monthIndex + 1, 0).getDate();
  const leading = new Date(year, monthIndex, 1).getDay();

  const visible = useMemo(() => holidays.flatMap((holiday) => {
    if (type !== 'all' && holiday.holiday_type !== type) return [];
    if (hteId !== 'all' && holiday.holiday_type === 'local_holiday'
      && !holiday.htes?.some((hte) => String(hte.id) === hteId)) return [];
    const original = datePart(holiday.date);
    const occurrence = holiday.is_recurring ? `${year}${original.slice(4)}` : original;
    return occurrence.startsWith(`${year}-${String(monthIndex + 1).padStart(2, '0')}-`)
      ? [{ ...holiday, occurrence }] : [];
  }), [holidays, type, hteId, year, monthIndex]);

  const byDate = useMemo(() => visible.reduce((map, holiday) => {
    (map[holiday.occurrence] ||= []).push(holiday);
    return map;
  }, {}), [visible]);
  const selectedHolidays = byDate[selected] || [];
  const monthHolidays = [...visible].sort((first, second) => first.occurrence.localeCompare(second.occurrence));
  const changeMonth = (offset) => {
    const next = new Date(year, monthIndex + offset, 1);
    setMonth(next);
    setSelected(dateKey(next.getFullYear(), next.getMonth(), 1));
  };

  return <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
    <div className="min-w-0">
      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div className="flex items-center gap-2">
          <button type="button" onClick={() => changeMonth(-1)} aria-label="Previous month" className="rounded-lg border border-slate-200 p-2 dark:border-gray-600"><FiChevronLeft /></button>
          <h3 className="min-w-40 text-center text-sm font-black text-slate-800 dark:text-white">{month.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })}</h3>
          <button type="button" onClick={() => changeMonth(1)} aria-label="Next month" className="rounded-lg border border-slate-200 p-2 dark:border-gray-600"><FiChevronRight /></button>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <label className="text-[10px] font-bold text-slate-500">Type
            <select value={type} onChange={(event) => setType(event.target.value)} className="mt-1 block w-36 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
              <option value="all">All types</option><option value="national_holiday">National</option><option value="local_holiday">Local</option><option value="special_non_working">Special non-working</option>
            </select>
          </label>
          <label className="text-[10px] font-bold text-slate-500">Applies to
            <select value={hteId} onChange={(event) => setHteId(event.target.value)} className="mt-1 block w-48 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
              <option value="all">All HTEs</option>{htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}
            </select>
          </label>
          {(type !== 'all' || hteId !== 'all') && <button type="button" onClick={() => { setType('all'); setHteId('all'); }} className="rounded-lg px-2 py-2 text-xs font-bold text-[#7D001D] hover:bg-rose-50">Clear filters</button>}
        </div>
      </div>
      <div className="grid grid-cols-7 text-center text-[10px] font-black uppercase text-slate-400">{['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => <div key={day} className="py-2">{day}</div>)}</div>
      <div className="grid grid-cols-7 gap-1">{Array.from({ length: leading + dayCount }, (_, index) => {
        const day = index - leading + 1;
        if (day < 1) return <div key={`blank-${index}`} />;
        const key = dateKey(year, monthIndex, day);
        const items = byDate[key] || [];
        return <button key={key} type="button" onClick={() => setSelected(key)} aria-label={`${key}: ${items.length} holiday(s)`} aria-pressed={selected === key} className={`min-h-16 rounded-xl border p-1.5 text-left align-top transition sm:min-h-20 sm:p-2 ${selected === key ? 'border-[#7D001D] bg-rose-50 ring-1 ring-[#7D001D] dark:bg-rose-950/30' : 'border-slate-100 hover:border-slate-300 dark:border-gray-700'}`}>
          <span className="text-xs font-black text-slate-700 dark:text-white">{day}</span>
          <span className="mt-1 block space-y-1">{items.slice(0, 2).map((holiday) => <span key={holiday.id} className={`block truncate rounded px-1 py-0.5 text-[9px] font-bold ${styles[holiday.holiday_type] || styles.national_holiday}`} title={holiday.name}>{holiday.name}</span>)}{items.length > 2 && <span className="block text-[9px] font-bold text-slate-500">+{items.length - 2} more</span>}</span>
        </button>;
      })}</div>
      <div className="mt-4 flex flex-wrap gap-3 text-[10px] font-bold text-slate-500">{Object.entries(labels).map(([key, label]) => <span key={key} className={`rounded-full px-2 py-1 ${styles[key]}`}>{label}</span>)}</div>
    </div>
    <aside className="self-start rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-gray-700 dark:bg-gray-900">
      <h3 className="text-sm font-black text-slate-800 dark:text-white">{new Date(`${selected}T00:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</h3>
      {selectedHolidays.length ? <div className="mt-4 space-y-3">{selectedHolidays.map((holiday) => <article key={holiday.id} className="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
        <div className="flex items-start justify-between gap-2"><p className="text-sm font-black text-slate-800 dark:text-white">{holiday.name}</p><div className="flex"><button type="button" onClick={() => onEdit(holiday)} aria-label={`Edit ${holiday.name}`} className="p-1 text-amber-600"><FiEdit2 /></button><button type="button" onClick={() => onDelete(holiday)} aria-label={`Delete ${holiday.name}`} className="p-1 text-rose-600"><FiTrash2 /></button></div></div>
        <span className={`mt-2 inline-block rounded px-2 py-1 text-[10px] font-bold ${styles[holiday.holiday_type] || styles.national_holiday}`}>{labels[holiday.holiday_type] || 'National'}</span>
        {holiday.affected_area && <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">Area: {holiday.affected_area}</p>}
        {!!holiday.htes?.length && <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">HTEs: {holiday.htes.map((hte) => hte.name).join(', ')}</p>}
        {holiday.official_reference && <p className="mt-2 text-xs text-slate-500">Reference: {holiday.official_reference}</p>}
        {holiday.source_url && <a href={holiday.source_url} target="_blank" rel="noreferrer" className="mt-1 block text-xs font-bold text-[#7D001D] underline">View source</a>}
        {holiday.is_recurring && <p className="mt-2 text-[10px] font-bold text-blue-600">Repeats yearly</p>}
      </article>)}</div> : <p className="mt-3 text-xs text-slate-500">No holidays on this date.</p>}
      <div className="mt-5 border-t border-slate-200 pt-4 dark:border-gray-700">
        <p className="text-[10px] font-black uppercase tracking-wide text-slate-500">This month · {monthHolidays.length}</p>
        {monthHolidays.length ? <div className="mt-2 space-y-1">{monthHolidays.map((holiday) => <button key={`${holiday.id}-${holiday.occurrence}`} type="button" onClick={() => setSelected(holiday.occurrence)} className="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left hover:bg-white dark:hover:bg-gray-800"><span className="w-11 shrink-0 text-xs font-black text-[#7D001D]">{new Date(`${holiday.occurrence}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</span><span className="min-w-0 text-xs font-bold text-slate-700 dark:text-slate-200">{holiday.name}</span></button>)}</div> : <p className="mt-2 text-xs text-slate-500">No holidays match the current filters.</p>}
      </div>
    </aside>
  </div>;
}

export function HolidayImportForm({ onClose, onImported }) {
  const [year, setYear] = useState(new Date().getFullYear());
  const [preview, setPreview] = useState(null);
  const [selected, setSelected] = useState([]);
  const [holidayTypes, setHolidayTypes] = useState({});
  const [reference, setReference] = useState('');
  const [sourceUrl, setSourceUrl] = useState('');
  const [busy, setBusy] = useState(false);

  const fetchHolidays = async () => {
    setBusy(true);
    setPreview(null);
    try {
      const { data } = await api.get('/coordinator/htes/holidays/import-preview', { params: { year } });
      setPreview(data);
      setSelected(data.holidays.filter((holiday) => !holiday.already_saved).map((holiday) => holiday.date));
      setHolidayTypes(Object.fromEntries(data.holidays.map((holiday) => [holiday.date, 'national_holiday'])));
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to fetch Philippine holidays.');
    } finally { setBusy(false); }
  };

  const importSelected = async () => {
    setBusy(true);
    try {
      const { data } = await api.post('/coordinator/htes/holidays/import', {
        year: preview.year, dates: selected, holiday_types: holidayTypes,
        official_reference: reference.trim(), source_url: sourceUrl.trim() || null,
      });
      toast.success(data.message);
      onImported(selected[0]);
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || 'Unable to import holidays.');
    } finally { setBusy(false); }
  };

  return <div className="space-y-4 p-5">
    <p className="text-xs text-slate-600 dark:text-slate-300">Fetches nationwide public holidays from <a className="font-bold text-[#7D001D] underline" href="https://nagerholidays.com/api" target="_blank" rel="noreferrer">Nager.Holidays</a>. Review dates and mark special non-working days against the Philippine proclamation before saving. Local holidays are added manually.</p>
    <div className="flex items-end gap-3"><label className="flex-1 text-xs font-bold text-slate-600 dark:text-slate-300">Year<input type="number" min="2000" max="2100" value={year} onChange={(event) => { setYear(Number(event.target.value)); setPreview(null); }} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900" /></label><button type="button" onClick={fetchHolidays} disabled={busy || year < 2000 || year > 2100} className="rounded-xl bg-[#7D001D] px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{busy ? 'Loading...' : 'Fetch'}</button></div>
    {preview && <>
      <div className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-gray-700">{preview.holidays.length ? preview.holidays.map((holiday) => <div key={holiday.date} className={`flex flex-wrap items-center gap-3 rounded-lg p-2 text-sm ${holiday.already_saved ? 'text-slate-400' : 'text-slate-700 dark:text-slate-200'}`}><label className="flex min-w-0 flex-1 items-center gap-3"><input type="checkbox" disabled={holiday.already_saved} checked={selected.includes(holiday.date)} onChange={(event) => setSelected((current) => event.target.checked ? [...current, holiday.date] : current.filter((date) => date !== holiday.date))} /><span className="w-28 shrink-0 text-xs font-bold">{new Date(`${holiday.date}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}</span><span>{holiday.name}</span></label>{holiday.already_saved ? <span className="text-[10px]">Already saved</span> : <select aria-label={`Holiday type for ${holiday.name}`} value={holidayTypes[holiday.date] || 'national_holiday'} onChange={(event) => setHolidayTypes((current) => ({ ...current, [holiday.date]: event.target.value }))} className="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-900"><option value="national_holiday">Regular / national</option><option value="special_non_working">Special non-working</option></select>}</div>) : <p className="p-3 text-xs text-slate-500">No nationwide public holidays returned for this year.</p>}</div>
      <p className="text-xs text-slate-500">{selected.length} selected · {preview.holidays.filter((holiday) => holiday.already_saved).length} already saved</p>
      <label className="block text-xs font-bold text-slate-600 dark:text-slate-300">Official proclamation or reference *<input value={reference} onChange={(event) => setReference(event.target.value)} placeholder="e.g. Proclamation No. 1006" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900" /></label>
      <label className="block text-xs font-bold text-slate-600 dark:text-slate-300">Official source URL (optional)<input type="url" value={sourceUrl} onChange={(event) => setSourceUrl(event.target.value)} placeholder="https://pco.gov.ph/..." className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-gray-600 dark:bg-gray-900" /></label>
    </>}
    <div className="flex justify-end gap-3 border-t border-slate-100 pt-4 dark:border-gray-700"><button type="button" onClick={onClose} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button type="button" disabled={busy || !preview || !selected.length || !reference.trim()} onClick={importSelected} className="rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Import Selected</button></div>
  </div>;
}
