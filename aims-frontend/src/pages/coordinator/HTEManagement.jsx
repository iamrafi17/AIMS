import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  FiAlertTriangle,
  FiBriefcase,
  FiCalendar,
  FiCheck,
  FiChevronDown,
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
import ContactNumberInput from '../../components/forms/ContactNumberInput';

const weekdays = [
  ['mon', 'Mon'], ['tue', 'Tue'], ['wed', 'Wed'], ['thu', 'Thu'],
  ['fri', 'Fri'], ['sat', 'Sat'], ['sun', 'Sun'],
];
const weekdayNames = {
  mon: 'Monday', tue: 'Tuesday', wed: 'Wednesday', thu: 'Thursday',
  fri: 'Friday', sat: 'Saturday', sun: 'Sunday',
};
const defaultDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
const emptyHte = {
  name: '', address: '', contact_person: '', contact_email: '', contact_number: '',
  latitude: '', longitude: '', geofence_radius: 100, geofence_enabled: false,
  default_am_start: '08:00', default_am_end: '12:00', default_pm_start: '13:00',
  default_pm_end: '17:00', work_days: defaultDays, is_active: true,
};
const emptyHoliday = { name: '', date: '', description: '', is_recurring: false };
const emptyMoa = { hte_id: '', effective_date: '', expiration_date: '', file: null };
const emptyDepartment = { department_name: '', supervisor_full_name: '' };
const emptyData = {
  htes: [], deployments: [], holidays: [], moas: [], colleges: [], batches: [], supervisors: [], expiration_alerts: [],
  summary: { total_htes: 0, active_htes: 0, deployed_students: 0, geofenced_htes: 0, valid_moas: 0, expiring_moas: 0 },
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-[#7D001D]/50 focus:ring-4 focus:ring-[#7D001D]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
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

function Modal({ title, subtitle, onClose, children, wide = false, compact = false }) {
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previous; };
  }, []);

  return <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5">
    <div role="dialog" aria-modal="true" aria-label={title} style={wide || compact ? { width: 'calc(100vw - 2rem)', maxWidth: '896px' } : undefined} className={`flex max-h-[calc(100dvh-1rem)] w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-gray-800 sm:max-h-[calc(100dvh-2.5rem)] ${wide ? 'max-w-5xl' : 'max-w-2xl'}`}>
      <header className="flex shrink-0 items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700"><div><h2 className="text-lg font-black text-[#690016] dark:text-white">{title}</h2>{subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}</div><button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close"><FiX /></button></header>
      <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
    </div>
  </div>;
}

function Field({ label, children, required = false }) {
  return <label><span className="mb-1.5 block text-[10px] font-black uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span>{children}</label>;
}

function WorkDays({ value = [], onChange }) {
  const toggle = (day) => onChange(value.includes(day) ? value.filter((item) => item !== day) : [...value, day]);
  return <div className="flex flex-wrap gap-2">{weekdays.map(([valueKey, label]) => <button key={valueKey} type="button" onClick={() => toggle(valueKey)} className={`h-9 min-w-11 rounded-lg px-2 text-xs font-black ${value.includes(valueKey) ? 'bg-[#7D001D] text-white' : 'bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-300'}`}>{label}</button>)}</div>;
}

function WorkingSchedule({ hte }) {
  const summary = hte.batch_schedule_summary;
  if (!summary?.groups?.length) {
    return <><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(hte.default_am_start, '08:00')}–{timeValue(hte.default_am_end, '12:00')}</p><p className="mt-1 text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(hte.default_pm_start, '13:00')}–{timeValue(hte.default_pm_end, '17:00')}</p><p className="mt-1 text-[10px] uppercase text-slate-400">{(hte.work_days || defaultDays).join(' · ')}</p></>;
  }

  const visibleGroups = summary.groups.slice(0, 3);
  return <div className="min-w-48 space-y-2">
    <p className="max-w-64 truncate text-[10px] font-black uppercase tracking-wide text-violet-600" title={summary.batch_label}>{summary.batch_label}</p>
    {visibleGroups.map((group) => <div key={`${group.am_start}-${group.am_end}-${group.pm_start}-${group.pm_end}`}>
      <p className="text-xs font-bold text-slate-700 dark:text-gray-200">{group.am_start}–{group.am_end} / {group.pm_start}–{group.pm_end}</p>
      <p className="mt-0.5 text-[10px] uppercase text-slate-400">{group.days.join(' · ')}</p>
    </div>)}
    {summary.groups.length > visibleGroups.length && <p className="text-[10px] font-bold text-violet-500">+{summary.groups.length - visibleGroups.length} more daily pattern(s)</p>}
    {summary.configured_batches > 1 && <p className="text-[10px] text-slate-400">Showing latest of {summary.configured_batches} configured batches</p>}
  </div>;
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
  const [newDepartments, setNewDepartments] = useState([emptyDepartment]);
  const [hteSearchOpen, setHteSearchOpen] = useState(false);
  const [hteSearchQuery, setHteSearchQuery] = useState('');
  const [hteSearchResults, setHteSearchResults] = useState([]);
  const [hteSearchComplete, setHteSearchComplete] = useState(false);
  const [hteSearchBusy, setHteSearchBusy] = useState(false);
  const [newHteSchedule, setNewHteSchedule] = useState(null);
  const [selectedHte, setSelectedHte] = useState(null);
  const [departmentForm, setDepartmentForm] = useState(emptyDepartment);
  const [batchScheduleEditor, setBatchScheduleEditor] = useState(null);
  const [scheduleLoadingId, setScheduleLoadingId] = useState(null);
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

  const openHteSearch = () => {
    setHteSearchQuery('');
    setHteSearchResults([]);
    setHteSearchComplete(false);
    setHteSearchOpen(true);
  };

  const searchExistingHtes = async (event) => {
    event.preventDefault();
    if (hteSearchQuery.trim().length < 2) return toast.error('Enter at least two characters to search.');
    setHteSearchBusy(true);
    try {
      const response = await api.get('/coordinator/htes/search', { params: { search: hteSearchQuery.trim() } });
      setHteSearchResults(response.data.htes || []);
      setHteSearchComplete(true);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to search HTE records.'));
    } finally {
      setHteSearchBusy(false);
    }
  };

  const linkExistingHte = async (hte) => {
    setHteSearchBusy(true);
    try {
      const response = await api.post(`/coordinator/htes/${hte.id}/link`);
      toast.success(response.data.message);
      setHteSearchOpen(false);
      await fetchWorkspace();
      setSelectedHte(response.data.hte);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to link the existing HTE.'));
    } finally {
      setHteSearchBusy(false);
    }
  };

  const openCreateHte = (prefilledName = '') => {
    const tools = {
      am_start: emptyHte.default_am_start,
      am_end: emptyHte.default_am_end,
      pm_start: emptyHte.default_pm_start,
      pm_end: emptyHte.default_pm_end,
    };
    setHteForm({ ...emptyHte, name: prefilledName.trim() });
    setNewDepartments([emptyDepartment]);
    setNewHteSchedule({
      batch_id: data.batches[0] ? String(data.batches[0].id) : '',
      tools,
      selected_days: defaultDays,
      days: Object.fromEntries(weekdays.map(([day]) => [day, { ...tools }])),
    });
    setHteSearchOpen(false);
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
    const selectedWorkDays = hteEditor.mode === 'create' ? newHteSchedule?.selected_days || [] : hteForm.work_days;
    if (!selectedWorkDays.length) return toast.error('Check at least one working day.');
    if (hteEditor.mode === 'create' && !newHteSchedule?.batch_id) return toast.error('Select an active batch before saving the HTE.');
    setBusy(true);
    try {
      const payload = {
        ...hteForm,
        latitude: hteForm.latitude === '' ? null : Number(hteForm.latitude),
        longitude: hteForm.longitude === '' ? null : Number(hteForm.longitude),
        geofence_radius: Number(hteForm.geofence_radius),
        geofence_enabled: Boolean(hteForm.geofence_enabled),
        is_active: Boolean(hteForm.is_active),
        ...(hteEditor.mode === 'create' ? {
          departments: newDepartments.filter((department) => department.department_name.trim() && department.supervisor_full_name.trim()),
        } : {}),
        ...(hteEditor.mode === 'create' ? {
          default_am_start: newHteSchedule.tools.am_start,
          default_am_end: newHteSchedule.tools.am_end,
          default_pm_start: newHteSchedule.tools.pm_start,
          default_pm_end: newHteSchedule.tools.pm_end,
          work_days: newHteSchedule.selected_days,
          batch_schedule: {
            academic_section_id: Number(newHteSchedule.batch_id),
            days: Object.fromEntries(weekdays.map(([day]) => [day, {
              ...newHteSchedule.days[day],
              is_working: newHteSchedule.selected_days.includes(day),
            }])),
          },
        } : {}),
      };
      const response = hteEditor.mode === 'create'
        ? await api.post('/coordinator/htes', payload)
        : await api.put(`/coordinator/htes/${hteEditor.hte.id}`, payload);
      toast.success(response.data.message);
      setHteEditor(null);
      setNewHteSchedule(null);
      setNewDepartments([emptyDepartment]);
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

  const saveDepartment = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      const response = await api.post(`/coordinator/htes/${selectedHte.id}/departments`, departmentForm);
      toast.success(response.data.message);
      setDepartmentForm(emptyDepartment);
      const refreshed = await api.get(`/coordinator/htes/${selectedHte.id}`);
      setSelectedHte(refreshed.data);
      fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to save the department and supervisor.'));
    } finally {
      setBusy(false);
    }
  };
  const cloneDays = (days = {}) => Object.fromEntries(
    weekdays.map(([day]) => [day, { ...(days[day] || {}) }]),
  );
  const checkedDays = (days = {}) => weekdays.map(([day]) => day).filter((day) => days[day]?.is_working);
  const toolsFromDays = (days = {}, fallback = emptyHte) => {
    const templateDay = checkedDays(days)[0] || 'mon';
    const template = days[templateDay] || {};
    return {
      am_start: timeValue(template.am_start, timeValue(fallback.default_am_start, '08:00')),
      am_end: timeValue(template.am_end, timeValue(fallback.default_am_end, '12:00')),
      pm_start: timeValue(template.pm_start, timeValue(fallback.default_pm_start, '13:00')),
      pm_end: timeValue(template.pm_end, timeValue(fallback.default_pm_end, '17:00')),
    };
  };
  const toggleCheckedDay = (setter, day) => setter((current) => ({
    ...current,
    selected_days: current.selected_days.includes(day)
      ? current.selected_days.filter((item) => item !== day)
      : [...current.selected_days, day],
  }));
  const applyNewHteScheduleToDays = () => {
    if (!newHteSchedule.selected_days.length) return toast.error('Select at least one day.');
    const { am_start, am_end, pm_start, pm_end } = newHteSchedule.tools;
    if (!am_start || !am_end || !pm_start || !pm_end) return toast.error('Complete all schedule tool times.');
    if (am_end <= am_start || pm_end <= pm_start) return toast.error('End times must be later than start times.');

    setNewHteSchedule((current) => ({
      ...current,
      days: Object.fromEntries(weekdays.map(([day]) => [
        day,
        current.selected_days.includes(day) ? { ...current.tools } : current.days[day],
      ])),
    }));
    toast.success('Schedule applied to the checked working days.');
  };
  const openBatchSchedule = async (hte) => {
    setScheduleLoadingId(hte.id);
    try {
      const response = await api.get(`/coordinator/htes/${hte.id}/batch-schedules`);
      const batches = response.data.batches || [];
      const batch = batches[0] || null;
      const selectedDays = checkedDays(batch?.days);
      setBatchScheduleEditor({
        hte,
        batches,
        batch_id: batch ? String(batch.id) : '',
        days: cloneDays(batch?.days),
        tools: toolsFromDays(batch?.days, hte),
        selected_days: selectedDays.length ? selectedDays : hte.work_days?.length ? hte.work_days : defaultDays,
      });
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load batch schedules.'));
    } finally {
      setScheduleLoadingId(null);
    }
  };
  const selectBatch = (batchId) => {
    setBatchScheduleEditor((current) => {
      const batch = current.batches.find((item) => String(item.id) === String(batchId));
      const selectedDays = checkedDays(batch?.days);
      return {
        ...current,
        batch_id: batchId,
        days: cloneDays(batch?.days),
        tools: toolsFromDays(batch?.days, current.hte),
        selected_days: selectedDays.length ? selectedDays : current.hte.work_days?.length ? current.hte.work_days : defaultDays,
      };
    });
  };
  const applyScheduleToDays = () => {
    if (!batchScheduleEditor.selected_days.length) return toast.error('Select at least one day.');
    const { am_start, am_end, pm_start, pm_end } = batchScheduleEditor.tools;
    if (!am_start || !am_end || !pm_start || !pm_end) return toast.error('Complete all schedule tool times.');
    if (am_end <= am_start || pm_end <= pm_start) return toast.error('End times must be later than start times.');

    setBatchScheduleEditor((current) => ({
      ...current,
      days: Object.fromEntries(weekdays.map(([day]) => [
        day,
        current.selected_days.includes(day) ? { ...current.tools } : current.days[day],
      ])),
    }));
    toast.success('Schedule applied to the checked working days.');
  };
  const saveBatchSchedule = async (event) => {
    event.preventDefault();
    if (!batchScheduleEditor.batch_id) return toast.error('Select a batch first.');
    setBusy(true);
    try {
      const response = await api.put(
        `/coordinator/htes/${batchScheduleEditor.hte.id}/batch-schedules/${batchScheduleEditor.batch_id}`,
        { days: Object.fromEntries(weekdays.map(([day]) => [day, {
          ...batchScheduleEditor.days[day],
          is_working: batchScheduleEditor.selected_days.includes(day),
        }])) },
      );
      toast.success(response.data.message);
      setBatchScheduleEditor(null);
      await fetchWorkspace();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to save the batch schedule.'));
    } finally {
      setBusy(false);
    }
  };
  const openDeployment = (student) => {
    const hte = data.htes.find((item) => String(item.id) === String(student.hte_id));
    setDeploymentEditor({
      ...student,
      hte_id: student.hte_id || '',
      hte_department_id: student.hte_department_id || '',
      supervisor_record_id: student.supervisor_record_id || '',
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
      hte_department_id: '',
      supervisor_record_id: '',
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
        hte_department_id: deploymentEditor.hte_department_id ? Number(deploymentEditor.hte_department_id) : null,
        supervisor_record_id: deploymentEditor.supervisor_record_id ? Number(deploymentEditor.supervisor_record_id) : null,
        supervisor_id: null,
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
  const deploymentHte = data.htes.find((hte) => String(hte.id) === String(deploymentEditor?.hte_id));
  const deploymentDepartments = deploymentHte?.departments || [];
  const deploymentDepartment = deploymentDepartments.find((department) => String(department.id) === String(deploymentEditor?.hte_department_id));
  const deploymentSupervisors = deploymentDepartment?.supervisors || [];

  if (loading) return <div className="grid h-64 place-items-center"><div className="h-11 w-11 animate-spin rounded-full border-4 border-[#7D001D]/20 border-t-[#7D001D]" /></div>;

  return <div className="space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[0.18em] text-[#a8750b]">Coordinator Portal</p><h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">HTE & Deployment Management</h1><p className="mt-1 text-sm text-slate-400">Partner records, internship schedules, geofences, holidays, and MOA monitoring.</p></div><div className="flex gap-2"><button onClick={() => { setMoaForm(emptyMoa); setMoaOpen(true); }} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#7D001D] dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiUpload /> Upload MOA</button><button onClick={openHteSearch} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Add HTE</button></div></header>

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">{[
      ['Partner HTEs', data.summary.total_htes, FiBriefcase, 'text-[#7D001D] bg-rose-50 dark:bg-rose-950/40'],
      ['Active HTEs', data.summary.active_htes, FiCheck, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'],
      ['Deployed Interns', data.summary.deployed_students, FiUsers, 'text-blue-600 bg-blue-50 dark:bg-blue-950/40'],
      ['Geofences', data.summary.geofenced_htes, FiMapPin, 'text-violet-600 bg-violet-50 dark:bg-violet-950/40'],
      ['Valid MOAs', data.summary.valid_moas, FiShield, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40'],
      ['Expiring MOAs', data.summary.expiring_moas, FiAlertTriangle, 'text-amber-600 bg-amber-50 dark:bg-amber-950/40'],
    ].map(([label, value, Icon, colors]) => <article key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><span className={`grid h-9 w-9 place-items-center rounded-xl ${colors}`}><Icon /></span><p className="mt-3 text-xl font-black text-slate-800 dark:text-white">{value || 0}</p><p className="text-[9px] font-black uppercase tracking-wide text-slate-400">{label}</p></article>)}</section>

    {data.expiration_alerts.length > 0 && <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"><div className="flex items-start gap-3"><FiAlertTriangle className="mt-0.5 shrink-0 text-amber-600" /><div><p className="text-sm font-black text-amber-900 dark:text-amber-200">MOA expiration alerts</p><div className="mt-2 flex flex-wrap gap-2">{data.expiration_alerts.map((alert) => <span key={alert.id} className={`rounded-lg px-3 py-1.5 text-xs font-bold ${alert.level === 'expired' || alert.level === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200'}`}>{alert.hte} ({alert.college}) · {alert.days_remaining < 0 ? `expired ${Math.abs(alert.days_remaining)} days ago` : `${alert.days_remaining} days left`}</span>)}</div></div></div></section>}

    <div className="flex flex-wrap items-center justify-between gap-3"><nav className="flex max-w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 dark:bg-gray-800">{[
      ['htes', 'HTE Records', FiBriefcase], ['deployments', 'Student Deployments', FiUsers], ['holidays', 'Holidays', FiCalendar], ['moas', 'MOA Monitoring', FiFileText],
    ].map(([value, label, Icon]) => <button key={value} onClick={() => setTab(value)} className={`inline-flex whitespace-nowrap items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black ${tab === value ? 'bg-white text-[#7D001D] shadow-sm dark:bg-gray-700 dark:text-rose-300' : 'text-slate-500'}`}><Icon /> {label}</button>)}</nav>{['htes', 'deployments'].includes(tab) && <label className="relative w-full sm:w-72"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className={`${inputClass} pl-10`} placeholder={tab === 'htes' ? 'Search HTE records...' : 'Search deployments...'} /></label>}</div>

    {tab === 'htes' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">HTE / Contact</th><th className="px-4 py-4">Interns</th><th className="px-4 py-4">Working Schedule</th><th className="px-4 py-4">Geofence</th><th className="px-4 py-4">MOA</th><th className="px-5 py-4 text-right">Actions</th></tr></thead><tbody>{filteredHtes.length ? filteredHtes.map((hte) => <tr key={hte.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40"><td className="px-5 py-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><FiBriefcase /></span><div><p className="text-sm font-black text-slate-800 dark:text-white">{hte.name}</p><p className="mt-1 max-w-64 truncate text-[11px] text-slate-400">{hte.address}</p><p className="mt-1 text-[10px] text-slate-400">{hte.contact_person} · {hte.contact_number}</p></div></div></td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{hte.students_count || 0}</p><p className="text-[10px] text-slate-400">{hte.active_students_count || 0} active</p></td><td className="px-4 py-4"><WorkingSchedule hte={hte} /></td><td className="px-4 py-4">{hte.geofence_enabled ? <div><span className="inline-flex items-center gap-1 text-xs font-black text-violet-600"><FiMapPin /> Active</span><p className="mt-1 text-[10px] text-slate-400">{hte.geofence_radius} meter radius</p></div> : <span className="text-xs text-slate-400">Disabled</span>}</td><td className="px-4 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{hte.valid_moa_count || 0}</p><p className="text-[10px] text-slate-400">valid agreement(s)</p></td><td className="px-5 py-4"><div className="flex justify-end gap-1.5"><button onClick={() => viewHte(hte)} className="rounded-lg bg-blue-100 p-2 text-blue-700" title="View"><FiEye /></button><button disabled={scheduleLoadingId === hte.id} onClick={() => openBatchSchedule(hte)} className="rounded-lg bg-violet-100 p-2 text-violet-700 disabled:opacity-50" title="Batch schedule"><FiCalendar className={scheduleLoadingId === hte.id ? 'animate-pulse' : ''} /></button><button onClick={() => openEditHte(hte)} className="rounded-lg bg-amber-100 p-2 text-amber-700" title="Edit"><FiEdit2 /></button></div></td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-slate-400">No HTE records match your search.</td></tr>}</tbody></table></div></section>}

    {tab === 'deployments' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="overflow-x-auto"><table className="w-full min-w-[1220px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">Student</th><th className="px-4 py-4">Assigned HTE / Department</th><th className="px-4 py-4">HTE Supervisor</th><th className="px-4 py-4">Internship Period</th><th className="px-4 py-4">Working Hours</th><th className="px-4 py-4">Status</th><th className="px-5 py-4 text-right">Action</th></tr></thead><tbody>{filteredDeployments.length ? filteredDeployments.map((student) => <tr key={student.id} className="border-t border-slate-100 dark:border-gray-700"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{student.last_name}, {student.first_name}</p><p className="mt-1 text-[11px] text-slate-400">{student.student_id} · {student.program?.code}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{student.hte?.name || 'Not deployed'}</p><p className="mt-1 text-[10px] text-slate-400">{student.hte_department?.department_name || 'Department not assigned'}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{student.supervisor_record?.full_name || student.supervisor?.name || 'Not assigned'}</p><p className="mt-1 text-[10px] text-slate-400">{student.supervisor_record?.user?.email || student.supervisor?.email || 'Account not yet created'}</p></td><td className="px-4 py-4"><p className="text-xs text-slate-600 dark:text-gray-300">{formatDate(student.ojt_start_date)} – {formatDate(student.ojt_end_date)}</p><p className="mt-1 text-[10px] text-slate-400">{student.required_ojt_hours || 486} required hours</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{timeValue(student.official_am_start, '08:00')}–{timeValue(student.official_am_end, '12:00')} / {timeValue(student.official_pm_start, '13:00')}–{timeValue(student.official_pm_end, '17:00')}</p><p className="mt-1 text-[10px] uppercase text-slate-400">{(student.work_days || defaultDays).join(' · ')}</p></td><td className="px-4 py-4"><Badge value={student.internship_status} /></td><td className="px-5 py-4 text-right"><button onClick={() => openDeployment(student)} className="inline-flex items-center gap-2 rounded-lg bg-amber-100 px-3 py-2 text-xs font-black text-amber-700"><FiEdit2 /> Configure</button></td></tr>) : <tr><td colSpan="7" className="py-16 text-center text-slate-400">No approved student records found.</td></tr>}</tbody></table></div></section>}

    {tab === 'holidays' && <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="mb-5 flex items-center justify-between"><div><h2 className="font-black text-slate-800 dark:text-white">Holiday Calendar</h2><p className="mt-1 text-xs text-slate-400">Non-working dates used by attendance monitoring.</p></div><button onClick={() => openHoliday()} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Add Holiday</button></div><div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{data.holidays.length ? data.holidays.map((holiday) => <article key={holiday.id} className="rounded-2xl border border-slate-100 p-4 dark:border-gray-700"><div className="flex items-start justify-between"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50"><FiCalendar /></span><div className="flex gap-1"><button onClick={() => openHoliday(holiday)} className="rounded-lg p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-gray-700"><FiEdit2 /></button><button onClick={() => deleteHoliday(holiday)} className="rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-gray-700"><FiTrash2 /></button></div></div><p className="mt-3 text-sm font-black text-slate-800 dark:text-white">{holiday.name}</p><p className="mt-1 text-xs font-bold text-violet-600">{formatDate(holiday.date)}</p><p className="mt-2 text-xs leading-5 text-slate-400">{holiday.description || 'No description provided.'}</p>{holiday.is_recurring && <span className="mt-3 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase text-blue-600 dark:bg-blue-950/50">Repeats yearly</span>}</article>) : <div className="col-span-full rounded-2xl border border-dashed border-slate-300 py-14 text-center text-slate-400 dark:border-gray-700">No holidays configured.</div>}</div></section>}

    {tab === 'moas' && <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center justify-between border-b border-slate-100 p-5 dark:border-gray-700"><div><h2 className="font-black text-slate-800 dark:text-white">MOA Upload & Monitoring</h2><p className="mt-1 text-xs text-slate-400">Uploaded agreements are submitted as pending for approval.</p></div><button onClick={() => { setMoaForm(emptyMoa); setMoaOpen(true); }} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white"><FiUpload /> Upload MOA</button></div><div className="overflow-x-auto"><table className="w-full min-w-[900px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4">HTE / College</th><th className="px-4 py-4">Effective Date</th><th className="px-4 py-4">Expiration</th><th className="px-4 py-4">Status</th><th className="px-5 py-4 text-right">Actions</th></tr></thead><tbody>{data.moas.length ? data.moas.map((moa) => <tr key={moa.id} className="border-t border-slate-100 dark:border-gray-700"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{moa.hte?.name}</p><p className="mt-1 text-[11px] text-slate-400">{moa.college?.code} · {moa.college?.name}</p></td><td className="px-4 py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{formatDate(moa.effective_date)}</td><td className="px-4 py-4"><p className={`text-xs font-black ${moa.days_remaining <= 30 ? 'text-rose-600' : 'text-slate-600 dark:text-gray-300'}`}>{formatDate(moa.expiration_date)}</p><p className="mt-1 text-[10px] text-slate-400">{moa.days_remaining < 0 ? `Expired ${Math.abs(moa.days_remaining)} days ago` : `${moa.days_remaining} days remaining`}</p></td><td className="px-4 py-4"><Badge value={moa.computed_status} /></td><td className="px-5 py-4"><div className="flex justify-end gap-2"><button onClick={() => downloadMoa(moa)} className="inline-flex items-center gap-2 rounded-lg bg-blue-100 px-3 py-2 text-xs font-black text-blue-700"><FiDownload /> Download</button></div></td></tr>) : <tr><td colSpan="5" className="py-16 text-center text-slate-400">No MOA records uploaded.</td></tr>}</tbody></table></div></section>}

    {hteSearchOpen && (
      <Modal title="Search HTE" subtitle="Search the shared database before adding a new partner establishment." onClose={() => setHteSearchOpen(false)} wide compact>
        <form onSubmit={searchExistingHtes} className="border-b border-slate-100 p-5 dark:border-gray-700">
          <Field label="HTE Name, Address, or Contact Person" required>
            <div className="flex flex-col gap-3 sm:flex-row">
              <div className="relative flex-1"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input autoFocus value={hteSearchQuery} onChange={(event) => { setHteSearchQuery(event.target.value); setHteSearchComplete(false); }} className={`${inputClass} pl-10`} placeholder="e.g. Marinduque State University" required minLength="2" /></div>
              <button disabled={hteSearchBusy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{hteSearchBusy ? 'Checking...' : 'Check Database'}</button>
            </div>
          </Field>
        </form>
        <div className="space-y-3 p-5">
          {!hteSearchComplete && <div className="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center dark:border-gray-700"><FiSearch className="mx-auto text-3xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-500">Search first to prevent duplicate HTE records.</p></div>}
          {hteSearchComplete && hteSearchResults.map((hte) => <article key={hte.id} className="rounded-2xl border border-slate-200 p-4 dark:border-gray-700">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div><div className="flex flex-wrap items-center gap-2"><h3 className="font-black text-slate-800 dark:text-white">{hte.name}</h3>{hte.linked_to_program && <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-700">Already linked</span>}</div><p className="mt-1 text-xs text-slate-400">{hte.address}</p><p className="mt-2 text-xs font-bold text-slate-600 dark:text-gray-300">{hte.contact_person} · {hte.contact_number}</p></div>
              <button type="button" disabled={hteSearchBusy} onClick={() => linkExistingHte(hte)} className="shrink-0 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white disabled:opacity-50">{hte.linked_to_program ? 'Use Existing HTE' : 'Link to My Program'}</button>
            </div>
            <div className="mt-4 grid gap-2 md:grid-cols-2">{hte.departments?.length ? hte.departments.map((department) => <div key={department.id} className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900"><p className="text-xs font-black text-[#7D001D] dark:text-rose-300">{department.department_name}</p><p className="mt-1 text-[11px] text-slate-500">Supervisor: {department.supervisors?.map((supervisor) => supervisor.full_name).join(', ') || 'None recorded'}</p></div>) : <p className="text-xs text-slate-400">No departments recorded yet.</p>}</div>
          </article>)}
          {hteSearchComplete && !hteSearchResults.length && <div className="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-6 text-center dark:border-amber-900 dark:bg-amber-950/30"><p className="font-black text-amber-800 dark:text-amber-200">No existing HTE found.</p><p className="mt-1 text-xs text-amber-700/80 dark:text-amber-300">You may now open the existing Add Partner HTE form.</p><button type="button" onClick={() => openCreateHte(hteSearchQuery)} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-5 py-2.5 text-xs font-black text-white"><FiPlus /> Add New HTE</button></div>}
        </div>
      </Modal>
    )}

    {hteEditor && (
      <Modal
        title={hteEditor.mode === 'create' ? 'Add Partner HTE' : 'Edit HTE Record'}
        subtitle={hteEditor.mode === 'create' ? 'Configure HTE details, the initial batch schedule, and the exact geofence location.' : 'Configure contact details and the exact geofence location.'}
        onClose={() => { setHteEditor(null); setNewHteSchedule(null); }}
        wide
        compact
      >
        <form onSubmit={saveHte}>
          <div className="grid gap-4 p-5 md:grid-cols-3">
            <Field label="HTE Name" required>
              <input value={hteForm.name} onChange={(event) => setHteForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} required />
            </Field>
            <Field label="Contact Person" required>
              <input value={hteForm.contact_person} onChange={(event) => setHteForm((current) => ({ ...current, contact_person: event.target.value }))} className={inputClass} required />
            </Field>
            <Field label="Contact Number" required>
              <ContactNumberInput value={hteForm.contact_number} onValueChange={(contact_number) => setHteForm((current) => ({ ...current, contact_number }))} className={inputClass} required placeholder="09XX XXX XXXX" />
            </Field>
            <Field label="Contact Email" required>
              <input type="email" value={hteForm.contact_email} onChange={(event) => setHteForm((current) => ({ ...current, contact_email: event.target.value }))} className={inputClass} required />
            </Field>
            <div className="md:col-span-2">
              <Field label="Address" required>
                <input value={hteForm.address} onChange={(event) => setHteForm((current) => ({ ...current, address: event.target.value }))} className={inputClass} required />
              </Field>
            </div>

            {hteEditor.mode === 'create' && <section className="md:col-span-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/50 sm:p-5">
              <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Department / Unit</h3><p className="mt-1 text-xs text-slate-400">Record the department and its supervisor. Matching records will be reused automatically.</p></div><button type="button" onClick={() => setNewDepartments((current) => [...current, emptyDepartment])} className="inline-flex items-center gap-2 rounded-xl border border-[#7D001D]/20 bg-white px-3 py-2 text-xs font-black text-[#7D001D] dark:bg-gray-800 dark:text-rose-300"><FiPlus /> Add Department</button></div>
              <div className="mt-4 space-y-3">{newDepartments.map((department, index) => <div key={`department-${index + 1}`} className="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-[1fr_1fr_auto]">
                <Field label={`Department Name ${index + 1}`}><input value={department.department_name} onChange={(event) => setNewDepartments((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, department_name: event.target.value } : item))} className={inputClass} placeholder="Learning Resources Center" required={Boolean(department.supervisor_full_name)} /></Field>
                <Field label="Supervisor Full Name"><input value={department.supervisor_full_name} onChange={(event) => setNewDepartments((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, supervisor_full_name: event.target.value } : item))} className={inputClass} placeholder="Maria Saint" required={Boolean(department.department_name)} /></Field>
                <button type="button" disabled={newDepartments.length === 1} onClick={() => setNewDepartments((current) => current.filter((_, itemIndex) => itemIndex !== index))} className="self-end rounded-xl p-3 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-rose-950/30" aria-label={`Remove department ${index + 1}`}><FiTrash2 /></button>
              </div>)}</div>
            </section>}

            {hteEditor.mode === 'create' && newHteSchedule && <div className="md:col-span-3 space-y-5 border-t border-slate-100 pt-4 dark:border-gray-700">
              <section className="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/50 sm:p-5">
                <div className="mb-4">
                  <h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Batch Schedule Configuration</h3>
                  <p className="mt-1 text-xs leading-5 text-slate-400">Select the initial batch, set common hours, then apply them only to the checked working days below.</p>
                </div>

                {data.batches.length ? <>
                  <Field label="Batch" required>
                    <select value={newHteSchedule.batch_id} onChange={(event) => setNewHteSchedule((current) => ({ ...current, batch_id: event.target.value }))} className={inputClass} required>
                      {data.batches.map((batch) => <option key={batch.id} value={batch.id}>{batch.label}</option>)}
                    </select>
                  </Field>

                  <div className="mt-5 border-t border-slate-200 pt-4 dark:border-gray-700">
                    <p className="mb-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Schedule Tools</p>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                      {[['AM Start', 'am_start'], ['AM End', 'am_end'], ['PM Start', 'pm_start'], ['PM End', 'pm_end']].map(([label, name]) => <Field key={name} label={label} required>
                        <input type="time" value={newHteSchedule.tools[name]} onChange={(event) => setNewHteSchedule((current) => ({ ...current, tools: { ...current.tools, [name]: event.target.value } }))} className={inputClass} required />
                      </Field>)}
                    </div>
                  </div>

                  <button type="button" onClick={applyNewHteScheduleToDays} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 text-xs font-black text-white sm:w-auto"><FiCheck /> Apply to Checked Days</button>
                </> : <div className="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-center text-sm font-bold text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">No active batch is configured for this program. Add an Academic Section first in Academic Management before creating an HTE.</div>}
              </section>

              {data.batches.length > 0 && <details open className="group overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 bg-slate-50/80 px-4 py-3 dark:bg-gray-900/50 sm:px-5">
                  <div>
                    <h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Per-Day Configuration</h3>
                    <p className="mt-1 text-xs leading-5 text-slate-400">Check working days and fine-tune their individual hours.</p>
                  </div>
                  <FiChevronDown className="shrink-0 text-slate-400 transition-transform group-open:rotate-180" />
                </summary>

                <div className="p-3 sm:p-4">
                  <div className="hidden grid-cols-[minmax(7rem,1fr)_6rem_repeat(4,minmax(7rem,1fr))] gap-3 px-3 pb-2 text-[10px] font-black uppercase tracking-wide text-slate-400 md:grid">
                    <span>Day</span><span>Working</span><span>AM Start</span><span>AM End</span><span>PM Start</span><span>PM End</span>
                  </div>
                  <div className="space-y-3 overflow-x-auto md:space-y-0 md:rounded-xl md:border md:border-slate-100 dark:md:border-gray-700">
                    {weekdays.map(([day]) => {
                      const enabled = newHteSchedule.selected_days.includes(day);
                      return <div key={day} className={`grid min-w-0 grid-cols-2 items-center gap-3 rounded-xl border p-3 md:min-w-[760px] md:grid-cols-[minmax(7rem,1fr)_6rem_repeat(4,minmax(7rem,1fr))] md:rounded-none md:border-0 md:border-t md:first:border-t-0 ${enabled ? 'border-[#7D001D]/20 bg-white dark:border-rose-900/60 dark:bg-gray-800' : 'border-slate-100 bg-slate-50/70 dark:border-gray-700 dark:bg-gray-900/40'}`}>
                        <h4 className="text-sm font-black text-slate-800 dark:text-white">{weekdayNames[day]}</h4>
                        <label className="inline-flex items-center justify-end gap-2 text-xs font-black text-slate-600 dark:text-gray-300 md:justify-start"><input type="checkbox" checked={enabled} onChange={() => toggleCheckedDay(setNewHteSchedule, day)} /><span className="md:hidden">Working</span></label>
                        {[['AM Start', 'am_start'], ['AM End', 'am_end'], ['PM Start', 'pm_start'], ['PM End', 'pm_end']].map(([label, name]) => <label key={name}>
                          <span className="mb-1.5 block text-[10px] font-black uppercase tracking-wide text-slate-400 md:hidden">{label}{enabled && ' *'}</span>
                          <input type="time" value={newHteSchedule.days[day]?.[name] || ''} onChange={(event) => setNewHteSchedule((current) => ({ ...current, days: { ...current.days, [day]: { ...current.days[day], [name]: event.target.value } } }))} className={`${inputClass} px-2 py-2 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:opacity-60 dark:disabled:bg-gray-900`} disabled={!enabled} required={enabled} aria-label={`${weekdayNames[day]} ${label}`} />
                        </label>)}
                      </div>})}
                  </div>
                </div>
              </details>}
            </div>}

            <details className="group md:col-span-3 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-700 dark:bg-gray-800">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 bg-slate-50/80 px-4 py-3 dark:bg-gray-900/50 sm:px-5">
                <div>
                  <h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Geofence Location & Radius</h3>
                  <p className="mt-1 text-xs text-slate-400">Optional location and WFO radius settings.</p>
                </div>
                <div className="flex items-center gap-3"><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${hteForm.geofence_enabled ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300' : 'bg-slate-200 text-slate-500 dark:bg-gray-700 dark:text-gray-300'}`}>{hteForm.geofence_enabled ? 'Enabled' : 'Disabled'}</span><FiChevronDown className="shrink-0 text-slate-400 transition-transform group-open:rotate-180" /></div>
              </summary>
              <div className="space-y-3 p-3 sm:p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-xs text-slate-400">Search for the HTE, then click or drag the pin to its exact entrance.</p>
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
                  compact
                  onPositionChange={(latitude, longitude) => setHteForm((current) => ({ ...current, latitude, longitude }))}
                  onRadiusChange={(geofence_radius) => setHteForm((current) => ({ ...current, geofence_radius }))}
                />
              </div>
            </details>

            <div className="md:col-span-3 flex flex-wrap gap-5 border-t border-slate-100 pt-4 text-sm font-bold dark:border-gray-700">
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={hteForm.is_active} onChange={(event) => setHteForm((current) => ({ ...current, is_active: event.target.checked }))} />
                Active partner establishment
              </label>
            </div>
          </div>
          <div className="sticky bottom-0 z-20 flex justify-end gap-3 border-t border-slate-100 bg-white/95 px-5 py-4 backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
            <button type="button" onClick={() => { setHteEditor(null); setNewHteSchedule(null); }} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button>
            <button disabled={busy || (hteEditor.mode === 'create' && !newHteSchedule?.batch_id)} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : hteEditor.mode === 'create' ? 'Save HTE & Batch Schedule' : 'Save HTE'}</button>
          </div>
        </form>
      </Modal>
    )}

    {batchScheduleEditor && (
      <Modal
        title="Configure Batch Schedule"
        subtitle={`${batchScheduleEditor.hte.name} · Set a common schedule, then fine-tune each day before saving.`}
        onClose={() => setBatchScheduleEditor(null)}
        wide
        compact
      >
        <form onSubmit={saveBatchSchedule}>
          <div className="space-y-6 p-5">
            <section className="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/50 sm:p-5">
              <div className="mb-4">
                <h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Batch Schedule Configuration</h3>
                <p className="mt-1 text-xs leading-5 text-slate-400">Choose a batch and copy the schedule tools only to the checked working days below.</p>
              </div>

              {batchScheduleEditor.batches.length ? <>
                <Field label="Batch" required>
                  <select value={batchScheduleEditor.batch_id} onChange={(event) => selectBatch(event.target.value)} className={inputClass} required>
                    {batchScheduleEditor.batches.map((batch) => <option key={batch.id} value={batch.id}>{batch.label}</option>)}
                  </select>
                </Field>

                <div className="mt-5 border-t border-slate-200 pt-4 dark:border-gray-700">
                  <p className="mb-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Schedule Tools</p>
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[['AM Start', 'am_start'], ['AM End', 'am_end'], ['PM Start', 'pm_start'], ['PM End', 'pm_end']].map(([label, name]) => (
                      <Field key={name} label={label} required>
                        <input type="time" value={batchScheduleEditor.tools[name]} onChange={(event) => setBatchScheduleEditor((current) => ({ ...current, tools: { ...current.tools, [name]: event.target.value } }))} className={inputClass} required />
                      </Field>
                    ))}
                  </div>
                </div>

                <button type="button" onClick={applyScheduleToDays} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 text-xs font-black text-white sm:w-auto">
                  <FiCheck /> Apply to Checked Days
                </button>
              </> : <div className="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-center text-sm font-bold text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">No active batch is configured for this program. Add an Academic Section first in Academic Management.</div>}
            </section>

            {batchScheduleEditor.batches.length > 0 && <section>
              <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                  <h3 className="text-sm font-black text-[#7D001D] dark:text-rose-300">Per-Day Configuration</h3>
                  <p className="mt-1 text-xs leading-5 text-slate-400">Check the actual working days. Unchecked days are disabled and will not be changed by Apply.</p>
                </div>
                {batchScheduleEditor.batches.find((batch) => String(batch.id) === String(batchScheduleEditor.batch_id))?.has_saved_schedule && <span className="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Saved schedule</span>}
              </div>

              <div className="grid gap-4 lg:grid-cols-2">
                {weekdays.map(([day]) => {
                  const enabled = batchScheduleEditor.selected_days.includes(day);
                  return <article key={day} className={`rounded-2xl border p-4 ${enabled ? 'border-[#7D001D]/20 bg-white dark:border-rose-900/60 dark:bg-gray-800' : 'border-slate-100 bg-slate-50/70 dark:border-gray-700 dark:bg-gray-900/40'}`}>
                  <div className="mb-3 flex items-center justify-between gap-3"><h4 className="text-sm font-black text-slate-800 dark:text-white">{weekdayNames[day]}</h4><label className="inline-flex items-center gap-2 text-xs font-black text-slate-600 dark:text-gray-300"><input type="checkbox" checked={enabled} onChange={() => toggleCheckedDay(setBatchScheduleEditor, day)} /> Working day</label></div>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {[['AM Start', 'am_start'], ['AM End', 'am_end'], ['PM Start', 'pm_start'], ['PM End', 'pm_end']].map(([label, name]) => (
                      <Field key={name} label={label} required>
                        <input type="time" value={batchScheduleEditor.days[day]?.[name] || ''} onChange={(event) => setBatchScheduleEditor((current) => ({ ...current, days: { ...current.days, [day]: { ...current.days[day], [name]: event.target.value } } }))} className={`${inputClass} disabled:cursor-not-allowed disabled:bg-slate-100 disabled:opacity-60 dark:disabled:bg-gray-900`} disabled={!enabled} required={enabled} />
                      </Field>
                    ))}
                  </div>
                </article>})}
              </div>
            </section>}
          </div>

          <div className="flex flex-col-reverse gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
            <button type="button" onClick={() => setBatchScheduleEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button>
            <button disabled={busy || !batchScheduleEditor.batch_id} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">{busy ? 'Saving...' : 'Save Batch Schedule'}</button>
          </div>
        </form>
      </Modal>
    )}

    {deploymentEditor && <Modal title="Configure Student Deployment" subtitle={`${deploymentEditor.first_name} ${deploymentEditor.last_name} · ${deploymentEditor.student_id}`} onClose={() => setDeploymentEditor(null)} wide><form onSubmit={saveDeployment}><div className="grid gap-4 p-5 md:grid-cols-3"><Field label="Assigned HTE"><select value={deploymentEditor.hte_id} onChange={(event) => selectDeploymentHte(event.target.value)} className={inputClass}><option value="">Not deployed</option>{data.htes.filter((hte) => hte.is_active).map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select></Field><Field label="Department / Unit"><select disabled={!deploymentEditor.hte_id} value={deploymentEditor.hte_department_id} onChange={(event) => setDeploymentEditor((current) => ({ ...current, hte_department_id: event.target.value, supervisor_record_id: '' }))} className={inputClass}><option value="">Select department</option>{deploymentDepartments.map((department) => <option key={department.id} value={department.id}>{department.department_name}</option>)}</select></Field><Field label="HTE Supervisor"><select disabled={!deploymentEditor.hte_department_id} value={deploymentEditor.supervisor_record_id} onChange={(event) => setDeploymentEditor((current) => ({ ...current, supervisor_record_id: event.target.value }))} className={inputClass}><option value="">Select supervisor</option>{deploymentSupervisors.map((supervisor) => <option key={supervisor.id} value={supervisor.id}>{supervisor.full_name}{supervisor.user_id ? '' : ' · No account yet'}</option>)}</select></Field><Field label="Internship Status"><select value={deploymentEditor.internship_status} onChange={(event) => setDeploymentEditor((current) => ({ ...current, internship_status: event.target.value }))} className={inputClass}><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option><option value="dropped">Dropped</option></select></Field><Field label="Required OJT Hours"><input type="number" min="1" value={deploymentEditor.required_ojt_hours} onChange={(event) => setDeploymentEditor((current) => ({ ...current, required_ojt_hours: event.target.value }))} className={inputClass} required /></Field><Field label="OJT Start Date"><input type="date" value={deploymentEditor.ojt_start_date} onChange={(event) => setDeploymentEditor((current) => ({ ...current, ojt_start_date: event.target.value }))} className={inputClass} /></Field><Field label="OJT End Date"><input type="date" value={deploymentEditor.ojt_end_date} onChange={(event) => setDeploymentEditor((current) => ({ ...current, ojt_end_date: event.target.value }))} className={inputClass} /></Field><label className="flex items-center gap-2 self-end rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold dark:border-gray-600"><input type="checkbox" checked={deploymentEditor.allow_past_attendance} onChange={(event) => setDeploymentEditor((current) => ({ ...current, allow_past_attendance: event.target.checked }))} /> Allow past attendance</label><div className="md:col-span-3 border-t border-slate-100 pt-4 dark:border-gray-700"><h3 className="mb-3 text-sm font-black text-[#7D001D] dark:text-rose-300">Student Working Hours</h3><div className="grid gap-3 sm:grid-cols-4">{[['AM Start', 'official_am_start'], ['AM End', 'official_am_end'], ['PM Start', 'official_pm_start'], ['PM End', 'official_pm_end']].map(([label, name]) => <Field key={name} label={label}><input type="time" value={deploymentEditor[name]} onChange={(event) => setDeploymentEditor((current) => ({ ...current, [name]: event.target.value }))} className={inputClass} required /></Field>)}</div><div className="mt-4"><Field label="Working Days"><WorkDays value={deploymentEditor.work_days} onChange={(work_days) => setDeploymentEditor((current) => ({ ...current, work_days }))} /></Field></div></div></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setDeploymentEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : 'Save Deployment'}</button></div></form></Modal>}

    {holidayEditor && <Modal title={holidayEditor.mode === 'create' ? 'Add Holiday' : 'Edit Holiday'} subtitle="Configure a non-working date for attendance monitoring." onClose={() => setHolidayEditor(null)}><form onSubmit={saveHoliday}><div className="space-y-4 p-5"><Field label="Holiday Name" required><input value={holidayForm.name} onChange={(event) => setHolidayForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} required /></Field><Field label="Date" required><input type="date" value={holidayForm.date} onChange={(event) => setHolidayForm((current) => ({ ...current, date: event.target.value }))} className={inputClass} required /></Field><Field label="Description"><textarea rows="3" value={holidayForm.description || ''} onChange={(event) => setHolidayForm((current) => ({ ...current, description: event.target.value }))} className={inputClass} /></Field><label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" checked={holidayForm.is_recurring} onChange={(event) => setHolidayForm((current) => ({ ...current, is_recurring: event.target.checked }))} /> Repeat every year</label></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setHolidayEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white">{busy ? 'Saving...' : 'Save Holiday'}</button></div></form></Modal>}

    {moaOpen && <Modal title="Upload Memorandum of Agreement" subtitle="PDF only, up to 10 MB. The MOA is automatically assigned to your college and program." onClose={() => setMoaOpen(false)}><form onSubmit={saveMoa}><div className="space-y-4 p-5"><Field label="Partner HTE" required><select value={moaForm.hte_id} onChange={(event) => setMoaForm((current) => ({ ...current, hte_id: event.target.value }))} className={inputClass} required><option value="">Select HTE</option>{data.htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select></Field><div className="grid gap-4 sm:grid-cols-2"><Field label="Effective Date" required><input type="date" value={moaForm.effective_date} onChange={(event) => setMoaForm((current) => ({ ...current, effective_date: event.target.value }))} className={inputClass} required /></Field><Field label="Expiration Date" required><input type="date" value={moaForm.expiration_date} onChange={(event) => setMoaForm((current) => ({ ...current, expiration_date: event.target.value }))} className={inputClass} required /></Field></div><Field label="MOA PDF" required><input type="file" accept=".pdf,application/pdf" onChange={(event) => setMoaForm((current) => ({ ...current, file: event.target.files?.[0] || null }))} className={inputClass} required /></Field></div><div className="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-gray-700"><button type="button" onClick={() => setMoaOpen(false)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">Cancel</button><button disabled={busy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Uploading...' : 'Upload MOA'}</button></div></form></Modal>}

    {selectedHte && <Modal title={selectedHte.name} subtitle={selectedHte.address} onClose={() => { setSelectedHte(null); setDepartmentForm(emptyDepartment); }} wide compact><div className="grid gap-5 p-5 lg:grid-cols-2"><div><div className="rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><div className="flex items-center justify-between"><Badge value={selectedHte.is_active ? 'active' : 'inactive'} />{selectedHte.geofence_enabled && <span className="inline-flex items-center gap-1 text-xs font-black text-violet-600"><FiMapPin /> {selectedHte.geofence_radius}m geofence</span>}</div><p className="mt-3 text-sm font-black text-slate-800 dark:text-white">{selectedHte.contact_person}</p><p className="mt-1 text-xs text-slate-400">{selectedHte.contact_email} · {selectedHte.contact_number}</p><p className="mt-3 text-xs font-bold text-slate-600 dark:text-gray-300">{timeValue(selectedHte.default_am_start, '08:00')}–{timeValue(selectedHte.default_am_end, '12:00')} / {timeValue(selectedHte.default_pm_start, '13:00')}–{timeValue(selectedHte.default_pm_end, '17:00')}</p></div><h3 className="mb-3 mt-5 font-black text-slate-800 dark:text-white">Deployed Students</h3><div className="space-y-2">{selectedHte.students?.length ? selectedHte.students.map((student) => <div key={student.id} className="flex items-center justify-between rounded-xl border border-slate-100 p-3 dark:border-gray-700"><div><p className="text-xs font-black text-slate-700 dark:text-gray-200">{student.first_name} {student.last_name}</p><p className="text-[10px] text-slate-400">{student.student_id} · {student.program?.code}</p></div><Badge value={student.internship_status} /></div>) : <p className="rounded-xl border border-dashed border-slate-300 py-8 text-center text-xs text-slate-400 dark:border-gray-700">No students deployed.</p>}</div></div><div><h3 className="font-black text-slate-800 dark:text-white">Departments / Units</h3><div className="mt-3 space-y-2">{selectedHte.departments?.length ? selectedHte.departments.map((department) => <div key={department.id} className="rounded-xl border border-slate-100 p-3 dark:border-gray-700"><p className="text-xs font-black text-[#7D001D] dark:text-rose-300">{department.department_name}</p><p className="mt-1 text-[11px] text-slate-500">Supervisor: {department.supervisors?.map((supervisor) => `${supervisor.full_name}${supervisor.user_id ? ' · Account created' : ' · No account'}`).join(', ') || 'None'}</p></div>) : <p className="rounded-xl border border-dashed border-slate-300 py-6 text-center text-xs text-slate-400 dark:border-gray-700">No departments recorded.</p>}</div><form onSubmit={saveDepartment} className="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 dark:bg-gray-900 sm:grid-cols-2"><Field label="Department Name" required><input value={departmentForm.department_name} onChange={(event) => setDepartmentForm((current) => ({ ...current, department_name: event.target.value }))} className={inputClass} required /></Field><Field label="Supervisor Full Name" required><input value={departmentForm.supervisor_full_name} onChange={(event) => setDepartmentForm((current) => ({ ...current, supervisor_full_name: event.target.value }))} className={inputClass} required /></Field><button disabled={busy} className="rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white disabled:opacity-50 sm:col-span-2">{busy ? 'Saving...' : 'Add or Reuse Department & Supervisor'}</button></form><h3 className="mb-3 mt-5 font-black text-slate-800 dark:text-white">MOA Records</h3><div className="space-y-3">{selectedHte.moas?.length ? selectedHte.moas.map((moa) => <div key={moa.id} className="rounded-xl border border-slate-100 p-4 dark:border-gray-700"><div className="flex items-start justify-between"><div><p className="text-xs font-black text-slate-700 dark:text-gray-200">{moa.college?.code} Agreement</p><p className="mt-1 text-[10px] text-slate-400">{formatDate(moa.effective_date)} – {formatDate(moa.expiration_date)}</p></div><Badge value={new Date(moa.expiration_date) < new Date() ? 'expired' : moa.status} /></div></div>) : <p className="rounded-xl border border-dashed border-slate-300 py-8 text-center text-xs text-slate-400 dark:border-gray-700">No MOA records.</p>}</div><button onClick={() => { setSelectedHte(null); openEditHte(selectedHte); }} className="mt-5 w-full rounded-xl border border-slate-200 py-3 text-sm font-black text-[#7D001D] dark:border-gray-600 dark:text-rose-300">Edit HTE Configuration</button></div></div></Modal>}
  </div>;
}

export default HTEManagement;
