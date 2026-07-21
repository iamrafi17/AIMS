import { useEffect, useRef, useState } from 'react';
import { PlusCircleIcon, PlusIcon, TrashIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import UserAvatar from '../../components/UserAvatar';
import { useAuth } from '../../context/AuthContext';
import api from '../../services/api';

function StudentProfile() {
  const { user, student, refreshUser } = useAuth();
  const fileInput = useRef(null);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [removing, setRemoving] = useState(false);
  const [formData, setFormData] = useState({ name: '', email: '', phone: '', address: '' });

  useEffect(() => {
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      phone: student?.phone || '',
      address: student?.address || '',
    });
  }, [user, student]);

  const handleChange = (event) => setFormData((current) => ({ ...current, [event.target.name]: event.target.value }));

  const handlePhoto = async (event) => {
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

    const payload = new FormData();
    payload.append('avatar', file);
    setUploading(true);
    try {
      const response = await api.post('/profile/avatar', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      await refreshUser();
      toast.success(response.data.message);
    } catch (error) {
      const validation = error.response?.data?.errors?.avatar?.[0];
      toast.error(validation || error.response?.data?.message || 'Unable to upload profile photo.');
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
      toast.error(error.response?.data?.message || 'Unable to remove profile photo.');
    } finally {
      setRemoving(false);
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const response = await api.put('/profile', formData);
      await refreshUser();
      toast.success(response.data.message);
      setEditing(false);
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || 'Failed to update profile.');
    } finally {
      setSaving(false);
    }
  };

  const cancelEditing = () => {
    setFormData({ name: user?.name || '', email: user?.email || '', phone: student?.phone || '', address: student?.address || '' });
    setEditing(false);
  };

  return (
    <div className="space-y-6">
      <div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Account settings</p><h1 className="mt-1 text-2xl font-black text-[#430909]">My Profile</h1></div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div className="text-center">
            <div className="relative mx-auto w-fit">
              <UserAvatar user={user} className="size-32" textClassName="text-4xl" />
              <button type="button" onClick={() => fileInput.current?.click()} disabled={uploading} className="absolute bottom-1 right-1 grid h-11 w-11 place-items-center rounded-full border-4 border-white bg-[#800000] text-white shadow-lg transition hover:bg-[#5c0000] disabled:opacity-60 dark:border-gray-800" aria-label="Upload profile photo">
                {uploading ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" /> : <PlusCircleIcon className="h-5 w-5" />}
              </button>
              <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" onChange={handlePhoto} className="hidden" />
            </div>
            <h2 className="mt-5 text-xl font-black text-gray-800 dark:text-white">{user?.name}</h2>
            <p className="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{user?.email}</p>
            <p className="mt-2 text-sm font-bold capitalize text-[#800000]">{user?.role?.replace('_', ' ')}</p>
            <div className="mt-5 flex justify-center gap-2">
              <button type="button" onClick={() => fileInput.current?.click()} disabled={uploading} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white disabled:opacity-60"><PlusIcon className="h-4 w-4" /> {user?.avatar_url ? 'Change Photo' : 'Upload Photo'}</button>
              {user?.avatar_url && <button type="button" onClick={removePhoto} disabled={removing} className="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-xs font-black text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900 dark:hover:bg-rose-950"><TrashIcon className="h-4 w-4" /> Remove</button>}
            </div>
            <p className="mt-3 text-[11px] leading-5 text-gray-400">JPG, PNG, or WebP · maximum 5 MB</p>
          </div>

          {student && <div className="mt-7 space-y-1 border-t border-slate-100 pt-5 dark:border-gray-700">{[
            ['Student ID', student.student_id],
            ['College', student.college?.name],
            ['Program', student.program?.name],
            ['Year & Section', `${student.year_level} - ${student.section}`],
            ['HTE', student.hte?.name || 'Not assigned'],
          ].map(([label, value]) => <div key={label} className="flex items-start justify-between gap-4 border-b border-slate-100 py-3 last:border-0 dark:border-gray-700"><span className="text-sm text-gray-500 dark:text-gray-400">{label}</span><span className="max-w-[58%] text-right text-sm font-bold text-gray-800 dark:text-gray-100">{value}</span></div>)}</div>}
        </section>

        <section className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
          <div className="mb-6 flex items-center justify-between"><div><h3 className="text-lg font-black text-gray-800 dark:text-white">Personal Information</h3><p className="mt-1 text-xs text-gray-400">Keep your contact details up to date.</p></div><button type="button" onClick={() => editing ? cancelEditing() : setEditing(true)} className="rounded-xl px-4 py-2 text-sm font-black text-[#800000] hover:bg-[#800000]/5">{editing ? 'Cancel' : 'Edit Profile'}</button></div>
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              {[
                { name: 'name', label: 'Full Name', type: 'text' },
                { name: 'email', label: 'Email Address', type: 'email' },
                { name: 'phone', label: 'Contact Number', type: 'tel' },
                { name: 'address', label: 'Address', type: 'text' },
              ].map((field) => <label key={field.name}><span className="mb-1.5 block text-xs font-extrabold uppercase tracking-wide text-gray-500 dark:text-gray-400">{field.label}</span><input type={field.type} name={field.name} value={formData[field.name]} onChange={handleChange} disabled={!editing} required={field.name === 'name' || field.name === 'email'} className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-[#800000]/50 focus:ring-4 focus:ring-[#800000]/5 disabled:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-700" /></label>)}
            </div>
            {editing && <div className="flex justify-end gap-3 border-t border-slate-100 pt-5 dark:border-gray-700"><button type="button" onClick={cancelEditing} className="rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button><button type="submit" disabled={saving} className="rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-60">{saving ? 'Saving...' : 'Save Changes'}</button></div>}
          </form>
        </section>
      </div>
    </div>
  );
}

export default StudentProfile;
