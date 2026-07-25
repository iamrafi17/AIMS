import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  FiAlertTriangle,
  FiBriefcase,
  FiCalendar,
  FiCheck,
  FiDownload,
  FiEdit2,
  FiEye,
  FiFileText,
  FiMapPin,
  FiPlus,
  FiSearch,
  FiShield,
  FiTrash2,
  FiUpload,
  FiUsers,
  FiX,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import GeofenceMap from '../../components/maps/GeofenceMap';
import api from '../../services/api';

const weekdays = [
  ['mon', 'Mon'], ['tue', 'Tue'], ['wed', 'Wed'], ['thu', 'Thu'],
  ['fri', 'Fri'], ['sat', 'Sat'], ['sun', 'Sun'],
];
const defaultDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
const emptyHte = {
  name: '', address: '', contact_person: '', contact_email: '', contact_phone: '',
  latitude: '', longitude: '', geofence_radius: 100, geofence_enabled: false,
  default_am_start: '08:00', default_am_end: '12:00', default_pm_start: '13:00',
  default_pm_end: '17:00', work_days: defaultDays, is_active: true,
};
const emptyHoliday = { name: '', date: '', description: '', is_recurring: false };
const emptyMoa = { hte_id: '', college_id: '', effective_date: '', expiration_date: '', file: null };
const emptyData = {
  htes: [], deployments: [], holidays: [], moas: [], colleges: [], supervisors: [], expiration_alerts: [],
  summary: { total_htes: 0, active_htes: 0, deployed_students: 0, geofenced_htes: 0, valid_moas: 0, expiring_moas: 0 },
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
const statusClass = {
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  inactive: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  expired: 'bg-slate-200 text-slate-700 dark:bg-gray-700 dark:text-gray-300',
  completed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  dropped: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
};

function Badge({ value }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${statusClass[value] || statusClass.pending}`}>{value}</span>;
}

function Modal({ title, subtitle, onClose, children, wide = false }) {
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previous; };
  }, []);

  return <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5">
    <div role="dialog" aria-modal="true" aria-label={title} className={`flex max-h-[calc(100vh-1.5rem)] w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800 ${wide ? 'max-w-5xl' : 'max-w-2xl'}`}>
      <header className="flex shrink-0 items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700"><div><h2 className="text-lg font-black text-[#430909] dark:text-white">{title}</h2>{subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}</div><button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close"><FiX /></button></header>
      <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
    </div>
  </div>;
}

function Field({ label, children, required = false }) {
  return <label><span className="mb-1.5 block text-[10px] font-black uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span>{children}</label>;
}

function WorkDays({ value = [], onChange }) {
  const toggle = (day) => onChange(value.includes(day) ? value.filter((item) => item !== day) : [...value, day]);
  return <div className="flex flex-wrap gap-2">{weekdays.map(([valueKey, label]) => <button key={valueKey} type="button" onClick={() => toggle(valueKey)} className={`h-9 min-w-11 rounded-lg px-2 text-xs font-black ${value.includes(valueKey) ? 'bg-[#800000] text-white' : 'bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-300'}`}>{label}</button>)}</div>;
}

const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Not set';
const timeValue = (value, fallback) => value ? String(value).slice(0, 5) : fallback;
const errorMessage = (error, fallback) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || fallback;
};

function HTEManagement() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [tab, setTab] = useState('htes');
  const [search, setSearch] = useState('');
  const [hteEditor, setHteEditor] = useState(null);
  const [hteForm, setHteForm] = useState(emptyHte);
  const [selectedHte, setSelectedHte] = useState(null);
  const [deploymentEditor, setDeploymentEditor] = useState(null);
  const [holidayEditor, setHolidayEditor] = useState(null);
  const [holidayForm, setHolidayForm] = useState(emptyHoliday);
  const [moaOpen, setMoaOpen] = useState(false);
  const [moaForm, setMoaForm] = useState(emptyMoa);

  const fetchWorkspace = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/htes');
      setData({ ...emptyData, ...response.data });
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load HTE management data.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchWorkspace(); }, [fetchWorkspace]);

  const filteredHtes = useMemo(() => {
    const term = search.trim().toLowerCase();
    return !term ? data.htes : data.htes.filter((hte) => [hte.name, hte.address, hte.contact_person].some((value) => value?.toLowerCase().includes(term)));
  }, [data.htes, search]);
  const filteredDeployments = useMemo(() => {
    const term = search.trim().toLowerCase();
    return !term ? data.deployments : data.deployments.filter((student) => `${student.student_id} ${student.first_name} ${student.last_name} ${student.hte?.name || ''}`.toLowerCase().includes(term));
  }, [data.deployments, search]);

  const openCreateHte = () => {
    setHteForm(emptyHte);
    setHteEditor({ mode: 'create' });
  };
  const openEditHte = (hte) => {
    setHteForm({
      ...emptyHte, ...hte,
      latitude: hte.latitude ?? '', longitude: hte.longitude ?? '',
      default_am_start: timeValue(hte.default_am_start, '08:00'),
      default_am_end: timeValue(hte.default_am_end, '12:00'),
      default_pm_start: timeValue(hte.default_pm_start, '13:00'),
      default_pm_end: timeValue(hte.default_pm_end, '17:00'),
      work_days: hte.work_days?.length ? hte.work_days : defaultDays,
    });
    setHteEditor({ mode: 'edit', hte });
  };
  const saveHte = async (event) => {
    event.preventDefault();
    if (!hteForm.work_days.length) return toast.error('Select at least one working day.');
    setBusy(true);
    try {
      const payload = {
        ...hteForm,
        latitude: hteForm.latitude === '' ? null : Number(hteForm.latitude),
        longitude: hteForm.longitude === '' ? null : Number(hteForm.longitude),
        geofence_radius: Number(hteForm.geofence_radius),
        geofence_enabled: Boolean(hteForm.geofence_enabled),
        is_active: Boolean(hteForm.is_active),
      };
      const response = hteEditor.mode === 'create'
        ? await api.post('/coordinator/htes', payload)
        : await api.put(`/coordinator/htes/${hteEditor.hte.id}`, payload);
      toast.success(response.data.message);
      setHteEditor(null);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to save HTE record.'));
    } finally {
      setBusy(false);
    }
  };
  const viewHte = async (hte) => {
    try {
      const response = await api.get(`/coordinator/htes/${hte.id}`);
      setSelectedHte(response.data);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load HTE details.'));
    }
  };
  const deleteHte = async (hte) => {
    if (!window.confirm(`Delete ${hte.name}? Existing MOA records will also be removed.`)) return;
    setBusy(true);
    try {
      const response = await api.delete(`/coordinator/htes/${hte.id}`);
      toast.success(response.data.message);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to delete HTE.'));
    } finally {
      setBusy(false);
    }
  };

  const openDeployment = (student) => {
    const hte = data.htes.find((item) => String(item.id) === String(student.hte_id));
    setDeploymentEditor({
      ...student,
      hte_id: student.hte_id || '',
      supervisor_id: student.supervisor_id || '',
      ojt_start_date: student.ojt_start_date?.slice(0, 10) || '',
      ojt_end_date: student.ojt_end_date?.slice(0, 10) || '',
      required_ojt_hours: Number(student.required_ojt_hours || 486),
      official_am_start: timeValue(student.official_am_start, timeValue(hte?.default_am_start, '08:00')),
      official_am_end: timeValue(student.official_am_end, timeValue(hte?.default_am_end, '12:00')),
      official_pm_start: timeValue(student.official_pm_start, timeValue(hte?.default_pm_start, '13:00')),
      official_pm_end: timeValue(student.official_pm_end, timeValue(hte?.default_pm_end, '17:00')),
      work_days: student.work_days?.length ? student.work_days : hte?.work_days?.length ? hte.work_days : defaultDays,
      allow_past_attendance: Boolean(student.allow_past_attendance),
    });
  };
  const selectDeploymentHte = (hteId) => {
    const hte = data.htes.find((item) => String(item.id) === String(hteId));
    setDeploymentEditor((current) => ({
      ...current,
      hte_id: hteId,
      official_am_start: timeValue(hte?.default_am_start, current.official_am_start),
      official_am_end: timeValue(hte?.default_am_end, current.official_am_end),
      official_pm_start: timeValue(hte?.default_pm_start, current.official_pm_start),
      official_pm_end: timeValue(hte?.default_pm_end, current.official_pm_end),
      work_days: hte?.work_days?.length ? hte.work_days : current.work_days,
      internship_status: hteId && current.internship_status === 'pending' ? 'active' : current.internship_status,
    }));
  };
  const saveDeployment = async (event) => {
    event.preventDefault();
    if (!deploymentEditor.work_days.length) return toast.error('Select at least one working day.');
    setBusy(true);
    try {
      const response = await api.put(`/coordinator/htes/deployments/${deploymentEditor.id}`, {
        hte_id: deploymentEditor.hte_id ? Number(deploymentEditor.hte_id) : null,
        supervisor_id: deploymentEditor.supervisor_id ? Number(deploymentEditor.supervisor_id) : null,
        ojt_start_date: deploymentEditor.ojt_start_date || null,
        ojt_end_date: deploymentEditor.ojt_end_date || null,
        required_ojt_hours: Number(deploymentEditor.required_ojt_hours),
        official_am_start: deploymentEditor.official_am_start,
        official_am_end: deploymentEditor.official_am_end,
        official_pm_start: deploymentEditor.official_pm_start,
        official_pm_end: deploymentEditor.official_pm_end,
        work_days: deploymentEditor.work_days,
        internship_status: deploymentEditor.hte_id ? deploymentEditor.internship_status : 'pending',
        allow_past_attendance: Boolean(deploymentEditor.allow_past_attendance),
      });
      toast.success(response.data.message);
      setDeploymentEditor(null);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update deployment.'));
    } finally {
      setBusy(false);
    }
  };

  const openHoliday = (holiday = null) => {
    setHolidayForm(holiday ? { ...emptyHoliday, ...holiday, date: holiday.date?.slice(0, 10) || '' } : emptyHoliday);
    setHolidayEditor(holiday ? { mode: 'edit', holiday } : { mode: 'create' });
  };
  const saveHoliday = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      const response = holidayEditor.mode === 'create'
        ? await api.post('/coordinator/htes/holidays', holidayForm)
        : await api.put(`/coordinator/htes/holidays/${holidayEditor.holiday.id}`, holidayForm);
      toast.success(response.data.message);
      setHolidayEditor(null);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to save holiday.'));
    } finally {
      setBusy(false);
    }
  };
  const deleteHoliday = async (holiday) => {
    if (!window.confirm(`Delete the holiday "${holiday.name}"?`)) return;
    try {
      const response = await api.delete(`/coordinator/htes/holidays/${holiday.id}`);
      toast.success(response.data.message);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to delete holiday.'));
    }
  };

  const saveMoa = async (event) => {
    event.preventDefault();
    if (!moaForm.file) return toast.error('Choose a PDF file.');
    const payload = new FormData();
    Object.entries(moaForm).forEach(([key, value]) => payload.append(key, value));
    setBusy(true);
    try {
      const response = await api.post('/coordinator/htes/moas', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message);
      setMoaOpen(false);
      setMoaForm(emptyMoa);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to upload MOA.'));
    } finally {
      setBusy(false);
    }
  };
  const downloadMoa = async (moa) => {
    try {
      const response = await api.get(`/coordinator/htes/moas/${moa.id}/download`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = moa.file_path?.split('/').pop() || `moa-${moa.id}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error('Unable to download the MOA file.');
    }
  };
  const deleteMoa = async (moa) => {
    if (!window.confirm(`Delete the MOA for ${moa.hte?.name}?`)) return;
    try {
      const response = await api.delete(`/coordinator/htes/moas/${moa.id}`);
      toast.success(response.data.message);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to delete MOA.'));
    }
  };

  if (loading) return <div className="grid h-64 place-items-center"><div className="h-11 w-11 animate-spin rounded-full border-4 border-[#800000]/20 border-t-[#800000]" /></div>;

  return <div className="space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Coordinator Portal</p><h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">HTE & Deployment Management</h1><p className="mt-1 text-sm text-slate-400">Partner records, internship schedules, geofences, holidays, and MOA monitoring.</p></div><div className="flex gap-2"><button onClick={() => { setMoaForm(emptyMoa); setMoaOpen(true); }} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#800000] dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiUpload /> Upload MOA</button><button onClick={openCreateHte} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Add HTE</button></div></header>

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">{[
      ['Partner HTEs', data.summary.total_htes, FiBriefcase, 'text-[#800000] bg-rose-50 dark:bg-rose-950/40'],
      ['Active HTEs', data.summary.active_htes, FiCheck, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'],
      ['Deployed Interns', data.summary.deployed_students, FiUsers, 'text-blue-600 bg-blue-50 dark:bg-blue-950/40'],
      ['Geofences', data.summary.geofenced_htes, FiMapPin, 'text-violet-600 bg-violet-50 dark:bg-violet-950/40'],
      ['Valid MOAs', data.summary.valid_moas, FiShield, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'],
      ['Expiring MOAs', data.summary.expiring_moas, FiAlertTriangle, 'text-amber-600 bg-amber-50 dark:bg-amber-950/40'],
    ].map(([label, value, Icon, colors]) => <article key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><span className={`grid h-9 w-9 place-items-center rounded-xl ${colors}`}><Icon /></span><p className="mt-3 text-xl font-black text-slate-800 dark:text-white">{value || 0}</p><p className="text-[9px] font-black uppercase tracking-wide text-slate-400">{label}</p></article>)}</section>

    {data.expiration_alerts.length > 0 && <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"><div className="flex items-start gap-3"><FiAlertTriangle className="mt-0.5 shrink-0 text-amber-600" /><div><p className="text-sm font-black text-amber-900 dark:text-amber-200">MOA expiration alerts</p><div className="mt-2 flex flex-wrap gap-2">{data.expiration_alerts.map((alert) => <span key={alert.id} className={`rounded-lg px-3 py-1.5 text-xs font-bold ${alert.level === 'expired' || alert.level === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200'}`}>{alert.hte} ({alert.college}) · {alert.days_remaining < 0 ? `expired ${Math.abs(alert.days_remaining)} days ago` : `${alert.days_remaining} days left`}</span>)}</div></div></div></section>}

    <div className="flex flex-wrap items-center justify-between gap-3"><nav className="flex max-w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 dark:bg-gray-800">{[
      ['htes', 'HTE Records', FiBriefcase], ['deployments', 'Student Deployments', FiUsers], ['holidays', 'Holidays', FiCalendar], ['moas', 'MOA Monitoring', FiFileText],
    ].map(([value, label, Icon]) => <button key={value} onClick={() => setTab(value)} className={`inline-flex whitespace-nowrap items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black ${tab === value ? 'bg-white text-[#800000] shadow-sm dark:bg-gray-700 dark:text-rose-300' : 'text-slate-500'}`}><Icon /> {label}</button>)}</nav>{['htes', 'deployments'].includes(tab) && <label className="relative w-full sm:w-72"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className={`${inputClass} pl-10`} placeholder={tab === 'htes' ? 'Search HTE records...' : 'Search deployments...'} /></label>}</div>

    {tab === 'htes' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">HTE / Contact</th><th className="px-4 py-4">Interns</th><th className="px-4 py-4">Working Schedule</th><th className="px-4 py-4">Geofence</th><th className="px-4 py-4">MOA</th><th className="px-5 py-4 text-right">Actions</th></tr></thead><tbody>{filteredHtes.length ? filteredHtes.map((hte) => <tr key={hte.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40"><td className="px-5 py-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><FiBriefcase /></span><div><p className="text-sm font-black text-slate-800 dark:text-white">{hte.name}</p><p className="mt-1 max-w-64 truncate text-[11px] text-slate-400">{hte.address}</p><p className="mt-1 text-[10px] text-slate-400">{hte.contact_person} · {hte.contact_phone}</p></div></div></td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{hte.students_count || 0}</p><p className="text-[10px] text-slate-400">{hte.active_students_count || 0} active</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(hte.default_am_start, '08:00')}–{timeValue(hte.default_am_end, '12:00')}</p><p className="mt-1 text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(hte.default_pm_start, '13:00')}–{timeValue(hte.default_pm_end, '17:00')}</p><p className="mt-1 text-[10px] uppercase text-slate-400">{(hte.work_days || defaultDays).join(' · ')}</p></td><td className="px-4 py-4">{hte.geofence_enabled ? <div><span className="inline-flex items-center gap-1 text-xs font-black text-violet-600"><FiMapPin /> Active</span><p className="mt-1 text-[10px] text-slate-400">{hte.geofence_radius} meter radius</p></div> : <span className="text-xs text-slate-400">Disabled</span>}</td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{hte.valid_moa_count || 0}</p><p className="text-[10px] text-slate-400">valid agreement(s)</p></td><td className="px-5 py-4"><div className="flex justify-end gap-1.5"><button onClick={() => viewHte(hte)} className="rounded-lg bg-blue-100 p-2 text-blue-700" title="View"><FiEye /></button><button onClick={() => openEditHte(hte)} className="rounded-lg bg-amber-100 p-2 text-amber-700" title="Edit"><FiEdit2 /></button><button onClick={() => deleteHte(hte)} className="rounded-lg bg-rose-100 p-2 text-rose-700" title="Delete"><FiTrash2 /></button></div></td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-slate-400">No HTE records match your search.</td></tr>}</tbody></table></div></section>}

    {tab === 'deployments' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="overflow-x-auto"><table className="w-full min-w-[1220px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">Student</th><th className="px-4 py-4">Assigned HTE</th><th className="px-4 py-4">HTE Supervisor</th><th className="px-4 py-4">Internship Period</th><th className="px-4 py-4">Working Hours</th><th className="px-4 py-4">Status</th><th className="px-5 py-4 text-right">Action</th></tr></thead><tbody>{filteredDeployments.length ? filteredDeployments.map((student) => <tr key={student.id} className="border-t border-slate-100 dark:border-gray-700"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{student.last_name}, {student.first_name}</p><p className="mt-1 text-[11px] text-slate-400">{student.student_id} · {student.program?.code}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{student.hte?.name || 'Not deployed'}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{student.supervisor?.name || 'Not assigned'}</p><p className="mt-1 text-[10px] text-slate-400">{student.supervisor?.email || 'Choose in configuration'}</p></td><td className="px-4 py-4"><p className="text-xs text-slate-600 dark:text-gray-300">{formatDate(student.ojt_start_date)} – {formatDate(student.ojt_end_date)}</p><p className="mt-1 text-[10px] text-slate-400">{student.required_ojt_hours || 486} required hours</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(student.official_am_start, '08:00')}–{timeValue(student.official_am_end, '12:00')} / {timeValue(student.official_pm_start, '13:00')}–{timeValue(student.official_pm_end, '17:00')}</p><p className="mt-1 text-[10px] uppercase text-slate-400">{(student.work_days || defaultDays).join(' · ')}</p></td><td className="px-4 py-4"><Badge value={student.internship_status} /></td><td className="px-5 py-4 text-right"><button onClick={() => openDeployment(student)} className="inline-flex items-center gap-2 rounded-lg bg-amber-100 px-3 py-2 text-xs font-black text-amber-700"><FiEdit2 /> Configure</button></td></tr>) : <tr><td colSpan="7" className="py-16 text-center text-slate-400">No approved student records found.</td></tr>}</tbody></table></div></section>}

    {tab === 'holidays' && <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="mb-5 flex items-center justify-between"><div><h2 className="font-black text-slate-800 dark:text-white">Holiday Calendar</h2><p className="mt-1 text-xs text-slate-400">Non-working dates used by attendance monitoring.</p></div><button onClick={() => openHoliday()} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Add Holiday</button></div><div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{data.holidays.length ? data.holidays.map((holiday) => <article key={holiday.id} className="rounded-2xl border border-slate-100 p-4 dark:border-gray-700"><div className="flex items-start justify-between"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50"><FiCalendar /></span><div className="flex gap-1"><button onClick={() => openHoliday(holiday)} className="rounded-lg p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-gray-700"><FiEdit2 /></button><button onClick={() => deleteHoliday(holiday)} className="rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-gray-700"><FiTrash2 /></button></div></div><p className="mt-3 text-sm font-black text-slate-800 dark:text-white">{holiday.name}</p><p className="mt-1 text-xs font-bold text-violet-600">{formatDate(holiday.date)}</p><p className="mt-2 text-xs leading-5 text-slate-400">{holiday.description || 'No description provided.'}</p>{holiday.is_recurring && <span className="mt-3 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase text-blue-600 dark:bg-blue-950/50">Repeats yearly</span>}</article>) : <div className="col-span-full rounded-2xl border border-dashed border-slate-300 py-14 text-center text-slate-400 dark:border-gray-700">No holidays configured.</div>}</div></section>}

    {tab === 'moas' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center justify-between border-b border-slate-100 p-5 dark:border-gray-700"><div><h2 className="font-black text-slate-800 dark:text-white">MOA Upload & Monitoring</h2><p className="mt-1 text-xs text-slate-400">Uploaded agreements are submitted as pending for approval.</p></div><button onClick={() => { setMoaForm(emptyMoa); setMoaOpen(true); }} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiUpload /> Upload MOA</button></div><div className="overflow-x-auto"><table className="w-full min-w-[900px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">HTE / College</th><th className="px-4 py-4">Effective Date</th><th className="px-4 py-4">Expiration</th><th className="px-4 py-4">Status</th><th className="px-5 py-4 text-right">Actions</th></tr></thead><tbody>{data.moas.length ? data.moas.map((moa) => <tr key={moa.id} className="border-t border-slate-100 dark:border-gray-700"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{moa.hte?.name}</p><p className="mt-1 text-[11px] text-slate-400">{moa.college?.code} · {moa.college?.name}</p></td><td className="px-4 py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{formatDate(moa.effective_date)}</td><td className="px-4 py-4"><p className={`text-xs font-black ${moa.days_remaining <= 30 ? 'text-rose-600' : 'text-slate-600 dark:text-gray-300'}`}>{formatDate(moa.expiration_date)}</p><p className="mt-1 text-[10px] text-slate-400">{moa.days_remaining < 0 ? `Expired ${Math.abs(moa.days_remaining)} days ago` : `${moa.days_remaining} days remaining`}</p></td><td className="px-4 py-4"><Badge value={moa.computed_status} /></td><td className="px-5 py-4"><div className="flex justify-end gap-2"><button onClick={() => downloadMoa(moa)} className="inline-flex items-center gap-2 rounded-lg bg-blue-100 px-3 py-2 text-xs font-black text-blue-700"><FiDownload /> Download</button><button onClick={() => deleteMoa(moa)} className="rounded-lg bg-rose-100 p-2 text-rose-700"><FiTrash2 /></button></div></td></tr>) : <tr><td colSpan="5" className="py-16 text-center text-slate-400">No MOA records uploaded.</td></tr>}</tbody></table></div></section>}

    {hteEditor && (
      <Modal
        title={hteEditor.mode === 'create' ? 'Add Partner HTE' : 'Edit HTE Record'}
        subtitle="Configure contact details, default working hours, and the exact geofence location."
        onClose={() => setHteEditor(null)}
        wide
      >
        <form onSubmit={saveHte}>
          <div className="grid gap-4 p-5 md:grid-cols-3">
            <Field label="HTE Name" required>
              <input value={hteForm.name} onChange={(event) => setHteForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} required />
            </Field>
            <Field label="Contact Person" required>
              <input value={hteForm.contact_person} onChange={(event) => setHteForm((current) => ({ ...current, contact_person: event.target.value }))} className={inputClass} required />
            </Field>
            <Field label="Contact Phone" required>
              <input value={hteForm.contact_phone} onChange={(event) => setHteForm((current) => ({ ...current, contact_phone: event.target.value }))} className={inputClass} required />
            </Field>
            <Field label="Contact Email" required>
              <input type="email" value={hteForm.contact_email} onChange={(event) => setHteForm((current) => ({ ...current, contact_email: event.target.value }))} className={inputClass} required />
            </Field>
            <div className="md:col-span-2">
              <Field label="Address" required>
                <input value={hteForm.address} onChange={(event) => setHteForm((current) => ({ ...current, address: event.target.value }))} className={inputClass} required />
              </Field>
            </div>

            <div className="md:col-span-3 border-t border-slate-100 pt-4 dark:border-gray-700">
              <h3 className="mb-3 text-sm font-black text-[#800000] dark:text-rose-300">Default Working Hours</h3>
              <div className="grid gap-3 sm:grid-cols-4">
                {[['AM Start', 'default_am_start'], ['AM End', 'default_am_end'], ['PM Start', 'default_pm_start'], ['PM End', 'default_pm_end']].map(([label, name]) => (
                  <Field key={name} label={label} required>
                    <input type="time" value={hteForm[name]} onChange={(event) => setHteForm((current) => ({ ...current, [name]: event.target.value }))} className={inputClass} required />
                  </Field>
                ))}
              </div>
              <div className="mt-4">
                <Field label="Working Days" required>
                  <WorkDays value={hteForm.work_days} onChange={(work_days) => setHteForm((current) => ({ ...current, work_days }))} />
                </Field>
              </div>
            </div>

            <div className="md:col-span-3 border-t border-slate-100 pt-4 dark:border-gray-700">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h3 className="text-sm font-black text-[#800000] dark:text-rose-300">Geofence Location & Radius</h3>
                  <p className="mt-1 text-xs text-slate-400">Zoom into the real map, then click or drag the pin to the exact HTE entrance.</p>
                </div>
                <label className="flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2 text-xs font-black text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                  <input type="checkbox" checked={hteForm.geofence_enabled} onChange={(event) => setHteForm((current) => ({ ...current, geofence_enabled: event.target.checked }))} />
                  Enable WFO geofence
                </label>
              </div>

              <GeofenceMap
                latitude={hteForm.latitude}
                longitude={hteForm.longitude}
                radius={hteForm.geofence_radius}
                editable
                onPositionChange={(latitude, longitude) => setHteForm((current) => ({ ...current, latitude, longitude }))}
                onRadiusChange={(geofence_radius) => setHteForm((current) => ({ ...current, geofence_radius }))}
              />

              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <Field label="Latitude">
                  <input
                    type="number"
                    step="any"
                    value={hteForm.latitude}
                    onChange={(event) => setHteForm((current) => ({ ...current, latitude: event.target.value }))}
                    className={inputClass}
                    required={hteForm.geofence_enabled}
                  />
                </Field>
                <Field label="Longitude">
                  <input
                    type="number"
                    step="any"
                    value={hteForm.longitude}
                    onChange={(event) => setHteForm((current) => ({ ...current, longitude: event.target.value }))}
                    className={inputClass}
                    required={hteForm.geofence_enabled}
                  />
                </Field>
                <Field label="Radius (meters)" required>
                  <input
                    type="number"
                    min="10"
                    max="10000"
                    value={hteForm.geofence_radius}
                    onChange={(event) => setHteForm((current) => ({ ...current, geofence_radius: event.target.value }))}
                    className={inputClass}
                    required
                  />
                </Field>
              </div>
            </div>

            <div className="md:col-span-3 flex flex-wrap gap-5 border-t border-slate-100 pt-4 text-sm font-bold dark:border-gray-700">
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={hteForm.is_active} onChange={(event) => setHteForm((current) => ({ ...current, is_active: event.target.checked }))} />
                Active partner establishment
              </label>
            </div>
          </div>
          <div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700">
            <button type="button" onClick={() => setHteEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button>
            <button disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : 'Save HTE'}</button>
          </div>
        </form>
      </Modal>
    )}

    {deploymentEditor && <Modal title="Configure Student Deployment" subtitle={`${deploymentEditor.first_name} ${deploymentEditor.last_name} · ${deploymentEditor.student_id}`} onClose={() => setDeploymentEditor(null)} wide><form onSubmit={saveDeployment}><div className="grid gap-4 p-5 md:grid-cols-3"><Field label="Assigned HTE"><select value={deploymentEditor.hte_id} onChange={(event) => selectDeploymentHte(event.target.value)} className={inputClass}><option value="">Not deployed</option>{data.htes.filter((hte) => hte.is_active).map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select></Field><Field label="HTE Supervisor"><select value={deploymentEditor.supervisor_id} onChange={(event) => setDeploymentEditor((current) => ({ ...current, supervisor_id: event.target.value }))} className={inputClass}><option value="">Not assigned</option>{data.supervisors.map((supervisor) => <option key={supervisor.id} value={supervisor.id}>{supervisor.name}</option>)}</select></Field><Field label="Internship Status"><select value={deploymentEditor.internship_status} onChange={(event) => setDeploymentEditor((current) => ({ ...current, internship_status: event.target.value }))} className={inputClass}><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option><option value="dropped">Dropped</option></select></Field><Field label="Required OJT Hours"><input type="number" min="1" value={deploymentEditor.required_ojt_hours} onChange={(event) => setDeploymentEditor((current) => ({ ...current, required_ojt_hours: event.target.value }))} className={inputClass} required /></Field><Field label="OJT Start Date"><input type="date" value={deploymentEditor.ojt_start_date} onChange={(event) => setDeploymentEditor((current) => ({ ...current, ojt_start_date: event.target.value }))} className={inputClass} /></Field><Field label="OJT End Date"><input type="date" value={deploymentEditor.ojt_end_date} onChange={(event) => setDeploymentEditor((current) => ({ ...current, ojt_end_date: event.target.value }))} className={inputClass} /></Field><label className="flex items-center gap-2 self-end rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold dark:border-gray-600"><input type="checkbox" checked={deploymentEditor.allow_past_attendance} onChange={(event) => setDeploymentEditor((current) => ({ ...current, allow_past_attendance: event.target.checked }))} /> Allow past attendance</label><div className="md:col-span-3 border-t border-slate-100 pt-4 dark:border-gray-700"><h3 className="mb-3 text-sm font-black text-[#800000] dark:text-rose-300">Student Working Hours</h3><div className="grid gap-3 sm:grid-cols-4">{[['AM Start', 'official_am_start'], ['AM End', 'official_am_end'], ['PM Start', 'official_pm_start'], ['PM End', 'official_pm_end']].map(([label, name]) => <Field key={name} label={label}><input type="time" value={deploymentEditor[name]} onChange={(event) => setDeploymentEditor((current) => ({ ...current, [name]: event.target.value }))} className={inputClass} required /></Field>)}</div><div className="mt-4"><Field label="Working Days"><WorkDays value={deploymentEditor.work_days} onChange={(work_days) => setDeploymentEditor((current) => ({ ...current, work_days }))} /></Field></div></div></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setDeploymentEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : 'Save Deployment'}</button></div></form></Modal>}

    {holidayEditor && <Modal title={holidayEditor.mode === 'create' ? 'Add Holiday' : 'Edit Holiday'} subtitle="Configure a non-working date for attendance monitoring." onClose={() => setHolidayEditor(null)}><form onSubmit={saveHoliday}><div className="space-y-4 p-5"><Field label="Holiday Name" required><input value={holidayForm.name} onChange={(event) => setHolidayForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} required /></Field><Field label="Date" required><input type="date" value={holidayForm.date} onChange={(event) => setHolidayForm((current) => ({ ...current, date: event.target.value }))} className={inputClass} required /></Field><Field label="Description"><textarea rows="3" value={holidayForm.description || ''} onChange={(event) => setHolidayForm((current) => ({ ...current, description: event.target.value }))} className={inputClass} /></Field><label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" checked={holidayForm.is_recurring} onChange={(event) => setHolidayForm((current) => ({ ...current, is_recurring: event.target.checked }))} /> Repeat every year</label></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setHolidayEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white">{busy ? 'Saving...' : 'Save Holiday'}</button></div></form></Modal>}

    {moaOpen && <Modal title="Upload Memorandum of Agreement" subtitle="PDF only, up to 10 MB. New uploads require approval." onClose={() => setMoaOpen(false)}><form onSubmit={saveMoa}><div className="space-y-4 p-5"><Field label="Partner HTE" required><select value={moaForm.hte_id} onChange={(event) => setMoaForm((current) => ({ ...current, hte_id: event.target.value }))} className={inputClass} required><option value="">Select HTE</option>{data.htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select></Field><Field label="College" required><select value={moaForm.college_id} onChange={(event) => setMoaForm((current) => ({ ...current, college_id: event.target.value }))} className={inputClass} required><option value="">Select college</option>{data.colleges.map((college) => <option key={college.id} value={college.id}>{college.code} — {college.name}</option>)}</select></Field><div className="grid gap-4 sm:grid-cols-2"><Field label="Effective Date" required><input type="date" value={moaForm.effective_date} onChange={(event) => setMoaForm((current) => ({ ...current, effective_date: event.target.value }))} className={inputClass} required /></Field><Field label="Expiration Date" required><input type="date" value={moaForm.expiration_date} onChange={(event) => setMoaForm((current) => ({ ...current, expiration_date: event.target.value }))} className={inputClass} required /></Field></div><Field label="MOA PDF" required><input type="file" accept=".pdf,application/pdf" onChange={(event) => setMoaForm((current) => ({ ...current, file: event.target.files?.[0] || null }))} className={inputClass} required /></Field></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setMoaOpen(false)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Uploading...' : 'Upload MOA'}</button></div></form></Modal>}

    {selectedHte && <Modal title={selectedHte.name} subtitle={selectedHte.address} onClose={() => setSelectedHte(null)} wide><div className="grid gap-5 p-5 lg:grid-cols-2"><div><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><div className="flex items-center justify-between"><Badge value={selectedHte.is_active ? 'active' : 'inactive'} />{selectedHte.geofence_enabled && <span className="inline-flex items-center gap-1 text-xs font-black text-violet-600"><FiMapPin /> {selectedHte.geofence_radius}m geofence</span>}</div><p className="mt-3 text-sm font-black text-slate-800 dark:text-white">{selectedHte.contact_person}</p><p className="mt-1 text-xs text-slate-400">{selectedHte.contact_email} · {selectedHte.contact_phone}</p><p className="mt-3 text-xs font-bold text-slate-600 dark:text-gray-300">{timeValue(selectedHte.default_am_start, '08:00')}–{timeValue(selectedHte.default_am_end, '12:00')} / {timeValue(selectedHte.default_pm_start, '13:00')}–{timeValue(selectedHte.default_pm_end, '17:00')}</p></div><h3 className="mb-3 mt-5 font-black text-slate-800 dark:text-white">Deployed Students</h3><div className="space-y-2">{selectedHte.students?.length ? selectedHte.students.map((student) => <div key={student.id} className="flex items-center justify-between rounded-xl border border-slate-100 p-3 dark:border-gray-700"><div><p className="text-xs font-black text-slate-700 dark:text-gray-200">{student.first_name} {student.last_name}</p><p className="text-[10px] text-slate-400">{student.student_id} · {student.program?.code}</p></div><Badge value={student.internship_status} /></div>) : <p className="rounded-xl border border-dashed border-slate-300 py-8 text-center text-xs text-slate-400 dark:border-gray-700">No students deployed.</p>}</div></div><div><h3 className="mb-3 font-black text-slate-800 dark:text-white">MOA Records</h3><div className="space-y-3">{selectedHte.moas?.length ? selectedHte.moas.map((moa) => <div key={moa.id} className="rounded-xl border border-slate-100 p-4 dark:border-gray-700"><div className="flex items-start justify-between"><div><p className="text-xs font-black text-slate-700 dark:text-gray-200">{moa.college?.code} Agreement</p><p className="mt-1 text-[10px] text-slate-400">{formatDate(moa.effective_date)} – {formatDate(moa.expiration_date)}</p></div><Badge value={new Date(moa.expiration_date) < new Date() ? 'expired' : moa.status} /></div></div>) : <p className="rounded-xl border border-dashed border-slate-300 py-8 text-center text-xs text-slate-400 dark:border-gray-700">No MOA records.</p>}</div><button onClick={() => { setSelectedHte(null); openEditHte(selectedHte); }} className="mt-5 w-full rounded-xl border border-slate-200 py-3 text-sm font-black text-[#800000] dark:border-gray-600 dark:text-rose-300">Edit HTE Configuration</button></div></div></Modal>}
  </div>;
}

export default HTEManagement;
