import { useCallback, useEffect, useState } from 'react';
import {
  CheckCircleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClipboardDocumentIcon,
  EyeIcon,
  EyeSlashIcon,
  KeyIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PlusIcon,
  PowerIcon,
  ShieldCheckIcon,
  TrashIcon,
  UserGroupIcon,
  UsersIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import useAuth from '../../context/useAuth';
import UserAvatar from '../../components/UserAvatar';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const roles = ['student', 'coordinator', 'program_head', 'dean', 'vpaa', 'admin', 'supervisor'];
const staffRoles = ['admin', 'coordinator', 'program_head', 'dean', 'vpaa', 'supervisor'];
const programScopedRoles = ['coordinator', 'program_head'];
const pageSizeOptions = [10, 15, 25, 50];

function roleLabel(role = '') {
  return role.replaceAll('_', ' ');
}

function formatLastLogin(value) {
  if (!value) return 'Never';

  return new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function UserModal({ item, onClose, onSaved }) {
  const [form, setForm] = useState(item
    ? {
        name: item.name,
        email: item.email || '',
        role: item.role,
        college_id: item.college_id || '',
        program_id: item.program_id || '',
        supervisor_record_id: item.supervisor_profile?.id || '',
        is_active: item.is_active,
      }
    : {
        name: '',
        email: '',
        role: 'coordinator',
        college_id: '',
        program_id: '',
        supervisor_record_id: '',
        is_active: true,
      });
  const [colleges, setColleges] = useState([]);
  const [saving, setSaving] = useState(false);
  const [emailError, setEmailError] = useState('');
  const [supervisorSearch, setSupervisorSearch] = useState('');
  const [supervisorResults, setSupervisorResults] = useState([]);
  const [supervisorSearchBusy, setSupervisorSearchBusy] = useState(false);
  const [supervisorSearchComplete, setSupervisorSearchComplete] = useState(false);
  const [selectedSupervisor, setSelectedSupervisor] = useState(item?.supervisor_profile ? {
    id: item.supervisor_profile.id,
    full_name: item.supervisor_profile.full_name,
    department: item.supervisor_profile.department,
    hte: item.supervisor_profile.department?.hte,
    has_account: true,
    account: item,
  } : null);
  const programScoped = programScopedRoles.includes(form.role);
  const supervisorRole = form.role === 'supervisor';
  const hasUserId = staffRoles.includes(form.role);
  const programs = colleges.find((college) => String(college.id) === String(form.college_id))?.programs || [];

  useEffect(() => {
    api.get('/admin/academic')
      .then((response) => {
        const academic = response.data;
        setColleges((academic.colleges || []).map((college) => ({
          ...college,
          programs: (academic.programs || []).filter(
            (program) => String(program.college_id) === String(college.id),
          ),
        })));
      })
      .catch(() => toast.error('Unable to load academic assignments.'));
  }, []);

  const updateRole = (role) => {
    setForm((current) => ({
      ...current,
      role,
      college_id: (programScopedRoles.includes(role) || role === 'dean') ? current.college_id : '',
      program_id: programScopedRoles.includes(role) ? current.program_id : '',
      supervisor_record_id: role === 'supervisor' ? current.supervisor_record_id : '',
    }));
    if (role !== 'supervisor') setSelectedSupervisor(null);
  };

  const searchSupervisors = async () => {
    if (supervisorSearch.trim().length < 2) return toast.error('Enter at least two characters to search.');
    setSupervisorSearchBusy(true);
    try {
      const response = await api.get('/admin/supervisor-assignments', { params: { search: supervisorSearch.trim() } });
      setSupervisorResults(response.data.supervisors || []);
      setSupervisorSearchComplete(true);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to search supervisor assignments.');
    } finally {
      setSupervisorSearchBusy(false);
    }
  };

  const chooseSupervisor = (supervisor) => {
    if (supervisor.has_account && supervisor.account?.id !== item?.id) {
      return toast.error('Supervisor already has an account.');
    }
    setSelectedSupervisor(supervisor);
    setForm((current) => ({ ...current, name: supervisor.full_name, supervisor_record_id: supervisor.id }));
  };

  const submit = async (event) => {
    event.preventDefault();
    if (saving) return;
    setEmailError('');
    setSaving(true);
    const payload = { ...form, email: form.email.trim() };

    try {
      if (item) {
        const response = await api.put(`/admin/users/${item.id}`, payload);
        toast.success(response.data.message || 'User account updated successfully.');
      } else {
        const response = await api.post('/admin/users', payload);
        toast.success(response.data.message);
        onSaved({
          idLabel: 'Username',
          userId: response.data.user?.username || response.data.user?.email || String(response.data.user?.id),
          temporaryPassword: response.data.temporary_password,
          name: response.data.user?.name || form.name,
          email: response.data.user?.email,
        });
        return;
      }
      onSaved();
    } catch (error) {
      const errors = error.response?.data?.errors;
      setEmailError(errors?.email?.[0] || '');
      toast.error(
        errors
          ? Object.values(errors).flat()[0]
          : error.response?.data?.message || 'Unable to save user account.',
      );
    } finally {
      setSaving(false);
    }
  };

  const inputClass = 'w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4" onMouseDown={onClose}>
      <form
        role="dialog"
        aria-modal="true"
        aria-label={item ? 'Update user account' : 'Create staff account'}
        onSubmit={submit}
        onMouseDown={(event) => event.stopPropagation()}
        className="flex max-h-[calc(100dvh-1rem)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 sm:max-h-[calc(100dvh-2rem)]"
      >
        <div className="flex shrink-0 items-start justify-between border-b border-slate-200 p-4 dark:border-slate-700 sm:p-6">
          <div>
            <p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">
              {item ? 'Account information and access' : 'Program-scoped access control'}
            </p>
            <h2 className="mt-1 text-2xl font-black">
              {item ? 'Update user account' : 'Create staff account'}
            </h2>
            {item && <p className="mt-1 text-sm text-slate-500">Update the user, role, college, and program assignment.</p>}
          </div>
          <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close account form">
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        <div className="min-h-0 space-y-5 overflow-y-auto p-4 sm:p-6">
          <div className="grid gap-5 sm:grid-cols-2">
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Full name</span>
              <input
                required
                readOnly={supervisorRole}
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
                className={`${inputClass} ${supervisorRole ? 'bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : ''}`}
                placeholder={supervisorRole ? 'Select an existing supervisor below' : ''}
              />
            </label>
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Email address</span>
              <input
                required
                type="email"
                name="email"
                autoComplete="email"
                maxLength={255}
                value={form.email}
                onChange={(event) => {
                  setForm({ ...form, email: event.target.value });
                  setEmailError('');
                }}
                aria-invalid={Boolean(emailError)}
                aria-describedby="staff-email-help"
                className={`${inputClass} ${emailError ? 'border-red-500' : ''}`}
                placeholder="name@example.com"
              />
              <span id="staff-email-help" role={emailError ? 'alert' : undefined} className={`mt-1 block text-xs ${emailError ? 'text-red-600 dark:text-red-400' : 'text-slate-500'}`}>
                {emailError || 'Use a unique email address. This can also be used to sign in.'}
              </span>
            </label>
          </div>

          <label className="block">
            <span className="mb-2 block text-sm font-bold">System role</span>
            <select
              value={form.role}
              onChange={(event) => updateRole(event.target.value)}
              className={`${inputClass} capitalize`}
            >
              {roles.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
            </select>
          </label>

          {supervisorRole && (
            <section className="rounded-2xl border border-[#7D001D]/20 bg-[#7D001D]/5 p-4">
              <div><h3 className="font-black text-[#690016] dark:text-rose-200">Supervisor Assignment Search</h3><p className="mt-1 text-xs leading-5 text-slate-500">Search by HTE name, Department / Unit, or Supervisor name, then select the existing record.</p></div>
              {!item && <div className="mt-4 flex flex-col gap-2 sm:flex-row"><div className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={supervisorSearch} onChange={(event) => { setSupervisorSearch(event.target.value); setSupervisorSearchComplete(false); }} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); searchSupervisors(); } }} className={`${inputClass} pl-12`} placeholder="Search HTE, department, or supervisor" /></div><button type="button" disabled={supervisorSearchBusy} onClick={searchSupervisors} className="rounded-2xl bg-[#7D001D] px-5 py-3 text-sm font-black text-white disabled:opacity-50">{supervisorSearchBusy ? 'Searching...' : 'Search'}</button></div>}
              {!item && supervisorSearchComplete && <div className="mt-3 max-h-64 space-y-2 overflow-y-auto">{supervisorResults.length ? supervisorResults.map((supervisor) => <button key={supervisor.id} type="button" disabled={supervisor.has_account} onClick={() => chooseSupervisor(supervisor)} className={`w-full rounded-xl border p-3 text-left transition ${String(selectedSupervisor?.id) === String(supervisor.id) ? 'border-[#7D001D] bg-white shadow-sm dark:bg-slate-900' : 'border-slate-200 bg-white/70 hover:border-[#7D001D]/40 dark:border-slate-700 dark:bg-slate-900/70'} disabled:cursor-not-allowed disabled:opacity-60`}><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-800 dark:text-white">{supervisor.full_name}</p><p className="mt-1 text-xs text-slate-500">{supervisor.hte?.name}</p><p className="mt-0.5 text-xs font-bold text-[#7D001D] dark:text-rose-300">{supervisor.department?.department_name}</p></div><span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${supervisor.has_account ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>{supervisor.has_account ? 'Account already created' : 'Available'}</span></div></button>) : <p className="rounded-xl border border-dashed border-slate-300 py-6 text-center text-sm text-slate-500 dark:border-slate-700">No matching supervisor record found.</p>}</div>}
              {selectedSupervisor && <div className="mt-4 grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30 sm:grid-cols-2"><ReadOnlyAssignment label="Full Name" value={selectedSupervisor.full_name} /><ReadOnlyAssignment label="Role" value="Supervisor" /><ReadOnlyAssignment label="HTE Name" value={selectedSupervisor.hte?.name || ''} /><ReadOnlyAssignment label="Department / Unit" value={selectedSupervisor.department?.department_name || ''} /></div>}
            </section>
          )}

          {hasUserId && (
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Username</span>
              <input
                readOnly
                value={item?.role === form.role && item?.username ? item.username : 'Generated automatically after saving'}
                className={`${inputClass} bg-slate-50 font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300`}
              />
              <span className="mt-1 block text-xs text-slate-500">The Username can be used instead of email when signing in.</span>
            </label>
          )}

          {(programScoped || form.role === 'dean') && (
            <div className="grid gap-4 rounded-2xl border border-[#d4af37]/40 bg-[#d4af37]/10 p-4 sm:grid-cols-2">
              <label className="block">
                <span className="mb-2 block text-sm font-bold">Assigned college</span>
                <select
                  required
                  value={form.college_id}
                  onChange={(event) => setForm({ ...form, college_id: event.target.value, program_id: '' })}
                  className={inputClass}
                >
                  <option value="">Select college</option>
                  {colleges.map((college) => (
                    <option key={college.id} value={college.id}>{college.code} — {college.name}</option>
                  ))}
                </select>
              </label>
              {programScoped && <label className="block">
                <span className="mb-2 block text-sm font-bold">Assigned program</span>
                <select
                  required
                  disabled={!form.college_id}
                  value={form.program_id}
                  onChange={(event) => setForm({ ...form, program_id: event.target.value })}
                  className={inputClass}
                >
                  <option value="">Select program</option>
                  {programs.map((program) => (
                    <option key={program.id} value={program.id}>{program.code} — {program.name}</option>
                  ))}
                </select>
              </label>}
              <p className="text-xs leading-5 text-[#6b4f00] dark:text-amber-200 sm:col-span-2">
                {form.role === 'dean' ? 'Dean access includes every program in the selected college.' : 'Coordinators and Program Heads can only access records assigned to their selected program.'}
              </p>
            </div>
          )}

          {!item && (
            <div className="rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-blue-700 dark:bg-blue-950/30 dark:text-blue-200">
              The system will generate a secure temporary password after saving. Staff accounts start as <strong>Pending Profile</strong> and must confirm their email, provide their contact number and address, change their password, and set three security questions on first login.
            </div>
          )}

          {!item && <label className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 text-sm font-bold dark:border-slate-700"><input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} /> Account is active</label>}
        </div>

        <div className="flex shrink-0 justify-end gap-3 border-t border-slate-200 p-4 dark:border-slate-700 sm:p-6">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">
            Cancel
          </button>
          <button disabled={saving || (supervisorRole && !form.supervisor_record_id)} className="rounded-2xl bg-[#7D001D] px-6 py-3 font-bold text-white disabled:opacity-50">
            {saving ? 'Saving...' : item ? 'Update account' : 'Create account'}
          </button>
        </div>
      </form>
    </div>
  );
}

function ReadOnlyAssignment({ label, value }) {
  return <div><p className="text-[10px] font-black uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{label}</p><p className="mt-1 text-sm font-bold text-slate-800 dark:text-white">{value || 'Not available'}</p></div>;
}

function CreatedCredentialsModal({ credentials, onClose }) {
  const copy = async (value, label) => {
    try {
      await navigator.clipboard.writeText(value);
      toast.success(`${label} copied.`);
    } catch {
      toast.error(`Unable to copy the ${label.toLowerCase()}.`);
    }
  };

  const copyAll = () => copy(
    `${credentials.idLabel}: ${credentials.userId}${credentials.email ? `\nEmail: ${credentials.email}` : ''}\nTemporary password: ${credentials.temporaryPassword}`,
    'Credentials',
  );

  const credentialRow = (label, value) => (
    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
      <p className="text-xs font-black uppercase tracking-wider text-slate-500">{label}</p>
      <div className="mt-2 flex items-center gap-3">
        <p className="min-w-0 flex-1 break-all font-mono text-lg font-black text-[#690016] dark:text-red-300">{value}</p>
        <button type="button" onClick={() => copy(value, label)} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-[#7D001D]/30 hover:text-[#7D001D] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" aria-label={`Copy ${label}`} title={`Copy ${label}`}>
          <ClipboardDocumentIcon className="h-5 w-5" />
        </button>
      </div>
    </div>
  );

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
      <section role="dialog" aria-modal="true" aria-labelledby="created-credentials-title" className="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
        <div className="bg-emerald-50 p-6 dark:bg-emerald-950/30">
          <div className="flex items-start gap-4">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
              <CheckCircleIcon className="h-7 w-7" />
            </div>
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Account created successfully</p>
              <h2 id="created-credentials-title" className="mt-1 text-2xl font-black">Save sign-in credentials</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Give these credentials to {credentials.name}. The temporary password is shown only in this popup. On first login, staff must replace it, set security questions, and complete their personal information.</p>
            </div>
          </div>
        </div>
        <div className="space-y-4 p-6">
          {credentialRow(credentials.idLabel, credentials.userId)}
          {credentials.email && credentialRow('Email address', credentials.email)}
          {credentialRow('Temporary password', credentials.temporaryPassword)}
          <p className="rounded-2xl bg-amber-50 p-4 text-xs leading-5 text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Copy the credentials before closing this popup.</p>
        </div>
        <div className="flex flex-col-reverse gap-3 border-t border-slate-200 p-6 dark:border-slate-700 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Done</button>
          <button type="button" onClick={copyAll} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#7D001D] px-5 py-3 font-black text-white">
            <ClipboardDocumentIcon className="h-5 w-5" />
            Copy all credentials
          </button>
        </div>
      </section>
    </div>
  );
}

function AccountActionModal({ user, action, onClose, onCompleted }) {
  const [busy, setBusy] = useState(false);
  const deleting = action === 'delete';
  const deactivating = !deleting && user.is_active;
  const actionLabel = deleting ? 'Delete account' : deactivating ? 'Deactivate account' : 'Activate account';

  const confirm = async () => {
    setBusy(true);

    try {
      const response = deleting
        ? await api.delete(`/admin/users/${user.id}/permanent`)
        : await api.put(`/admin/users/${user.id}/status`);
      toast.success(response.data.message);
      onCompleted();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update this account.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}>
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="account-action-title"
        onMouseDown={(event) => event.stopPropagation()}
        className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
      >
        <div className={`p-6 ${deleting ? 'bg-rose-50 dark:bg-rose-950/30' : deactivating ? 'bg-amber-50 dark:bg-amber-950/30' : 'bg-emerald-50 dark:bg-emerald-950/30'}`}>
          <div className="flex items-start gap-4">
            <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-2xl ${deleting ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300' : deactivating ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'}`}>
              {deleting ? <TrashIcon className="h-6 w-6" /> : <PowerIcon className="h-6 w-6" />}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-black uppercase tracking-widest text-slate-500">{deleting ? 'Permanent delete confirmation' : 'Account access confirmation'}</p>
              <h2 id="account-action-title" className="mt-1 text-2xl font-black">{actionLabel}</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {deleting
                  ? 'This permanently deletes the account and cannot be undone. Accounts with connected historical records cannot be deleted.'
                  : deactivating
                  ? 'The user will no longer be able to sign in. Their account and related records will remain available.'
                  : 'The user will regain access and can sign in using their existing credentials.'}
              </p>
            </div>
            <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-500 hover:bg-white/70 dark:hover:bg-slate-800" aria-label="Close confirmation">
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="space-y-5 p-6">
          <div className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <UserAvatar user={user} className="h-11 w-11" />
            <div className="min-w-0">
              <p className="truncate font-black">{user.name}</p>
              <p className="truncate text-sm text-slate-500">{user.email}</p>
              <p className="mt-0.5 text-xs font-bold capitalize text-[#7D001D] dark:text-red-300">{roleLabel(user.role)}</p>
            </div>
          </div>

        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 p-6 dark:border-slate-700">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">
            Cancel
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={confirm}
            className={`rounded-2xl px-5 py-3 font-black text-white disabled:cursor-not-allowed disabled:opacity-40 ${deleting ? 'bg-rose-700' : deactivating ? 'bg-amber-600' : 'bg-emerald-600'}`}
          >
            {busy ? 'Processing...' : actionLabel}
          </button>
        </div>
      </section>
    </div>
  );
}

function ResetPasswordModal({ user, onClose, onCompleted }) {
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);

  const submit = async (event) => {
    event.preventDefault();

    if (password.length < 8) {
      toast.error('Password must contain at least 8 characters.');
      return;
    }

    if (password !== passwordConfirmation) {
      toast.error('Password confirmation does not match.');
      return;
    }

    setBusy(true);
    try {
      const response = await api.post(`/admin/users/${user.id}/reset-password`, { password });
      toast.success(response.data.message);
      onCompleted();
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'Unable to reset password.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] grid place-items-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={busy ? undefined : onClose}>
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="reset-password-title"
        onMouseDown={(event) => event.stopPropagation()}
        className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
      >
        <div className="border-b border-slate-200 bg-amber-50 p-6 dark:border-slate-700 dark:bg-amber-950/30">
          <div className="flex items-start gap-4">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
              <KeyIcon className="h-6 w-6" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Account security</p>
              <h2 id="reset-password-title" className="mt-1 text-2xl font-black">Reset Password</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Create a new temporary password for this account.</p>
            </div>
            <button type="button" onClick={onClose} disabled={busy} className="rounded-xl p-2 text-slate-500 transition hover:bg-white/70 disabled:opacity-40 dark:hover:bg-slate-800" aria-label="Close reset password popup">
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <form onSubmit={submit}>
          <div className="space-y-5 p-6">
            <div className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
              <UserAvatar user={user} className="h-11 w-11" />
              <div className="min-w-0">
                <p className="truncate font-black">{user.name}</p>
                <p className="truncate text-sm text-slate-500">{user.username || user.email}</p>
                <p className="mt-0.5 text-xs font-bold text-[#7D001D] dark:text-red-300">{roleLabel(user.role)}</p>
              </div>
            </div>

            <label className="block">
              <span className="mb-2 block text-sm font-black text-slate-700 dark:text-slate-200">New temporary password</span>
              <div className="relative">
                <KeyIcon className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-amber-600" />
                <input
                  autoFocus
                  required
                  minLength="8"
                  maxLength="255"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-12 pr-12 outline-none transition focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
                  placeholder="Minimum 8 characters"
                />
                <button type="button" onClick={() => setShowPassword((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-[#7D001D]" aria-label={showPassword ? 'Hide passwords' : 'Show passwords'}>
                  {showPassword ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                </button>
              </div>
            </label>

            <label className="block">
              <span className="mb-2 block text-sm font-black text-slate-700 dark:text-slate-200">Confirm temporary password</span>
              <div className="relative">
                <KeyIcon className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-amber-600" />
                <input
                  required
                  minLength="8"
                  maxLength="255"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  className="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-12 pr-4 outline-none transition focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
                  placeholder="Re-enter the password"
                />
              </div>
            </label>

            <p className="rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-500 dark:bg-slate-950 dark:text-slate-400">The user’s existing login sessions will be signed out after the reset.</p>
          </div>

          <div className="flex flex-col-reverse gap-3 border-t border-slate-200 p-6 dark:border-slate-700 sm:flex-row sm:justify-end">
            <button type="button" onClick={onClose} disabled={busy} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold disabled:opacity-40 dark:border-slate-700">Cancel</button>
            <button type="submit" disabled={busy} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#7D001D] px-5 py-3 font-black text-white transition hover:bg-[#690016] disabled:cursor-not-allowed disabled:opacity-40">
              <KeyIcon className="h-5 w-5" />
              {busy ? 'Resetting...' : 'Reset Password'}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}

export default function AdminUsers() {
  const { user: authenticatedUser } = useAuth();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [college, setCollege] = useState('');
  const [colleges, setColleges] = useState([]);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 });
  const [editor, setEditor] = useState(null);
  const [accountAction, setAccountAction] = useState(null);
  const [createdCredentials, setCreatedCredentials] = useState(null);
  const [passwordResetUser, setPasswordResetUser] = useState(null);

  useEffect(() => {
    api.get('/admin/academic')
      .then((response) => setColleges(response.data.colleges || []))
      .catch(() => toast.error('Unable to load colleges.'));
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/admin/users', {
        params: {
          search: search || undefined,
          role: role || undefined,
          college_id: college || undefined,
          page,
          per_page: perPage,
        },
      });
      setUsers(response.data.data || []);
      setPagination({
        currentPage: response.data.current_page || 1,
        lastPage: response.data.last_page || 1,
        total: response.data.total || 0,
        from: response.data.from || 0,
        to: response.data.to || 0,
      });
    } catch {
      toast.error('Unable to load user accounts.');
    } finally {
      setLoading(false);
    }
  }, [college, page, perPage, role, search]);

  useEffect(() => {
    const timer = setTimeout(load, 250);
    return () => clearTimeout(timer);
  }, [load]);

  const isPendingProfile = (user) => Boolean(
    user.requires_password_change || user.requires_security_setup || user.requires_profile_setup
  );
  const active = users.filter((user) => user.is_active && !isPendingProfile(user)).length;
  const staff = users.filter((user) => user.role !== 'student').length;

  return (
    <div className="space-y-6">
      <PageIntro
        eyebrow="Identity & access management"
        title="User Accounts & Roles"
        description="Review user, role, college, program, status, and sign-in details, then manage account access through confirmed actions."
        icon={ShieldCheckIcon}
        actions={(
          <button
            type="button"
            onClick={() => setEditor({ item: null })}
            className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#690016]"
          >
            <PlusIcon className="h-5 w-5" />
            Create account
          </button>
        )}
      />

      <div className="portal-metric-grid grid gap-4 sm:grid-cols-3">
        <MetricCard label="Accounts shown" value={users.length} icon={UsersIcon} />
        <MetricCard label="Active access" value={active} icon={CheckCircleIcon} tone="green" />
        <MetricCard label="Staff accounts" value={staff} icon={UserGroupIcon} tone="blue" />
      </div>

      <section className="portal-filter-panel rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="portal-filter-grid portal-filter-grid--four grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_15rem_9rem]">
          <label className="portal-filter-search relative flex-1">
            <MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
              placeholder="Search name, email, or Username..."
            />
          </label>
          <select
            value={role}
            onChange={(event) => {
              setRole(event.target.value);
              setPage(1);
            }}
            aria-label="Filter by role"
            className="rounded-2xl border border-slate-200 px-4 py-3 capitalize outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950 md:min-w-48"
          >
            <option value="">All roles</option>
            {roles.map((name) => <option key={name} value={name}>{roleLabel(name)}</option>)}
          </select>
          <select
            value={college}
            onChange={(event) => {
              setCollege(event.target.value);
              setPage(1);
            }}
            aria-label="Filter by college"
            className="rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
          >
            <option value="">All colleges</option>
            {colleges.map((item) => <option key={item.id} value={item.id}>{item.code} - {item.name}</option>)}
          </select>
          <select
            value={perPage}
            onChange={(event) => {
              setPerPage(Number(event.target.value));
              setPage(1);
            }}
            aria-label="Rows per page"
            className="rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
          >
            {pageSizeOptions.map((size) => <option key={size} value={size}>{size} per page</option>)}
          </select>
        </div>
      </section>

      {loading ? (
        <LoadingPanel />
      ) : users.length === 0 ? (
        <EmptyPanel icon={UsersIcon} title="No user accounts found" description="Create a staff account or change the current filters." />
      ) : (
        <section className="portal-table-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div className="touch-layout-only divide-y divide-slate-100 dark:divide-slate-800 lg:hidden">
            {users.map((user) => {
              const isCurrentAccount = user.id === authenticatedUser?.id;
              const pendingProfile = isPendingProfile(user);
              const assignedCollege = user.college || user.student?.college;
              const assignedProgram = user.program || user.student?.program;

              return (
                <details key={user.id} className="mobile-account-card px-4 py-3">
                  <summary className="mobile-account-summary flex cursor-pointer items-start gap-3">
                    <UserAvatar user={user} className="h-11 w-11 shrink-0" />
                    <div className="min-w-0 flex-1">
                      <div className="flex min-w-0 items-center gap-2">
                        <h2 className="truncate text-sm font-black text-slate-900 dark:text-white">{user.name}</h2>
                        {isCurrentAccount && <span className="shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-black text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">You</span>}
                      </div>
                      <p className="truncate text-xs text-slate-500">{user.email}</p>
                      {user.username && <p className="mt-0.5 font-mono text-[11px] font-bold text-[#7D001D] dark:text-red-300">{user.username}</p>}
                      <p className="mt-0.5 text-[10px] font-bold capitalize text-slate-400">{roleLabel(user.role)} · Tap for details</p>
                    </div>
                    <span className={`inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-[10px] font-black ${!user.is_active ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200' : pendingProfile ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'}`}>
                      {!user.is_active ? <XCircleIcon className="h-3.5 w-3.5" /> : <CheckCircleIcon className="h-3.5 w-3.5" />}
                      {!user.is_active ? 'Inactive' : pendingProfile ? 'Pending' : 'Active'}
                    </span>
                  </summary>

                  <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 rounded-2xl bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                    <div className="min-w-0">
                      <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Role</p>
                      <p className="mt-0.5 truncate font-bold capitalize text-slate-700 dark:text-slate-200">{roleLabel(user.role)}</p>
                    </div>
                    <div className="min-w-0">
                      <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Last login</p>
                      <p className="mt-0.5 truncate font-bold text-slate-700 dark:text-slate-200">{formatLastLogin(user.last_login_at)}</p>
                    </div>
                    <div className="min-w-0">
                      <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">College</p>
                      <p className="mt-0.5 truncate font-bold text-slate-700 dark:text-slate-200">{assignedCollege ? `${assignedCollege.code} - ${assignedCollege.name}` : 'Not assigned'}</p>
                    </div>
                    <div className="min-w-0">
                      <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Program</p>
                      <p className="mt-0.5 truncate font-bold text-slate-700 dark:text-slate-200">{assignedProgram ? `${assignedProgram.code} - ${assignedProgram.name}` : 'Not assigned'}</p>
                    </div>
                  </div>

                  <div className="mt-3 grid grid-cols-4 gap-2">
                    <button
                      type="button"
                      onClick={() => setEditor({ item: user })}
                      className="inline-flex min-h-10 items-center justify-center gap-1 rounded-xl border border-slate-200 px-2 text-xs font-black text-slate-600 dark:border-slate-700 dark:text-slate-300"
                      aria-label={`Edit ${user.name}`}
                    >
                      <PencilSquareIcon className="h-4 w-4" />
                      Edit
                    </button>
                    <button type="button" onClick={() => setPasswordResetUser(user)} className="grid min-h-10 place-items-center rounded-xl border border-amber-200 text-amber-700 dark:border-amber-900 dark:text-amber-300" title="Reset password" aria-label={`Reset password for ${user.name}`}><KeyIcon className="h-4 w-4" /></button>
                    <button type="button" disabled={isCurrentAccount} onClick={() => setAccountAction({ user, action: 'status' })} className={`grid min-h-10 place-items-center rounded-xl border disabled:cursor-not-allowed disabled:opacity-35 ${user.is_active ? 'border-amber-200 text-amber-700 dark:border-amber-900 dark:text-amber-300' : 'border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-300'}`} title={isCurrentAccount ? 'You cannot change the status of your current account' : user.is_active ? 'Deactivate user' : 'Activate user'} aria-label={`${user.is_active ? 'Deactivate' : 'Activate'} ${user.name}`}><PowerIcon className="h-4 w-4" /></button>
                    <button type="button" disabled={isCurrentAccount} onClick={() => setAccountAction({ user, action: 'delete' })} className="grid min-h-10 place-items-center rounded-xl border border-rose-200 text-rose-700 disabled:cursor-not-allowed disabled:opacity-35 dark:border-rose-900 dark:text-rose-300" title={isCurrentAccount ? 'You cannot delete your current account' : 'Permanently delete user'} aria-label={`Permanently delete ${user.name}`}><TrashIcon className="h-4 w-4" /></button>
                  </div>
                </details>
              );
            })}
          </div>

          <div className="desktop-layout-only hidden overflow-x-auto lg:block">
            <table className="w-full min-w-[1280px]">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800">
                <tr>
                  <th className="px-5 py-4">User</th>
                  <th className="px-5 py-4">Role</th>
                  <th className="px-5 py-4">College</th>
                  <th className="px-5 py-4">Program</th>
                  <th className="px-5 py-4">Status</th>
                  <th className="px-5 py-4">Last login</th>
                  <th className="px-5 py-4 text-right">Account actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {users.map((user) => {
                  const isCurrentAccount = user.id === authenticatedUser?.id;
                  const pendingProfile = isPendingProfile(user);
                  const assignedCollege = user.college || user.student?.college;
                  const assignedProgram = user.program || user.student?.program;

                  return (
                    <tr key={user.id} className="align-middle transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <UserAvatar user={user} className="h-10 w-10" />
                          <div className="min-w-0">
                            <div className="flex items-center gap-2">
                              <p className="max-w-56 truncate font-black">{user.name}</p>
                              {isCurrentAccount && <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-black text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">You</span>}
                            </div>
                            <p className="max-w-60 truncate text-xs text-slate-500">{user.email}</p>
                            {user.username && <p className="mt-0.5 font-mono text-xs font-bold text-[#7D001D] dark:text-red-300">{user.username}</p>}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <span className="inline-flex whitespace-nowrap rounded-full bg-[#7D001D]/10 px-3 py-1 text-xs font-black capitalize text-[#7D001D] dark:text-red-300">
                          {roleLabel(user.role)}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        {assignedCollege ? (
                          <div>
                            <p className="text-sm font-black">{assignedCollege.code}</p>
                            <p className="mt-0.5 max-w-44 text-xs leading-4 text-slate-500">{assignedCollege.name}</p>
                          </div>
                        ) : <span className="text-sm text-slate-400">Not assigned</span>}
                      </td>
                      <td className="px-5 py-4">
                        {assignedProgram ? (
                          <div>
                            <p className="text-sm font-black">{assignedProgram.code}</p>
                            <p className="mt-0.5 max-w-44 text-xs leading-4 text-slate-500">{assignedProgram.name}</p>
                          </div>
                        ) : <span className="text-sm text-slate-400">Not assigned</span>}
                      </td>
                      <td className="px-5 py-4">
                        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black ${!user.is_active ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200' : pendingProfile ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'}`}>
                          {!user.is_active ? <XCircleIcon className="h-4 w-4" /> : <CheckCircleIcon className="h-4 w-4" />}
                          {!user.is_active ? 'Inactive' : pendingProfile ? 'Pending Profile' : 'Active'}
                        </span>
                      </td>
                      <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                        {formatLastLogin(user.last_login_at)}
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => setEditor({ item: user })}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 transition hover:border-[#7D001D]/30 hover:bg-[#7D001D]/5 hover:text-[#7D001D] dark:border-slate-700 dark:text-slate-300"
                            title="Edit and update user"
                          >
                            <PencilSquareIcon className="h-4 w-4" />
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => setPasswordResetUser(user)}
                            className="rounded-xl border border-slate-200 p-2 text-amber-700 transition hover:bg-amber-50 dark:border-slate-700 dark:text-amber-300 dark:hover:bg-amber-950/30"
                            title="Reset password"
                            aria-label={`Reset password for ${user.name}`}
                          >
                            <KeyIcon className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            disabled={isCurrentAccount}
                            onClick={() => setAccountAction({ user, action: 'status' })}
                            className={`rounded-xl border p-2 transition disabled:cursor-not-allowed disabled:opacity-35 ${user.is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/30' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/30'}`}
                            title={isCurrentAccount ? 'You cannot change the status of your current account' : user.is_active ? 'Deactivate user' : 'Activate user'}
                            aria-label={`${user.is_active ? 'Deactivate' : 'Activate'} ${user.name}`}
                          >
                            <PowerIcon className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            disabled={isCurrentAccount}
                            onClick={() => setAccountAction({ user, action: 'delete' })}
                            className="rounded-xl border border-rose-200 p-2 text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                            title={isCurrentAccount ? 'You cannot delete your current account' : 'Permanently delete user'}
                            aria-label={`Permanently delete ${user.name}`}
                          >
                            <TrashIcon className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <div className="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-sm dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-slate-500">Showing {pagination.from}-{pagination.to} of {pagination.total} accounts</p>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={pagination.currentPage <= 1}
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Previous page"
                title="Previous page"
              >
                <ChevronLeftIcon className="h-5 w-5" />
              </button>
              <span className="whitespace-nowrap text-xs font-bold text-slate-600 dark:text-slate-300">Page {pagination.currentPage} of {pagination.lastPage}</span>
              <button
                type="button"
                onClick={() => setPage((current) => Math.min(pagination.lastPage, current + 1))}
                disabled={pagination.currentPage >= pagination.lastPage}
                className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Next page"
                title="Next page"
              >
                <ChevronRightIcon className="h-5 w-5" />
              </button>
            </div>
          </div>
        </section>
      )}

      {editor && (
        <UserModal
          item={editor.item}
          onClose={() => setEditor(null)}
          onSaved={(credentials) => {
            setEditor(null);
            if (credentials) setCreatedCredentials(credentials);
            load();
          }}
        />
      )}

      {accountAction && (
        <AccountActionModal
          user={accountAction.user}
          action={accountAction.action}
          onClose={() => setAccountAction(null)}
          onCompleted={() => {
            setAccountAction(null);
            load();
          }}
        />
      )}

      {createdCredentials && (
        <CreatedCredentialsModal
          credentials={createdCredentials}
          onClose={() => setCreatedCredentials(null)}
        />
      )}

      {passwordResetUser && (
        <ResetPasswordModal
          user={passwordResetUser}
          onClose={() => setPasswordResetUser(null)}
          onCompleted={() => {
            setPasswordResetUser(null);
            load();
          }}
        />
      )}
    </div>
  );
}
