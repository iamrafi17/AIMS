import { useEffect, useRef, useState } from 'react';
import {
  BriefcaseIcon,
  CheckCircleIcon,
  CheckIcon,
  EnvelopeIcon,
  EyeIcon,
  EyeSlashIcon,
  IdentificationIcon,
  KeyIcon,
  MapPinIcon,
  PencilSquareIcon,
  PhoneIcon,
  PhotoIcon,
  ShieldCheckIcon,
  TrashIcon,
  UserCircleIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import UserAvatar from '../../components/UserAvatar';
import useAuth from '../../context/useAuth';
import api from '../../services/api';

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-[#800000]/45 focus:ring-4 focus:ring-[#800000]/8 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-700 dark:disabled:text-gray-400';

function SmallIcon({ icon: Icon, className = '' }) {
  return (
    <Icon
      aria-hidden="true"
      className={`coordinator-profile-icon shrink-0 ${className}`}
      style={{ width: '18px', height: '18px', minWidth: '18px', minHeight: '18px' }}
    />
  );
}

function Field({ label, icon, children, className = '' }) {
  return (
    <label className={className}>
      <span className="mb-1.5 flex min-h-9 items-start gap-2 text-[11px] font-extrabold uppercase leading-4 tracking-wide text-slate-500 dark:text-gray-400">
        <SmallIcon icon={icon} className="text-[#a8750b]" />
        <span>{label}</span>
      </span>
      {children}
    </label>
  );
}

function SectionHeader({ icon, title, description, editing, onToggle }) {
  return (
    <div className="mb-6 flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center dark:border-gray-700">
      <div className="flex items-start gap-3">
        <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-[#800000]/8 text-[#800000] dark:bg-rose-950 dark:text-rose-300">
          <SmallIcon icon={icon} />
        </div>
        <div>
          <h2 className="font-black text-[#430909] dark:text-white">{title}</h2>
          <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
        </div>
      </div>
      {onToggle && (
        <button type="button" onClick={onToggle} className="inline-flex items-center justify-center gap-2 self-start rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-[#800000] transition hover:bg-[#800000]/5 sm:self-auto dark:border-gray-600 dark:text-rose-300">
          <SmallIcon icon={editing ? XMarkIcon : PencilSquareIcon} />
          {editing ? 'Cancel' : 'Edit'}
        </button>
      )}
    </div>
  );
}

function CoordinatorProfile() {
  const { user, refreshUser } = useAuth();
  const roleLabel = {
    coordinator: 'Internship Coordinator',
    program_head: 'Program Head',
    vpaa: 'Vice President for Academic Affairs',
    admin: 'System Administrator',
    supervisor: 'HTE Supervisor',
  }[user?.role] || 'AIMS User';
  const roleAccountLabel = {
    coordinator: 'Coordinator',
    program_head: 'Program Head',
    vpaa: 'VPAA',
    admin: 'Administrator',
    supervisor: 'Supervisor',
  }[user?.role] || 'Account';
  const photoInput = useRef(null);
  const [account, setAccount] = useState({ name: '' });
  const [contact, setContact] = useState({ email: '', phone: '', address: '' });
  const [passwords, setPasswords] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [editingAccount, setEditingAccount] = useState(false);
  const [editingContact, setEditingContact] = useState(false);
  const [showPasswords, setShowPasswords] = useState(false);
  const [busy, setBusy] = useState(null);

  const resetAccount = () => setAccount({ name: user?.name || '' });
  const resetContact = () => setContact({
    email: user?.email || '',
    phone: user?.phone || '',
    address: user?.address || '',
  });

  useEffect(() => {
    resetAccount();
    resetContact();
  }, [user]); // eslint-disable-line react-hooks/exhaustive-deps

  const errorMessage = (error, fallback) => {
    const errors = error.response?.data?.errors;
    return errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || fallback;
  };

  const toggleAccount = () => {
    if (editingAccount) resetAccount();
    setEditingAccount((current) => !current);
  };

  const toggleContact = () => {
    if (editingContact) resetContact();
    setEditingContact((current) => !current);
  };

  const saveAccount = async (event) => {
    event.preventDefault();
    setBusy('account');
    try {
      const response = await api.put('/profile/account', account);
      await refreshUser();
      setEditingAccount(false);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update account information.'));
    } finally {
      setBusy(null);
    }
  };

  const saveContact = async (event) => {
    event.preventDefault();
    setBusy('contact');
    try {
      const response = await api.put('/profile/contact', contact);
      await refreshUser();
      setEditingContact(false);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to update contact information.'));
    } finally {
      setBusy(null);
    }
  };

  const changePassword = async (event) => {
    event.preventDefault();
    if (passwords.password !== passwords.password_confirmation) {
      toast.error('New password confirmation does not match.');
      return;
    }

    setBusy('password');
    try {
      const response = await api.put('/profile/password', passwords);
      setPasswords({ current_password: '', password: '', password_confirmation: '' });
      setShowPasswords(false);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to change password.'));
    } finally {
      setBusy(null);
    }
  };

  const uploadPhoto = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      toast.error('Choose a JPG, PNG, or WebP image.');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Profile photos must be 5 MB or smaller.');
      return;
    }

    const formData = new FormData();
    formData.append('avatar', file);
    setBusy('photo');
    try {
      const response = await api.post('/profile/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await refreshUser();
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to upload profile photo.'));
    } finally {
      setBusy(null);
    }
  };

  const removePhoto = async () => {
    if (!window.confirm('Remove your current profile photo?')) return;
    setBusy('remove-photo');
    try {
      const response = await api.delete('/profile/avatar');
      await refreshUser();
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to remove profile photo.'));
    } finally {
      setBusy(null);
    }
  };

  const formattedLogin = user?.last_login_at
    ? new Date(user.last_login_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
    : 'No login record';

  return (
    <div className="space-y-6">
      <header>
        <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">{roleAccountLabel} account</p>
        <h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">My Profile</h1>
        <p className="mt-1 text-sm text-slate-400">Manage your coordinator identity, contact details, photo, and account security.</p>
      </header>

      <div className="grid min-w-0 gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
        <aside className="self-start overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm xl:sticky xl:top-28 dark:border-gray-700 dark:bg-gray-800">
          <div className="bg-gradient-to-br from-[#800000] to-[#4b0000] px-6 py-8 text-center text-white">
            <div className="relative mx-auto w-fit">
              <UserAvatar user={user} className="size-32 ring-4 ring-white/20" textClassName="text-4xl" />
              <button type="button" onClick={() => photoInput.current?.click()} disabled={busy === 'photo'} className="absolute bottom-1 right-1 grid size-11 place-items-center rounded-full border-4 border-[#650000] bg-[#d4af37] text-[#4b0000] shadow-lg transition hover:scale-105 disabled:opacity-60" aria-label="Upload coordinator profile photo">
                <SmallIcon icon={PhotoIcon} />
              </button>
              <input ref={photoInput} type="file" accept="image/jpeg,image/png,image/webp" onChange={uploadPhoto} className="hidden" />
            </div>
            <h2 className="mt-5 text-xl font-black">{user?.name || roleAccountLabel}</h2>
            <p className="mt-1 break-all text-sm text-white/65">{user?.email}</p>
            <span className="mt-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#f5d77f]">
              <SmallIcon icon={BriefcaseIcon} /> {roleLabel}
            </span>
          </div>

          <div className="p-5">
            <div className="space-y-3">
              <div className="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/40">
                <div className="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300"><SmallIcon icon={CheckCircleIcon} /></div>
                <div><p className="text-[10px] font-black uppercase tracking-wide text-emerald-600">Account status</p><p className="text-sm font-black text-emerald-800 dark:text-emerald-200">{user?.is_active === false ? 'Inactive' : 'Active'}</p></div>
              </div>
              <div className="rounded-xl border border-slate-100 p-3 dark:border-gray-700">
                <p className="text-[10px] font-black uppercase tracking-wide text-slate-400">Last login</p>
                <p className="mt-1 text-xs font-bold text-slate-700 dark:text-gray-200">{formattedLogin}</p>
              </div>
            </div>

            <button type="button" onClick={() => photoInput.current?.click()} disabled={busy === 'photo'} className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#800000] px-4 py-3 text-xs font-black text-white transition hover:bg-[#650000] disabled:opacity-60">
              <SmallIcon icon={PhotoIcon} /> {busy === 'photo' ? 'Uploading...' : user?.avatar_url ? 'Change Photo' : 'Upload Photo'}
            </button>
            {user?.avatar_url && (
              <button type="button" onClick={removePhoto} disabled={busy === 'remove-photo'} className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 py-3 text-xs font-black text-rose-600 transition hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900 dark:hover:bg-rose-950">
                <SmallIcon icon={TrashIcon} /> {busy === 'remove-photo' ? 'Removing...' : 'Remove Photo'}
              </button>
            )}
            <p className="mt-3 text-center text-[11px] text-slate-400">JPG, PNG, or WebP · maximum 5 MB</p>
          </div>
        </aside>

        <main className="min-w-0 space-y-6">
          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={UserCircleIcon} title={`${roleAccountLabel} Information`} description="Update the name displayed throughout your AIMS portal." editing={editingAccount} onToggle={toggleAccount} />
            <form onSubmit={saveAccount}>
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Full Name" icon={IdentificationIcon}>
                  <input value={account.name} onChange={(event) => setAccount({ name: event.target.value })} disabled={!editingAccount} className={inputClass} required />
                </Field>
                <Field label="System Role" icon={BriefcaseIcon}>
                  <input value={roleLabel} disabled className={inputClass} />
                </Field>
              </div>
              {editingAccount && (
                <div className="mt-6 flex justify-end">
                  <button disabled={busy === 'account'} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60">
                    <SmallIcon icon={CheckIcon} /> {busy === 'account' ? 'Saving...' : `Save ${roleAccountLabel} Information`}
                  </button>
                </div>
              )}
            </form>
          </section>

          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={PhoneIcon} title="Contact Information" description="Keep your university email, active phone number, and office address current." editing={editingContact} onToggle={toggleContact} />
            <form onSubmit={saveContact}>
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Email Address" icon={EnvelopeIcon}>
                  <input type="email" value={contact.email} onChange={(event) => setContact((current) => ({ ...current, email: event.target.value }))} disabled={!editingContact} className={inputClass} required />
                </Field>
                <Field label="Contact Number" icon={PhoneIcon}>
                  <input type="tel" value={contact.phone} onChange={(event) => setContact((current) => ({ ...current, phone: event.target.value }))} disabled={!editingContact} className={inputClass} required />
                </Field>
                <Field label="Office / Present Address" icon={MapPinIcon} className="md:col-span-2">
                  <textarea rows="3" value={contact.address} onChange={(event) => setContact((current) => ({ ...current, address: event.target.value }))} disabled={!editingContact} className={`${inputClass} resize-none`} required />
                </Field>
              </div>
              {editingContact && (
                <div className="mt-6 flex justify-end">
                  <button disabled={busy === 'contact'} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60">
                    <SmallIcon icon={CheckIcon} /> {busy === 'contact' ? 'Saving...' : 'Save Contact Information'}
                  </button>
                </div>
              )}
            </form>
          </section>

          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <SectionHeader icon={ShieldCheckIcon} title="Change Password" description="Verify your current password before setting a new one." />
            <form onSubmit={changePassword}>
              <div className="grid gap-4 lg:grid-cols-3">
                {[
                  ['current_password', 'Current Password'],
                  ['password', 'New Password'],
                  ['password_confirmation', 'Confirm Password'],
                ].map(([name, label]) => (
                  <Field key={name} label={label} icon={KeyIcon}>
                    <div className="relative">
                      <input
                        type={showPasswords ? 'text' : 'password'}
                        value={passwords[name]}
                        onChange={(event) => setPasswords((current) => ({ ...current, [name]: event.target.value }))}
                        minLength={name === 'current_password' ? undefined : 8}
                        autoComplete={name === 'current_password' ? 'current-password' : 'new-password'}
                        className={`${inputClass} pr-11`}
                        required
                      />
                      <button type="button" onClick={() => setShowPasswords((current) => !current)} className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:text-[#800000]" aria-label={showPasswords ? 'Hide passwords' : 'Show passwords'}>
                        <SmallIcon icon={showPasswords ? EyeSlashIcon : EyeIcon} />
                      </button>
                    </div>
                  </Field>
                ))}
              </div>
              <div className="mt-5 flex flex-col justify-between gap-4 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center dark:bg-gray-900/60">
                <p className="text-xs leading-5 text-slate-500 dark:text-gray-400">Use at least 8 characters. The new password must differ from your current password.</p>
                <button disabled={busy === 'password'} className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60">
                  <SmallIcon icon={KeyIcon} /> {busy === 'password' ? 'Changing...' : 'Change Password'}
                </button>
              </div>
            </form>
          </section>
        </main>
      </div>
    </div>
  );
}

export default CoordinatorProfile;
