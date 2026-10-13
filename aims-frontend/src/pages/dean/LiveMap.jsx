import { useCallback, useEffect, useMemo, useState } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import toast from 'react-hot-toast';
import api from '../../services/api';

const marker = L.divIcon({ className: 'live-student-marker', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14] });
const warningMarker = L.divIcon({ className: 'live-student-marker live-student-marker--outside', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14] });

function FitMarkers({ locations }) {
  const map = useMap();
  const positions = useMemo(() => locations.map((item) => [item.latitude, item.longitude]), [locations]);
  useEffect(() => {
    if (positions.length === 1) map.setView(positions[0], 16);
    if (positions.length > 1) map.fitBounds(positions, { padding: [40, 40], maxZoom: 16 });
  }, [map, positions]);
  return null;
}

export default function DeanLiveMap() {
  const [live, setLive] = useState({ locations: [], sharing: 0 });
  const [lastRefresh, setLastRefresh] = useState(null);
  const load = useCallback(async () => {
    try {
      setLive((await api.get('/dean/live-locations')).data);
      setLastRefresh(new Date());
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load live locations.');
    }
  }, []);
  useEffect(() => {
    load();
    const timer = window.setInterval(load, 15000);
    return () => window.clearInterval(timer);
  }, [load]);

  return <div className="space-y-6">
    <header className="rounded-3xl bg-[#690016] p-6 text-white sm:p-8">
      <p className="text-xs font-bold uppercase tracking-widest text-amber-200">Dean workspace</p>
      <h1 className="mt-2 text-3xl font-black text-white">Live Map</h1>
      <p className="mt-2 text-sm text-white/75">Currently timed-in interns sharing their location in your college.</p>
    </header>
    <section className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 className="text-lg font-black">{live.sharing} currently sharing</h2><p className="text-xs text-slate-500">Updates every 15 seconds · Pins expire after 60 seconds without a location update</p></div><button type="button" onClick={load} className="rounded-lg border px-3 py-2 text-sm">Refresh</button></div>
      <div className="h-[430px] overflow-hidden rounded-xl"><MapContainer center={[12.8797, 121.774]} zoom={6} className="h-full w-full"><TileLayer attribution="&copy; OpenStreetMap contributors" url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maxZoom={19} /><FitMarkers locations={live.locations} />{live.locations.map((item) => <Marker key={item.id} position={[item.latitude, item.longitude]} icon={item.outside_geofence ? warningMarker : marker}><Popup><strong>{item.name}</strong><br />{item.school_id} · {item.program}<br />{item.hte || 'No HTE'}<br />{item.outside_geofence ? 'Outside geofence' : 'Timed in'}<br />Updated {new Date(item.captured_at).toLocaleTimeString('en-PH')}</Popup></Marker>)}</MapContainer></div>
      {lastRefresh && <p className="mt-2 text-xs text-slate-400">Map refreshed {lastRefresh.toLocaleTimeString('en-PH')}</p>}
    </section>
  </div>;
}
