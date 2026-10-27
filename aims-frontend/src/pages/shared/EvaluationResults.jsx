import { useCallback, useEffect, useState } from "react";
import { StarIcon } from "@heroicons/react/24/outline";
import api from "../../services/api";
import { PageIntro } from "../../components/common/PortalUI";
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
} from "../../components/evaluations/evaluationUtils";

export default function EvaluationResults({ embedded = false }) {
  const [data, setData] = useState(null);
  const [detail, setDetail] = useState(null);
  const [error, setError] = useState("");
  const [filters, setFilters] = useState({
    search: "",
    academic_period_id: "",
    section_id: "",
    status: "",
    page: 1,
    legacy_page: 1,
  });
  const load = useCallback(async () => {
    try {
      setData(
        (
          await api.get("/evaluation-results", {
            params: Object.fromEntries(
              Object.entries(filters).filter(([, v]) => v !== ""),
            ),
          })
        ).data,
      );
      setError("");
    } catch (e) {
      setError(errorText(e));
    }
  }, [filters]);
  useEffect(() => {
    const timer = setTimeout(load, 200);
    return () => clearTimeout(timer);
  }, [load]);
  const filter = (key, value) =>
    setFilters((f) => ({ ...f, [key]: value, page: 1 }));
  const open = async (id) => {
    try {
      setDetail((await api.get(`/evaluation-results/${id}`)).data);
      setError("");
    } catch (e) {
      setError(errorText(e));
    }
  };
  return (
    <div className="evaluation-workspace space-y-5">
      {!embedded && (
        <PageIntro
          eyebrow="Academic monitoring"
          title="Evaluation Results"
          description="View submitted supervisor evaluations within your assigned scope."
          icon={StarIcon}
        />
      )}
      <ErrorBox message={error} />
      {detail ? (
        <>
          <Button onClick={() => setDetail(null)}>Back to results</Button>
          <div className="ev-panel">
            <h2 className="text-xl font-bold">{detail.student?.name}</h2>
            <p className="text-sm">
              Evaluator: {detail.evaluator?.name} · Submitted:{" "}
              {detail.evaluation.submitted_at
                ? new Date(detail.evaluation.submitted_at).toLocaleString()
                : "—"}{" "}
              · Read-only
            </p>
          </div>
          {detail.legacy ? (
            <LegacyResult data={detail} />
          ) : (
            <>
              <ScaleGuide form={detail.form} />
              <Summary summary={detail.summary} />
              <Rubric
                form={detail.form}
                responses={detail.evaluation.responses}
              />
              <div className="ev-panel space-y-3">
                <p className="whitespace-pre-wrap">
                  Feedback: {detail.evaluation.feedback || "—"}
                </p>
                <p className="whitespace-pre-wrap">
                  Recommendations: {detail.evaluation.recommendations || "—"}
                </p>
              </div>
            </>
          )}
        </>
      ) : (
        <>
          <section className="ev-panel grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Field label="Search student">
              <input
                className="ev-input"
                value={filters.search}
                onChange={(e) => filter("search", e.target.value)}
              />
            </Field>
            <Field label="Academic period">
              <select
                className="ev-input"
                value={filters.academic_period_id}
                onChange={(e) => filter("academic_period_id", e.target.value)}
              >
                <option value="">All periods</option>
                {data?.periods.map((p) => (
                  <option key={p.id} value={p.id}>
                    {periodLabel(p)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Section">
              <select
                className="ev-input"
                value={filters.section_id}
                onChange={(e) => filter("section_id", e.target.value)}
              >
                <option value="">All authorized sections</option>
                {data?.sections
                  .filter(
                    (s) =>
                      !filters.academic_period_id ||
                      s.academic_period_id ===
                        Number(filters.academic_period_id),
                  )
                  .map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
              </select>
            </Field>
            <Field label="Status">
              <select
                className="ev-input"
                value={filters.status}
                onChange={(e) => filter("status", e.target.value)}
              >
                <option value="">All statuses</option>
                <option value="not_started">Not started</option>
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
              </select>
            </Field>
          </section>
          {!data && <p role="status">Loading results…</p>}
          {data?.results.data.map((item) => (
            <article className="ev-panel space-y-2" key={item.id}>
              <div className="ev-toolbar justify-between">
                <h2 className="font-bold">
                  {item.name} · {item.student_id}
                </h2>
                <span className="ev-status">{statusLabel(item.status)}</span>
              </div>
              <p className="text-sm">
                {item.program} · {item.section} · {periodLabel(item.period)}
              </p>
              <p className="text-sm">
                Supervisor: {item.supervisor || "Unassigned"}
              </p>
              {item.overall !== null && (
                <p className="font-semibold">
                  Overall result: {item.overall} · {item.rating_direction}{" "}
                  number is better
                </p>
              )}
              {item.status === "submitted" ? (
                <Button onClick={() => open(item.evaluation_id)}>
                  View submitted result
                </Button>
              ) : (
                <p className="text-sm text-slate-500">
                  {item.status === "draft"
                    ? "Draft answers are private until submission."
                    : "Pending evaluation. No grade yet."}
                </p>
              )}
            </article>
          ))}
          {data?.results.data.length === 0 && (
            <p className="ev-panel">No internships match these filters.</p>
          )}
          <Pagination
            page={data?.results}
            onPage={(page) => setFilters({ ...filters, page })}
          />
          {data?.legacy.data.length > 0 && (
            <details className="ev-panel">
              <summary>Legacy Midterm / Final results</summary>
              <div className="space-y-3">
                {data.legacy.data.map((item) => (
                  <div className="ev-toolbar justify-between" key={item.id}>
                    <span>
                      {item.student} · {item.type}
                    </span>
                    <Button onClick={() => open(item.id)}>
                      View original result
                    </Button>
                  </div>
                ))}
                <Pagination
                  page={data.legacy}
                  onPage={(legacy_page) =>
                    setFilters({ ...filters, legacy_page })
                  }
                />
              </div>
            </details>
          )}
        </>
      )}
    </div>
  );
}
