import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ArchiveBoxArrowDownIcon,
  ArrowDownTrayIcon,
  BellAlertIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
  DocumentPlusIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  PaperClipIcon,
  PencilSquareIcon,
  PlusIcon,
  TrashIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import api from '../../services/api';

const emptyForm = {
  title: '',
  content: '',
  category: 'general',
  target_audience: 'all',
  scheduled_at: '',
  is_published: true,
  attachment: null,
};

const categoryStyles = {
  general: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-100',
  advisory: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200',
  activity: 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-200',
  deadline: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
  emergency: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-200',
};

function formatDate(value, includeTime = true) {
  if (!value) return 'Not scheduled';
  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    ...(includeTime ? { hour: 'numeric', minute: '2-digit' } : {}),
  }).format(new Date(value));
}

function statusOf(item) {
  if (item.archived_at) return 'Archived';
  if (item.scheduled_at && new Date(item.scheduled_at) > new Date()) return 'Scheduled';
  return item.is_published ? 'Published' : 'Draft';
}

function LoadingState() {
  return (
    <div className="flex min-h-64 items-center justify-center rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
      <div className="h-11 w-11 animate-spin rounded-full border-4 border-[#800000]/20 border-t-[#800000]" />
    </div>
  );
}

function EmptyState({ managing }) {
  return (
    <div className="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
      <BellAlertIcon className="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
      <h3 className="mt-4 text-lg font-bold text-slate-800 dark:text-white">No announcements found</h3>
      <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {managing ? 'Create a new announcement or change the current filters.' : 'New university notices will appear here.'}
      </p>
    </div>
  );
}

function AnnouncementModal({ item, onClose, onSaved }) {
  const [form, setForm] = useState(() => item ? {
    title: item.title,
    content: item.content,
    category: item.category || 'general',
    target_audience: item.target_audience,
    scheduled_at: item.scheduled_at ? new Date(item.scheduled_at).toISOString().slice(0, 16) : '',
    is_published: Boolean(item.is_published),
    attachment: null,
  } : emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    const payload = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (value !== null && value !== '') payload.append(key, key === 'is_published' ? (value ? '1' : '0') : value);
    });

    try {
      const response = item
        ? await api.post(`/vpaa/announcements/${item.id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } })
        : await api.post('/vpaa/announcements', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      onSaved(response.data.message);
    } catch (requestError) {
      const errors = requestError.response?.data?.errors;
      setError(errors ? Object.values(errors).flat()[0] : requestError.response?.data?.message || 'Unable to save the announcement.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}>
      <form
        onSubmit={submit}
        onMouseDown={(event) => event.stopPropagation()}
        className="max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
      >
        <div className="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-5 dark:border-slate-700 dark:bg-slate-900 sm:px-8">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-[#a8750b]">University communication</p>
            <h2 className="mt-1 text-2xl font-black text-slate-900 dark:text-white">
              {item ? 'Edit announcement' : 'Create announcement'}
            </h2>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        <div className="space-y-5 p-6 sm:p-8">
          {error && <div className="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">{error}</div>}
          <label className="block">
            <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Title</span>
            <input
              value={form.title}
              onChange={(event) => setForm({ ...form, title: event.target.value })}
              className="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/10 dark:border-slate-700"
              placeholder="Enter a clear announcement title"
              required
            />
          </label>
          <label className="block">
            <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Message</span>
            <textarea
              value={form.content}
              onChange={(event) => setForm({ ...form, content: event.target.value })}
              className="min-h-40 w-full resize-y rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/10 dark:border-slate-700"
              placeholder="Write the advisory, activity details, or deadline..."
              required
            />
          </label>
          <div className="grid gap-5 sm:grid-cols-2">
            <label>
              <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Category</span>
              <select value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                <option value="general">General</option>
                <option value="advisory">Internship advisory</option>
                <option value="activity">Upcoming activity</option>
                <option value="deadline">Deadline</option>
                <option value="emergency">Emergency notice</option>
              </select>
            </label>
            <label>
              <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Audience</span>
              <select value={form.target_audience} onChange={(event) => setForm({ ...form, target_audience: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                <option value="all">All users</option>
                <option value="students">Students</option>
                <option value="coordinators">Coordinators</option>
                <option value="program_heads">Program heads</option>
                <option value="vpaa">VPAA</option>
                <option value="admin">Administrators</option>
                <option value="supervisor">Supervisors</option>
              </select>
            </label>
          </div>
          <div className="grid gap-5 sm:grid-cols-2">
            <label>
              <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Schedule publishing (optional)</span>
              <input type="datetime-local" value={form.scheduled_at} onChange={(event) => setForm({ ...form, scheduled_at: event.target.value })} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" />
            </label>
            <label>
              <span className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Attachment (optional)</span>
              <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onChange={(event) => setForm({ ...form, attachment: event.target.files[0] || null })} className="w-full rounded-2xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700" />
            </label>
          </div>
          <label className="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
            <input type="checkbox" checked={form.is_published} onChange={(event) => setForm({ ...form, is_published: event.target.checked })} className="h-5 w-5 accent-[#800000]" />
            <span>
              <span className="block text-sm font-bold text-slate-800 dark:text-white">Publish announcement</span>
              <span className="text-xs text-slate-500 dark:text-slate-400">Turn this off to keep it as a draft.</span>
            </span>
          </label>
        </div>

        <div className="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-6 py-5 dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:justify-end sm:px-8">
          <button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
          <button disabled={saving} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white shadow-lg shadow-[#800000]/20 transition hover:bg-[#650000] disabled:opacity-60">
            <DocumentPlusIcon className="h-5 w-5" />
            {saving ? 'Saving...' : item ? 'Save changes' : 'Create announcement'}
          </button>
        </div>
      </form>
    </div>
  );
}

export default function AnnouncementCenter({ managing = false }) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('all');
  const [status, setStatus] = useState('all');
  const [selected, setSelected] = useState(null);
  const [editing, setEditing] = useState(undefined);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const fetchItems = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api.get(managing ? '/vpaa/announcements' : '/announcements', {
        params: {
          search: search || undefined,
          category: !managing && category !== 'all' ? category : undefined,
          status: managing && status !== 'all' ? status : undefined,
        },
      });
      setItems(response.data.data || []);
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to load announcements.');
    } finally {
      setLoading(false);
    }
  }, [category, managing, search, status]);

  useEffect(() => {
    const timeout = setTimeout(fetchItems, 250);
    return () => clearTimeout(timeout);
  }, [fetchItems]);

  const unread = useMemo(() => items.filter((item) => !item.is_read).length, [items]);

  const openItem = async (item) => {
    setSelected(item);
    if (!managing && !item.is_read) {
      try {
        await api.post(`/announcements/${item.id}/read`);
        setItems((current) => current.map((entry) => entry.id === item.id ? { ...entry, is_read: true } : entry));
      } catch {
        // Reading the content remains available if the receipt request fails.
      }
    }
  };

  const mutate = async (item, action) => {
    const destructive = action === 'delete';
    if (destructive && !window.confirm(`Permanently delete “${item.title}”?`)) return;
    try {
      if (action === 'delete') await api.delete(`/vpaa/announcements/${item.id}`);
      else await api.put(`/vpaa/announcements/${item.id}/${action}`);
      setNotice(action === 'archive' ? 'Announcement archived.' : action === 'restore' ? 'Announcement restored.' : 'Announcement deleted.');
      fetchItems();
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'The requested action could not be completed.');
    }
  };

  const saved = (message) => {
    setEditing(undefined);
    setNotice(message);
    fetchItems();
  };

  const download = async (item) => {
    const response = await api.get(`/announcements/${item.id}/download`, { responseType: 'blob' });
    const url = URL.createObjectURL(response.data);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = item.attachment_name || 'announcement-attachment';
    anchor.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6">
      <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-[#5d0000] via-[#800000] to-[#9d1b1b] p-6 text-white shadow-xl shadow-[#800000]/15 sm:p-8">
        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-2xl">
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
              <BellAlertIcon className="h-7 w-7 text-[#f4cf63]" />
            </div>
            <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#f4cf63]">MarSU AIMS</p>
            <h1 className="mt-2 text-3xl font-black sm:text-4xl">{managing ? 'Announcement Management' : 'Announcements'}</h1>
            <p className="mt-3 max-w-xl text-sm leading-6 text-white/75 sm:text-base">
              {managing
                ? 'Create, schedule, publish, archive, and monitor official university internship notices.'
                : 'Stay updated with internship advisories, university notices, deadlines, and upcoming activities.'}
            </p>
          </div>
          {managing ? (
            <button onClick={() => setEditing(null)} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#430909] shadow-lg transition hover:-translate-y-0.5 hover:bg-[#e3c45e]">
              <PlusIcon className="h-5 w-5" /> New announcement
            </button>
          ) : (
            <div className="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/15 backdrop-blur">
              <p className="text-xs uppercase tracking-wider text-white/60">Unread notices</p>
              <p className="mt-1 text-3xl font-black">{unread}</p>
            </div>
          )}
        </div>
      </section>

      {(notice || error) && (
        <div className={`flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold ${error ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'}`}>
          <span>{error || notice}</span>
          <button onClick={() => { setError(''); setNotice(''); }} aria-label="Dismiss"><XMarkIcon className="h-5 w-5" /></button>
        </div>
      )}

      <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <div className="flex flex-col gap-3 md:flex-row">
          <label className="relative flex-1">
            <MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search announcements..." className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 outline-none transition focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/10 dark:border-slate-700" />
          </label>
          {managing ? (
            <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 md:min-w-44">
              <option value="all">All statuses</option>
              <option value="published">Published</option>
              <option value="scheduled">Scheduled</option>
              <option value="draft">Draft</option>
              <option value="archived">Archived</option>
            </select>
          ) : (
            <select value={category} onChange={(event) => setCategory(event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 md:min-w-52">
              <option value="all">All categories</option>
              <option value="general">General</option>
              <option value="advisory">Internship advisories</option>
              <option value="activity">Upcoming activities</option>
              <option value="deadline">Deadlines</option>
              <option value="emergency">Emergency notices</option>
            </select>
          )}
        </div>
      </section>

      {loading ? <LoadingState /> : items.length === 0 ? <EmptyState managing={managing} /> : (
        <div className="grid gap-5 xl:grid-cols-2">
          {items.map((item) => {
            const itemStatus = statusOf(item);
            return (
              <article key={item.id} className={`group relative overflow-hidden rounded-3xl border bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:bg-slate-900 ${!managing && !item.is_read ? 'border-[#800000]/30 ring-1 ring-[#800000]/10 dark:border-red-400/30' : 'border-slate-200 dark:border-slate-700'}`}>
                {!managing && !item.is_read && <span className="absolute right-5 top-5 h-2.5 w-2.5 rounded-full bg-[#800000] ring-4 ring-[#800000]/10" />}
                <div className="flex flex-wrap items-center gap-2">
                  <span className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${categoryStyles[item.category] || categoryStyles.general}`}>{item.category || 'general'}</span>
                  {managing && <span className="rounded-full bg-[#800000]/8 px-3 py-1 text-xs font-bold text-[#800000] dark:bg-red-400/10 dark:text-red-300">{itemStatus}</span>}
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">{item.target_audience.replaceAll('_', ' ')}</span>
                </div>
                <h2 className="mt-4 pr-5 text-xl font-black leading-snug text-slate-900 dark:text-white">{item.title}</h2>
                <p className="mt-3 line-clamp-3 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{item.content}</p>
                <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                  <span className="inline-flex items-center gap-1.5"><CalendarDaysIcon className="h-4 w-4" />{formatDate(item.published_at || item.scheduled_at || item.created_at)}</span>
                  {item.attachment_name && <span className="inline-flex items-center gap-1.5"><PaperClipIcon className="h-4 w-4" />{item.attachment_name}</span>}
                  {managing && <span className="inline-flex items-center gap-1.5"><EyeIcon className="h-4 w-4" />{item.readers_count || 0} reads</span>}
                </div>
                <div className="mt-5 flex flex-wrap gap-2">
                  <button onClick={() => openItem(item)} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#650000]"><EyeIcon className="h-4 w-4" />Read</button>
                  {item.attachment_name && <button onClick={() => download(item)} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"><ArrowDownTrayIcon className="h-4 w-4" />Attachment</button>}
                  {managing && (
                    <>
                      <button onClick={() => setEditing(item)} className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" title="Edit"><PencilSquareIcon className="h-5 w-5" /></button>
                      <button onClick={() => mutate(item, item.archived_at ? 'restore' : 'archive')} className="rounded-xl border border-slate-200 p-2 text-amber-700 hover:bg-amber-50 dark:border-slate-700 dark:text-amber-300 dark:hover:bg-amber-950/30" title={item.archived_at ? 'Restore' : 'Archive'}><ArchiveBoxArrowDownIcon className="h-5 w-5" /></button>
                      <button onClick={() => mutate(item, 'delete')} className="rounded-xl border border-slate-200 p-2 text-rose-700 hover:bg-rose-50 dark:border-slate-700 dark:text-rose-300 dark:hover:bg-rose-950/30" title="Delete"><TrashIcon className="h-5 w-5" /></button>
                    </>
                  )}
                </div>
              </article>
            );
          })}
        </div>
      )}

      {selected && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={() => setSelected(null)}>
          <article onMouseDown={(event) => event.stopPropagation()} className="max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8">
            <div className="flex items-start justify-between gap-4">
              <div>
                <span className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${categoryStyles[selected.category] || categoryStyles.general}`}>{selected.category || 'general'}</span>
                <h2 className="mt-4 text-2xl font-black text-slate-900 dark:text-white sm:text-3xl">{selected.title}</h2>
              </div>
              <button onClick={() => setSelected(null)} className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"><XMarkIcon className="h-6 w-6" /></button>
            </div>
            <div className="mt-4 flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
              <span>{selected.author?.name || 'Marinduque State University'}</span>
              <span>•</span>
              <span>{formatDate(selected.published_at || selected.created_at)}</span>
            </div>
            <p className="mt-7 whitespace-pre-line text-base leading-8 text-slate-700 dark:text-slate-200">{selected.content}</p>
            {selected.attachment_name && (
              <button onClick={() => download(selected)} className="mt-8 inline-flex items-center gap-2 rounded-2xl bg-[#800000] px-5 py-3 font-bold text-white">
                <ArrowDownTrayIcon className="h-5 w-5" /> Download {selected.attachment_name}
              </button>
            )}
            {!managing && <div className="mt-8 flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200"><CheckCircleIcon className="h-5 w-5" />Marked as read</div>}
          </article>
        </div>
      )}

      {editing !== undefined && <AnnouncementModal item={editing} onClose={() => setEditing(undefined)} onSaved={saved} />}
    </div>
  );
}
