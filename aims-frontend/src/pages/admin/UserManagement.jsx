import { useCallback, useEffect, useState } from 'react';
import {
  CheckCircleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  EyeIcon,
  EyeSlashIcon,
  KeyIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PlusIcon,
  PowerIcon,
  ShieldCheckIcon,
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

const roles = ['student', 'coordinator', 'program_head', 'vpaa', 'admin', 'supervisor'];
const staffRoles = ['coordinator', 'program_head', 'vpaa', 'supervisor'];
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
        email: item.email,
        role: item.role,
        college_id: item.college_id || '',
        program_id: item.program_id || '',
      }
    : {
        name: '',
        email: '',
        role: 'coordinator',
        password: '',
        college_id: '',
        program_id: '',
      });
  const [colleges, setColleges] = useState([]);
  const [saving, setSaving] = useState(false);
  const [showTemporaryPassword, setShowTemporaryPassword] = useState(false);
  const programScoped = programScopedRoles.includes(form.role);
  const hasStaffId = staffRoles.includes(form.role);
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
      college_id: programScopedRoles.includes(role) ? current.college_id : '',
      program_id: programScopedRoles.includes(role) ? current.program_id : '',
    }));
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);

    try {
      if (item) {
        const response = await api.put(`/admin/users/${item.id}`, form);
        toast.success(response.data.message || 'User account updated successfully.');
      } else {
        const response = await api.post('/admin/users', form);
        toast.success(response.data.message);
      }
      onSaved();
    } catch (error) {
      const errors = error.response?.data?.errors;
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
        className="max-h-[calc(100dvh-1rem)] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900 sm:max-h-[calc(100dvh-2rem)]"
      >
        <div className="flex items-start justify-between border-b border-slate-200 p-4 dark:border-slate-700 sm:p-6">
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

        <div className="space-y-5 p-4 sm:p-6">
          <div className="grid gap-5 sm:grid-cols-2">
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Full name</span>
              <input
                required
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
                className={inputClass}
              />
            </label>
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Email address</span>
              <input
                required
                type="email"
                value={form.email}
                onChange={(event) => setForm({ ...form, email: event.target.value })}
                className={inputClass}
              />
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

          {hasStaffId && (
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Staff ID</span>
              <input
                readOnly
                value={item?.role === form.role && item?.staff_id ? item.staff_id : 'Generated automatically after saving'}
                className={`${inputClass} bg-slate-50 font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300`}
              />
              <span className="mt-1 block text-xs text-slate-500">The Staff ID can be used instead of email when signing in.</span>
            </label>
          )}

          {programScoped && (
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
              <label className="block">
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
              </label>
              <p className="text-xs leading-5 text-[#6b4f00] dark:text-amber-200 sm:col-span-2">
                Coordinators and Program Heads can only access records assigned to their selected program.
              </p>
            </div>
          )}

          {!item && (
            <label className="block">
              <span className="mb-2 block text-sm font-bold">Temporary password</span>
              <div className="relative">
                <input
                  required
                  minLength="8"
                  type={showTemporaryPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={(event) => setForm({ ...form, password: event.target.value })}
                  className={`${inputClass} pr-12`}
                />
                <button
                  type="button"
                  onClick={() => setShowTemporaryPassword((visible) => !visible)}
                  className="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-[#7D001D] focus:outline-none focus:ring-2 focus:ring-[#7D001D]/30 dark:hover:bg-slate-800"
                  aria-label={showTemporaryPassword ? 'Hide temporary password' : 'Show temporary password'}
                  title={showTemporaryPassword ? 'Hide password' : 'Show password'}
                >
                  {showTemporaryPassword ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                </button>
              </div>
              <span className="mt-1 block text-xs text-slate-500">Use at least 8 characters. The user can change it from Profile.</span>
            </label>
          )}

          <div className="rounded-2xl bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-200">
            Student credentials should normally be created during student registration. Use Student Management for official OJT enrollment records.
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 p-6 dark:border-slate-700">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">
            Cancel
          </button>
          <button disabled={saving} className="rounded-2xl bg-[#7D001D] px-6 py-3 font-bold text-white disabled:opacity-50">
            {saving ? 'Saving...' : item ? 'Update account' : 'Create account'}
          </button>
        </div>
      </form>
    </div>
  );
}

function AccountActionModal({ user, onClose, onCompleted }) {
  const [busy, setBusy] = useState(false);
  const deactivating = user.is_active;
  const actionLabel = deactivating ? 'Deactivate account' : 'Activate account';

  const confirm = async () => {
    setBusy(true);

    try {
      const response = await api.put(`/admin/users/${user.id}/status`);
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
        <div className={`p-6 ${deactivating ? 'bg-amber-50 dark:bg-amber-950/30' : 'bg-emerald-50 dark:bg-emerald-950/30'}`}>
          <div className="flex items-start gap-4">
            <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-2xl ${deactivating ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'}`}>
              <PowerIcon className="h-6 w-6" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-black uppercase tracking-widest text-slate-500">Account access confirmation</p>
              <h2 id="account-action-title" className="mt-1 text-2xl font-black">{actionLabel}</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {deactivating
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
            className={`rounded-2xl px-5 py-3 font-black text-white disabled:cursor-not-allowed disabled:opacity-40 ${deactivating ? 'bg-amber-600' : 'bg-emerald-600'}`}
          >
            {busy ? 'Processing...' : actionLabel}
          </button>
        </div>
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

  const resetPassword = async (user) => {
    const password = window.prompt(`Enter a new temporary password for ${user.name} (minimum 8 characters):`) || '';
    if (!password) return;
    if (password.length < 8) {
      toast.error('Password must contain at least 8 characters.');
      return;
    }

    try {
      const response = await api.post(`/admin/users/${user.id}/reset-password`, { password });
      toast.success(response.data.message);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to reset password.');
    }
  };

  const active = users.filter((user) => user.is_active).length;
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

      <div className="grid gap-4 sm:grid-cols-3">
        <MetricCard label="Accounts shown" value={users.length} icon={UsersIcon} />
        <MetricCard label="Active access" value={active} icon={CheckCircleIcon} tone="green" />
        <MetricCard label="Staff accounts" value={staff} icon={UserGroupIcon} tone="blue" />
      </div>

      <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_15rem_9rem]">
          <label className="relative flex-1">
            <MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 outline-none focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-slate-700 dark:bg-slate-950"
              placeholder="Search name, email, or Staff ID..."
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
        <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div className="overflow-x-auto">
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
                            {user.staff_id && <p className="mt-0.5 font-mono text-xs font-bold text-[#7D001D] dark:text-red-300">{user.staff_id}</p>}
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
                        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black ${user.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'}`}>
                          {user.is_active ? <CheckCircleIcon className="h-4 w-4" /> : <XCircleIcon className="h-4 w-4" />}
                          {user.is_active ? 'Active' : 'Inactive'}
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
                            onClick={() => resetPassword(user)}
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
          onSaved={() => {
            setEditor(null);
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
    </div>
  );
}
