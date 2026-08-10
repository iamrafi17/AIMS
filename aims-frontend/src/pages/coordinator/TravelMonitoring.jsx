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
  UserGroupIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, MetricCard, PageIntro } from '../../components/common/PortalUI';

const statusStyles = {
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  completed: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
};

const pointIcons = {
  departure: L.divIcon({ className: 'travel-point travel-point--departure', html: '<span>D</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
  arrival: L.divIcon({ className: 'travel-point travel-point--arrival', html: '<span>A</span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
};

function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat()[0] : error.response?.data?.message || fallback;
}

function ProtectedImage({ endpoint, alt, className }) {
  const [source, setSource] = useState('');

  useEffect(() => {
    if (!endpoint) return undefined;
    let objectUrl = '';
    let mounted = true;
    api.get(endpoint, { responseType: 'blob' }).then((response) => {
      objectUrl = URL.createObjectURL(response.data);
      if (mounted) setSource(objectUrl);
    }).catch(() => setSource(''));

    return () => {
      mounted = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [endpoint]);

  return source ? <img src={source} alt={alt} className={className} /> : null;
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString() : 'Unavailable';
}

function formatTime(value) {
  return value ? new Date(value).toLocaleTimeString() : 'Unavailable';
}

function pointTitle(point) {
  if (point.is_final_destination) return 'Final Arrival';
  return point.point_type === 'arrival' ? 'Arrival' : 'Departure';
}

function requestCurrentLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('This browser does not support location services.'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        const latitude = Number(coords.latitude);
        const longitude = Number(coords.longitude);
        if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90 || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) {
          reject(new Error('The browser returned invalid GPS coordinates. Please try again.'));
          return;
        }
        resolve({ latitude, longitude });
      },
      (error) => {
        const messages = {
          1: 'Location permission was denied. Allow location access in your browser settings and try again.',
          2: 'Your current GPS location is unavailable. Move to an open area and try again.',
          3: 'Getting your GPS location timed out. Please try again.',
        };
        reject(new Error(messages[error.code] || 'Unable to obtain your current GPS location.'));
      },
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

function RouteMap({ points }) {
  const positions = useMemo(() => points.map((point) => [point.latitude, point.longitude]), [points]);

  if (!positions.length) {
    return (
      <div className="grid h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center dark:border-slate-700 dark:bg-slate-900">
        <div><MapPinIcon className="mx-auto h-10 w-10 text-slate-300" /><p className="mt-3 font-black">No captured points yet</p><p className="mt-1 text-sm text-slate-500">The map will begin with the saved departure point.</p></div>
      </div>
    );
  }

  return (
    <div className="h-[360px] overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-700 sm:h-[460px]">
      <MapContainer center={positions[0]} zoom={17} scrollWheelZoom className="h-full w-full">
        <TileLayer attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maxZoom={19} />
        <FitRoute positions={positions} />
        {positions.length > 1 && <Polyline positions={positions} pathOptions={{ color: '#7D001D', weight: 5, opacity: 0.8 }} />}
        {points.map((point) => (
          <Marker key={point.id} position={[point.latitude, point.longitude]} icon={pointIcons[point.point_type] || pointIcons.departure}>
            <Popup>
              <div className="min-w-48 text-sm">
                <p className="font-black text-[#7D001D]">{pointTitle(point)}</p>
                <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                  <dt className="font-bold">Date</dt><dd>{formatDate(point.captured_at)}</dd>
                  <dt className="font-bold">Time</dt><dd>{formatTime(point.captured_at)}</dd>
                  <dt className="font-bold">Latitude</dt><dd>{Number(point.latitude).toFixed(8)}</dd>
                  <dt className="font-bold">Longitude</dt><dd>{Number(point.longitude).toFixed(8)}</dd>
                </dl>
              </div>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}

function StudentTable({ students, selectedIds, onToggle, disabled }) {
  if (!students.length) {
    return <EmptyPanel icon={UserGroupIcon} title="No assigned students" description="This HTE has no approved students assigned in the database." />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
      <table className="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-700">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th className="px-4 py-3">Join</th><th className="px-4 py-3">School number</th><th className="px-4 py-3">Full name</th><th className="px-4 py-3">Program</th><th className="px-4 py-3">Department</th><th className="px-4 py-3">HTE</th></tr></thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
          {students.map((student) => <tr key={student.id}><td className="px-4 py-3"><input aria-label={`Select ${student.full_name}`} type="checkbox" checked={selectedIds.includes(student.id)} disabled={disabled} onChange={() => onToggle(student.id)} className="h-5 w-5 accent-[#7D001D]" /></td><td className="px-4 py-3 font-black">{student.school_number}</td><td className="px-4 py-3">{student.full_name}</td><td className="px-4 py-3">{student.program?.code || '—'}</td><td className="px-4 py-3">{student.department?.name || '—'}</td><td className="px-4 py-3">{student.hte?.name || '—'}</td></tr>)}
        </tbody>
      </table>
    </div>
  );
}

export default function TravelMonitoring() {
  const [setup, setSetup] = useState({ htes: [] });
  const [history, setHistory] = useState({ sessions: [], summary: {} });
  const [session, setSession] = useState(null);
  const [loading, setLoading] = useState(true);
  const [preparing, setPreparing] = useState(false);
  const [selectedHteId, setSelectedHteId] = useState('');
  const [students, setStudents] = useState([]);
  const [studentsLoading, setStudentsLoading] = useState(false);
  const [selectedStudentIds, setSelectedStudentIds] = useState([]);
  const [gpsReady, setGpsReady] = useState(false);
  const [busy, setBusy] = useState('');
  const [arrivalPhoto, setArrivalPhoto] = useState(null);
  const [completionMessage, setCompletionMessage] = useState('');

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    try {
      const [setupResponse, activeResponse, historyResponse] = await Promise.all([
        api.get('/coordinator/travel/setup'),
        api.get('/coordinator/travel/active'),
        api.get('/coordinator/travel'),
      ]);
      setSetup(setupResponse.data);
      setSession(activeResponse.data.session);
      setHistory(historyResponse.data);
      if (activeResponse.data.session) setPreparing(false);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load travel monitoring data.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const departure = session?.points?.find((point) => point.point_type === 'departure');
  const finalArrival = session?.points?.find((point) => point.is_final_destination);
  const lastPoint = session?.points?.at(-1);
  const selectedHte = setup.htes.find((hte) => String(hte.id) === String(selectedHteId));

  const selectHte = async (hteId) => {
    setSelectedHteId(hteId);
    setStudents([]);
    setSelectedStudentIds([]);
    setGpsReady(false);
    if (!hteId) return;
    setStudentsLoading(true);
    try {
      const response = await api.get(`/coordinator/travel/htes/${hteId}/students`);
      setStudents(response.data.students);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load students assigned to this HTE.'));
    } finally {
      setStudentsLoading(false);
    }
  };

  const toggleStudent = (studentId) => {
    setSelectedStudentIds((current) => current.includes(studentId) ? current.filter((id) => id !== studentId) : [...current, studentId]);
    setGpsReady(false);
  };

  const verifyGps = async () => {
    setBusy('gps');
    try {
      await requestCurrentLocation();
      setGpsReady(true);
      toast.success('GPS location is available.');
    } catch (error) {
      setGpsReady(false);
      toast.error(error.message);
    } finally {
      setBusy('');
    }
  };

  const startTravel = async () => {
    if (!selectedHteId || !selectedStudentIds.length || !gpsReady) return;
    setBusy('start');
    try {
      const coordinates = await requestCurrentLocation();
      const response = await api.post('/coordinator/travel/start', { hte_id: Number(selectedHteId), student_ids: selectedStudentIds, ...coordinates });
      setSession(response.data.session);
      setPreparing(false);
      setCompletionMessage('');
      toast.success(response.data.message);
      await load(true);
    } catch (error) {
      toast.error(error.response ? errorMessage(error, 'Unable to start travel.') : error.message);
    } finally {
      setBusy('');
    }
  };

  const capture = async (kind, isFinalDestination = false) => {
    if (!session) return;
    setBusy(kind);
    try {
      const coordinates = await requestCurrentLocation();
      const response = await api.post(`/coordinator/travel/${session.id}/${kind}`, {
        ...coordinates,
        ...(kind === 'arrival' ? { is_final_destination: isFinalDestination } : {}),
      });
      toast.success(response.data.message);
      const activeResponse = await api.get('/coordinator/travel/active');
      setSession(activeResponse.data.session);
    } catch (error) {
      toast.error(error.response ? errorMessage(error, 'Unable to capture the location.') : error.message);
    } finally {
      setBusy('');
    }
  };

  const choosePhoto = (event) => {
    const file = event.target.files?.[0] || null;
    if (file && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      toast.error('Use a JPG, PNG, or WebP image.');
      event.target.value = '';
      setArrivalPhoto(null);
      return;
    }
    if (file && file.size > 5 * 1024 * 1024) {
      toast.error('The arrival photo must not exceed 5 MB.');
      event.target.value = '';
      setArrivalPhoto(null);
      return;
    }
    setArrivalPhoto(file);
  };

  const uploadPhoto = async () => {
    if (!session || !arrivalPhoto) return;
    setBusy('photo');
    try {
      const form = new FormData();
      form.append('photo', arrivalPhoto);
      const response = await api.post(`/coordinator/travel/${session.id}/arrival-photo`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
      setSession(response.data.session);
      setArrivalPhoto(null);
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to upload the arrival photo.'));
    } finally {
      setBusy('');
    }
  };

  const endSession = async () => {
    if (!session || !departure || !finalArrival || !session.has_arrival_photo) return;
    setBusy('end');
    try {
      const response = await api.post(`/coordinator/travel/${session.id}/end`);
      setCompletionMessage(response.data.message);
      setSession(null);
      setSelectedHteId('');
      setSelectedStudentIds([]);
      setStudents([]);
      setGpsReady(false);
      toast.success(response.data.message);
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to end the travel session.'));
    } finally {
      setBusy('');
    }
  };

  if (loading) return <LoadingPanel />;

  return (
    <div className="space-y-6">
      <PageIntro eyebrow="Coordinator travel monitoring" title="Travel Monitoring" description="Record official departure and arrival actions for each travel segment. Saved database points restore automatically." icon={MapIcon} actions={!session && !preparing ? <button onClick={() => { setPreparing(true); setCompletionMessage(''); }} className="inline-flex items-center gap-2 rounded-2xl bg-[#d4af37] px-5 py-3 font-black text-[#690016]"><PlayIcon className="h-5 w-5" />Start Session</button> : null} />

      {completionMessage && <section role="status" className="flex items-center gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"><CheckCircleIcon className="h-7 w-7 shrink-0" /><div><p className="font-black">Session complete</p><p className="text-sm">{completionMessage}</p></div></section>}

      {!session && preparing && <section className="space-y-5 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Preparing</p><h2 className="mt-1 text-xl font-black">Choose an HTE and participants</h2><p className="mt-1 text-sm text-slate-500">No travel session is saved until Start Travel succeeds.</p></div><button onClick={() => { setPreparing(false); setSelectedHteId(''); setStudents([]); setSelectedStudentIds([]); setGpsReady(false); }} className="rounded-xl border border-slate-200 px-4 py-2 font-bold dark:border-slate-700">Cancel</button></div>
        <label className="block"><span className="mb-2 block text-sm font-black">Destination HTE</span><select aria-label="Destination HTE" value={selectedHteId} onChange={(event) => selectHte(event.target.value)} className="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700"><option value="">Select an active HTE</option>{setup.htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name} ({hte.assigned_students_count} assigned)</option>)}</select>{selectedHte && <p className="mt-2 text-sm text-slate-500">{selectedHte.address}</p>}</label>
        {studentsLoading ? <LoadingPanel /> : selectedHteId && <StudentTable students={students} selectedIds={selectedStudentIds} onToggle={toggleStudent} disabled={Boolean(busy)} />}
        <div className="flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-black">{selectedStudentIds.length} participant{selectedStudentIds.length === 1 ? '' : 's'} selected</p><p className="text-sm text-slate-500">GPS is checked now and again immediately before the session is created.</p></div><div className="flex flex-wrap gap-2"><button disabled={!selectedHteId || !selectedStudentIds.length || Boolean(busy)} onClick={verifyGps} className="rounded-xl border border-[#7D001D]/30 px-4 py-2 font-black text-[#7D001D] disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-300">{busy === 'gps' ? 'Checking GPS...' : gpsReady ? 'GPS Ready' : 'Verify GPS'}</button><button disabled={!selectedHteId || !selectedStudentIds.length || !gpsReady || Boolean(busy)} onClick={startTravel} className="rounded-xl bg-[#7D001D] px-5 py-2 font-black text-white disabled:cursor-not-allowed disabled:opacity-40">{busy === 'start' ? 'Starting...' : 'Start Travel'}</button></div></div>
      </section>}

      {!session && !preparing && !setup.htes.length && <EmptyPanel icon={MapPinIcon} title="No HTEs available" description="No active HTE is assigned to your program in the database." />}

      {session && <>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><MetricCard label="Session" value={session.session_code} icon={MapIcon} /><MetricCard label="Status" value={session.status} icon={ArrowPathIcon} tone="green" /><MetricCard label="Participants" value={session.participants.length} icon={UserGroupIcon} tone="violet" /><MetricCard label="Saved points" value={session.points.length} icon={MapPinIcon} tone="gold" /></div>
        <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-widest text-[#a8750b]">Destination HTE</p><h2 className="mt-1 text-2xl font-black">{session.hte.name}</h2><p className="mt-1 text-sm text-slate-500">{session.hte.address}</p></div><span className={`rounded-full px-3 py-1 text-xs font-black capitalize ${statusStyles[session.status] || statusStyles.active}`}>{session.status}</span></div><div className="mt-5 flex flex-wrap gap-2">{session.participants.map((student) => <span key={student.id} className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold dark:bg-slate-800">{student.school_number} · {student.full_name}</span>)}</div></section>
        <RouteMap points={session.points} />
        <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-xl font-black">Location capture</h2><p className="mt-1 text-sm text-slate-500">Each action requests a fresh GPS position and saves it with the server timestamp.</p></div><div className="flex flex-wrap gap-2">{session.status === 'active' && lastPoint?.point_type === 'arrival' && !lastPoint.is_final_destination && <button disabled={Boolean(busy)} onClick={() => capture('departure')} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40"><MapPinIcon className="h-5 w-5" />{busy === 'departure' ? 'Capturing...' : 'Start Departure'}</button>}{session.status === 'active' && lastPoint?.point_type === 'departure' && <><button disabled={Boolean(busy)} onClick={() => capture('arrival')} className="inline-flex items-center gap-2 rounded-xl border border-[#7D001D]/30 px-5 py-3 font-black text-[#7D001D] disabled:opacity-40 dark:text-red-300"><MapPinIcon className="h-5 w-5" />{busy === 'arrival' ? 'Capturing...' : 'Mark Arrival'}</button><button disabled={Boolean(busy)} onClick={() => capture('arrival', true)} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-black text-white disabled:opacity-40"><CheckCircleIcon className="h-5 w-5" />{busy === 'arrival' ? 'Marking...' : 'Mark Final Arrival'}</button></>}</div></div>
          {!!session.points.length && <div className="mt-5 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="text-xs uppercase text-slate-500"><tr><th className="px-3 py-2">Point</th><th className="px-3 py-2">Captured date</th><th className="px-3 py-2">Captured time</th><th className="px-3 py-2">Latitude</th><th className="px-3 py-2">Longitude</th></tr></thead><tbody>{session.points.map((point) => <tr key={point.id} className="border-t border-slate-100 dark:border-slate-800"><td className="px-3 py-3 font-black">{pointTitle(point)}</td><td className="px-3 py-3">{formatDate(point.captured_at)}</td><td className="px-3 py-3">{formatTime(point.captured_at)}</td><td className="px-3 py-3">{Number(point.latitude).toFixed(8)}</td><td className="px-3 py-3">{Number(point.longitude).toFixed(8)}</td></tr>)}</tbody></table></div>}
        </section>
        {finalArrival && <section className="rounded-3xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30 sm:p-6"><div className="flex items-start gap-3"><CameraIcon className="h-7 w-7 shrink-0 text-amber-700" /><div className="w-full"><h2 className="text-xl font-black">Arrival photo required</h2><p className="mt-1 text-sm text-slate-600 dark:text-slate-300">Upload a JPG, PNG, or WebP image up to 5 MB before ending the session.</p>{session.has_arrival_photo && <ProtectedImage endpoint={session.arrival_photo_endpoint} alt="Uploaded arrival evidence" className="mt-4 h-48 w-full rounded-2xl object-cover sm:w-80" />}<div className="mt-4 flex flex-wrap gap-2"><input aria-label="Arrival photo" type="file" accept="image/jpeg,image/png,image/webp" onChange={choosePhoto} className="max-w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm dark:bg-slate-900" /><button disabled={!arrivalPhoto || Boolean(busy)} onClick={uploadPhoto} className="rounded-xl bg-amber-600 px-5 py-2 font-black text-white disabled:opacity-40">{busy === 'photo' ? 'Uploading...' : session.has_arrival_photo ? 'Replace Photo' : 'Upload Photo'}</button></div></div></div></section>}
        <section className="flex flex-col gap-4 rounded-3xl bg-slate-950 p-5 text-white sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><p className="font-black">End session requirements</p><div className="mt-2 flex flex-wrap gap-3 text-xs"><span className={departure ? 'text-emerald-300' : 'text-slate-400'}>Departure {departure ? 'saved' : 'required'}</span><span className={finalArrival ? 'text-emerald-300' : 'text-slate-400'}>Final arrival {finalArrival ? 'saved' : 'required'}</span><span className={session.has_arrival_photo ? 'text-emerald-300' : 'text-slate-400'}>Photo {session.has_arrival_photo ? 'uploaded' : 'required'}</span></div></div><button disabled={!departure || !finalArrival || !session.has_arrival_photo || Boolean(busy)} onClick={endSession} className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-6 py-3 font-black text-[#690016] disabled:cursor-not-allowed disabled:opacity-40"><StopIcon className="h-5 w-5" />{busy === 'end' ? 'Ending...' : 'End Session'}</button></section>
      </>}

      <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"><div className="mb-4 flex items-center justify-between"><div><h2 className="text-xl font-black">Session history</h2><p className="text-sm text-slate-500">Coordinator sessions loaded from the database.</p></div><button disabled={loading} onClick={() => load()} aria-label="Refresh travel sessions" className="rounded-xl border border-slate-200 p-2 dark:border-slate-700"><ArrowPathIcon className="h-5 w-5" /></button></div>{history.sessions.length ? <div className="space-y-3">{history.sessions.map((item) => <article key={item.id} className="flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">{item.session_code}</p><p className="mt-1 font-black">{item.hte?.name}</p><p className="mt-1 text-xs text-slate-500">{item.participants.length} participant{item.participants.length === 1 ? '' : 's'} · {item.points.length} saved point{item.points.length === 1 ? '' : 's'}</p></div><div className="flex items-center gap-3"><span className={`rounded-full px-3 py-1 text-xs font-black capitalize ${statusStyles[item.status] || statusStyles.active}`}>{item.status}</span><span className="inline-flex items-center gap-1 text-xs text-slate-500"><ClockIcon className="h-4 w-4" />{item.start_time ? new Date(item.start_time).toLocaleString() : '—'}</span></div></article>)}</div> : <EmptyPanel icon={MapIcon} title="No coordinator sessions yet" description="Completed and active coordinator travel sessions will appear here." />}</section>
    </div>
  );
}
