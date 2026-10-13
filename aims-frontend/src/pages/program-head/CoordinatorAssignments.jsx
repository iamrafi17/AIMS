import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { PageIntro } from '../../components/common/PortalUI';
import { CheckCircleIcon, UserGroupIcon } from '@heroicons/react/24/outline';

function CoordinatorLabel({ coordinator, workloadCount = 0 }) {
  if (!coordinator) return <span>Coordinator unavailable</span>;

  return (
    <span className="block min-w-0">
      <span className="block truncate font-semibold">{coordinator.name || 'Coordinator name unavailable'}</span>
      <span className="block truncate text-xs font-normal text-slate-500 dark:text-slate-400">
        {coordinator.username} · {workloadCount} {workloadCount === 1 ? 'section' : 'sections'} assigned
      </span>
    </span>
  );
}

export default function CoordinatorAssignments() {
  const [data, setData] = useState(null);
  const [choices, setChoices] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [openSectionId, setOpenSectionId] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);
  const [periodId, setPeriodId] = useState('');
  const [search, setSearch] = useState('');
  const [assignmentFilter, setAssignmentFilter] = useState('all');
  const [bulkOpen, setBulkOpen] = useState(false);
  const [bulkSectionIds, setBulkSectionIds] = useState([]);
  const [bulkCoordinatorId, setBulkCoordinatorId] = useState('');
  const periods = data?.periods || [];
  const activePeriod = periods.find((period) => period.status === 'active') || periods[0];
  const selectedPeriodId = periodId || activePeriod?.id || '';
  const visibleSections = data?.sections.filter((section) => !selectedPeriodId || String(section.academic_period_id) === String(selectedPeriodId)) || [];
  const listedSections = visibleSections.filter((section) => {
    if (search && !section.name.toLowerCase().includes(search.trim().toLowerCase())) return false;
    if (assignmentFilter === 'assigned' && !choices[section.id]) return false;
    if (assignmentFilter === 'unassigned' && choices[section.id]) return false;
    return true;
  });
  const assignedCount = visibleSections.filter((section) => Boolean(choices[section.id])).length;
  const pendingCount = data?.sections.filter((section) => String(choices[section.id] || '') !== String(section.coordinator_id || '')).length || 0;
  const workloadCount = (coordinatorId) => visibleSections.filter((section) => String(choices[section.id]) === String(coordinatorId)).length;

  const applyBulkAssignment = () => {
    if (!bulkSectionIds.length || !bulkCoordinatorId) return;
    setChoices((current) => ({
      ...current,
      ...Object.fromEntries(bulkSectionIds.map((sectionId) => [sectionId, bulkCoordinatorId])),
    }));
    setBulkOpen(false);
    setBulkSectionIds([]);
    setBulkCoordinatorId('');
  };

  useEffect(() => {
    setLoading(true);
    api.get('/program-head/coordinator-assignments')
      .then(({ data: result }) => {
        setData(result);
        setChoices(Object.fromEntries(result.sections.map((section) => [section.id, section.coordinator_id || ''])));
        setBulkOpen(false);
        setBulkSectionIds([]);
        setPeriodId((current) => result.periods?.some((period) => String(period.id) === String(current))
          ? current
          : String(result.periods?.find((period) => period.status === 'active')?.id || result.periods?.[0]?.id || ''));
      })
      .catch((error) => toast.error(error.response?.data?.message || 'Unable to load section assignments.'))
      .finally(() => setLoading(false));
  }, [refreshKey]);

  const save = async () => {
    if (!data || saving) return;
    const changes = data.sections.filter((section) => String(choices[section.id] || '') !== String(section.coordinator_id || ''));
    if (!changes.length) return toast('No assignment changes to save.');
    const accessChanges = changes.filter((section) => section.coordinator_id);
    if (accessChanges.length) {
      const coordinatorName = (id) => data.coordinators.find((coordinator) => String(coordinator.id) === String(id))?.name || 'the current Coordinator';
      const message = accessChanges.length === 1
        ? choices[accessChanges[0].id]
          ? `Reassign Section ${accessChanges[0].name} from ${coordinatorName(accessChanges[0].coordinator_id)} to ${coordinatorName(choices[accessChanges[0].id])}? The previous Coordinator will lose access to this section's student records.`
          : `Unassign Section ${accessChanges[0].name} from ${coordinatorName(accessChanges[0].coordinator_id)}? That Coordinator will lose access to this section's student records.`
        : `Change assignments for ${accessChanges.length} sections? Previous Coordinators will lose access to the affected student records.`;
      if (!window.confirm(message)) return;
    }

    setSaving(true);
    try {
      const { data: result } = await api.put('/program-head/coordinator-assignments', {
        assignments: changes.map((section) => ({
          section_id: section.id,
          coordinator_id: choices[section.id] ? Number(choices[section.id]) : null,
        })),
      });
      setData(result);
      setChoices(Object.fromEntries(result.sections.map((section) => [section.id, section.coordinator_id || ''])));
      toast.success('Section assignments saved.');
    } catch (error) {
      toast.error(error.response?.data?.errors?.assignments?.[0] || error.response?.data?.message || 'Unable to save assignments.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <PageIntro eyebrow="Program management" title="Coordinator Assignments" description="Assign a Coordinator to each section. One Coordinator can handle multiple sections." icon={UserGroupIcon} />
      {loading ? <p className="text-sm text-slate-500">Loading sections...</p> : !data ? null : (
        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-700 sm:p-6">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-[#7D001D] dark:text-rose-300">Program</p>
              <h2 className="mt-1 text-xl font-bold">{data.program?.code} <span className="font-normal text-slate-500">· {data.program?.name}</span></h2>
              {periods.length > 1 ? <label className="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                Academic Period
                <select value={selectedPeriodId} onChange={(event) => { setPeriodId(event.target.value); setOpenSectionId(null); setBulkOpen(false); setBulkSectionIds([]); }} className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-800">
                  {periods.map((period) => <option key={period.id} value={period.id}>{period.semester} · AY {period.academic_year}</option>)}
                </select>
              </label> : <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{activePeriod ? `${activePeriod.semester} · AY ${activePeriod.academic_year}` : 'Academic period unavailable'}</p>}
              <p className="mt-2 text-sm text-slate-500">
                {assignedCount} of {visibleSections.length} {visibleSections.length === 1 ? 'section' : 'sections'} assigned
                <span className="mx-2" aria-hidden="true">·</span>
                {data.coordinators.length} {data.coordinators.length === 1 ? 'Coordinator' : 'Coordinators'} available
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              <button type="button" onClick={() => { if (!pendingCount || window.confirm('Discard unsaved assignment changes and refresh the list?')) setRefreshKey((current) => current + 1); }} disabled={saving} className="text-sm font-semibold text-slate-600 underline-offset-4 hover:underline disabled:opacity-50 dark:text-slate-300">Refresh</button>
              {!pendingCount && !saving && <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 dark:text-emerald-300"><CheckCircleIcon className="h-5 w-5" />All changes saved</span>}
              <button type="button" onClick={save} disabled={saving || !pendingCount} className="rounded-lg bg-[#7D001D] px-4 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 dark:disabled:bg-slate-700 dark:disabled:text-slate-400">
                {saving ? 'Saving...' : 'Save changes'}
              </button>
            </div>
          </div>

          {pendingCount > 0 && <p className="border-b border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-medium text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            {pendingCount} unsaved {pendingCount === 1 ? 'change' : 'changes'}. Save to update Coordinator access.
          </p>}

          {!data.coordinators.length && <p className="m-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">No active Coordinator account is assigned to {data.program?.code}. Ask the Administrator to add one to this program.</p>}

          {visibleSections.length > 1 && data.coordinators.length > 0 && <div className="border-b border-slate-200 px-5 py-3 dark:border-slate-700 sm:px-6">
            <button type="button" onClick={() => setBulkOpen((current) => !current)} aria-expanded={bulkOpen} className="text-sm font-semibold text-[#7D001D] underline-offset-4 hover:underline dark:text-rose-300">Assign Coordinator to Multiple Sections</button>
            {bulkOpen && <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
              <p className="text-sm text-slate-600 dark:text-slate-300">Select sections, choose a Coordinator, then save the changes.</p>
              <div className="mt-3 flex flex-wrap gap-3">
                {visibleSections.map((section) => <label key={section.id} className="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={bulkSectionIds.includes(section.id)} onChange={(event) => setBulkSectionIds((current) => event.target.checked ? [...current, section.id] : current.filter((id) => id !== section.id))} />
                  Section {section.name}
                </label>)}
              </div>
              <div className="mt-4 flex flex-wrap items-center gap-2">
                <select value={bulkCoordinatorId} onChange={(event) => setBulkCoordinatorId(event.target.value)} aria-label="Coordinator for selected sections" className="min-w-52 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                  <option value="">Select Coordinator</option>
                  {data.coordinators.map((coordinator) => <option key={coordinator.id} value={coordinator.id}>{coordinator.name} · {workloadCount(coordinator.id)} {workloadCount(coordinator.id) === 1 ? 'section' : 'sections'} assigned</option>)}
                </select>
                <button type="button" onClick={applyBulkAssignment} disabled={!bulkSectionIds.length || !bulkCoordinatorId} className="rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Apply selection</button>
              </div>
            </div>}
          </div>}

          {visibleSections.length >= 4 && <div className="flex flex-wrap gap-3 border-b border-slate-200 p-5 dark:border-slate-700 sm:px-6">
            <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search sections" aria-label="Search sections" className="min-w-48 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />
            <select value={assignmentFilter} onChange={(event) => setAssignmentFilter(event.target.value)} aria-label="Filter assignment status" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
              <option value="all">All sections</option>
              <option value="assigned">Assigned</option>
              <option value="unassigned">Unassigned</option>
            </select>
          </div>}

          {visibleSections.length ? listedSections.length ? <div className="divide-y divide-slate-200 dark:divide-slate-700">{listedSections.map((section) => (
            <div key={section.id} className="grid gap-4 p-5 sm:p-6 md:grid-cols-[minmax(0,1fr)_minmax(17rem,24rem)] md:items-center">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-base font-bold">Section {section.name}</p>
                  <span className={'rounded-full px-2.5 py-1 text-xs font-semibold ' + (choices[section.id] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200')}>
                    {choices[section.id] ? 'Assigned' : 'Unassigned'}
                  </span>
                </div>
                <p className="mt-1 text-sm text-slate-500">Year {section.year_level} · AY {section.academic_year}</p>
                <p className="mt-1 text-sm text-slate-500">{section.student_count} {section.student_count === 1 ? 'student' : 'students'}</p>
              </div>
              <div>
                <span className="mb-1.5 block text-sm font-medium">Coordinator</span>
                <span className="relative block">
                  <button type="button" aria-expanded={openSectionId === section.id} aria-haspopup="listbox" onClick={() => setOpenSectionId((current) => current === section.id ? null : section.id)} className="flex min-h-14 w-full items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#7D001D]/30 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    {choices[section.id]
                      ? <CoordinatorLabel coordinator={data.coordinators.find((coordinator) => String(coordinator.id) === String(choices[section.id]))} workloadCount={workloadCount(choices[section.id])} />
                      : <span className="text-slate-500">Select Coordinator</span>}
                    <span aria-hidden="true">▾</span>
                  </button>
                  {openSectionId === section.id && <span role="listbox" aria-label={'Coordinator for Section ' + section.name} className="absolute left-0 right-0 z-20 mt-1 block max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-600 dark:bg-slate-800">
                    <button type="button" role="option" aria-selected={!choices[section.id]} onClick={() => { setChoices((current) => ({ ...current, [section.id]: '' })); setOpenSectionId(null); }} className="block w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">Unassigned</button>
                    {data.coordinators.map((coordinator) => <button key={coordinator.id} type="button" role="option" aria-selected={String(choices[section.id]) === String(coordinator.id)} onClick={() => { setChoices((current) => ({ ...current, [section.id]: String(coordinator.id) })); setOpenSectionId(null); }} className="block w-full rounded-md px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-100 dark:text-white dark:hover:bg-slate-700"><CoordinatorLabel coordinator={coordinator} workloadCount={workloadCount(coordinator.id)} /></button>)}
                  </span>}
                </span>
              </div>
            </div>
          ))}</div> : <p className="p-6 text-sm text-slate-500">No sections match the current search or filter.</p> : <p className="p-6 text-sm text-slate-500">No active sections in this academic period yet. Ask the Administrator to create them in Academic Management.</p>}
        </section>
      )}
    </div>
  );
}
