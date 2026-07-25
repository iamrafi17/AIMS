import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BellAlertIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
  ClockIcon,
  DocumentCheckIcon,
  InformationCircleIcon,
  MapPinIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const icons = {
  approval: ClipboardDocumentCheckIcon,
  requirement: DocumentCheckIcon,
  travel: MapPinIcon,
  task: CheckCircleIcon,
  announcement: BellAlertIcon,
  information: InformationCircleIcon,
};

export default function Notifications() {
  const navigate = useNavigate();
  const [data, setData] = useState({ unread_count: 0, notifications: { data: [] } });
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const load = useCallback(async () => {
    setLoading(true);
    try { setData((await api.get('/notifications', { params: { unread: filter === 'unread' ? 1 : undefined } })).data); }
    catch { toast.error('Unable to load notifications.'); }
    finally { setLoading(false); }
  }, [filter]);
  useEffect(() => { load(); }, [load]);
  const open = async (notification) => {
    if (!notification.read_at) await api.put(`/notifications/${notification.id}/read`);
    if (notification.action_url) navigate(notification.action_url);
    else load();
  };
  const readAll = async () => { const response = await api.put('/notifications/read-all'); toast.success(response.data.message); load(); };
  const items = data.notifications?.data || [];
  return <div className="space-y-6">
    <PageIntro eyebrow="AIMS activity center" title="Notifications" description="Follow registration, document, deployment, MOA, attendance, evaluation, task, travel, and announcement updates addressed to your account." icon={BellAlertIcon} actions={<button onClick={readAll} disabled={!data.unread_count} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#430909] disabled:opacity-50"><CheckCircleIcon className="h-5 w-5" />Mark all read</button>} />
    <div className="grid gap-4 sm:grid-cols-2"><MetricCard label="Unread notifications" value={data.unread_count} icon={BellAlertIcon} /><MetricCard label="Items shown" value={items.length} icon={ClockIcon} tone="gold" /></div>
    <section className="flex gap-1 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">{['all', 'unread'].map((name) => <button key={name} onClick={() => setFilter(name)} className={`rounded-2xl px-5 py-2.5 text-sm font-black capitalize ${filter === name ? 'bg-[#800000] text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'}`}>{name}</button>)}</section>
    {loading ? <LoadingPanel /> : items.length === 0 ? <EmptyPanel icon={BellAlertIcon} title="No notifications found" description={filter === 'unread' ? 'You are all caught up.' : 'Workflow updates addressed to you will appear here.'} /> : <div className="space-y-3">{items.map((notification) => { const Icon = icons[notification.type] || InformationCircleIcon; return <button key={notification.id} onClick={() => open(notification)} className={`flex w-full items-start gap-4 rounded-3xl border bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 ${notification.read_at ? 'border-slate-200 dark:border-slate-700' : 'border-[#800000]/30 ring-1 ring-[#800000]/10 dark:border-red-400/30'}`}><div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${notification.read_at ? 'bg-slate-100 text-slate-500 dark:bg-slate-800' : 'bg-[#800000]/10 text-[#800000] dark:text-red-300'}`}><Icon className="h-6 w-6" /></div><div className="min-w-0 flex-1"><div className="flex items-start justify-between gap-3"><h2 className="font-black text-slate-900 dark:text-white">{notification.title}</h2>{!notification.read_at && <span className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-[#800000]" />}</div><p className="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{notification.message}</p><p className="mt-2 text-xs font-semibold text-slate-400">{new Date(notification.created_at).toLocaleString()}</p></div></button>; })}</div>}
  </div>;
}
