import { useCallback, useEffect, useMemo, useState } from "react";
import L from "leaflet";
import {
  MapContainer,
  Marker,
  Popup,
  TileLayer,
  useMap,
} from "react-leaflet";
import "leaflet/dist/leaflet.css";
import TravelRoadRoute from "../../components/maps/TravelRoadRoute";
import {
  ArrowPathIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
  ClockIcon,
  DocumentTextIcon,
  EyeIcon,
  MapIcon,
  MapPinIcon,
  MagnifyingGlassIcon,
} from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import api from "../../services/api";
import {
  LoadingPanel,
  MetricCard,
  PageIntro,
} from "../../components/common/PortalUI";

const tabs = [
  ["scheduled", "Scheduled"],
  ["active", "Active Sessions"],
  ["completed", "Completed Sessions"],
  ["pending", "Submitted Reports"],
  ["validated", "Validated Reports"],
];

const reportLabels = {
  draft: "Draft",
  pending_program_head: "Pending Program Head Validation",
  pending_vpaa: "Pending Dean Final Validation",
  final_validated: "Final Validated",
};

const pointIcons = {
  start: L.divIcon({
    className: "travel-point travel-point--departure",
    html: "<span>S</span>",
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  }),
  departure: L.divIcon({
    className: "travel-point travel-point--departure",
    html: "<span>D</span>",
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  }),
  checkpoint: L.divIcon({
    className: "travel-point travel-point--checkpoint",
    html: "<span>C</span>",
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  }),
  final_arrival: L.divIcon({
    className: "travel-point travel-point--arrival",
    html: "<span>A</span>",
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  }),
};

function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  return errors
    ? Object.values(errors).flat()[0]
    : error.response?.data?.message || fallback;
}

function formatTravelDate(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" }) : "—";
}

function formatStudentSection(student) {
  const section = student.section?.trim();
  if (section && section.length > 2) return section;
  return `${student.program?.code || ""} ${student.year_level || ""}${section || ""}`.trim() || "Section not assigned";
}

function FitRoute({ positions }) {
  const map = useMap();
  useEffect(() => {
    window.setTimeout(() => {
      map.invalidateSize();
      if (positions.length === 1) map.setView(positions[0], 17);
      if (positions.length > 1)
        map.fitBounds(positions, { padding: [36, 36], maxZoom: 17 });
    }, 0);
  }, [map, positions]);
  return null;
}

function RouteMap({ points }) {
  const positions = useMemo(
    () => points.map((point) => [point.latitude, point.longitude]),
    [points],
  );
  if (!positions.length)
    return (
      <div className="grid h-72 place-items-center rounded-3xl border border-dashed border-slate-300 text-sm text-slate-500">
        No GPS points recorded.
      </div>
    );
  return (
    <div className="relative h-[400px] overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-700">
      {positions.length > 1 && <span className="pointer-events-none absolute right-3 top-3 z-[500] rounded-full bg-white/95 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-red-700 shadow-md backdrop-blur dark:bg-slate-900/95 dark:text-red-300">Estimated road route</span>}
      <MapContainer
        center={positions[0]}
        zoom={17}
        scrollWheelZoom
        className="h-full w-full"
      >
        <TileLayer
          attribution="&copy; OpenStreetMap contributors"
          url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
          maxZoom={19}
        />
        <FitRoute positions={positions} />
        <TravelRoadRoute positions={positions} />
        {points.map((point) => (
          <Marker
            key={point.id}
            position={[point.latitude, point.longitude]}
            icon={pointIcons[point.event_type] || pointIcons.checkpoint}
          >
            <Popup>
              <p className="font-black">{point.label}</p>
              <p>{point.readable_location}</p>
              <p>{new Date(point.captured_at).toLocaleString()}</p>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}

function ProtectedImage({ endpoint }) {
  const [source, setSource] = useState("");
  useEffect(() => {
    if (!endpoint) return undefined;
    let url = "";
    api
      .get(endpoint, { responseType: "blob" })
      .then(({ data }) => {
        url = URL.createObjectURL(data);
        setSource(url);
      })
      .catch(() => setSource(""));
    return () => {
      if (url) URL.revokeObjectURL(url);
    };
  }, [endpoint]);
  return source ? (
    <img
      src={source}
      alt="Student arrival evidence"
      className="h-72 w-full rounded-3xl object-cover"
    />
  ) : (
    <div className="grid h-72 place-items-center rounded-3xl bg-slate-100 text-sm text-slate-400 dark:bg-slate-800">
      Arrival photo unavailable
    </div>
  );
}

function belongsToTab(session, tab) {
  if (tab === "scheduled") return session.status === "scheduled";
  if (tab === "active")
    return ["active", "traveling", "arrived"].includes(session.status);
  if (tab === "completed")
    return (
      session.status === "completed" &&
      (!session.report || session.report.status === "draft")
    );
  if (tab === "pending")
    return ["pending_program_head", "pending_vpaa"].includes(
      session.report?.status,
    );
  return session.report?.status === "final_validated";
}

export default function TravelMonitoring() {
  const [data, setData] = useState({ summary: {}, sessions: [] });
  const [loading, setLoading] = useState(true);
  const requestedSession = Number(new URLSearchParams(window.location.search).get('session')) || null;
  const [tab, setTab] = useState(requestedSession ? "active" : "scheduled");
  const [selectedId, setSelectedId] = useState(requestedSession);
  const [remarks, setRemarks] = useState("");
  const [busy, setBusy] = useState("");
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const [studentSearch, setStudentSearch] = useState("");
  const [studentOptions, setStudentOptions] = useState([]);
  const [studentSearchLoading, setStudentSearchLoading] = useState(false);
  const [studentSearchComplete, setStudentSearchComplete] = useState(false);
  const [selectedStudents, setSelectedStudents] = useState([]);
  const [scheduleDate, setScheduleDate] = useState("");
  const [scheduleSuccess, setScheduleSuccess] = useState(null);
  const [cancelTarget, setCancelTarget] = useState(null);

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    try {
      const response = await api.get("/coordinator/travel");
      setData(response.data);
      setSelectedId((current) =>
        current && response.data.sessions.some((item) => item.id === current)
          ? current
          : requestedSession && response.data.sessions.some((item) => item.id === requestedSession) ? requestedSession : null,
      );
    } catch (error) {
      toast.error(
        errorMessage(error, "Unable to load Student travel sessions."),
      );
    } finally {
      setLoading(false);
    }
  }, [requestedSession]);

  useEffect(() => {
    load();
  }, [load]);
  const sessions = data.sessions.filter((session) =>
    belongsToTab(session, tab),
  );
  const selected =
    data.sessions.find((session) => session.id === selectedId) || null;

  useEffect(() => {
    if (!scheduleOpen || selected?.status === "scheduled") return;
    const term = studentSearch.trim();
    if (!term) {
      setStudentOptions([]);
      setStudentSearchLoading(false);
      setStudentSearchComplete(false);
      return;
    }

    let cancelled = false;
    setStudentSearchLoading(true);
    setStudentSearchComplete(false);
    const timer = window.setTimeout(async () => {
      try {
        const response = await api.get("/coordinator/travel/students", { params: { q: term } });
        if (!cancelled) {
          setStudentOptions(response.data.students);
          setStudentSearchComplete(true);
        }
      } catch (error) {
        if (!cancelled) toast.error(errorMessage(error, "Unable to search students."));
      } finally {
        if (!cancelled) setStudentSearchLoading(false);
      }
    }, 250);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [studentSearch, scheduleOpen, selected?.status]);

  const saveSchedule = async (event) => {
    event.preventDefault();
    setBusy("schedule");
    try {
      const response = selected?.status === "scheduled"
        ? await api.put(`/coordinator/travel/${selected.id}/schedule`, { travel_date: scheduleDate })
        : await api.post("/coordinator/travel/batch", { student_ids: selectedStudents.map((student) => student.id), travel_date: scheduleDate });
      if (selected?.status === "scheduled") toast.success(response.data.message);
      else setScheduleSuccess({ count: response.data.count, travelDate: response.data.travel_date });
      setScheduleOpen(false);
      setSelectedStudents([]);
      setStudentSearch("");
      setScheduleDate("");
      setTab("scheduled");
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to save the travel schedule."));
    } finally {
      setBusy("");
    }
  };

  const toggleScheduleStudent = (student) => {
    setSelectedStudents((current) => current.some((item) => item.id === student.id)
      ? current.filter((item) => item.id !== student.id)
      : [...current, student]);
  };

  const cancelSchedule = async () => {
    if (!cancelTarget) return;
    setBusy("cancel");
    try {
      const response = await api.post(`/coordinator/travel/${cancelTarget.id}/cancel`);
      toast.success(response.data.message);
      setCancelTarget(null);
      setSelectedId(null);
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to cancel the travel schedule."));
    } finally {
      setBusy("");
    }
  };

  useEffect(() => {
    setRemarks(selected?.report?.remarks || "");
  }, [selectedId, selected?.report?.remarks]);

  const generate = async () => {
    setBusy("generate");
    try {
      const response = await api.post(
        `/coordinator/travel/${selected.id}/reports`,
      );
      toast.success(response.data.message);
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to generate the report."));
    } finally {
      setBusy("");
    }
  };

  const save = async () => {
    setBusy("save");
    try {
      const response = await api.put(
        `/coordinator/travel/reports/${selected.report.id}`,
        { remarks },
      );
      toast.success(response.data.message);
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to save Coordinator remarks."));
    } finally {
      setBusy("");
    }
  };

  const submit = async () => {
    setBusy("submit");
    try {
      await api.put(`/coordinator/travel/reports/${selected.report.id}`, {
        remarks,
      });
      const response = await api.post(
        `/coordinator/travel/reports/${selected.report.id}/submit`,
      );
      toast.success(response.data.message);
      setSelectedId(null);
      setTab("pending");
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to submit the report."));
    } finally {
      setBusy("");
    }
  };

  if (loading) return <LoadingPanel />;

  return (
    <div className="coordinator-travel space-y-6">
      <PageIntro
        eyebrow="Coordinator monitoring and reporting"
        title="Travel Monitoring"
        description="Schedule student travel, monitor GPS routes, and prepare completed reports for validation."
        icon={MapIcon}
        actions={
          <div className="flex w-full flex-wrap gap-2 sm:w-auto">
            <button type="button" onClick={() => { setSelectedId(null); setStudentOptions([]); setSelectedStudents([]); setStudentSearch(""); setScheduleDate(""); setScheduleOpen(true); }} className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#650018] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7D001D] sm:flex-none">
              <CalendarDaysIcon className="h-5 w-5" /> Schedule Travel
            </button>
            <button type="button" onClick={() => load()} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7D001D] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
              <ArrowPathIcon className="h-5 w-5" /> Refresh
            </button>
          </div>
        }
      />
      {scheduleOpen && <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div className="flex items-start justify-between gap-3"><div><h2 className="text-xl font-black">{selected?.status === "scheduled" ? "Reschedule Travel" : "Schedule Travel"}</h2><p className="mt-1 text-sm text-slate-500">{selected?.status === "scheduled" ? "Choose a new travel date." : "Search and select students, then choose their travel date."}</p></div><button type="button" onClick={() => setScheduleOpen(false)} className="text-sm font-bold text-slate-500">Close</button></div>
        <form onSubmit={saveSchedule} className="mt-5 space-y-4">
          {selected?.status === "scheduled" ? <p className="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">{selected.student?.full_name} · {selected.hte?.name} · Current: {formatTravelDate(selected.scheduled_date)}</p> : <>
            <div>
              <label htmlFor="travel-student-search" className="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Search Student</label>
              <div className="relative"><MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input id="travel-student-search" autoComplete="off" value={studentSearch} onChange={(event) => { setStudentSearch(event.target.value); setStudentOptions([]); setStudentSearchComplete(false); }} placeholder="Type a student name or school ID" className="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm outline-none focus:border-[#7D001D] focus:ring-2 focus:ring-[#7D001D]/15 dark:border-slate-700 dark:bg-slate-800" /></div>
              {studentSearch.trim() && <div className="mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p className="px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">Matching Students</p>
                {studentSearchLoading && <p className="px-3 py-3 text-sm text-slate-500">Searching students...</p>}
                {!studentSearchLoading && studentSearchComplete && !studentOptions.length && <p className="px-3 py-3 text-sm text-slate-500">No matching students found.</p>}
                {!studentSearchLoading && studentOptions.map((student) => <label key={student.id} className="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-700"><input type="checkbox" checked={selectedStudents.some((item) => item.id === student.id)} onChange={() => toggleScheduleStudent(student)} className="mt-1 h-4 w-4 accent-[#7D001D]" /><span><span className="block font-bold">{student.school_number} &nbsp; {student.full_name}</span><span className="mt-1 block text-xs text-slate-500">{student.program?.name || "Program not assigned"} · {formatStudentSection(student)}</span></span></label>)}
              </div>}
            </div>
            <div><p className="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Selected Students ({selectedStudents.length})</p><div className="space-y-2 rounded-xl border border-slate-200 p-2 dark:border-slate-700">{selectedStudents.length ? selectedStudents.map((student) => <div key={student.id} className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800"><span>{student.full_name}</span><button type="button" onClick={() => toggleScheduleStudent(student)} aria-label={`Remove ${student.full_name}`} className="rounded-lg px-2 py-1 font-bold text-slate-500 hover:text-rose-700">×</button></div>) : <p className="px-2 py-2 text-sm text-slate-500">No students selected yet.</p>}</div></div>
          </>}
          <label className="block text-sm font-black">Travel Date<input required type="date" value={scheduleDate} onChange={(event) => setScheduleDate(event.target.value)} className="mt-2 block w-full rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-800" /></label>
          <div className="flex justify-end gap-2"><button type="button" onClick={() => setScheduleOpen(false)} className="rounded-xl border border-slate-200 px-5 py-3 font-bold">Cancel</button><button disabled={busy === "schedule" || (!selected && !selectedStudents.length)} className="rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40">{busy === "schedule" ? "Saving..." : selected?.status === "scheduled" ? "Save New Schedule" : "Schedule Travel"}</button></div>
        </form>
      </section>}
      {scheduleSuccess && <div className="fixed inset-0 z-[1000] grid place-items-center bg-slate-950/50 p-4" role="presentation"><div role="dialog" aria-modal="true" aria-labelledby="travel-schedule-success-title" className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900"><h2 id="travel-schedule-success-title" className="text-xl font-black text-emerald-700 dark:text-emerald-300">✓ Travel Scheduled Successfully</h2><p className="mt-4 text-sm">{scheduleSuccess.count} student{scheduleSuccess.count === 1 ? " has" : "s have"} been scheduled for travel on {formatTravelDate(scheduleSuccess.travelDate)}.</p><p className="mt-3 text-sm">{scheduleSuccess.count === 1 ? "Student has" : "Students have"} been notified.</p><div className="mt-6 flex justify-end"><button type="button" autoFocus onClick={() => setScheduleSuccess(null)} className="rounded-xl bg-[#7D001D] px-5 py-2.5 font-bold text-white">OK</button></div></div></div>}
      {cancelTarget && <div className="fixed inset-0 z-[1000] grid place-items-center bg-slate-950/50 p-4" role="presentation"><div role="dialog" aria-modal="true" aria-labelledby="cancel-travel-title" aria-describedby="cancel-travel-description" onKeyDown={(event) => { if (event.key === "Escape" && busy !== "cancel") setCancelTarget(null); }} className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900"><h2 id="cancel-travel-title" className="text-xl font-black text-slate-900 dark:text-white">Cancel travel schedule?</h2><p id="cancel-travel-description" className="mt-3 text-sm text-slate-600 dark:text-slate-300">Cancel the travel schedule for <span className="font-bold">{cancelTarget.student?.full_name}</span> on {formatTravelDate(cancelTarget.scheduled_date)}?</p><div className="mt-6 flex justify-end gap-3"><button type="button" autoFocus disabled={busy === "cancel"} onClick={() => setCancelTarget(null)} className="rounded-xl border border-slate-300 px-5 py-2.5 font-bold text-slate-700 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200">Keep Schedule</button><button type="button" disabled={busy === "cancel"} onClick={cancelSchedule} className="rounded-xl bg-[#7D001D] px-5 py-2.5 font-bold text-white disabled:opacity-50">{busy === "cancel" ? "Cancelling..." : "Cancel Schedule"}</button></div></div></div>}
      <section className="portal-metric-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <MetricCard label="Scheduled" value={data.summary.scheduled_sessions || 0} icon={ClockIcon} tone="gold" />
        <MetricCard
          label="Active"
          value={data.summary.active_sessions || 0}
          icon={MapPinIcon}
          tone="green"
        />
        <MetricCard
          label="Completed"
          value={data.summary.completed_sessions || 0}
          icon={CheckCircleIcon}
        />
        <MetricCard
          label="Pending"
          value={data.summary.pending_reports || 0}
          icon={ClockIcon}
          tone="gold"
        />
        <MetricCard
          label="Final Validated"
          value={data.summary.validated_reports || 0}
          icon={DocumentTextIcon}
          tone="green"
        />
      </section>
      <nav
        className="portal-mobile-tabs flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        aria-label="Travel workflow sections"
      >
        {tabs.map(([id, label]) => (
          <button
            key={id}
            onClick={() => {
              setTab(id);
              setSelectedId(null);
            }}
            className={`whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-bold transition-colors ${tab === id ? "bg-[#7D001D] text-white shadow-sm" : "text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"}`}
          >
            {label}
          </button>
        ))}
      </nav>

      {!selected && (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
          <h2 className="text-xl font-black">
            {tabs.find(([id]) => id === tab)?.[1]}
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            {sessions.length} record{sessions.length === 1 ? "" : "s"}
          </p>
          {sessions.length ? (
            <div className="mt-5 space-y-3">
              {sessions.map((session) => (
                <article
                  key={session.id}
                  className="flex flex-col gap-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 md:flex-row md:items-center md:justify-between"
                >
                  <div>
                    <p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">
                      {session.session_code}
                    </p>
                    <p className="mt-1 text-lg font-black">
                      {session.student?.full_name}
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                      {session.student?.school_number} · {session.hte?.name}
                    </p>
                    {session.status === "scheduled" && <p className="mt-1 text-xs font-bold text-[#7D001D]">Travel: {formatTravelDate(session.scheduled_date)}</p>}
                    <p className="mt-2 text-xs text-slate-400">
                      Status:{" "}
                      <span className="font-black capitalize">
                        {session.status}
                      </span>{" "}
                      · Latest update{" "}
                      {session.last_location_update
                        ? new Date(
                            session.last_location_update,
                          ).toLocaleString()
                        : "unavailable"}
                    </p>
                    {session.report && (
                      <p className="mt-2 text-xs font-black text-[#7D001D] dark:text-red-300">
                        {reportLabels[session.report.status]} · Version{" "}
                        {session.report.version}
                      </p>
                    )}
                  </div>
                  <button
                    onClick={() => setSelectedId(session.id)}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-3 text-sm font-black text-white"
                  >
                    <EyeIcon className="h-5 w-5" />
                    Review Session
                  </button>
                </article>
              ))}
            </div>
          ) : (
            <div className="mt-5">
              <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-10 text-center dark:border-slate-700 dark:bg-slate-800/30">
                <MapIcon className="mx-auto h-9 w-9 text-slate-300 dark:text-slate-600" />
                <h3 className="mt-3 text-base font-bold text-slate-800 dark:text-white">{tab === "scheduled" ? "No travel scheduled yet" : `No ${tabs.find(([id]) => id === tab)?.[1].toLowerCase()} yet`}</h3>
                <p className="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{tab === "scheduled" ? "Schedule a student assigned to an HTE outside Marinduque to get started." : "Records will appear here when a travel session reaches this stage."}</p>
                {tab === "scheduled" && <button type="button" onClick={() => { setSelectedId(null); setStudentOptions([]); setSelectedStudents([]); setStudentSearch(""); setScheduleDate(""); setScheduleOpen(true); }} className="mt-5 rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#650018]">Schedule Travel</button>}
              </div>
            </div>
          )}
        </section>
      )}

      {selected && (
        <div className="space-y-6">
          <button
            onClick={() => setSelectedId(null)}
            className="text-sm font-black text-[#7D001D] dark:text-red-300"
          >
            ← Back to list
          </button>
          <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p className="text-xs font-black uppercase text-[#a8750b]">
                  {selected.session_code}
                </p>
                <h2 className="mt-1 text-2xl font-black">
                  {selected.student?.full_name}
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  {selected.student?.school_number} ·{" "}
                  {selected.student?.program?.code} ·{" "}
                  {selected.student?.department?.name}
                </p>
                <p className="mt-1 text-sm text-slate-500">
                  {selected.hte?.name} · Coordinator:{" "}
                  {selected.coordinator?.name}
                </p>
              </div>
              <span className="rounded-full bg-[#7D001D]/10 px-3 py-1 text-xs font-black capitalize text-[#7D001D] dark:text-red-300">
                {selected.status}
              </span>
            </div>
            <div className="mt-5 grid gap-3 sm:grid-cols-5">
              {[
                ["Scheduled", selected.scheduled_date],
                ["Started", selected.start_time],
                ["Departed", selected.departed_at],
                ["Arrived", selected.arrived_at],
                ["Ended", selected.end_time],
                ["Points", selected.points.length],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800"
                >
                  <p className="text-[10px] font-black uppercase text-slate-400">
                    {label}
                  </p>
                  <p className="mt-1 text-xs font-bold">
                    {label === "Points"
                      ? value
                      : label === "Scheduled"
                        ? formatTravelDate(value)
                      : value
                        ? new Date(value).toLocaleString()
                        : "—"}
                  </p>
                </div>
              ))}
            </div>
            {selected.status === "scheduled" && <div className="mt-5 flex flex-wrap gap-2"><button onClick={() => { setScheduleDate(selected.scheduled_date || ""); setScheduleOpen(true); }} className="rounded-xl bg-[#7D001D] px-4 py-2.5 text-sm font-black text-white">Reschedule</button><button onClick={() => setCancelTarget(selected)} className="rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-black text-rose-700">Cancel Schedule</button></div>}
          </section>
          <RouteMap points={selected.points} />
          <section className="grid gap-6 lg:grid-cols-2">
            <div>
              <h2 className="mb-3 text-lg font-black">Route timeline</h2>
              <div className="space-y-2">
                {selected.points.map((point) => (
                  <div
                    key={point.id}
                    className="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
                  >
                    <div className="flex justify-between gap-3">
                      <p className="font-black">
                        {point.sequence}. {point.label}
                      </p>
                      <p className="text-xs text-slate-400">
                        {new Date(point.captured_at).toLocaleString()}
                      </p>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {point.readable_location ||
                        `${point.latitude}, ${point.longitude}`}
                    </p>
                    {point.remarks && (
                      <p className="mt-2 text-sm">{point.remarks}</p>
                    )}
                  </div>
                ))}
              </div>
            </div>
            <div>
              <h2 className="mb-3 text-lg font-black">Arrival evidence</h2>
              <ProtectedImage endpoint={selected.arrival_photo_endpoint} />
            </div>
          </section>

          {selected.status === "completed" && (
            <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <p className="text-xs font-black uppercase tracking-wider text-[#a8750b]">
                    Official report
                  </p>
                  <h2 className="mt-1 text-xl font-black">
                    Travel Monitoring Report
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    All Student, HTE, GPS, and timestamp fields above are reused
                    directly from the saved session.
                  </p>
                </div>
                {selected.report && (
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-black dark:bg-slate-800">
                    {reportLabels[selected.report.status]} · v
                    {selected.report.version}
                  </span>
                )}
              </div>
              {!selected.report ? (
                <button
                  disabled={busy}
                  onClick={generate}
                  className="mt-5 rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40"
                >
                  {busy === "generate" ? "Generating..." : "Generate Report"}
                </button>
              ) : (
                <>
                  <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                      <p className="text-[10px] font-black uppercase text-slate-400">
                        Report number
                      </p>
                      <p className="mt-1 font-black">
                        {selected.report.report_number}
                      </p>
                    </div>
                    <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                      <p className="text-[10px] font-black uppercase text-slate-400">
                        Prepared by
                      </p>
                      <p className="mt-1 font-black">
                        {selected.report.coordinator?.name}
                      </p>
                    </div>
                  </div>
                  <label className="mt-5 block">
                    <span className="mb-2 block text-sm font-black">
                      Coordinator Remarks
                    </span>
                    <textarea
                      value={remarks}
                      onChange={(event) => setRemarks(event.target.value)}
                      disabled={selected.report.status !== "draft"}
                      rows="5"
                      className="w-full rounded-2xl border border-slate-200 p-4 disabled:bg-slate-100 dark:border-slate-700 dark:disabled:bg-slate-800"
                      placeholder="Add official observations or context for the validators."
                    />
                  </label>
                  {selected.report.status === "draft" && (
                    <div className="mt-5 flex flex-wrap gap-2">
                      <button
                        disabled={busy}
                        onClick={save}
                        className="rounded-xl border border-[#7D001D]/30 px-5 py-3 font-black text-[#7D001D] disabled:opacity-40 dark:text-red-300"
                      >
                        {busy === "save" ? "Saving..." : "Save Draft"}
                      </button>
                      <button
                        disabled={busy}
                        onClick={submit}
                        className="rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40"
                      >
                        {busy === "submit"
                          ? "Submitting..."
                          : "Submit to Program Head"}
                      </button>
                    </div>
                  )}
                </>
              )}
            </section>
          )}
        </div>
      )}
    </div>
  );
}
