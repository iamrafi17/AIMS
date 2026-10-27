import { useEffect, useMemo, useRef, useState } from 'react';
import L from 'leaflet';
import { MapContainer, Marker, Popup, TileLayer } from 'react-leaflet';
import { FiMapPin, FiMaximize, FiMinimize, FiRefreshCw, FiSearch } from 'react-icons/fi';
import 'leaflet/dist/leaflet.css';

const MARINDUQUE = [13.4767, 121.9626];
const marker = L.divIcon({ className: 'live-student-marker', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14] });
const warningMarker = L.divIcon({ className: 'live-student-marker live-student-marker--outside', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14] });
const statusLabels = { online: 'GPS Online', delayed: 'GPS Delayed', stale: 'GPS Stale', unavailable: 'GPS Unavailable' };
const statusColors = { online: 'text-emerald-700', delayed: 'text-amber-700', stale: 'text-rose-700', unavailable: 'text-slate-500' };
const timestamp = (value) => value ? new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : 'Never';
const elapsed = (value, serverTime) => {
  if (!value) return 'Never';
  const seconds = Math.max(0, Math.floor((new Date(serverTime || Date.now()) - new Date(value)) / 1000));
  return seconds < 60 ? `${seconds}s ago` : seconds < 3600 ? `${Math.floor(seconds / 60)}m ago` : `${Math.floor(seconds / 3600)}h ago`;
};

export default function CoordinatorLiveMap({ students = [], htes = [], serverTime, loading, refreshError = '', scopeFilter = '', onRefresh }) {
  const [search, setSearch] = useState('');
  const [hteId, setHteId] = useState('');
  const [fullscreen, setFullscreen] = useState(false);
  const [fullscreenError, setFullscreenError] = useState('');
  const [clock, setClock] = useState(Date.now);
  const mapRef = useRef(null);
  const shellRef = useRef(null);
  const fittedView = useRef({ filter: null, fitted: false });
  useEffect(() => {
    setSearch('');
    setHteId('');
  }, [scopeFilter]);
  // Advance server time locally, including while refresh requests are failing.
  const clockAnchor = useMemo(() => ({ server: Date.parse(serverTime), local: Date.now() }), [serverTime]);
  const currentTime = Number.isFinite(clockAnchor.server)
    ? clockAnchor.server + Math.max(0, clock - clockAnchor.local) : clock;
  useEffect(() => {
    const timer = window.setInterval(() => setClock(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, []);
  const monitoredStudents = useMemo(() => students.map((student) => {
    if (!student.location) return { ...student, gps_status: 'unavailable' };
    const capturedAt = Date.parse(student.location.captured_at);
    const age = Number.isFinite(capturedAt) ? Math.max(0, (currentTime - capturedAt) / 1000) : Infinity;
    const gpsStatus = age <= 30 ? 'online' : age <= 120 ? 'delayed' : 'stale';
    return { ...student, gps_status: gpsStatus };
  }), [students, currentTime]);
  const query = search.trim().toLocaleLowerCase();
  const filtered = useMemo(() => monitoredStudents.filter((student) =>
    (!hteId || String(student.hte_id) === hteId) &&
    (!query || `${student.name} ${student.school_id}`.toLocaleLowerCase().includes(query))), [monitoredStudents, hteId, query]);
  const located = filtered.filter((student) => student.location && Number.isFinite(Number(student.location.latitude)) && Number.isFinite(Number(student.location.longitude)));
  const markerSet = JSON.stringify(located.map((student) => [student.location.latitude, student.location.longitude]));
  const counts = {
    online: filtered.filter((student) => student.gps_status === 'online').length,
    delayed: filtered.filter((student) => student.gps_status === 'delayed').length,
    stale: filtered.filter((student) => student.gps_status === 'stale').length,
    unavailable: filtered.filter((student) => student.gps_status === 'unavailable').length,
  };
  const fitAll = () => {
    if (!mapRef.current) return;
    const points = located.map((student) => [student.location.latitude, student.location.longitude]);
    if (points.length === 1) mapRef.current.setView(points[0], 17);
    else if (points.length) mapRef.current.fitBounds(points, { padding: [40, 40], maxZoom: 17 });
    else mapRef.current.setView(MARINDUQUE, 10);
  };
  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (!mapRef.current) return;
      const points = JSON.parse(markerSet);
      const filter = JSON.stringify([scopeFilter, hteId, query]);
      // Fit on initial locations or a filter change; GPS updates preserve the user's view.
      if (fittedView.current.filter === filter && fittedView.current.fitted) return;
      mapRef.current.invalidateSize();
      if (points.length === 1) mapRef.current?.setView(points[0], 17);
      else if (points.length) mapRef.current?.fitBounds(points, { padding: [40, 40], maxZoom: 17 });
      else mapRef.current?.setView(MARINDUQUE, 10);
      fittedView.current = { filter, fitted: points.length > 0 };
    }, 0);
    return () => window.clearTimeout(timer);
  }, [markerSet, scopeFilter, hteId, query]);
  const focus = (student) => {
    if (student.location) {
      mapRef.current?.setView([student.location.latitude, student.location.longitude], 17);
      mapRef.current?.invalidateSize();
    }
  };
  useEffect(() => {
    const shell = shellRef.current;
    let timer;
    const handleFullscreenChange = () => {
      setFullscreen(document.fullscreenElement === shell);
      window.clearTimeout(timer);
      timer = window.setTimeout(() => mapRef.current?.invalidateSize({ pan: false }), 100);
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => {
      document.removeEventListener('fullscreenchange', handleFullscreenChange);
      window.clearTimeout(timer);
    };
  }, []);
  const toggleFullscreen = async () => {
    setFullscreenError('');
    try {
      if (document.fullscreenElement === shellRef.current) await document.exitFullscreen();
      else if (shellRef.current?.requestFullscreen) await shellRef.current.requestFullscreen();
      else setFullscreenError('Fullscreen is unavailable in this browser.');
    } catch {
      setFullscreenError('Unable to change fullscreen. Please try again.');
    }
  };

  return <section className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
    <article className="rounded-3xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div><h2 className="font-black text-slate-800 dark:text-white">Timed-In Student Live Locations</h2><p className="mt-1 text-xs text-slate-400">Last known GPS positions during active Time In sessions</p></div>
        <span role="status" className={`text-xs font-bold ${refreshError ? 'text-amber-700' : 'text-emerald-700'}`}>{refreshError ? 'Connection interrupted · showing last known data' : loading ? 'Refreshing locations…' : 'Live · refreshes every 15s'}</span>
      </div>
      {fullscreenError && <p role="alert" className="mb-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">{fullscreenError}</p>}
      <div ref={shellRef} className="relative isolate h-[430px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-gray-700 dark:bg-gray-900 [&:fullscreen]:h-screen">
        <MapContainer center={MARINDUQUE} zoom={10} scrollWheelZoom className="h-full w-full" ref={mapRef}>
          <TileLayer attribution="&copy; OpenStreetMap contributors" url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maxZoom={19} />
          {located.map((student) => <Marker key={student.student_id} position={[student.location.latitude, student.location.longitude]} icon={student.gps_status === 'stale' || student.location.geofence_status === 'outside' ? warningMarker : marker}>
            <Popup><div className="min-w-48 text-xs"><p className="font-black text-[#690016]">{student.name}</p><p>{student.school_id} · {student.hte || 'No HTE'}</p><p className={statusColors[student.gps_status]}>{statusLabels[student.gps_status]}</p><p>Time In: {timestamp(student.time_in)}</p><p>Last GPS: {timestamp(student.location.captured_at)}</p><p>Accuracy: {student.location.accuracy == null ? 'Unknown' : `±${Math.round(student.location.accuracy)} m`}</p><p>Geofence: {student.location.geofence_status || 'unavailable'}</p></div></Popup>
          </Marker>)}
        </MapContainer>
        <div className="absolute left-1/2 top-3 z-[1000] flex w-[calc(100%-5rem)] max-w-xl -translate-x-1/2 flex-col gap-2 sm:flex-row" onMouseDown={(event) => event.stopPropagation()}>
          <label className="relative min-w-0 flex-1"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input aria-label="Search active student" list="active-student-suggestions" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search student name or ID..." className="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs shadow-md outline-none focus:border-[#7D001D]" /><datalist id="active-student-suggestions">{students.filter((student) => !hteId || String(student.hte_id) === hteId).map((student) => <option key={student.student_id} value={student.name}>{student.school_id}</option>)}</datalist></label>
          <select aria-label="Filter active students by HTE" value={hteId} onChange={(event) => setHteId(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs shadow-md outline-none focus:border-[#7D001D]"><option value="">All HTEs</option>{htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</select>
        </div>
        <div className="absolute bottom-4 right-3 z-[1000] flex flex-wrap justify-end gap-2"><button type="button" onClick={fitAll} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow" title="Fit all active markers">Fit All</button><button type="button" onClick={onRefresh} disabled={loading} aria-label="Refresh locations" className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow disabled:opacity-50" title="Refresh locations"><FiRefreshCw /></button><button type="button" onClick={toggleFullscreen} aria-label={fullscreen ? 'Exit fullscreen' : 'Fullscreen map'} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow" title={fullscreen ? 'Exit fullscreen' : 'Fullscreen map'}>{fullscreen ? <FiMinimize /> : <FiMaximize />}</button></div>
        {!loading && !students.length && <div className="absolute bottom-16 left-1/2 z-[1000] w-[min(90%,24rem)] -translate-x-1/2 rounded-xl bg-white/95 p-3 text-center text-xs shadow">No Active Timed-In Students. Student locations will appear when an attendance session starts.</div>}
        {!loading && students.length > 0 && !located.length && <div className="absolute bottom-16 left-1/2 z-[1000] w-[min(90%,24rem)] -translate-x-1/2 rounded-xl bg-white/95 p-3 text-center text-xs shadow">{filtered.length ? 'No GPS Locations Available. Students are timed in, but no GPS position has been received.' : 'No matching active students.'}</div>}
      </div>
    </article>
    <aside className="rounded-3xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <h3 className="text-xs font-black uppercase tracking-wider text-slate-400">GPS Monitoring Status</h3>
      <div className="mt-3 grid grid-cols-2 gap-2">{[['Timed In', filtered.length, 'text-[#7D001D]'], ['GPS Online', counts.online, 'text-emerald-700'], ['GPS Stale', counts.stale, 'text-rose-700'], ['No GPS', counts.unavailable, 'text-slate-600'], ['GPS Delayed', counts.delayed, 'text-amber-700']].map(([label, count, color]) => <div key={label} className={`rounded-xl bg-slate-50 p-3 dark:bg-gray-900 ${label === 'GPS Delayed' ? 'col-span-2' : ''}`}><p className={`text-xl font-black ${color}`}>{count}</p><p className="text-[10px] font-black uppercase text-slate-400">{label}</p></div>)}</div>
      <p className="mt-2 text-[11px] leading-4 text-slate-500">Online: updated within 30s · Delayed: 31–120s · Stale: over 120s · No GPS: no location yet</p>
      <h3 className="mt-5 text-xs font-black uppercase tracking-wider text-slate-400">Currently Timed In · {filtered.length} Students</h3>
      <div className="mt-3 max-h-[430px] space-y-2 overflow-y-auto">{filtered.map((student) => <div key={student.student_id} className="rounded-xl border border-slate-200 p-3 text-xs dark:border-gray-700"><div className="flex items-start gap-2"><FiMapPin className="mt-0.5 shrink-0 text-[#7D001D]" /><div className="min-w-0"><p className="font-black text-slate-800 dark:text-white">{student.name}</p><p className="text-slate-500">{student.school_id} · {student.hte || 'No HTE'} · {student.program}</p><p className={`mt-1 font-bold ${statusColors[student.gps_status]}`}>{statusLabels[student.gps_status]}</p>{student.gps_status === 'stale' && <p className="font-bold text-rose-700">GPS disconnected or delayed. Verify with the student.</p>}<p className="mt-1 text-slate-500">Time In: {timestamp(student.time_in)}</p><p className="text-slate-500">Last GPS: {elapsed(student.location?.captured_at, currentTime)}</p>{student.location && <><p className="text-slate-500">Accuracy: {student.location.accuracy == null ? 'Unknown' : `±${Math.round(student.location.accuracy)} m`}</p><p className="text-slate-500">Geofence: {student.location.geofence_status || 'unavailable'}</p><button type="button" onClick={() => focus(student)} className="mt-2 font-black text-[#7D001D]">{student.gps_status === 'stale' ? 'Last Known Location' : 'Focus on Map'}</button></>}</div></div></div>)}{!filtered.length && <p className="rounded-xl border border-dashed border-slate-300 p-5 text-center text-xs text-slate-400">{students.length ? 'No matching active students.' : 'No active timed-in students.'}</p>}</div>
      <p className="mt-4 rounded-xl bg-blue-50 p-3 text-[11px] leading-5 text-blue-700">A stale GPS signal does not end the attendance session. Last known positions remain until Time Out.</p>
    </aside>
  </section>;
}
