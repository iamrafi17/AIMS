import { useCallback, useEffect, useState } from 'react';
import {
  BellAlertIcon,
  CheckCircleIcon,
  ClockIcon,
  Cog6ToothIcon,
  LockClosedIcon,
  ServerStackIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { LoadingPanel, PageIntro } from '../../components/common/PortalUI';
import AcademicPeriodManager from '../../components/admin/AcademicPeriodManager';

const groupDetails = {
  general: { title: 'General Configuration', description: 'University identity and portal defaults.', icon: Cog6ToothIcon },
  internship: { title: 'Internship Defaults', description: 'Default values used for newly created internship records.', icon: ClockIcon },
  security: { title: 'Security & Access', description: 'Account and session security rules.', icon: LockClosedIcon },
  notifications: { title: 'Notifications & Alerts', description: 'Communication and automated warning behavior.', icon: BellAlertIcon },
};

function SettingInput({ setting, value, onChange }) {
  if (setting.type === 'boolean') {
    const checked = value === true || value === 1 || value === '1';
    return (
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={setting.label}
        onClick={() => onChange(checked ? '0' : '1')}
        className={`settings-switch relative shrink-0 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#7D001D]/20 ${checked ? 'bg-[#7D001D]' : 'bg-slate-300 dark:bg-slate-600'}`}
      >
        <span className={`absolute top-1 h-[18px] w-[18px] rounded-full bg-white transition-[left] ${checked ? 'left-[46px]' : 'left-[6px]'}`} />
      </button>
    );
  }
  return <input type={setting.type === 'integer' ? 'number' : 'text'} min="0" value={value ?? ''} onChange={(event) => onChange(event.target.value)} className="w-full max-w-sm rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700" />;
}

export default function AdminSystem() {
  const [groups, setGroups] = useState({});
  const [values, setValues] = useState({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [mobileGroup, setMobileGroup] = useState('general');

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      const response = await api.get('/admin/settings');
      setGroups(response.data.settings || {});
      const nextValues = {};
      Object.values(response.data.settings || {}).flat().forEach((setting) => { nextValues[setting.key] = setting.value; });
      setValues(nextValues);
      setDirty(false);
    } catch (error) {
      const message = error.response?.data?.message || 'Unable to load system settings.';
      setLoadError(message);
      toast.error(message);
    }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const change = (key, value) => { setValues((current) => ({ ...current, [key]: value })); setDirty(true); };
  const save = async () => {
    setSaving(true);
    try {
      const response = await api.put('/admin/settings', { settings: values });
      toast.success(response.data.message); setDirty(false);
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to save settings.'); }
    finally { setSaving(false); }
  };

  if (loading) return <LoadingPanel />;
  const groupNames = Object.keys(groups);
  const selectedMobileGroup = groups[mobileGroup] ? mobileGroup : groupNames[0];

  return (
    <div className="admin-system-settings space-y-6">
      <PageIntro eyebrow="System administration" title="System Settings" description="Control institution-wide defaults, internship behavior, security preferences, and notification rules from one protected workspace." icon={ServerStackIcon} actions={
        <button onClick={save} disabled={!dirty || saving} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#690016] transition disabled:cursor-not-allowed disabled:opacity-50"><CheckCircleIcon className="h-5 w-5" />{saving ? 'Saving...' : 'Save changes'}</button>
      } />

      <AcademicPeriodManager />

      {(loadError || groupNames.length === 0) && (
        <section role="status" className="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
          <h2 className="text-lg font-black">{loadError ? 'Unable to load settings' : 'No system settings available'}</h2>
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{loadError || 'The system configuration has not been initialized. Restore the default settings, then reload this page.'}</p>
          <button type="button" onClick={load} className="mt-4 rounded-2xl bg-[#7D001D] px-5 py-3 text-sm font-bold text-white">Reload settings</button>
        </section>
      )}

      {groupNames.length > 0 && <section className="touch-layout-only rounded-3xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:hidden">
        <label className="block">
          <span className="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500">Settings section</span>
          <select value={selectedMobileGroup} onChange={(event) => setMobileGroup(event.target.value)} className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950">
            {groupNames.map((group) => <option key={group} value={group}>{(groupDetails[group] || groupDetails.general).title}</option>)}
          </select>
        </label>
      </section>}

      <div className="grid gap-6 xl:grid-cols-2">
        {Object.entries(groups).map(([group, settings]) => {
          const details = groupDetails[group] || groupDetails.general;
          const Icon = details.icon;
          return (
            <section key={group} data-mobile-active={selectedMobileGroup === group ? 'true' : 'false'} className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
              <div className="flex items-start gap-4 border-b border-slate-100 pb-5 dark:border-slate-800">
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#7D001D]/10 text-[#7D001D] dark:bg-red-400/10 dark:text-red-300"><Icon className="h-6 w-6" /></div>
                <div><h2 className="text-xl font-black text-slate-900 dark:text-white">{details.title}</h2><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{details.description}</p></div>
              </div>
              <div className="divide-y divide-slate-100 dark:divide-slate-800">
                {settings.map((setting) => (
                  <div key={setting.key} className="flex flex-col gap-3 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="max-w-md"><p className="font-bold text-slate-800 dark:text-white">{setting.label}</p><p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{setting.description}</p></div>
                    <SettingInput setting={setting} value={values[setting.key]} onChange={(value) => change(setting.key, value)} />
                  </div>
                ))}
              </div>
            </section>
          );
        })}
      </div>
      <div className="flex items-start gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
        <LockClosedIcon className="mt-0.5 h-5 w-5 shrink-0" />
        <p>Every settings update is written to the audit trail with the administrator, time, network address, and changed values.</p>
      </div>
    </div>
  );
}
