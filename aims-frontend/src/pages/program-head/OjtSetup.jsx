import { useCallback, useEffect, useState } from 'react';
import { AcademicCapIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { PageIntro } from '../../components/common/PortalUI';

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-[#7D001D] dark:border-slate-700 dark:bg-slate-900 dark:text-white';

export default function OjtSetup() {
  const [workspace, setWorkspace] = useState({ program: null, periods: [], settings: [] });
  const [periodId, setPeriodId] = useState('');
  const [hours, setHours] = useState('');
  const [startDate, setStartDate] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/program-head/ojt-settings');
      setWorkspace(data);
      setPeriodId((current) => current || String(data.periods.find((period) => period.status === 'active')?.id || ''));
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load OJT settings.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const selectedPeriod = workspace.periods.find((period) => String(period.id) === periodId);
  const currentSetting = workspace.settings.find((setting) => String(setting.academic_period_id) === periodId);

  useEffect(() => {
    setHours(currentSetting ? String(currentSetting.required_hours) : '');
    setStartDate(currentSetting?.ojt_start_date?.slice(0, 10) || '');
  }, [currentSetting?.id, currentSetting?.updated_at, periodId]);

  const publish = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const { data } = await api.post('/program-head/ojt-settings/publish', {
        academic_period_id: Number(periodId),
        required_hours: Number(hours),
        ojt_start_date: startDate,
      });
      toast.success(data.message);
      await load();
    } catch (error) {
      toast.error(Object.values(error.response?.data?.errors || {}).flat()[0]
        || error.response?.data?.message || 'Unable to publish OJT setup.');
    } finally {
      setSaving(false);
    }
  };

  return <div className="space-y-6">
    <PageIntro eyebrow="Program management" title="OJT Setup" description="Publish required hours and the OJT start date for your program and academic period." icon={AcademicCapIcon} />
    <form onSubmit={publish} className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      {loading ? <p className="text-sm text-slate-500">Loading OJT setup...</p> : <>
        <div className="grid gap-5 sm:grid-cols-2">
          <label className="block text-sm font-bold">Program
            <input className={`${inputClass} mt-2 bg-slate-50 dark:bg-slate-900`} value={workspace.program ? `${workspace.program.code} · ${workspace.program.name}` : ''} readOnly />
          </label>
          <label className="block text-sm font-bold">Academic Period
            <select className={`${inputClass} mt-2`} value={periodId} onChange={(event) => setPeriodId(event.target.value)} required>
              <option value="">Select academic period</option>
              {workspace.periods.map((period) => <option key={period.id} value={period.id}>{period.academic_year} · {period.semester} ({period.status})</option>)}
            </select>
          </label>
          <label className="block text-sm font-bold">Required OJT Hours
            <input className={`${inputClass} mt-2`} type="number" min="1" max="2000" step="0.5" value={hours} onChange={(event) => setHours(event.target.value)} disabled={selectedPeriod?.status === 'completed'} required />
          </label>
          <label className="block text-sm font-bold">OJT Start Date
            <input className={`${inputClass} mt-2`} type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} disabled={selectedPeriod?.status === 'completed'} required />
          </label>
        </div>
        <p className="mt-5 text-sm text-slate-600 dark:text-slate-300">
          {currentSetting ? `Published on ${new Date(currentSetting.published_at).toLocaleString('en-PH')}. Changes are blocked once a student deployment uses this setup.` : 'Publishing makes these values available to Coordinator deployment. OJT end dates remain per student.'}
        </p>
        {selectedPeriod?.publish_block_reason && <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">{selectedPeriod.publish_block_reason}</p>}
        <button type="submit" disabled={saving || !periodId || Boolean(selectedPeriod?.publish_block_reason)} className="mt-5 rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Publishing...' : currentSetting ? 'Update Published Setup' : 'Publish OJT Setup'}</button>
      </>}
    </form>
  </div>;
}
