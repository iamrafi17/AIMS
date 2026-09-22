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
  CheckCircleIcon,
  ClockIcon,
  DocumentTextIcon,
  EyeIcon,
  MapIcon,
  MapPinIcon,
} from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import api from "../../services/api";
import {
  EmptyPanel,
  LoadingPanel,
  MetricCard,
  PageIntro,
} from "../../components/common/PortalUI";

const tabs = [
  ["active", "Active Sessions"],
  ["completed", "Completed Sessions"],
  ["pending", "Submitted Reports"],
  ["validated", "Validated Reports"],
];

const reportLabels = {
  draft: "Draft",
  pending_program_head: "Pending Program Head Validation",
  pending_vpaa: "Pending VPAA Final Validation",
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
  const [tab, setTab] = useState("active");
  const [selectedId, setSelectedId] = useState(null);
  const [remarks, setRemarks] = useState("");
  const [busy, setBusy] = useState("");

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    try {
      const response = await api.get("/coordinator/travel");
      setData(response.data);
      setSelectedId((current) =>
        current && response.data.sessions.some((item) => item.id === current)
          ? current
          : null,
      );
    } catch (error) {
      toast.error(
        errorMessage(error, "Unable to load Student travel sessions."),
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);
  const sessions = data.sessions.filter((session) =>
    belongsToTab(session, tab),
  );
  const selected =
    data.sessions.find((session) => session.id === selectedId) || null;

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
        title="Travel Monitoring Management"
        description="Monitor Student-submitted GPS routes, review completed evidence, and prepare the official report for Program Head validation."
        icon={MapIcon}
        actions={
          <button
            onClick={() => load()}
            className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-black text-white"
          >
            <ArrowPathIcon className="h-5 w-5" />
            Refresh
          </button>
        }
      />
      <section className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
        className="portal-mobile-tabs flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900"
        aria-label="Travel workflow sections"
      >
        {tabs.map(([id, label]) => (
          <button
            key={id}
            onClick={() => {
              setTab(id);
              setSelectedId(null);
            }}
            className={`whitespace-nowrap rounded-xl px-4 py-3 text-xs font-black ${tab === id ? "bg-[#7D001D] text-white" : "text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"}`}
          >
            {label}
          </button>
        ))}
      </nav>

      {!selected && (
        <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
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
              <EmptyPanel
                icon={MapIcon}
                title="No records in this section"
                description="Matching travel sessions and reports will appear here automatically."
              />
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
                      : value
                        ? new Date(value).toLocaleString()
                        : "—"}
                  </p>
                </div>
              ))}
            </div>
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
