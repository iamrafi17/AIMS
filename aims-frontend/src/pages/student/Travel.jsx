import { useCallback, useEffect, useState } from 'react';
import {
  ArrowPathIcon,
  CalendarDaysIcon,
  CameraIcon,
  CheckCircleIcon,
  ClockIcon,
  MapIcon,
  MapPinIcon,
  PlayIcon,
  ShieldCheckIcon,
  StopIcon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, PageIntro } from '../../components/common/PortalUI';

const statusStyles = {
  scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  completed: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
};

function getLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('Location services are not supported by this browser.'));
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
      () => reject(new Error('Allow location permission to continue.')),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  });
}

function CheckpointModal({ checkpoint, onClose, onSaved }) {
  const [notes, setNotes] = useState(checkpoint.notes || '');
  const [photo, setPhoto] = useState(null);
  const [saving, setSaving] = useState(false);
  const submit = async (event) => {
    event.preventDefault(); setSaving(true);
    try {
      const location = await getLocation();
      const form = new FormData(); form.append('latitude', location.latitude); form.append('longitude', location.longitude); form.append('notes', notes); if (photo) form.append('photo', photo);
      const response = await api.post(`/student/travel/checkpoints/${checkpoint.id}`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message); onSaved();
    } catch (error) { toast.error(error.response?.data?.message || error.message || 'Unable to submit checkpoint evidence.'); }
    finally { setSaving(false); }
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={onClose}><form onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900"><div className="flex items-start justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">GPS checkpoint</p><h2 className="mt-1 text-2xl font-black">{checkpoint.checkpoint_name}</h2></div><button type="button" onClick={onClose}><XMarkIcon className="h-6 w-6 text-slate-500" /></button></div><div className="mt-6 rounded-2xl bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-200"><p className="flex items-center gap-2 font-bold"><MapPinIcon className="h-5 w-5" />Your live GPS location will be captured when submitted.</p></div><label className="mt-5 block"><span className="mb-2 block text-sm font-bold">Geotagged photo</span><input type="file" accept="image/*" capture="environment" onChange={(event) => setPhoto(event.target.files[0] || null)} className="w-full rounded-2xl border border-slate-200 px-3 py-3 dark:border-slate-700" /></label><label className="mt-4 block"><span className="mb-2 block text-sm font-bold">Checkpoint notes</span><textarea value={notes} onChange={(event) => setNotes(event.target.value)} className="min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700" placeholder="Describe your arrival, activity, or any route concern..." /></label><div className="mt-6 flex justify-end gap-3"><button type="button" onClick={onClose} className="rounded-2xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Cancel</button><button disabled={saving} className="inline-flex items-center gap-2 rounded-2xl bg-[#800000] px-6 py-3 font-bold text-white"><CameraIcon className="h-5 w-5" />{saving ? 'Capturing...' : 'Submit evidence'}</button></div></form></div>;
}

export default function StudentTravel() {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [checkpoint, setCheckpoint] = useState(null);
  const load = useCallback(async () => { setLoading(true); try { setSessions((await api.get('/student/travel')).data || []); } catch { toast.error('Unable to load assigned travel sessions.'); } finally { setLoading(false); } }, []);
  useEffect(() => { load(); }, [load]);
  const runAction = async (session, action) => {
    if (action === 'end' && !window.confirm('Complete this travel session now?')) return;
    setBusy(true);
    try { const location = action === 'start' ? await getLocation() : {}; const response = await api.post(`/student/travel/${session.id}/${action}`, location); toast.success(response.data.message); load(); }
    catch (error) { toast.error(error.response?.data?.message || error.message || 'Unable to update travel session.'); }
    finally { setBusy(false); }
  };
  if (loading && !sessions.length) return <LoadingPanel />;
  const active = sessions.filter((session) => session.status === 'active').length;
  const upcoming = sessions.filter((session) => session.status === 'scheduled').length;
  const completed = sessions.filter((session) => session.status === 'completed').length;
  return <div className="space-y-6"><PageIntro eyebrow="Coordinator-approved travel" title="My Travel Sessions" description="View assigned routes and companions, start using verified server time and GPS, submit checkpoint evidence, and complete authorized travel." icon={MapIcon} /><div className="grid gap-4 sm:grid-cols-3"><div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Upcoming</p><p className="mt-2 text-3xl font-black">{upcoming}</p></div><div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Active now</p><p className="mt-2 text-3xl font-black text-emerald-600">{active}</p></div><div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Completed</p><p className="mt-2 text-3xl font-black">{completed}</p></div></div>{sessions.length === 0 ? <EmptyPanel icon={MapIcon} title="No assigned travel" description="Only the Internship Coordinator can create and assign an official travel session." /> : <div className="space-y-6">{sessions.map((session) => <article key={session.id} className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"><div className="bg-gradient-to-r from-[#800000]/8 to-amber-50 p-6 dark:from-red-400/10 dark:to-amber-950/20"><div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{session.session_code}</p><h2 className="mt-1 text-2xl font-black">{session.destination}</h2><p className="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{session.purpose}</p></div><span className={`w-fit rounded-full px-3 py-1 text-xs font-black capitalize ${statusStyles[session.status]}`}>{session.status}</span></div><div className="mt-5 flex flex-wrap gap-4 text-sm text-slate-500"><span className="inline-flex items-center gap-2"><CalendarDaysIcon className="h-5 w-5" />{session.scheduled_at ? new Date(session.scheduled_at).toLocaleString() : 'Schedule pending'}</span><span className="inline-flex items-center gap-2"><UserGroupIcon className="h-5 w-5" />{session.companions?.length || 0} companion(s)</span><span className="inline-flex items-center gap-2"><ShieldCheckIcon className="h-5 w-5" />Created by {session.creator?.name || 'Internship Coordinator'}</span></div></div><div className="p-6"><div className="grid gap-5 lg:grid-cols-[1fr_1.3fr]"><div><h3 className="font-black">Approved route instructions</h3><p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-500">{session.route_notes || 'Follow the configured checkpoints and coordinator instructions.'}</p>{session.companions?.length > 0 && <div className="mt-5"><h3 className="font-black">Travel companions</h3><div className="mt-2 space-y-2">{session.companions.map((person) => <div key={person.id} className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"><p className="text-sm font-bold">{person.name}</p><p className="text-xs capitalize text-slate-500">{person.type} · {person.relationship || 'Companion'} {person.contact ? `· ${person.contact}` : ''}</p></div>)}</div></div>}</div><div><h3 className="font-black">Route checkpoints</h3><div className="mt-3 space-y-3">{session.checkpoints?.map((stop, index) => <div key={stop.id} className="flex items-center gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><div className={`grid h-10 w-10 shrink-0 place-items-center rounded-full font-black ${stop.photo_path ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500 dark:bg-slate-700'}`}>{stop.photo_path ? <CheckCircleIcon className="h-5 w-5" /> : index + 1}</div><div className="min-w-0 flex-1"><p className="font-black">{stop.checkpoint_name}</p><p className="mt-1 text-xs text-slate-500">{stop.expected_at ? `Expected ${new Date(stop.expected_at).toLocaleString()}` : `${Number(stop.latitude).toFixed(5)}, ${Number(stop.longitude).toFixed(5)}`}</p>{stop.photo_path && <p className={`mt-1 text-xs font-bold ${stop.is_verified ? 'text-emerald-600' : 'text-amber-600'}`}>{stop.is_verified ? 'Evidence verified' : 'Evidence pending verification'}</p>}</div>{session.status === 'active' && <button onClick={() => setCheckpoint(stop)} className="rounded-xl bg-[#800000] px-3 py-2 text-xs font-black text-white">{stop.photo_path ? 'Update' : 'Check in'}</button>}</div>)}</div></div></div><div className="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">{session.status === 'scheduled' && <button disabled={busy} onClick={() => runAction(session, 'start')} className="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 font-black text-white"><PlayIcon className="h-5 w-5" />Start with GPS</button>}{session.status === 'active' && <><span className="mr-auto inline-flex items-center gap-2 text-sm font-bold text-emerald-600"><ArrowPathIcon className="h-5 w-5 animate-spin" />Travel currently active</span><button disabled={busy} onClick={() => runAction(session, 'end')} className="inline-flex items-center gap-2 rounded-2xl bg-[#800000] px-5 py-3 font-black text-white"><StopIcon className="h-5 w-5" />Complete travel</button></>}{session.start_time && <span className="inline-flex items-center gap-2 text-xs text-slate-500"><ClockIcon className="h-4 w-4" />Started {new Date(session.start_time).toLocaleString()}</span>}</div></div></article>)}</div>}{checkpoint && <CheckpointModal checkpoint={checkpoint} onClose={() => setCheckpoint(null)} onSaved={() => { setCheckpoint(null); load(); }} />}</div>;
}
