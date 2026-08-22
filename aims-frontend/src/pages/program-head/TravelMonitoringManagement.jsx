import { useCallback, useEffect, useMemo, useState } from "react";
import L from "leaflet";
import {
  MapContainer,
  Marker,
  Polyline,
  Popup,
  TileLayer,
  useMap,
} from "react-leaflet";
import "leaflet/dist/leaflet.css";
import {
  FiCheck,
  FiClock,
  FiEye,
  FiFileText,
  FiMap,
  FiMapPin,
  FiRefreshCw,
  FiShield,
} from "react-icons/fi";
import toast from "react-hot-toast";
import api from "../../services/api";

const reportLabels = {
  draft: "Draft",
  pending_program_head: "Pending Program Head Validation",
  pending_vpaa: "Pending VPAA Final Validation",
  final_validated: "Final Validated",
};

const icons = {
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
      <div className="grid h-72 place-items-center rounded-3xl border border-dashed border-slate-300 text-sm text-slate-400">
        No route evidence.
      </div>
    );
  return (
    <div className="h-[400px] overflow-hidden rounded-3xl">
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
        {positions.length > 1 && (
          <Polyline
            positions={positions}
            pathOptions={{ color: "#7D001D", weight: 5 }}
          />
        )}
        {points.map((point) => (
          <Marker
            key={point.id}
            position={[point.latitude, point.longitude]}
            icon={icons[point.event_type] || icons.checkpoint}
          >
            <Popup>
              <p className="font-black">{point.label}</p>
              <p>{point.readable_location}</p>
              <p>
                {Number(point.latitude).toFixed(6)},{" "}
                {Number(point.longitude).toFixed(6)}
              </p>
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
      alt="Arrival evidence"
      className="h-72 w-full rounded-3xl object-cover"
    />
  ) : (
    <div className="grid h-72 place-items-center rounded-3xl bg-slate-100 text-sm text-slate-400 dark:bg-slate-800">
      Arrival photo unavailable
    </div>
  );
}

function Metric({ label, value, icon: Icon }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <div className="flex justify-between">
        <div>
          <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">
            {label}
          </p>
          <p className="mt-2 text-3xl font-black text-[#690016] dark:text-white">
            {value || 0}
          </p>
        </div>
        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-[#7D001D]/10 text-[#7D001D] dark:text-red-300">
          <Icon />
        </div>
      </div>
    </div>
  );
}

export default function TravelMonitoringManagement({
  apiBase = "/program-head/travel",
}) {
  const isVpaa = apiBase.startsWith("/vpaa");
  const [data, setData] = useState({ summary: {}, sessions: [] });
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(isVpaa ? "pending" : "sessions");
  const [selectedId, setSelectedId] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(
    async (quiet = false) => {
      if (!quiet) setLoading(true);
      try {
        const response = await api.get(apiBase);
        setData(response.data);
        setSelectedId((current) =>
          current && response.data.sessions.some((item) => item.id === current)
            ? current
            : null,
        );
      } catch (error) {
        toast.error(errorMessage(error, "Unable to load travel reports."));
      } finally {
        setLoading(false);
      }
    },
    [apiBase],
  );
  useEffect(() => {
    load();
  }, [load]);

  const availableTabs = isVpaa
    ? [
        ["pending", "Pending Final Validation"],
        ["final", "Final Validated"],
      ]
    : [
        ["sessions", "Travel Sessions"],
        ["pending", "Pending Validation"],
        ["forwarded", "Validated & Forwarded"],
      ];
  const sessions = data.sessions.filter((session) => {
    if (tab === "sessions") return true;
    if (tab === "pending")
      return (
        session.report?.status ===
        (isVpaa ? "pending_vpaa" : "pending_program_head")
      );
    if (tab === "forwarded")
      return ["pending_vpaa", "final_validated"].includes(
        session.report?.status,
      );
    return session.report?.status === "final_validated";
  });
  const selected =
    data.sessions.find((session) => session.id === selectedId) || null;

  const review = async () => {
    setBusy(true);
    try {
      const response = await api.put(
        `${apiBase}/reports/${selected.report.id}/review`,
      );
      toast.success(response.data.message);
      setSelectedId(null);
      setTab(isVpaa ? "final" : "forwarded");
      await load(true);
    } catch (error) {
      toast.error(errorMessage(error, "Unable to validate the report."));
    } finally {
      setBusy(false);
    }
  };

  if (loading)
    return (
      <div className="grid min-h-[420px] place-items-center">
        <div className="text-center">
          <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#7D001D]/15 border-b-[#7D001D]" />
          <p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">
            Loading travel workflow
          </p>
        </div>
      </div>
    );

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#690016] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8">
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]">
              <FiShield /> {isVpaa ? "Final validation" : "Program validation"}
            </div>
            <h1 className="text-2xl font-black sm:text-3xl">
              Travel Report Management
            </h1>
            <p className="mt-2 max-w-2xl text-sm text-white/70">
              Review the Coordinator-generated report against the preserved
              Student GPS route, timestamps, checkpoints, and arrival photo.
            </p>
          </div>
          <button
            onClick={() => load()}
            className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black"
          >
            <FiRefreshCw />
            Refresh
          </button>
        </div>
      </header>
      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric
          label="Active sessions"
          value={data.summary.active_sessions}
          icon={FiMapPin}
        />
        <Metric
          label="Completed sessions"
          value={data.summary.completed_sessions}
          icon={FiMap}
        />
        <Metric
          label="Pending validation"
          value={data.summary.pending_validation}
          icon={FiClock}
        />
        <Metric
          label="Validated reports"
          value={data.summary.validated_reports}
          icon={FiCheck}
        />
      </section>
      <nav className="flex gap-1 overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        {availableTabs.map(([id, label]) => (
          <button
            key={id}
            onClick={() => {
              setTab(id);
              setSelectedId(null);
            }}
            className={`whitespace-nowrap rounded-xl px-4 py-3 text-xs font-black ${tab === id ? "bg-[#7D001D] text-white" : "text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"}`}
          >
            {label}
          </button>
        ))}
      </nav>

      {!selected && (
        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-6">
          <h2 className="text-xl font-black">
            {availableTabs.find(([id]) => id === tab)?.[1]}
          </h2>
          <div className="mt-5 space-y-3">
            {sessions.map((session) => (
              <article
                key={session.id}
                className="flex flex-col gap-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <p className="text-xs font-black uppercase text-[#a8750b]">
                    {session.report?.report_number || session.session_code}
                  </p>
                  <p className="mt-1 text-lg font-black">
                    {session.student?.full_name}
                  </p>
                  <p className="mt-1 text-sm text-slate-500">
                    {session.student?.school_number} ·{" "}
                    {session.student?.program?.code} · {session.hte?.name}
                  </p>
                  <p className="mt-2 text-xs font-black text-[#7D001D] dark:text-red-300">
                    {session.report
                      ? `${reportLabels[session.report.status]} · Version ${session.report.version}`
                      : `Session ${session.status}`}
                  </p>
                </div>
                <button
                  onClick={() => setSelectedId(session.id)}
                  className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-4 py-3 text-sm font-black text-white"
                >
                  <FiEye />
                  Open Review
                </button>
              </article>
            ))}
            {!sessions.length && (
              <div className="py-16 text-center">
                <FiFileText className="mx-auto text-4xl text-slate-300" />
                <p className="mt-3 text-sm font-bold text-slate-400">
                  No records in this category.
                </p>
              </div>
            )}
          </div>
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
          <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-6">
            <div className="flex flex-wrap justify-between gap-4">
              <div>
                <p className="text-xs font-black uppercase text-[#a8750b]">
                  {selected.report?.report_number || selected.session_code}
                </p>
                <h2 className="mt-1 text-2xl font-black">
                  {selected.student?.full_name}
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  {selected.student?.school_number} ·{" "}
                  {selected.student?.program?.name} ·{" "}
                  {selected.student?.department?.name}
                </p>
                <p className="mt-1 text-sm text-slate-500">
                  HTE: {selected.hte?.name} · Prepared by:{" "}
                  {selected.report?.coordinator?.name || "Not generated"}
                </p>
              </div>
              {selected.report && (
                <span className="h-fit rounded-full bg-[#7D001D]/10 px-3 py-1 text-xs font-black text-[#7D001D] dark:text-red-300">
                  {reportLabels[selected.report.status]} · v
                  {selected.report.version}
                </span>
              )}
            </div>
            <div className="mt-5 grid gap-3 sm:grid-cols-5">
              {[
                ["Started", selected.start_time],
                ["Departed", selected.departed_at],
                ["Arrived", selected.arrived_at],
                ["Ended", selected.end_time],
                ["Route points", selected.points.length],
              ].map(([label, value]) => (
                <div
                  key={label}
                  className="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900"
                >
                  <p className="text-[10px] font-black uppercase text-slate-400">
                    {label}
                  </p>
                  <p className="mt-1 text-xs font-bold">
                    {label === "Route points"
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
            <div className="rounded-3xl border border-slate-100 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
              <h2 className="font-black">Recorded route evidence</h2>
              <div className="mt-4 space-y-2">
                {selected.points.map((point) => (
                  <div
                    key={point.id}
                    className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"
                  >
                    <div className="flex justify-between gap-2">
                      <p className="font-black">
                        {point.sequence}. {point.label}
                      </p>
                      <p className="text-xs text-slate-400">
                        {new Date(point.captured_at).toLocaleString()}
                      </p>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {point.readable_location}
                    </p>
                    <p className="mt-1 text-xs text-slate-400">
                      {point.latitude.toFixed(6)}, {point.longitude.toFixed(6)}{" "}
                      · ±{point.gps_accuracy || "—"}m
                    </p>
                    {point.remarks && (
                      <p className="mt-2 text-sm">{point.remarks}</p>
                    )}
                  </div>
                ))}
              </div>
            </div>
            <div className="rounded-3xl border border-slate-100 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
              <h2 className="mb-4 font-black">Final arrival photo</h2>
              <ProtectedImage endpoint={selected.arrival_photo_endpoint} />
            </div>
          </section>
          {selected.report && (
            <section className="rounded-3xl border border-slate-100 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 sm:p-6">
              <h2 className="text-xl font-black">Coordinator Report</h2>
              <p className="mt-1 text-sm text-slate-500">
                Generated{" "}
                {new Date(selected.report.generated_at).toLocaleString()} ·
                Submitted{" "}
                {selected.report.submitted_at
                  ? new Date(selected.report.submitted_at).toLocaleString()
                  : "not yet"}
              </p>
              <div className="mt-5 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                <p className="text-[10px] font-black uppercase text-slate-400">
                  Coordinator remarks
                </p>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-6">
                  {selected.report.remarks || "No Coordinator remarks."}
                </p>
              </div>
              {selected.report.validations.length > 0 && (
                <div className="mt-5">
                  <p className="text-sm font-black">Validation history</p>
                  <div className="mt-3 space-y-2">
                    {selected.report.validations.map((item) => (
                      <div
                        key={item.id}
                        className="rounded-2xl border border-slate-200 p-4 text-sm dark:border-slate-700"
                      >
                        <p className="font-black capitalize">
                          {item.action.replaceAll("_", " ")} · {item.reviewer}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          {item.reviewer_role.replaceAll("_", " ")} · Version{" "}
                          {item.report_version} ·{" "}
                          {new Date(item.reviewed_at).toLocaleString()}
                        </p>
                        {item.comments && (
                          <p className="mt-1">{item.comments}</p>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}
              <div className="mt-5 flex flex-wrap gap-2">
                {((!isVpaa &&
                  selected.report.status === "pending_program_head") ||
                  (isVpaa && selected.report.status === "pending_vpaa")) && (
                  <button
                    disabled={busy}
                    onClick={review}
                    className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-5 py-3 font-black text-white disabled:opacity-40"
                  >
                    <FiShield />
                    {busy
                      ? "Validating..."
                      : isVpaa
                        ? "Final Validate"
                        : "Validate & Send to VPAA"}
                  </button>
                )}
              </div>
            </section>
          )}
        </div>
      )}
    </div>
  );
}
