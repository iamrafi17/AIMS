import { useCallback, useEffect, useState } from 'react';
import {
  CheckCircleIcon,
  KeyIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PlusIcon,
  ShieldCheckIcon,
  UserGroupIcon,
  UsersIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import UserAvatar from '../../components/UserAvatar';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const roles = ['student', 'coordinator', 'program_head', 'vpaa', 'admin', 'supervisor'];
const staffRoles = ['coordinator', 'program_head', 'vpaa', 'supervisor'];

function UserModal({ item, onClose, onSaved }) {
  const [form, setForm] = useState(item
    ? { name: item.name, email: item.email, role: item.role, password: '', college_id: item.college_id || '', program_id: item.program_id || '' }
    : { name: '', email: '', role: 'coordinator', password: '', college_id: '', program_id: '' });
  const [colleges, setColleges] = useState([]);
  const [saving, setSaving] = useState(false);
  const programScoped = ['coordinator', 'program_head'].includes(form.role);
  const hasStaffId = staffRoles.includes(form.role);
  const programs = colleges.find((college) => String(college.id) === String(form.college_id))?.programs || [];

  useEffect(() => {
    api.get('/admin/academic')
      .then((response) => {
        const academic = response.data;
        setColleges((academic.colleges || []).map((college) => ({
          ...college,
          programs: (academic.programs || []).filter((program) => String(program.college_id) === String(college.id)),
        })));
      })
      .catch(() => toast.error('Unable to load academic assignments.'));
  }, []);

  const updateRole = (role) => {
    setForm((current) => ({
      ...current,
      role,
      college_id: ['coordinator', 'program_head'].includes(role) ? current.college_id : '',
      program_id: ['coordinator', 'program_head'].includes(role) ? current.program_id : '',
    }));
  };

  const submit = async (event) => {
    event.preventDefault(); setSaving(true);
    try {
      if (item) {
        await api.put(`/admin/users/${item.id}`, form);
        toast.success('User account updated successfully.');
      } else {
        const response = await api.post('/admin/users', form); toast.success(response.data.message);
      }
      onSaved();
    } catch (error) { const errors = error.response?.data?.errors; toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'Unable to save user account.'); }
    finally { setSaving(false); }
  };
  const inputClass = 'w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700';

  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}>
    <form onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} className="max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
      <div className="flex items-start justify-between border-b border-slate-200 p-6 dark:border-slate-700">
        <div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Program-scoped access control</p><h2 className="mt-1 text-2xl font-black">{item ? 'Edit user account' : 'Create staff account'}</h2></div>
        <button type="button" onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button>
      </div>
      <div className="space-y-4 p-6">
        <label className="block"><span className="mb-2 block text-sm font-bold">Full name</span><input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className={inputClass} /></label>
        <label className="block"><span className="mb-2 block text-sm font-bold">Email address</span><input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} className={inputClass} /></label>
        {hasStaffId && <label className="block"><span className="mb-2 block text-sm font-bold">Staff ID</span><input readOnly value={item?.role === form.role && item?.staff_id ? item.staff_id : 'Generated automatically after saving'} className={`${inputClass} bg-slate-50 font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300`} /><span className="mt-1 block text-xs text-slate-500">This unique ID can be used instead of the email address when logging in.</span></label>}
        <label className="block"><span className="mb-2 block text-sm font-bold">System role</span><select value={form.role} onChange={(event) => updateRole(event.target.value)} className={`${inputClass} capitalize`}>{roles.map((role) => <option key={role} value={role}>{role.replace('_', ' ')}</option>)}</select></label>
        {programScoped && <div className="grid gap-4 rounded-2xl border border-[#d4af37]/40 bg-[#d4af37]/10 p-4 sm:grid-cols-2">
          <label className="block"><span className="mb-2 block text-sm font-bold">Assigned college</span><select required value={form.college_id} onChange={(event) => setForm({ ...form, college_id: event.target.value, program_id: '' })} className={inputClass}><option value="">Select college</option>{colleges.map((college) => <option key={college.id} value={college.id}>{college.code} — {college.name}</option>)}</select></label>
          <label className="block"><span className="mb-2 block text-sm font-bold">Assigned program</span><select required disabled={!form.college_id} value={form.program_id} onChange={(event) => setForm({ ...form, program_id: event.target.value })} className={inputClass}><option value="">Select program</option>{programs.map((program) => <option key={program.id} value={program.id}>{program.code} — {program.name}</option>)}</select></label>
          <p className="text-xs leading-5 text-[#6b4f00] dark:text-amber-200 sm:col-span-2">This account can only view and manage students and records assigned to this program.</p>
        </div>}
        {!item && <label className="block"><span className="mb-2 block text-sm font-bold">Temporary password</span><input required minLength="8" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} className={inputClass} /><span className="mt-1 block text-xs text-slate-500">At least 8 characters. The user can change it from Profile.</span></label>}
        <div className="rounded-2xl bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-200">Student credentials should normally be created by students during registration. Use Student Management to enroll official OJT students.</div>
      </div>
      <div className="flex justify-end gap-3 border-t border-slate-200 p-6 dark:border-slate-700"><button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button disabled={saving} className="rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white disabled:opacity-50">{saving ? 'Saving...' : 'Save account'}</button></div>
    </form>
  </div>;
}

export default function AdminUsers() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [modal, setModal] = useState(undefined);
  const load = useCallback(async () => {
    setLoading(true);
    try { setUsers((await api.get('/admin/users', { params: { search: search || undefined, role: role || undefined } })).data.data || []); }
    catch { toast.error('Unable to load user accounts.'); }
    finally { setLoading(false); }
  }, [role, search]);
  useEffect(() => { const timer = setTimeout(load, 250); return () => clearTimeout(timer); }, [load]);
  const toggle = async (user) => { try { const response = await api.put(`/admin/users/${user.id}/status`); toast.success(response.data.message); load(); } catch { toast.error('Unable to change account status.'); } };
  const resetPassword = async (user) => {
    const password = window.prompt(`Enter a new temporary password for ${user.name} (minimum 8 characters):`) || '';
    if (!password) return; if (password.length < 8) return toast.error('Password must contain at least 8 characters.');
    try { const response = await api.post(`/admin/users/${user.id}/reset-password`, { password }); toast.success(response.data.message); } catch (error) { toast.error(error.response?.data?.message || 'Unable to reset password.'); }
  };
  const active = users.filter((user) => user.is_active).length;
  const staff = users.filter((user) => user.role !== 'student').length;
  return <div className="space-y-6"><PageIntro eyebrow="Identity & access management" title="User Accounts & Roles" description="Create staff accounts, update identity and roles, activate or deactivate access, and issue controlled password resets." icon={ShieldCheckIcon} actions={<button onClick={() => setModal(null)} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#430909]"><PlusIcon className="h-5 w-5" />Create account</button>} /><div className="grid gap-4 sm:grid-cols-3"><MetricCard label="Accounts shown" value={users.length} icon={UsersIcon} /><MetricCard label="Active access" value={active} icon={CheckCircleIcon} tone="green" /><MetricCard label="Staff accounts" value={staff} icon={UserGroupIcon} tone="blue" /></div><section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="flex flex-col gap-3 md:flex-row"><label className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" placeholder="Search name, email, or Staff ID..." /></label><select value={role} onChange={(event) => setRole(event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 capitalize dark:border-slate-700 md:min-w-48"><option value="">All roles</option>{roles.map((name) => <option key={name} value={name}>{name.replace('_', ' ')}</option>)}</select></div></section>{loading ? <LoadingPanel /> : users.length === 0 ? <EmptyPanel icon={UsersIcon} title="No user accounts found" description="Create a staff account or change the current filters." /> : <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="overflow-x-auto"><table className="w-full min-w-[900px]"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800"><tr><th className="px-6 py-4">User</th><th className="px-6 py-4">Role</th><th className="px-6 py-4">Status</th><th className="px-6 py-4">Last login</th><th className="px-6 py-4 text-right">Account actions</th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{users.map((user) => <tr key={user.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td className="px-6 py-4"><div className="flex items-center gap-3"><UserAvatar user={user} className="h-10 w-10" /><div><p className="font-black">{user.name}</p><p className="text-xs text-slate-500">{user.email}</p>{user.staff_id && <p className="mt-0.5 font-mono text-xs font-bold text-[#800000] dark:text-red-300">{user.staff_id}</p>}</div></div></td><td className="px-6 py-4"><span className="rounded-full bg-[#800000]/10 px-3 py-1 text-xs font-black capitalize text-[#800000] dark:text-red-300">{user.role.replace('_', ' ')}</span></td><td className="px-6 py-4"><button onClick={() => toggle(user)} className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black ${user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>{user.is_active ? <CheckCircleIcon className="h-4 w-4" /> : <XCircleIcon className="h-4 w-4" />}{user.is_active ? 'Active' : 'Inactive'}</button></td><td className="px-6 py-4 text-sm text-slate-500">{user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never'}</td><td className="px-6 py-4"><div className="flex justify-end gap-2"><button onClick={() => setModal(user)} className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:text-[#800000] dark:border-slate-700 dark:text-slate-300" title="Edit user"><PencilSquareIcon className="h-5 w-5" /></button><button onClick={() => resetPassword(user)} className="rounded-xl border border-slate-200 p-2 text-amber-700 dark:border-slate-700 dark:text-amber-300" title="Reset password"><KeyIcon className="h-5 w-5" /></button></div></td></tr>)}</tbody></table></div></section>}{modal !== undefined && <UserModal item={modal} onClose={() => setModal(undefined)} onSaved={() => { setModal(undefined); load(); }} />}</div>;
}
