import { useCallback, useEffect, useState } from 'react';
import {
  ClockIcon,
  MapIcon,
  MapPinIcon,
  ShieldCheckIcon,
  UserGroupIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, PageIntro } from '../../components/common/PortalUI';

const statusStyles = {
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  completed: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
};

function pointLabel(point) {
  if (point.is_final_destination) return 'Final Arrival';
  return point.point_type === 'arrival' ? 'Arrival' : 'Departure';
}

export default function StudentTravel() {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setSessions((await api.get('/student/travel')).data || []);
    } catch {
      toast.error('Unable to load official travel sessions.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  if (loading && !sessions.length) return <LoadingPanel />;

  return (
    <div className="space-y-6">
      <PageIntro eyebrow="Official travel history" title="My Travel Sessions" description="View Coordinator-recorded participants, departure and arrival points, and completed route history." icon={MapIcon} />

      {sessions.length === 0 ? (
        <EmptyPanel icon={MapIcon} title="No official travel sessions" description="Sessions where you are a listed participant will appear here." />
      ) : (
        <div className="space-y-5">
          {sessions.map((session) => (
            <article key={session.id} className="border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
              <header className="border-b border-slate-100 p-5 dark:border-slate-800 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p className="text-xs font-black uppercase text-[#a8750b]">{session.session_code}</p>
                    <h2 className="mt-1 text-xl font-black">{session.hte?.name || 'Official travel session'}</h2>
                    <p className="mt-1 text-sm text-slate-500">{session.hte?.address}</p>
                  </div>
                  <span className={`px-3 py-1 text-xs font-black capitalize ${statusStyles[session.status] || statusStyles.active}`}>{session.status}</span>
                </div>
                <div className="mt-4 flex flex-wrap gap-4 text-xs text-slate-500">
                  <span className="inline-flex items-center gap-2"><ShieldCheckIcon className="h-4 w-4" />{session.creator?.name || 'Internship Coordinator'}</span>
                  <span className="inline-flex items-center gap-2"><UserGroupIcon className="h-4 w-4" />{session.participants?.length || 0} participants</span>
                  <span className="inline-flex items-center gap-2"><ClockIcon className="h-4 w-4" />{session.start_time ? new Date(session.start_time).toLocaleString() : 'Not started'}</span>
                </div>
              </header>

              <div className="grid gap-6 p-5 sm:p-6 lg:grid-cols-[0.8fr_1.2fr]">
                <section>
                  <h3 className="text-sm font-black">Participants</h3>
                  <div className="mt-3 space-y-2">
                    {session.participants?.map((participant) => (
                      <div key={participant.id} className="bg-slate-50 p-3 dark:bg-slate-800">
                        <p className="text-sm font-bold">{participant.first_name} {participant.last_name}</p>
                        <p className="mt-1 text-xs text-slate-500">{participant.student_id}</p>
                      </div>
                    ))}
                  </div>
                </section>

                <section>
                  <h3 className="text-sm font-black">Saved route points</h3>
                  <div className="mt-3 space-y-2">
                    {session.checkpoints?.map((point) => (
                      <div key={point.id} className="flex gap-3 border border-slate-200 p-3 dark:border-slate-700">
                        <MapPinIcon className={`mt-0.5 h-5 w-5 shrink-0 ${point.point_type === 'arrival' ? 'text-emerald-600' : 'text-[#7D001D]'}`} />
                        <div className="min-w-0">
                          <p className="text-sm font-black">{String(point.sequence).padStart(2, '0')} {pointLabel(point)}</p>
                          <p className="mt-1 text-xs text-slate-500">{point.readable_location || `${Number(point.latitude).toFixed(6)}, ${Number(point.longitude).toFixed(6)}`}</p>
                          <p className="mt-1 text-xs text-slate-400">{point.captured_at ? new Date(point.captured_at).toLocaleString() : 'Time unavailable'}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
