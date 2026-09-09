import { useEffect, useMemo, useState } from 'react';
import { Polyline } from 'react-leaflet';

const OSRM_ROUTE_URL = 'https://router.project-osrm.org/route/v1/driving';
const routeCache = new Map();

export default function TravelRoadRoute({ positions = [] }) {
  const coordinateKey = useMemo(
    () => positions
      .map(([latitude, longitude]) => `${Number(longitude).toFixed(6)},${Number(latitude).toFixed(6)}`)
      .join(';'),
    [positions],
  );
  const [roadPositions, setRoadPositions] = useState(() => routeCache.get(coordinateKey) || []);
  const [routeStatus, setRouteStatus] = useState(() => routeCache.has(coordinateKey) ? 'ready' : 'loading');

  useEffect(() => {
    if (positions.length < 2) {
      setRoadPositions([]);
      setRouteStatus('idle');
      return undefined;
    }

    const cached = routeCache.get(coordinateKey);
    if (cached) {
      setRoadPositions(cached);
      setRouteStatus('ready');
      return undefined;
    }

    const controller = new AbortController();
    const snapRadius = positions.map(() => 1000).join(';');
    const routeUrl = `${OSRM_ROUTE_URL}/${coordinateKey}?overview=full&geometries=geojson&steps=false&continue_straight=false&radiuses=${snapRadius}`;
    setRoadPositions([]);
    setRouteStatus('loading');

    fetch(routeUrl, { signal: controller.signal })
      .then((response) => {
        if (!response.ok) throw new Error('Road route request failed.');
        return response.json();
      })
      .then((payload) => {
        const coordinates = payload.code === 'Ok' ? payload.routes?.[0]?.geometry?.coordinates : null;
        if (!Array.isArray(coordinates) || coordinates.length < 2) throw new Error('No drivable route found.');

        const nextPositions = coordinates.map(([longitude, latitude]) => [latitude, longitude]);
        routeCache.set(coordinateKey, nextPositions);
        setRoadPositions(nextPositions);
        setRouteStatus('ready');
      })
      .catch((error) => {
        if (error.name !== 'AbortError') setRouteStatus('unavailable');
      });

    return () => controller.abort();
  }, [coordinateKey, positions]);

  const routeUnavailable = routeStatus === 'unavailable';
  const displayedPositions = routeStatus === 'ready' ? roadPositions : routeUnavailable ? positions : [];
  if (displayedPositions.length < 2) return null;

  return <>
    <Polyline
      positions={displayedPositions}
      pathOptions={{
        color: '#ffffff',
        weight: routeUnavailable ? 8 : 10,
        opacity: 0.95,
        lineCap: 'round',
        lineJoin: 'round',
        dashArray: routeUnavailable ? '10 10' : null,
      }}
    />
    <Polyline
      positions={displayedPositions}
      pathOptions={{
        color: routeUnavailable ? '#f59e0b' : '#dc2626',
        weight: routeUnavailable ? 4 : 5,
        opacity: 1,
        lineCap: 'round',
        lineJoin: 'round',
        dashArray: routeUnavailable ? '10 10' : null,
      }}
    />
  </>;
}
