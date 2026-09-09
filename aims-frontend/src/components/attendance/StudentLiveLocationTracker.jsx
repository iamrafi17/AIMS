import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import toast from 'react-hot-toast';
import useAuth from '../../context/useAuth';
import api from '../../services/api';

function StudentLiveLocationTracker() {
  const { user } = useAuth();
  const location = useLocation();
  const [activeSession, setActiveSession] = useState(false);
  const latestLocationRef = useRef(null);
  const sendingRef = useRef(false);
  const outsideGeofenceRef = useRef(false);
  const shouldTrackOutsideDashboard = user?.role === 'student' && location.pathname !== '/student/dashboard';

  useEffect(() => {
    if (!shouldTrackOutsideDashboard) {
      setActiveSession(false);
      return undefined;
    }

    let active = true;
    const syncClock = async () => {
      try {
        const response = await api.get('/student/attendance/quick-clock');
        if (active) {
          setActiveSession(Boolean(
            response.data.attendance
            && !response.data.completed
            && response.data.next_action?.type === 'out',
          ));
        }
      } catch {
        if (active) setActiveSession(false);
      }
    };

    syncClock();
    const timer = window.setInterval(syncClock, 15000);
    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, [shouldTrackOutsideDashboard]);

  useEffect(() => {
    if (!shouldTrackOutsideDashboard || !activeSession || !navigator.geolocation || !window.isSecureContext) {
      latestLocationRef.current = null;
      outsideGeofenceRef.current = false;
      return undefined;
    }

    let active = true;
    const shareLatest = async () => {
      if (!active || !latestLocationRef.current || sendingRef.current) return;
      sendingRef.current = true;

      try {
        const response = await api.post('/student/attendance/live-location', latestLocationRef.current);
        if (!active) return;

        if (response.data.outside_geofence && !outsideGeofenceRef.current) {
          toast.error(response.data.message, { id: 'live-geofence', duration: 8000 });
        } else if (!response.data.outside_geofence && outsideGeofenceRef.current) {
          toast.success('You are back within the HTE geofence.', { id: 'live-geofence' });
        }
        outsideGeofenceRef.current = Boolean(response.data.outside_geofence);
      } catch (error) {
        if (error.response?.status === 422 && active) setActiveSession(false);
      } finally {
        sendingRef.current = false;
      }
    };

    const watchId = navigator.geolocation.watchPosition(
      ({ coords }) => {
        latestLocationRef.current = {
          latitude: coords.latitude,
          longitude: coords.longitude,
          accuracy: coords.accuracy,
        };
        shareLatest();
      },
      () => {},
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 },
    );
    const heartbeat = window.setInterval(shareLatest, 15000);

    return () => {
      active = false;
      navigator.geolocation.clearWatch(watchId);
      window.clearInterval(heartbeat);
      latestLocationRef.current = null;
      outsideGeofenceRef.current = false;
    };
  }, [activeSession, shouldTrackOutsideDashboard]);

  return null;
}

export default StudentLiveLocationTracker;
