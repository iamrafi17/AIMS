import { useEffect, useMemo, useState } from 'react';
import L from 'leaflet';
import {
  Circle,
  MapContainer,
  Marker,
  TileLayer,
  useMap,
  useMapEvents,
} from 'react-leaflet';
import { FiCrosshair, FiMapPin } from 'react-icons/fi';
import 'leaflet/dist/leaflet.css';

const SANTA_CRUZ_CENTER = [13.4767, 122.0269];

const pinIcon = L.divIcon({
  className: 'aims-leaflet-pin',
  html: '<span aria-hidden="true"></span>',
  iconAnchor: [18, 38],
  iconSize: [36, 42],
});

function validCoordinate(value, min, max) {
  if (value === '' || value === null || value === undefined) return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed >= min && parsed <= max ? parsed : null;
}

function MapViewport({ position, hasSelectedLocation }) {
  const map = useMap();

  useEffect(() => {
    const timer = window.setTimeout(() => {
      map.invalidateSize();
      if (hasSelectedLocation) map.setView(position, Math.max(map.getZoom(), 17), { animate: true });
    }, 80);

    return () => window.clearTimeout(timer);
  }, [hasSelectedLocation, map, position]);

  return null;
}

function MapClickPicker({ editable, onPositionChange }) {
  useMapEvents({
    click(event) {
      if (!editable) return;
      onPositionChange(event.latlng.lat, event.latlng.lng);
    },
  });

  return null;
}

function CurrentLocationButton({ onPositionChange }) {
  const [locating, setLocating] = useState(false);
  const [message, setMessage] = useState('');

  const locate = () => {
    if (!navigator.geolocation) {
      setMessage('Location is not supported by this browser.');
      return;
    }

    setLocating(true);
    setMessage('');
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        onPositionChange(coords.latitude, coords.longitude);
        setLocating(false);
      },
      () => {
        setMessage('Unable to get your location. Allow location permission and try again.');
        setLocating(false);
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 },
    );
  };

  return (
    <div>
      <button
        type="button"
        onClick={locate}
        disabled={locating}
        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-[#800000] shadow-sm disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-rose-300"
      >
        <FiCrosshair />
        {locating ? 'Finding location...' : 'Use current location'}
      </button>
      {message && <p className="mt-2 text-xs font-semibold text-rose-600">{message}</p>}
    </div>
  );
}

function GeofenceMap({
  latitude,
  longitude,
  radius = 100,
  editable = false,
  onPositionChange = () => {},
  onRadiusChange = () => {},
  className = '',
}) {
  const parsedLatitude = validCoordinate(latitude, -90, 90);
  const parsedLongitude = validCoordinate(longitude, -180, 180);
  const hasSelectedLocation = parsedLatitude !== null && parsedLongitude !== null;
  const position = useMemo(
    () => (hasSelectedLocation ? [parsedLatitude, parsedLongitude] : SANTA_CRUZ_CENTER),
    [hasSelectedLocation, parsedLatitude, parsedLongitude],
  );
  const safeRadius = Math.min(10000, Math.max(10, Number(radius) || 100));
  const area = Math.PI * safeRadius * safeRadius;
  const areaLabel = area >= 10000
    ? `${(area / 10000).toFixed(2)} hectares`
    : `${Math.round(area).toLocaleString()} m²`;

  const updatePosition = (lat, lng) => {
    onPositionChange(Number(lat.toFixed(8)), Number(lng.toFixed(8)));
  };

  return (
    <section className={`overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-gray-600 dark:bg-gray-900 ${className}`}>
      <div className="relative h-[360px] min-h-[300px]">
        <MapContainer
          center={position}
          zoom={hasSelectedLocation ? 17 : 13}
          scrollWheelZoom
          className="h-full w-full"
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
            maxZoom={19}
          />
          <MapViewport position={position} hasSelectedLocation={hasSelectedLocation} />
          <MapClickPicker editable={editable} onPositionChange={updatePosition} />
          {hasSelectedLocation && (
            <>
              <Circle
                center={position}
                radius={safeRadius}
                pathOptions={{
                  color: '#800000',
                  fillColor: '#d4af37',
                  fillOpacity: 0.2,
                  weight: 3,
                }}
              />
              <Marker
                position={position}
                icon={pinIcon}
                draggable={editable}
                eventHandlers={{
                  dragend(event) {
                    const next = event.target.getLatLng();
                    updatePosition(next.lat, next.lng);
                  },
                }}
              />
            </>
          )}
        </MapContainer>
        {!hasSelectedLocation && (
          <div className="pointer-events-none absolute inset-x-4 top-4 z-[500] mx-auto max-w-md rounded-xl bg-white/95 px-4 py-3 text-center text-xs font-black text-slate-700 shadow-lg backdrop-blur dark:bg-gray-900/95 dark:text-gray-200">
            Click the exact HTE location on the map to place the pin.
          </div>
        )}
      </div>

      <div className="space-y-4 border-t border-slate-200 p-4 dark:border-gray-700">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-white">
              <FiMapPin className="text-[#800000] dark:text-rose-300" />
              {hasSelectedLocation ? 'Exact location selected' : 'No location pinned yet'}
            </p>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {hasSelectedLocation
                ? `${parsedLatitude.toFixed(8)}, ${parsedLongitude.toFixed(8)}`
                : 'Default view: Santa Cruz, Marinduque'}
            </p>
          </div>
          {editable && <CurrentLocationButton onPositionChange={updatePosition} />}
        </div>

        {editable && (
          <div>
            <div className="mb-2 flex items-center justify-between gap-3">
              <label htmlFor="geofence-radius-slider" className="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">
                Geofence radius
              </label>
              <span className="rounded-lg bg-[#800000] px-3 py-1 text-xs font-black text-white">
                {safeRadius.toLocaleString()} meters
              </span>
            </div>
            <input
              id="geofence-radius-slider"
              type="range"
              min="10"
              max="10000"
              step="10"
              value={safeRadius}
              onChange={(event) => onRadiusChange(Number(event.target.value))}
              className="aims-radius-slider w-full"
            />
            <div className="mt-2 flex items-center justify-between text-[10px] font-bold text-slate-400">
              <span>10 m</span>
              <span>Covered area: {areaLabel}</span>
              <span>10 km</span>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
              {[50, 100, 250, 500, 1000].map((preset) => (
                <button
                  key={preset}
                  type="button"
                  onClick={() => onRadiusChange(preset)}
                  className={`rounded-lg px-3 py-1.5 text-[10px] font-black ${
                    safeRadius === preset
                      ? 'bg-[#800000] text-white'
                      : 'bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'
                  }`}
                >
                  {preset >= 1000 ? `${preset / 1000} km` : `${preset} m`}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}

export default GeofenceMap;
