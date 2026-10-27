import { cloneElement, useId } from "react";
import { periodLabel } from "./evaluationUtils";
import "./evaluations.css";

export function ErrorBox({ message }) {
  return message ? (
    <div role="alert" className="ev-error">
      {message}
    </div>
  ) : null;
}
export function Field({ label, children }) {
  const generatedId = useId();
  const id = children.props.id || generatedId;
  return (
    <div className="block min-w-0">
      <label className="ev-label" htmlFor={id}>
        {label}
      </label>
      {cloneElement(children, { id })}
    </div>
  );
}
export function Button({ children, primary = false, ...props }) {
  return (
    <button
      type="button"
      className={`ev-button ${primary ? "ev-primary" : ""}`}
      {...props}
    >
      {children}
    </button>
  );
}
export function Pagination({ page, onPage }) {
  if (!page || page.last_page <= 1) return null;
  return (
    <div className="ev-toolbar">
      <Button
        disabled={page.current_page <= 1}
        onClick={() => onPage(page.current_page - 1)}
      >
        Previous
      </Button>
      <span>
        Page {page.current_page} of {page.last_page}
      </span>
      <Button
        disabled={page.current_page >= page.last_page}
        onClick={() => onPage(page.current_page + 1)}
      >
        Next
      </Button>
    </div>
  );
}
export function ScaleGuide({ form }) {
  return (
    <div className="ev-panel space-y-3">
      <p className="font-bold">
        {form.name} · Version {form.version}
      </p>
      <p className="text-sm">
        {form.program?.code} · {periodLabel(form.academic_period)} · Whole
        Semester
      </p>
      <p className="text-sm">
        Better performance:{" "}
        <strong>{form.rating_direction || "Not configured"} number</strong>
      </p>
      <div className="flex flex-wrap gap-2">
        {form.rating_options.map((option, i) => (
          <span className="ev-status ev-rating" key={option.id || i}>
            {option.numeric_value} — {option.label}
          </span>
        ))}
      </div>
      <p className="text-sm">
        No Rating:{" "}
        {form.allow_no_rating
          ? "Allowed for unobserved criteria. Excluded from numeric averages."
          : "Not allowed."}
      </p>
      <p className="text-sm">
        Computation:{" "}
        {form.computation_method?.replaceAll("_", " ") || "Not configured"}
        {form.computation_method !== "ratings_only" && (
          <>
            {" "}
            · {form.computation_settings?.decimal_places ?? "—"} decimal places
            ·{" "}
            {form.computation_settings?.rounding?.replaceAll("_", " ") ||
              "Rounding not set"}
            {form.computation_method === "category_mean" && (
              <>
                {" "}
                · Empty categories:{" "}
                {form.computation_settings?.empty_category?.replaceAll(
                  "_",
                  " ",
                ) || "Not set"}
              </>
            )}
          </>
        )}
      </p>
    </div>
  );
}
export function Rubric({
  form,
  responses = [],
  answers,
  onAnswer,
  readOnly = true,
  preview = false,
  missing = [],
}) {
  const saved = Object.fromEntries(
    responses.map((r) => [
      r.evaluation_criterion_id,
      {
        rating_option_id: r.evaluation_rating_option_id,
        is_no_rating: r.is_no_rating,
        extra_text: r.extra_text || "",
      },
    ]),
  );
  const options = Object.fromEntries(
    form.rating_options.map((o) => [String(o.id), o]),
  );
  const renderCriterion = (criterion, index) => {
    const answer = answers?.[criterion.id] || saved[criterion.id];
    const selected = answer?.is_no_rating
      ? "no_rating"
      : String(answer?.rating_option_id ?? "");
    const option = options[selected];
    const isMissing = missing.some((m) => m.id === criterion.id);
    return (
      <div
        id={`criterion-${criterion.id}`}
        className="ev-criterion scroll-mt-24"
        key={criterion.id || index}
      >
        <label className="block" htmlFor={`rating-${criterion.id}`}>
          <span className="font-medium">{criterion.label}</span>
          {isMissing && (
            <span className="ml-2 text-sm text-rose-700">Rating required</span>
          )}
        </label>
        {readOnly && !preview ? (
          <p className="mt-2 text-sm font-semibold">
            {selected === "no_rating"
              ? "No Rating"
              : option
                ? `${option.numeric_value} — ${option.label}`
                : "Unanswered"}
          </p>
        ) : (
          <select
            disabled={preview}
            id={`rating-${criterion.id}`}
            aria-invalid={isMissing}
            className="ev-input mt-2"
            value={selected}
            onChange={(event) =>
              onAnswer(criterion.id, {
                ...answer,
                rating_option_id:
                  event.target.value && event.target.value !== "no_rating"
                    ? Number(event.target.value)
                    : null,
                is_no_rating: event.target.value === "no_rating",
              })
            }
          >
            <option value="">Select a rating</option>
            {form.rating_options.map((o) => (
              <option key={o.id} value={o.id}>
                {o.numeric_value} — {o.label}
              </option>
            ))}
            {form.allow_no_rating && (
              <option value="no_rating">No Rating — not observed</option>
            )}
          </select>
        )}
        {criterion.extra_input_label && (
          <div className="mt-3">
            {readOnly && !preview ? (
              <p className="text-sm">
                {criterion.extra_input_label}: {answer?.extra_text || "—"}
              </p>
            ) : (
              <Field label={criterion.extra_input_label}>
                <input
                  className="ev-input"
                  maxLength={2000}
                  disabled={preview || !selected}
                  placeholder={
                    selected ? "Specify here" : "Select a rating first"
                  }
                  value={answer?.extra_text || ""}
                  onChange={(event) =>
                    onAnswer(criterion.id, {
                      ...answer,
                      extra_text: event.target.value,
                    })
                  }
                />
              </Field>
            )}
          </div>
        )}
      </div>
    );
  };
  return (
    <div className="space-y-4">
      {form.categories.map((category, i) => (
        <details className="ev-panel" open key={category.id || i}>
          <summary>{category.title}</summary>
          {category.criteria.map(renderCriterion)}
          {category.subgroups.map((group, j) => (
            <details
              className="ml-2 mt-3 border-l-2 border-amber-200 pl-4"
              open
              key={group.id || j}
            >
              <summary>{group.title}</summary>
              {group.criteria.map(renderCriterion)}
            </details>
          ))}
        </details>
      ))}
    </div>
  );
}
export function Summary({ summary }) {
  return (
    <div className="ev-panel space-y-2">
      <p className="font-bold">
        {summary.overall === null
          ? "Criterion ratings"
          : `Overall result: ${summary.overall}`}
      </p>
      <p className="text-sm">
        {summary.answered}/{summary.total_criteria} answered ·{" "}
        {summary.numeric_count} numeric · {summary.no_rating_count} No Rating
      </p>
      {summary.method !== "ratings_only" &&
        summary.categories.map((c) => (
          <p className="text-sm" key={c.id}>
            {c.title}: {c.numeric_count ? c.mean : "No Rating"} (
            {c.numeric_count} numeric)
          </p>
        ))}
    </div>
  );
}
export function LegacyResult({ data }) {
  return (
    <div className="ev-panel space-y-3">
      <p className="font-bold">
        Legacy {data.evaluation.evaluation_type} evaluation ·{" "}
        {data.student?.name}
      </p>
      <p>Original six-criterion rating format · {data.evaluation.status}</p>
      {[
        "work_quality",
        "communication",
        "professionalism",
        "attendance",
        "technical_skills",
        "teamwork",
      ].map((key) => (
        <p key={key} className="capitalize">
          {key.replaceAll("_", " ")}: {data.evaluation[key] ?? "—"}
        </p>
      ))}
      <p className="whitespace-pre-wrap">
        Feedback: {data.evaluation.feedback || "—"}
      </p>
      <p className="whitespace-pre-wrap">
        Recommendations: {data.evaluation.recommendations || "—"}
      </p>
    </div>
  );
}
