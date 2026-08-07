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
    <div className="space-y-6">
      <PageIntro
        eyebrow="Private account history"
        title="My Activity"
        description="Review important actions completed through your account. This history is private and is never visible to other users or administrators."
        icon={ClipboardDocumentCheckIcon}
      />

      {loading ? (
        <LoadingPanel />
      ) : error ? (
        <section className="rounded-3xl border border-rose-200 bg-rose-50 p-8 text-center dark:border-rose-900/60 dark:bg-rose-950/30">
          <p className="font-bold text-rose-700 dark:text-rose-300">{error}</p>
          <button type="button" onClick={load} className="mt-4 rounded-xl bg-[#800000] px-5 py-2.5 text-sm font-black text-white">
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
          <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px]">
                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                  <tr>
                    <th className="px-6 py-4">Date and Time</th>
                    <th className="px-6 py-4">Activity Type</th>
                    <th className="px-6 py-4">Activity Description</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {logs.map((log) => (
                    <tr key={log.id} className="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="whitespace-nowrap px-6 py-5 text-sm text-slate-500 dark:text-slate-400">
                        <span className="inline-flex items-center gap-2">
                          <ClockIcon className="h-4 w-4" />
                          {formatDateTime(log.created_at)}
                        </span>
                      </td>
                      <td className="px-6 py-5">
                        <span className="inline-flex rounded-full bg-[#800000]/10 px-3 py-1 text-xs font-black text-[#800000] dark:bg-red-400/10 dark:text-red-300">
                          {log.activity_type}
                        </span>
                      </td>
                      <td className="px-6 py-5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {log.description}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
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
