import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import api from '../../services/api';
import CoordinatorLiveMap from '../../pages/coordinator/CoordinatorLiveMap';

const emptyLive = { active_students: [], server_time: null, filter_options: { colleges: [], programs: [] } };

export default function ScopedLiveMap({ scope }) {
  const isDean = scope === 'dean';
  const [live, setLive] = useState(emptyLive);
  const [collegeId, setCollegeId] = useState('');
  const [programId, setProgramId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const requestRef = useRef(null);

  const load = useCallback(async () => {
    if (requestRef.current) return;
    const controller = new AbortController();
    requestRef.current = controller;
    setLoading(true);
    try {
      const response = await api.get(`/${scope}/attendance/live-locations`, {
        signal: controller.signal,
        timeout: 20000,
      });
      if (controller.signal.aborted) return;
      setLive(response.data);
      setError('');
    } catch (requestError) {
      if (controller.signal.aborted) return;
      setError(requestError.response?.data?.message || 'Unable to load attendance locations.');
    } finally {
      if (requestRef.current === controller) {
        requestRef.current = null;
        setLoading(false);
      }
    }
  }, [scope]);

  useEffect(() => {
    load();
    const timer = window.setInterval(load, 15000);
    return () => {
      window.clearInterval(timer);
      requestRef.current?.abort();
      requestRef.current = null;
    };
  }, [load]);

  const students = live.active_students || emptyLive.active_students;
  const colleges = live.filter_options?.colleges || [];
  const programs = (live.filter_options?.programs || []).filter((program) =>
    !collegeId || String(program.college_id) === collegeId);
  const scopedStudents = useMemo(() => students.filter((student) =>
    (!collegeId || String(student.college_id) === collegeId) &&
    (!programId || String(student.program_id) === programId)), [students, collegeId, programId]);
  const htes = useMemo(() => Array.from(new Map(scopedStudents
    .filter((student) => student.hte_id)
    .map((student) => [String(student.hte_id), { id: student.hte_id, name: student.hte }])).values())
    .sort((a, b) => a.name.localeCompare(b.name)), [scopedStudents]);

  return <div className="space-y-6">
    <header className={`${isDean ? 'dean-dashboard-header' : 'vpaa-live-map-header'} rounded-3xl bg-[#690016] p-6 text-white sm:p-8`}>
      <p className="text-xs font-bold uppercase tracking-widest text-amber-200">{isDean ? 'Dean workspace' : 'VPAA · University oversight'}</p>
      <h1 className="mt-2 text-3xl font-black">Live Map</h1>
      <p className="mt-2 text-sm text-white/75">{isDean ? 'Timed-in interns and GPS status in your college.' : 'GPS positions of students currently timed in across all colleges and programs.'}</p>
    </header>

    <section className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <div className="flex flex-wrap items-end gap-3">
        {!isDean && <label className="block min-w-48 flex-1 text-xs font-bold text-slate-600 dark:text-slate-300">College
          <select aria-label="Filter by college" value={collegeId} onChange={(event) => { setCollegeId(event.target.value); setProgramId(''); }} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800">
            <option value="">All colleges</option>
            {colleges.map((college) => <option key={college.id} value={college.id}>{college.name}</option>)}
          </select>
        </label>}
        {isDean && <p className="min-w-48 flex-1 text-sm font-bold text-slate-700 dark:text-slate-200">College: {colleges[0]?.name || 'Assigned college'}</p>}
        <label className="block min-w-48 flex-1 text-xs font-bold text-slate-600 dark:text-slate-300">Program
          <select aria-label="Filter by program" value={programId} onChange={(event) => setProgramId(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800">
            <option value="">All programs</option>
            {programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
          </select>
        </label>
      </div>
      {error && <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{error}</p>}
    </section>

    <CoordinatorLiveMap scopeFilter={`${collegeId}:${programId}`} students={scopedStudents} htes={htes} serverTime={live.server_time} loading={loading} refreshError={error} onRefresh={load} />
  </div>;
}
