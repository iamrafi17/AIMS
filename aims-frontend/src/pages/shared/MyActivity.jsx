import { useCallback, useEffect, useState } from 'react';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  ClipboardDocumentCheckIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, PageIntro } from '../../components/common/PortalUI';

function formatDateTime(value) {
  return new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function activityTone(type) {
  const normalized = type?.toLowerCase() || '';

  if (normalized.includes('authentication')) return 'bg-blue-50 text-blue-700 ring-blue-600/10 dark:bg-blue-400/10 dark:text-blue-300';
  if (normalized.includes('evaluation')) return 'bg-violet-50 text-violet-700 ring-violet-600/10 dark:bg-violet-400/10 dark:text-violet-300';
  if (normalized.includes('attendance') || normalized.includes('time')) return 'bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-400/10 dark:text-emerald-300';
  if (normalized.includes('profile')) return 'bg-cyan-50 text-cyan-700 ring-cyan-600/10 dark:bg-cyan-400/10 dark:text-cyan-300';
  if (normalized.includes('management') || normalized.includes('settings')) return 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-400/10 dark:text-amber-300';

  return 'bg-[#7D001D]/8 text-[#7D001D] ring-[#7D001D]/10 dark:bg-rose-400/10 dark:text-rose-300';
}

export default function MyActivity() {
  const [logs, setLogs] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const response = await api.get('/activity-logs', { params: { page } });
      setLogs(response.data.data || []);
      setLastPage(response.data.last_page || 1);
    } catch {
      setError('Your activity history could not be loaded right now.');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div className="mx-auto w-full max-w-5xl space-y-5">
      <PageIntro
        eyebrow="Private account history"
        title="My Activity"
        description="Review important actions completed through your account. This history is private and is never visible to other users or administrators."
        icon={ClipboardDocumentCheckIcon}
      />

      {loading ? (
        <LoadingPanel />
      ) : error ? (
        <section className="!rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center dark:border-rose-900/60 dark:bg-rose-950/30">
          <p className="font-bold text-rose-700 dark:text-rose-300">{error}</p>
          <button type="button" onClick={load} className="mt-4 rounded-xl bg-[#7D001D] px-5 py-2.5 text-sm font-black text-white">
            Try again
          </button>
        </section>
      ) : logs.length === 0 ? (
        <EmptyPanel
          icon={ClipboardDocumentCheckIcon}
          title="No activity yet"
          description="Important actions such as sign-ins, profile updates, approvals, submissions, and attendance logs will appear here."
        />
      ) : (
        <>
          <section className="overflow-hidden !rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <div className="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:px-5 dark:border-slate-700 dark:bg-slate-800/50">
              <div>
                <h2 className="!text-sm !font-bold text-slate-800 dark:!text-white">Recent activity</h2>
                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Latest actions recorded for this account</p>
              </div>
              <span className="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-500 ring-1 ring-inset ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">{logs.length} entries</span>
            </div>

            <ol className="divide-y divide-slate-100 dark:divide-slate-800">
              {logs.map((log) => (
                <li key={log.id} className="flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50/70 sm:items-center sm:px-5 dark:hover:bg-slate-800/35">
                  <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <ClockIcon className="size-4.5" aria-hidden="true" />
                  </span>

                  <div className="min-w-0 flex-1">
                    <div className="flex flex-col items-start gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                      <span className={`inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset ${activityTone(log.activity_type)}`}>
                        {log.activity_type}
                      </span>
                      <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">{log.description}</p>
                    </div>
                    <time dateTime={log.created_at} className="mt-1.5 block text-xs text-slate-400 sm:hidden">{formatDateTime(log.created_at)}</time>
                  </div>

                  <time dateTime={log.created_at} className="hidden shrink-0 text-right text-xs font-medium text-slate-400 sm:block">{formatDateTime(log.created_at)}</time>
                </li>
              ))}
            </ol>
          </section>

          {lastPage > 1 && (
            <nav className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-label="Activity history pages">
              <button
                type="button"
                disabled={page === 1}
                onClick={() => setPage((current) => current - 1)}
                className="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-200 dark:hover:bg-slate-800"
              >
                <ChevronLeftIcon className="h-4 w-4" />
                Previous
              </button>
              <p className="text-xs font-bold text-slate-500 dark:text-slate-400">Page {page} of {lastPage}</p>
              <button
                type="button"
                disabled={page === lastPage}
                onClick={() => setPage((current) => current + 1)}
                className="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-200 dark:hover:bg-slate-800"
              >
                Next
                <ChevronRightIcon className="h-4 w-4" />
              </button>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
