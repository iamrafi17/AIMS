import { useEffect, useRef, useState } from 'react';
import {
  CalendarDaysIcon,
  CheckIcon,
  EnvelopeIcon,
  EyeIcon,
  EyeSlashIcon,
  IdentificationIcon,
  KeyIcon,
  MapPinIcon,
  PencilSquareIcon,
  PhoneIcon,
  PlusCircleIcon,
  PlusIcon,
  ShieldCheckIcon,
  TrashIcon,
  UserCircleIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import UserAvatar from '../../components/UserAvatar';
import useAuth from '../../context/useAuth';
import api from '../../services/api';

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-[#800000]/45 focus:ring-4 focus:ring-[#800000]/8 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-700 dark:disabled:text-gray-400';

function Field({ label, icon: Icon, children, className = '' }) {
  return (
    <label className={className}>
      <span className="mb-1.5 flex min-h-9 items-start gap-1.5 text-[11px] font-extrabold uppercase leading-4 tracking-wide text-slate-500 dark:text-gray-400">
        {Icon && (
          <Icon
            aria-hidden="true"
            className="profile-field-icon shrink-0 text-[#a8750b]"
            style={{ width: '16px', height: '16px', minWidth: '16px', minHeight: '16px' }}
          />
        )}
        <span>{label}</span>
      </span>
      {children}
    </label>
  );
}

function SectionHeader({ icon: Icon, title, description, editing, onEdit, onCancel }) {
  return (
    <div className="mb-6 flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center dark:border-gray-700">
      <div className="flex items-start gap-3">
        <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-[#800000]/8 text-[#800000] dark:bg-rose-950 dark:text-rose-300"><Icon className="size-5" /></div>
        <div>
          <h2 className="font-black text-[#430909] dark:text-white">{title}</h2>
          <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
        </div>
      </div>
      {onEdit && (
        <button type="button" onClick={editing ? onCancel : onEdit} className="inline-flex items-center justify-center gap-2 self-start rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-[#800000] transition hover:bg-[#800000]/5 sm:self-auto dark:border-gray-600 dark:text-rose-300">
          {editing ? <><XMarkIcon className="size-4" /> Cancel</> : <><PencilSquareIcon className="size-4" /> Edit</>}
        </button>
      )}
    </div>
  );
}

function StudentProfile() {
  const { user, student, refreshUser } = useAuth();
  const fileInput = useRef(null);
  const [editingPersonal, setEditingPersonal] = useState(false);
  const [editingContact, setEditingContact] = useState(false);
  const [savingSection, setSavingSection] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [removing, setRemoving] = useState(false);
  const [showPasswords, setShowPasswords] = useState(false);
  const [personal, setPersonal] = useState({ first_name: '', middle_name: '', last_name: '', gender: 'female', birth_date: '' });
  const [contact, setContact] = useState({ email: '', phone: '', address: '' });
  const [passwords, setPasswords] = useState({ current_password: '', password: '', password_confirmation: '' });

  const resetPersonal = () => setPersonal({
    first_name: student?.first_name || '',
    middle_name: student?.middle_name || '',
    last_name: student?.last_name || '',
    gender: student?.gender || 'female',
    birth_date: student?.birth_date?.slice(0, 10) || '',
  });
  const resetContact = () => setContact({
    email: user?.email || '',
    phone: student?.phone || '',
    address: student?.address || '',
  });

  useEffect(() => {
    resetPersonal();
    resetContact();
  }, [user, student]); // eslint-disable-line react-hooks/exhaustive-deps

  const errorMessage = (error, fallback) => {
    const errors = error.response?.data?.errors;
    return errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || fallback;
  };

  const handlePhoto = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return toast.error('Choose a JPG, PNG, or WebP image.');
    if (file.size > 5 * 1024 * 1024) return toast.error('Profile photos must be 5 MB or smaller.');

    const payload = new FormData();
    payload.append('avatar', file);
    setUploading(true);
    try {
      const response = await api.post('/profile/avatar', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      await refreshUser();
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to upload profile photo.'));
    } finally {
      setUploading(false);
    }
  };

  const removePhoto = async () => {
    if (!window.confirm('Remove your current profile photo?')) return;
    setRemoving(true);
    try {
      const response = await api.delete('/profile/avatar');
      await refreshUser();
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to remove profile photo.'));
    } finally {
      setRemoving(false);
    }
  };

  const savePersonal = async (event) => {
    event.preventDefault();
    setSavingSection('personal');
    try {
      const response = await api.put('/profile/information', personal);
      await refreshUser();
      setEditingPersonal(false);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update profile information.'));
    } finally {
      setSavingSection(null);
    }
  };

  const saveContact = async (event) => {
    event.preventDefault();
    setSavingSection('contact');
    try {
      const response = await api.put('/profile/contact', contact);
      await refreshUser();
      setEditingContact(false);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update contact information.'));
    } finally {
      setSavingSection(null);
    }
  };

  const changePassword = async (event) => {
    event.preventDefault();
    if (passwords.password !== passwords.password_confirmation) return toast.error('New password confirmation does not match.');
    setSavingSection('password');
    try {
      const response = await api.put('/profile/password', passwords);
      setPasswords({ current_password: '', password: '', password_confirmation: '' });
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to change password.'));
    } finally {
      setSavingSection(null);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Account settings</p>
        <h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">My Profile</h1>
        <p className="mt-1 text-sm text-slate-400">Manage your personal details, contact information, profile photo, and password.</p>
      </div>

      <div className="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
        <aside className="self-start rounded-3xl border border-slate-100 bg-white p-6 shadow-sm xl:sticky xl:top-28 dark:border-gray-700 dark:bg-gray-800">
          <div className="text-center">
            <div className="relative mx-auto w-fit">
              <UserAvatar user={user} className="size-32" textClassName="text-4xl" />
              <button type="button" onClick={() => fileInput.current?.click()} disabled={uploading} className="absolute bottom-1 right-1 grid size-11 place-items-center rounded-full border-4 border-white bg-[#800000] text-white shadow-lg transition hover:bg-[#5c0000] disabled:opacity-60 dark:border-gray-800" aria-label="Upload profile photo">
                {uploading ? <span className="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" /> : <PlusCircleIcon className="size-5" />}
              </button>
              <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" onChange={handlePhoto} className="hidden" />
            </div>
            <h2 className="mt-5 text-xl font-black text-gray-800 dark:text-white">{user?.name}</h2>
            <p className="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{user?.email}</p>
            <span className="mt-3 inline-flex rounded-full bg-[#800000]/8 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-[#800000] dark:bg-rose-950 dark:text-rose-300">Student Intern</span>
            <div className="mt-5 flex flex-wrap justify-center gap-2">
              <button type="button" onClick={() => fileInput.current?.click()} disabled={uploading} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white disabled:opacity-60"><PlusIcon className="size-4" /> {user?.avatar_url ? 'Change Photo' : 'Upload Photo'}</button>
              {user?.avatar_url && <button type="button" onClick={removePhoto} disabled={removing} className="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-xs font-black text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900 dark:hover:bg-rose-950"><TrashIcon className="size-4" /> Remove</button>}
            </div>
            <p className="mt-3 text-[11px] text-gray-400">JPG, PNG, or WebP · maximum 5 MB</p>
          </div>

          {student && (
            <div className="mt-7 space-y-1 border-t border-slate-100 pt-5 dark:border-gray-700">
              {[
                ['Student ID', student.student_id],
                ['Program', student.program?.name || 'Not assigned'],
                ['Year & Section', `${student.year_level || '—'} · ${student.section || '—'}`],
                ['HTE', student.hte?.name || 'Not assigned'],
              ].map(([label, value]) => <div key={label} className="flex items-start justify-between gap-4 border-b border-slate-100 py-3 last:border-0 dark:border-gray-700"><span className="text-xs text-gray-500 dark:text-gray-400">{label}</span><span className="max-w-[60%] text-right text-xs font-black text-gray-800 dark:text-gray-100">{value}</span></div>)}
            </div>
          )}
        </aside>

        <div className="space-y-6">
          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={UserCircleIcon} title="Profile Information" description="Update your legal name and basic personal information." editing={editingPersonal} onEdit={() => setEditingPersonal(true)} onCancel={() => { resetPersonal(); setEditingPersonal(false); }} />
            <form onSubmit={savePersonal}>
              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Field label="First Name" icon={IdentificationIcon}><input className={inputClass} value={personal.first_name} onChange={(event) => setPersonal((current) => ({ ...current, first_name: event.target.value }))} disabled={!editingPersonal} required /></Field>
                <Field label="Middle Name" icon={IdentificationIcon}><input className={inputClass} value={personal.middle_name} onChange={(event) => setPersonal((current) => ({ ...current, middle_name: event.target.value }))} disabled={!editingPersonal} /></Field>
                <Field label="Last Name" icon={IdentificationIcon}><input className={inputClass} value={personal.last_name} onChange={(event) => setPersonal((current) => ({ ...current, last_name: event.target.value }))} disabled={!editingPersonal} required /></Field>
                <Field label="Gender" icon={UserCircleIcon}>
                  <select className={inputClass} value={personal.gender} onChange={(event) => setPersonal((current) => ({ ...current, gender: event.target.value }))} disabled={!editingPersonal} required>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                  </select>
                </Field>
                <Field label="Birth Date" icon={CalendarDaysIcon}><input type="date" className={inputClass} value={personal.birth_date} onChange={(event) => setPersonal((current) => ({ ...current, birth_date: event.target.value }))} disabled={!editingPersonal} required /></Field>
                <Field label="Student ID" icon={IdentificationIcon}><input className={inputClass} value={student?.student_id || ''} disabled /></Field>
              </div>
              {editingPersonal && <div className="mt-6 flex justify-end"><button type="submit" disabled={savingSection === 'personal'} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60"><CheckIcon className="size-4" /> {savingSection === 'personal' ? 'Saving...' : 'Save Profile Information'}</button></div>}
            </form>
          </section>

          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={PhoneIcon} title="Contact Information" description="Keep your active email, phone number, and present address updated." editing={editingContact} onEdit={() => setEditingContact(true)} onCancel={() => { resetContact(); setEditingContact(false); }} />
            <form onSubmit={saveContact}>
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Email Address" icon={EnvelopeIcon}><input type="email" className={inputClass} value={contact.email} onChange={(event) => setContact((current) => ({ ...current, email: event.target.value }))} disabled={!editingContact} required /></Field>
                <Field label="Contact Number" icon={PhoneIcon}><input type="tel" className={inputClass} value={contact.phone} onChange={(event) => setContact((current) => ({ ...current, phone: event.target.value }))} disabled={!editingContact} required /></Field>
                <Field label="Present Address" icon={MapPinIcon} className="md:col-span-2"><textarea rows="3" className={`${inputClass} resize-none`} value={contact.address} onChange={(event) => setContact((current) => ({ ...current, address: event.target.value }))} disabled={!editingContact} required /></Field>
              </div>
              {editingContact && <div className="mt-6 flex justify-end"><button type="submit" disabled={savingSection === 'contact'} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60"><CheckIcon className="size-4" /> {savingSection === 'contact' ? 'Saving...' : 'Save Contact Information'}</button></div>}
            </form>
          </section>

          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={ShieldCheckIcon} title="Change Password" description="Confirm your current password before creating a new secure password." />
            <form onSubmit={changePassword}>
              <div className="grid gap-4 lg:grid-cols-3">
                {[
                  ['current_password', 'Current Password'],
                  ['password', 'New Password'],
                  ['password_confirmation', 'Confirm Password'],
                ].map(([name, label]) => (
                  <Field key={name} label={label} icon={KeyIcon}>
                    <div className="relative">
                      <input type={showPasswords ? 'text' : 'password'} className={`${inputClass} pr-11`} value={passwords[name]} onChange={(event) => setPasswords((current) => ({ ...current, [name]: event.target.value }))} minLength={name === 'current_password' ? undefined : 8} autoComplete={name === 'current_password' ? 'current-password' : 'new-password'} required />
                      <button type="button" onClick={() => setShowPasswords((visible) => !visible)} className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:text-[#800000]" aria-label={showPasswords ? 'Hide passwords' : 'Show passwords'}>
                        {showPasswords ? <EyeSlashIcon className="size-5" /> : <EyeIcon className="size-5" />}
                      </button>
                    </div>
                  </Field>
                ))}
              </div>
              <div className="mt-4 flex flex-col justify-between gap-4 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center dark:bg-gray-900/60">
                <p className="text-xs leading-5 text-slate-500 dark:text-gray-400">Use at least 8 characters. Your new password must differ from your current password.</p>
                <button type="submit" disabled={savingSection === 'password'} className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60"><KeyIcon className="size-4" /> {savingSection === 'password' ? 'Changing...' : 'Change Password'}</button>
              </div>
            </form>
          </section>
        </div>
      </div>
    </div>
  );
}

export default StudentProfile;
