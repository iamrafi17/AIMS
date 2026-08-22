import { useCallback, useEffect, useMemo, useState } from 'react';
import L from 'leaflet';
import { MapContainer, Marker, Polyline, Popup, TileLayer, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import {
  ArrowPathIcon,
  CameraIcon,
  CheckCircleIcon,
  ClockIcon,
  MapIcon,
  MapPinIcon,
  PlayIcon,
  StopIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const eventIcons = {
  start: L.divIcon({ className: 'travel-point travel-point--departure', html: '<span>S</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
  departure: L.divIcon({ className: 'travel-point travel-point--departure', html: '<span>D</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
  checkpoint: L.divIcon({ className: 'travel-point travel-point--checkpoint', html: '<span>C</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
  final_arrival: L.divIcon({ className: 'travel-point travel-point--arrival', html: '<span>A</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
};

const statusStyles = {
  active: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
  traveling: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
  arrived: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
  completed: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
  cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
};

const reportLabels = {
  draft: 'Draft report',
  pending_program_head: 'Pending Program Head Validation',
  pending_vpaa: 'Pending VPAA Validation',
  final_validated: 'Final Validated',
};

function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat()[0] : error.response?.data?.message || fallback;
}

function currentLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('This browser does not support location services.'));
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ latitude: Number(coords.latitude), longitude: Number(coords.longitude), accuracy: Number(coords.accuracy) }),
      (error) => reject(new Error({ 1: 'Location permission was denied. Enable location access and try again.', 2: 'Your GPS location is unavailable. Move to an open area and try again.', 3: 'GPS capture timed out. Please try again.' }[error.code] || 'Unable to capture location.')),
      { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
    );
  });
}

function FitRoute({ positions }) {
  const map = useMap();
  useEffect(() => {
    window.setTimeout(() => {
      map.invalidateSize();
      if (positions.length === 1) map.setView(positions[0], 17);
      if (positions.length > 1) map.fitBounds(positions, { padding: [36, 36], maxZoom: 17 });
    }, 0);
  }, [map, positions]);
  return null;
}

function RouteMap({ points = [] }) {
  const positions = useMemo(() => points.map((point) => [point.latitude, point.longitude]), [points]);
  if (!positions.length) return <div className="grid h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">No GPS route points recorded.</div>;

  return (
    <div className="h-[360px] overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-700 sm:h-[440px]">
      <MapContainer center={positions[0]} zoom={17} scrollWheelZoom className="h-full w-full">
        <TileLayer attribution='&copy; OpenStreetMap contributors' url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maxZoom={19} />
        <FitRoute positions={positions} />
        {positions.length > 1 && <Polyline positions={positions} pathOptions={{ color: '#7D001D', weight: 5, opacity: 0.8 }} />}
        {points.map((point) => <Marker key={point.id} position={[point.latitude, point.longitude]} icon={eventIcons[point.event_type] || eventIcons.checkpoint}><Popup><p className="font-black text-[#7D001D]">{point.label}</p><p>{point.readable_location}</p><p>{Number(point.latitude).toFixed(6)}, {Number(point.longitude).toFixed(6)}</p><p>{new Date(point.captured_at).toLocaleString()}</p></Popup></Marker>)}
      </MapContainer>
    </div>
  );
}

function ProtectedImage({ endpoint }) {
  const [source, setSource] = useState('');
  useEffect(() => {
    if (!endpoint) return undefined;
    let url = '';
    api.get(endpoint, { responseType: 'blob' }).then(({ data }) => { url = URL.createObjectURL(data); setSource(url); }).catch(() => setSource(''));
    return () => { if (url) URL.revokeObjectURL(url); };
  }, [endpoint]);
  return source ? <img src={source} alt="Arrival evidence" className="h-52 w-full rounded-2xl object-cover sm:w-80" /> : null;
}

export default function StudentTravel() {
  const [data, setData] = useState({ student: null, coordinator: null, can_start: { allowed: false, reasons: [] }, current_session: null, history: [] });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [remarks, setRemarks] = useState('');
  const [photo, setPhoto] = useState(null);

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    try { setData((await api.get('/student/travel')).data); }
    catch (error) { toast.error(errorMessage(error, 'Unable to load travel monitoring.')); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);
  const session = data.current_session;

  const performGpsAction = async (action, endpoint, payload = {}) => {
    setBusy(action);
    try {
      const gps = await currentLocation();
      const response = await api.post(endpoint, { ...gps, ...payload });
      toast.success(response.data.message);
      await load(true);
      if (action === 'checkpoint') setRemarks('');
    } catch (error) { toast.error(error.response ? errorMessage(error, 'Unable to save the GPS event.') : error.message); }
    finally { setBusy(''); }
  };

  const uploadPhoto = async () => {
    if (!session || !photo) return;
    setBusy('photo');
    try {
      const form = new FormData(); form.append('photo', photo);
      const response = await api.post(`/student/travel/${session.id}/arrival-photo`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success(response.data.message); setPhoto(null); await load(true);
    } catch (error) { toast.error(errorMessage(error, 'Arrival photo was not uploaded successfully.')); }
    finally { setBusy(''); }
  };

  const endSession = async () => {
    setBusy('end');
    try { const response = await api.post(`/student/travel/${session.id}/end`); toast.success(response.data.message); await load(true); }
    catch (error) { toast.error(errorMessage(error, 'Unable to end the travel session.')); }
    finally { setBusy(''); }
  };

  if (loading) return <LoadingPanel />;

  return (
    <div className="space-y-6">
      <PageIntro eyebrow="Student-controlled GPS record" title="Travel Monitoring" description="Start your own session, record route checkpoints, upload arrival evidence, and follow the official report validation status." icon={MapIcon} actions={!session && <button disabled={!data.can_start.allowed || busy} onClick={() => performGpsAction('start', '/student/travel/start')} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#690016] disabled:cursor-not-allowed disabled:opacity-40"><PlayIcon className="h-5 w-5" />{busy === 'start' ? 'Capturing GPS...' : 'Start Session'}</button>} />

      {!session && !data.can_start.allowed && <section className="rounded-3xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30"><p className="font-black text-amber-800 dark:text-amber-200">Travel session unavailable</p><ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-700 dark:text-amber-300">{data.can_start.reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul></section>}

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Assigned HTE" value={data.student?.hte?.name || 'Not assigned'} icon={MapPinIcon} />
        <MetricCard label="Coordinator" value={data.coordinator?.name || 'Not assigned'} icon={CheckCircleIcon} tone="violet" />
        <MetricCard label="Current status" value={session?.status || 'Not started'} icon={ArrowPathIcon} tone="green" />
        <MetricCard label="Travel history" value={data.history.length} icon={ClockIcon} tone="gold" />
      </section>

      {session && <>
        <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{session.session_code}</p><h2 className="mt-1 text-2xl font-black">{session.hte?.name}</h2><p className="mt-1 text-sm text-slate-500">{session.hte?.address}</p></div><span className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-black capitalize ${statusStyles[session.status]}`}>{session.status}</span></div><p className="mt-4 text-sm text-slate-500">Started {new Date(session.start_time).toLocaleString()} · {session.points.length} route point{session.points.length === 1 ? '' : 's'}</p></section>
        <RouteMap points={session.points} />
        <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><h2 className="text-xl font-black">Travel actions</h2><p className="mt-1 text-sm text-slate-500">Every action captures a fresh GPS position and preserves its timestamp and accuracy.</p><div className="mt-5 flex flex-wrap gap-3">{session.status === 'active' && <button disabled={busy} onClick={() => performGpsAction('departure', `/student/travel/${session.id}/departure`)} className="rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40">{busy === 'departure' ? 'Capturing...' : 'Start Departure'}</button>}{session.status === 'traveling' && <button disabled={busy} onClick={() => performGpsAction('arrival', `/student/travel/${session.id}/arrival`)} className="rounded-xl bg-emerald-600 px-5 py-3 font-black text-white disabled:opacity-40">{busy === 'arrival' ? 'Capturing...' : 'Mark Final Arrival'}</button>}</div>{session.status === 'traveling' && <div className="mt-5 flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 sm:flex-row"><input value={remarks} onChange={(event) => setRemarks(event.target.value)} maxLength={1000} placeholder="Optional checkpoint remarks" className="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700" /><button disabled={busy} onClick={() => performGpsAction('checkpoint', `/student/travel/${session.id}/checkpoints`, { remarks })} className="rounded-xl border border-[#7D001D]/30 px-5 py-3 font-black text-[#7D001D] disabled:opacity-40 dark:text-red-300">{busy === 'checkpoint' ? 'Saving...' : 'Add Checkpoint'}</button></div>}</section>
        {session.status === 'arrived' && <section className="rounded-3xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30 sm:p-6"><div className="flex items-start gap-3"><CameraIcon className="h-7 w-7 shrink-0 text-amber-700" /><div className="w-full"><h2 className="text-xl font-black">Arrival photo required</h2><p className="mt-1 text-sm text-slate-600 dark:text-slate-300">Upload a JPG, PNG, or WebP image up to 5 MB before ending the session.</p>{session.has_arrival_photo && <div className="mt-4"><ProtectedImage endpoint={session.arrival_photo_endpoint} /></div>}<div className="mt-4 flex flex-wrap gap-2"><input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => setPhoto(event.target.files?.[0] || null)} className="max-w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm dark:bg-slate-900" /><button disabled={!photo || busy} onClick={uploadPhoto} className="rounded-xl bg-amber-600 px-5 py-2 font-black text-white disabled:opacity-40">{busy === 'photo' ? 'Uploading...' : 'Upload Photo'}</button></div></div></div></section>}
        {session.status === 'arrived' && <section className="flex flex-col gap-4 rounded-3xl bg-slate-950 p-5 text-white sm:flex-row sm:items-center sm:justify-between"><div><p className="font-black">End travel session</p><p className="mt-1 text-sm text-slate-300">Final arrival saved · Photo {session.has_arrival_photo ? 'uploaded' : 'required'}</p></div><button disabled={!session.has_arrival_photo || busy} onClick={endSession} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-6 py-3 font-black text-[#690016] disabled:opacity-40"><StopIcon className="h-5 w-5" />{busy === 'end' ? 'Ending...' : 'End Session'}</button></section>}
      </>}

      <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><div className="mb-4 flex items-center justify-between"><div><h2 className="text-xl font-black">Travel History</h2><p className="text-sm text-slate-500">Your completed and previous sessions with report validation status.</p></div><button onClick={() => load()} aria-label="Refresh history" className="rounded-xl border border-slate-200 p-2 dark:border-slate-700"><ArrowPathIcon className="h-5 w-5" /></button></div>{data.history.length ? <div className="space-y-3">{data.history.map((item) => <article key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-black uppercase text-[#a8750b]">{item.session_code}</p><p className="mt-1 font-black">{item.hte?.name}</p><p className="mt-1 text-xs text-slate-500">{item.points.length} route points · {item.start_time ? new Date(item.start_time).toLocaleString() : 'Unknown date'}</p></div><div className="flex flex-wrap gap-2"><span className={`rounded-full px-3 py-1 text-xs font-black capitalize ${statusStyles[item.status] || statusStyles.completed}`}>{item.status}</span><span className="rounded-full bg-[#7D001D]/10 px-3 py-1 text-xs font-black text-[#7D001D] dark:text-red-300">{item.report ? reportLabels[item.report.status] || item.report.status : 'Not Yet Generated'}</span></div></div></article>)}</div> : <EmptyPanel icon={MapIcon} title="No travel history" description="Completed sessions will appear here." />}</section>
    </div>
  );
}
