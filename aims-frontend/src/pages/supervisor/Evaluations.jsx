import { useCallback, useEffect, useState } from "react";
import {
  StarIcon,
  CheckBadgeIcon,
  ClockIcon,
  UserGroupIcon,
} from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import api from "../../services/api";
import { PageIntro, MetricCard } from "../../components/common/PortalUI";
import {
  Button,
  ErrorBox,
  Field,
  LegacyResult,
  Pagination,
  Rubric,
  ScaleGuide,
  Summary,
} from "../../components/evaluations/EvaluationUI";
import {
  errorText,
  periodLabel,
  statusLabel,
  useUnsavedWarning,
} from "../../components/evaluations/evaluationUtils";

function EvaluationEditor({ initial, onBack }) {
  const [data, setData] = useState(initial);
  const savedAnswers = (ev) =>
    Object.fromEntries(
      (ev.responses || []).map((r) => [
        r.evaluation_criterion_id,
        {
          rating_option_id: r.evaluation_rating_option_id,
          is_no_rating: r.is_no_rating,
          extra_text: r.extra_text || "",
        },
      ]),
    );
  const [answers, setAnswers] = useState(() =>
    savedAnswers(initial.evaluation),
  );
  const [feedback, setFeedback] = useState(initial.evaluation.feedback || "");
  const [recommendations, setRecommendations] = useState(
    initial.evaluation.recommendations || "",
  );
  const [dirty, setDirty] = useState(false);
  const [review, setReview] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  useUnsavedWarning(dirty);
  const locked = data.evaluation.status !== "draft" || data.legacy;
  const answered = Object.values(answers).filter(
    (a) => a.rating_option_id || a.is_no_rating,
  ).length;
  const action = async (type) => {
    setBusy(true);
    setError("");
    try {
      if (type === "submit") {
        if (
          !window.confirm(
            "Submit and lock this evaluation? Submitted answers cannot be edited.",
          )
        )
          return;
        const response = await api.post(
          `/supervisor/evaluations/${data.evaluation.id}/submit`,
          { row_version: data.evaluation.row_version },
        );
        setData(response.data);
        setDirty(false);
        toast.success(response.data.message);
      } else {
        const response = await api.put(
          `/supervisor/evaluations/${data.evaluation.id}/draft`,
          {
            row_version: data.evaluation.row_version,
            feedback,
            recommendations,
            responses: Object.entries(answers).map(([id, answer]) => ({
              criterion_id: Number(id),
              rating_option_id: answer.rating_option_id ?? null,
              is_no_rating: answer.is_no_rating ?? false,
              extra_text: answer.extra_text || null,
            })),
          },
        );
        setData(response.data);
        setAnswers(savedAnswers(response.data.evaluation));
        setDirty(false);
        if (type === "review") {
          setData(
            (
              await api.get(
                `/supervisor/evaluations/${data.evaluation.id}/review`,
              )
            ).data,
          );
          setReview(true);
        } else toast.success("Draft saved.");
      }
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  const openMissing = (criterion) => {
    setReview(false);
    setTimeout(() => {
      const element = document.getElementById(`criterion-${criterion.id}`);
      let parent = element?.parentElement;
      while (parent) {
        if (parent.tagName === "DETAILS") parent.open = true;
        parent = parent.parentElement;
      }
      element?.scrollIntoView({ behavior: "smooth", block: "center" });
      document.getElementById(`rating-${criterion.id}`)?.focus();
    }, 0);
  };
  return (
    <div className="space-y-5">
      <div className="ev-toolbar justify-between">
        <Button
          disabled={busy}
          onClick={() => {
            if (!dirty || window.confirm("Discard unsaved evaluation changes?"))
              onBack();
          }}
        >
          Back to interns
        </Button>
        <span className="ev-status">{data.evaluation.status}</span>
      </div>
      <ErrorBox message={error} />
      <div className="ev-panel">
        <h2 className="text-xl font-bold">{data.student?.name}</h2>
        <p>{data.student?.student_id}</p>
        {data.internship && (
          <p className="mt-2 text-sm">
            {data.internship.section} ·{" "}
            {data.internship.hte || "HTE unavailable"} ·{" "}
            {data.internship.department || "Department unavailable"}
          </p>
        )}
        {locked && (
          <p className="mt-2 text-sm">
            Evaluator: {data.evaluator?.name} · Submitted:{" "}
            {data.evaluation.submitted_at
              ? new Date(data.evaluation.submitted_at).toLocaleString()
              : "—"}{" "}
            · Read-only
          </p>
        )}
      </div>
      {data.legacy ? (
        <LegacyResult data={data} />
      ) : (
        <>
          <ScaleGuide form={data.form} />
          {(locked || review) && <Summary summary={data.summary} />}
          {review && !locked && (
            <section className="ev-panel space-y-3">
              <h3 className="font-bold">Review before submission</h3>
              <ErrorBox message={data.summary.errors.join("\n")} />
              {data.summary.missing.map((m) => (
                <div key={m.id}>
                  <Button onClick={() => openMissing(m)}>
                    Complete: {m.label}
                  </Button>
                </div>
              ))}
            </section>
          )}
          <fieldset disabled={busy} className="space-y-4">
            <Rubric
              form={data.form}
              answers={answers}
              readOnly={locked || review}
              missing={review ? data.summary.missing : []}
              onAnswer={(id, answer) => {
                setAnswers((current) => ({ ...current, [id]: answer }));
                setDirty(true);
              }}
            />
            <section className="ev-panel grid gap-4 sm:grid-cols-2">
              <Field label="Performance feedback">
                <textarea
                  className="ev-input min-h-28"
                  maxLength={5000}
                  readOnly={locked || review}
                  value={feedback}
                  onChange={(e) => {
                    setFeedback(e.target.value);
                    setDirty(true);
                  }}
                />
              </Field>
              <Field label="Recommendations">
                <textarea
                  className="ev-input min-h-28"
                  maxLength={5000}
                  readOnly={locked || review}
                  value={recommendations}
                  onChange={(e) => {
                    setRecommendations(e.target.value);
                    setDirty(true);
                  }}
                />
              </Field>
            </section>
          </fieldset>
          {!locked && (
            <div className="ev-sticky space-y-3">
              <div className="ev-toolbar justify-between text-sm" role="status">
                <span>
                  {answered}/{data.summary.total_criteria} answered
                </span>
                <span>
                  {busy
                    ? "Saving…"
                    : dirty
                      ? "Unsaved changes"
                      : "All changes saved"}
                </span>
              </div>
              <progress
                aria-label="Answered criteria"
                value={answered}
                max={data.summary.total_criteria || 1}
                className="w-full accent-[#7D001D]"
              />
              <div className="ev-toolbar justify-end">
                {review ? (
                  <>
                    <Button disabled={busy} onClick={() => setReview(false)}>
                      Back to edit
                    </Button>
                    <Button
                      primary
                      disabled={busy || !data.summary.can_submit}
                      onClick={() => action("submit")}
                    >
                      Submit and lock
                    </Button>
                  </>
                ) : (
                  <>
                    <Button disabled={busy} onClick={() => action("save")}>
                      Save draft
                    </Button>
                    <Button
                      primary
                      disabled={busy}
                      onClick={() => action("review")}
                    >
                      Save and review
                    </Button>
                  </>
                )}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default function SupervisorEvaluations() {
  const [data, setData] = useState(null);
  const [editor, setEditor] = useState(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [search, setSearch] = useState("");
  const [period, setPeriod] = useState("");
  const [page, setPage] = useState(1);
  const [legacy, setLegacy] = useState(null);
  const load = useCallback(async () => {
    try {
      setData(
        (
          await api.get("/supervisor/evaluation-students", {
            params: { search, academic_period_id: period || undefined, page },
          })
        ).data,
      );
      setError("");
    } catch (e) {
      setError(errorText(e));
    }
  }, [search, period, page]);
  useEffect(() => {
    const timer = setTimeout(load, 200);
    return () => clearTimeout(timer);
  }, [load]);
  const open = async (student) => {
    setBusy(true);
    setError("");
    try {
      setEditor(
        (student.evaluation_id
          ? await api.get(`/supervisor/evaluations/${student.evaluation_id}`)
          : await api.post(
              `/supervisor/internships/${student.id}/evaluation/start`,
            )
        ).data,
      );
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  const history = async (historyPage = 1) => {
    try {
      setLegacy(
        (
          await api.get("/supervisor/evaluation-history", {
            params: { page: historyPage },
          })
        ).data.legacy,
      );
    } catch (e) {
      setError(errorText(e));
    }
  };
  return (
    <div className="evaluation-workspace space-y-5">
      <PageIntro
        eyebrow="Performance management"
        title="Student Evaluations"
        description="Complete one whole-semester evaluation for each assigned internship using its program rubric."
        icon={StarIcon}
      />
      {editor ? (
        <EvaluationEditor
          key={editor.evaluation.id}
          initial={editor}
          onBack={() => {
            setEditor(null);
            load();
          }}
        />
      ) : (
        <>
          <ErrorBox message={error} />
          <div className="grid gap-4 sm:grid-cols-3">
            <MetricCard
              label="Assigned interns"
              value={data?.overview.assigned || 0}
              icon={UserGroupIcon}
            />
            <MetricCard
              label="Submitted evaluations"
              value={data?.overview.submitted || 0}
              icon={CheckBadgeIcon}
              tone="green"
            />
            <MetricCard
              label="Remaining evaluations"
              value={data?.overview.remaining || 0}
              icon={ClockIcon}
              tone="gold"
            />
          </div>
          <div className="ev-panel grid gap-3 sm:grid-cols-2">
            <Field label="Search intern">
              <input
                className="ev-input"
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                placeholder="Student name or number"
              />
            </Field>
            <Field label="Academic period">
              <select
                className="ev-input"
                value={period}
                onChange={(e) => {
                  setPeriod(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">All assigned periods</option>
                {data?.periods.map((p) => (
                  <option key={p.id} value={p.id}>
                    {periodLabel(p)}
                  </option>
                ))}
              </select>
            </Field>
          </div>
          {!data && <p role="status">Loading evaluations…</p>}
          <div className="grid gap-4 lg:grid-cols-2">
            {data?.students.data.map((student) => (
              <article key={student.id} className="ev-panel space-y-3">
                <div className="ev-toolbar justify-between">
                  <h2 className="font-bold">{student.name}</h2>
                  <span className="ev-status">
                    {statusLabel(student.status)}
                  </span>
                </div>
                <p className="text-sm">
                  {student.student_id} · {student.program} · {student.section}
                </p>
                <p className="text-sm">{periodLabel(student.period)}</p>
                <p className="text-sm">
                  {student.hte || "HTE unavailable"} ·{" "}
                  {student.department || "Department unavailable"}
                </p>
                {student.blocked_reason && (
                  <p className="text-sm text-amber-700 dark:text-amber-300">
                    {student.blocked_reason}
                  </p>
                )}
                <Button
                  primary
                  disabled={busy || !!student.blocked_reason}
                  onClick={() => open(student)}
                >
                  {student.status === "submitted"
                    ? "View submitted result"
                    : student.status === "draft"
                      ? "Continue evaluation"
                      : "Evaluate whole semester"}
                </Button>
              </article>
            ))}
          </div>
          {data?.students.data.length === 0 && (
            <p className="ev-panel">No assigned internships found.</p>
          )}
          <Pagination page={data?.students} onPage={setPage} />
          <Button onClick={() => (legacy ? setLegacy(null) : history())}>
            Legacy Midterm / Final history
          </Button>
          {legacy && (
            <section className="ev-panel space-y-3">
              <h2 className="font-bold">
                Original evaluation records · Read-only
              </h2>
              {legacy.data.map((item) => (
                <div key={item.id} className="ev-toolbar justify-between">
                  <span>
                    {item.student?.first_name} {item.student?.last_name} ·{" "}
                    {item.evaluation_type} · {item.status}
                  </span>
                  <Button
                    disabled={busy}
                    onClick={async () => {
                      try {
                        setEditor(
                          (await api.get(`/supervisor/evaluations/${item.id}`))
                            .data,
                        );
                      } catch (e) {
                        setError(errorText(e));
                      }
                    }}
                  >
                    View
                  </Button>
                </div>
              ))}
              {legacy.data.length === 0 && <p>No legacy evaluations.</p>}
              <Pagination page={legacy} onPage={history} />
            </section>
          )}
        </>
      )}
    </div>
  );
}
