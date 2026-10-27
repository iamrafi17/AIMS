import { useCallback, useEffect, useState } from "react";
import { StarIcon } from "@heroicons/react/24/outline";
import toast from "react-hot-toast";
import api from "../../services/api";
import { PageIntro } from "../../components/common/PortalUI";
import {
  Button,
  ErrorBox,
  Field,
  Pagination,
  Rubric,
  ScaleGuide,
} from "../../components/evaluations/EvaluationUI";
import {
  errorText,
  periodLabel,
  useUnsavedWarning,
} from "../../components/evaluations/evaluationUtils";
import EvaluationResults from "../shared/EvaluationResults";

const categoryTemplate = () => ({ title: "", criteria: [], subgroups: [] });
async function deleteDraft(form) {
  if (
    !window.confirm(
      `Delete draft "${form.name}" and all of its criteria and rating options? This cannot be undone.`,
    )
  )
    return false;
  const { data } = await api.delete(
    `/coordinator/evaluation-forms/${form.id}`,
    { data: { row_version: form.row_version } },
  );
  toast.success(data.message);
  return true;
}
const move = (items, index, step) => {
  const copy = [...items];
  [copy[index], copy[index + step]] = [copy[index + step], copy[index]];
  return copy;
};
function OrderButtons({ index, items, setItems, label }) {
  return (
    <div className="ev-toolbar">
      <Button
        aria-label={`Move ${label} up`}
        disabled={index === 0}
        onClick={() => setItems(move(items, index, -1))}
      >
        ↑
      </Button>
      <Button
        aria-label={`Move ${label} down`}
        disabled={index === items.length - 1}
        onClick={() => setItems(move(items, index, 1))}
      >
        ↓
      </Button>
      <Button onClick={() => setItems(items.filter((_, i) => i !== index))}>
        Remove
      </Button>
    </div>
  );
}
function CriteriaEditor({ criteria, onChange }) {
  return (
    <div className="space-y-3">
      {criteria.map((criterion, i) => (
        <div
          key={i}
          className="space-y-2 rounded-xl border border-slate-200 p-3"
        >
          <Field label={`Criterion ${i + 1}`}>
            <textarea
              className="ev-input"
              maxLength={4000}
              value={criterion.label}
              onChange={(e) =>
                onChange(
                  criteria.map((c, j) =>
                    j === i ? { ...c, label: e.target.value } : c,
                  ),
                )
              }
            />
          </Field>
          <Field label="Optional text field label (e.g. Programming language)">
            <input
              className="ev-input"
              maxLength={160}
              value={criterion.extra_input_label || ""}
              onChange={(e) =>
                onChange(
                  criteria.map((c, j) =>
                    j === i
                      ? { ...c, extra_input_label: e.target.value || null }
                      : c,
                  ),
                )
              }
            />
          </Field>
          <OrderButtons
            index={i}
            items={criteria}
            setItems={onChange}
            label="criterion"
          />
        </div>
      ))}
      <Button
        onClick={() =>
          onChange([...criteria, { label: "", extra_input_label: null }])
        }
      >
        Add criterion
      </Button>
    </div>
  );
}

function CsvImport({ form, onImported, onClose }) {
  const [file, setFile] = useState(null);
  const [mapping, setMapping] = useState({
    layout: "report",
    category_column: "A",
    criterion_column: "B",
    subgroup_column: "",
    start_row: "1",
    end_row: "",
  });
  const [preview, setPreview] = useState(null);
  const [mode, setMode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const col = (value) =>
    [...value.toUpperCase()].reduce(
      (n, c) => n * 26 + c.charCodeAt(0) - 64,
      0,
    ) - 1;
  const change = (key, value) => {
    setMapping({ ...mapping, [key]: value });
    setPreview(null);
    setMode("");
  };
  const parse = async () => {
    setBusy(true);
    setError("");
    setPreview(null);
    try {
      if (!file) throw new Error("Choose a CSV file.");
      const body = new FormData();
      body.append("file", file);
      body.append("row_version", form.row_version);
      for (const [key, value] of Object.entries(mapping)) {
        if (value !== "") {
          if (key.endsWith("_column") && !/^[A-Za-z]{1,2}$/.test(value))
            throw new Error("Enter column letters such as A, B, or AA.");
          body.append(key, key.endsWith("_column") ? col(value) : value);
        }
      }
      setPreview(
        (
          await api.post(
            `/coordinator/evaluation-forms/${form.id}/imports/preview`,
            body,
            { headers: { "Content-Type": "multipart/form-data" } },
          )
        ).data,
      );
    } catch (e) {
      setError(e.response ? errorText(e) : e.message);
    } finally {
      setBusy(false);
    }
  };
  const confirm = async () => {
    setBusy(true);
    setError("");
    try {
      const { data } = await api.post(
        `/coordinator/evaluation-forms/${form.id}/imports/confirm`,
        { token: preview.token, mode },
      );
      onImported(data.form);
      toast.success(data.message);
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  return (
    <section className="ev-panel space-y-4" aria-label="CSV import">
      <div className="ev-toolbar justify-between">
        <h2 className="text-lg font-bold">
          Import CSV · Preview before applying
        </h2>
        <Button disabled={busy} onClick={onClose}>
          Cancel import
        </Button>
      </div>
      <ErrorBox message={error} />
      <Field label="CSV file (up to 2 MB)">
        <input
          type="file"
          accept=".csv,.txt"
          onChange={(e) => {
            setFile(e.target.files[0]);
            setPreview(null);
          }}
        />
      </Field>
      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Layout">
          <select
            className="ev-input"
            value={mapping.layout}
            onChange={(e) => change("layout", e.target.value)}
          >
            <option value="report">
              Report: category + numbered/lettered items
            </option>
            <option value="normalized">
              Columns: category, subgroup, criterion
            </option>
          </select>
        </Field>
        {[
          ["category_column", "Category column"],
          ["criterion_column", "Criterion column"],
          ...(mapping.layout === "normalized"
            ? [["subgroup_column", "Subgroup column (optional)"]]
            : []),
          ["start_row", "Start row"],
          ["end_row", "End row (optional)"],
        ].map(([key, label]) => (
          <Field key={key} label={label}>
            <input
              className="ev-input"
              value={mapping[key]}
              onChange={(e) => change(key, e.target.value)}
            />
          </Field>
        ))}
      </div>
      <p className="text-sm text-slate-500">
        Map the actual columns used by your program. Student columns, averages,
        and the documents checklist are excluded. Ambiguous report rows must be
        corrected before import.
      </p>
      <Button disabled={busy} onClick={parse}>
        Preview import
      </Button>
      {preview && (
        <>
          <p className="font-bold">
            {preview.category_count} categories · {preview.subgroup_count}{" "}
            subgroups · {preview.criteria_count} criteria
          </p>
          <ErrorBox
            message={preview.errors
              .map((e) => `${e.row ? `Row ${e.row}: ` : ""}${e.message}`)
              .join("\n")}
          />
          <details>
            <summary>Skipped rows ({preview.skipped.length})</summary>
            {preview.skipped.map((s) => (
              <p key={s.row} className="text-sm">
                Row {s.row}: {s.reason}
              </p>
            ))}
          </details>
          {preview.categories.map((c, i) => (
            <details key={i} open>
              <summary>{c.title}</summary>
              {c.criteria.map((q, j) => (
                <p className="text-sm py-1" key={j}>
                  {q.label}
                </p>
              ))}
              {c.subgroups.map((g, j) => (
                <div key={j} className="ml-4">
                  <p className="font-semibold">{g.title}</p>
                  {g.criteria.map((q, k) => (
                    <p className="text-sm py-1" key={k}>
                      {q.label}
                    </p>
                  ))}
                </div>
              ))}
            </details>
          ))}
          {preview.token && (
            <div className="ev-toolbar">
              <Field label="Apply to this draft">
                <select
                  className="ev-input"
                  value={mode}
                  onChange={(e) => setMode(e.target.value)}
                >
                  <option value="">Choose Append or Replace</option>
                  <option value="append">Append to existing categories</option>
                  <option value="replace">Replace all existing criteria</option>
                </select>
              </Field>
              <Button primary disabled={busy || !mode} onClick={confirm}>
                Confirm import
              </Button>
            </div>
          )}
        </>
      )}
    </section>
  );
}

function Builder({ initial, onBack }) {
  const [form, setForm] = useState(initial);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [preview, setPreview] = useState(initial.status !== "draft");
  const [importing, setImporting] = useState(false);
  useUnsavedWarning(dirty);
  const editable = form.status === "draft";
  const change = (key, value) => {
    setForm((current) => ({ ...current, [key]: value }));
    setDirty(true);
  };
  const save = async () => {
    const { data } = await api.patch(
      `/coordinator/evaluation-forms/${form.id}`,
      form,
    );
    setForm(data.form);
    setDirty(false);
    return data.form;
  };
  const action = async (type) => {
    setBusy(true);
    setError("");
    try {
      if (type === "delete") {
        if (await deleteDraft(form)) {
          setDirty(false);
          onBack();
        }
        return;
      }
      const saved = dirty ? await save() : form;
      if (type === "publish") {
        if (
          !window.confirm(
            "Publish and lock this form version for this program and academic period?",
          )
        )
          return;
        const { data } = await api.post(
          `/coordinator/evaluation-forms/${saved.id}/publish`,
          { row_version: saved.row_version },
        );
        setForm(data.form);
        setPreview(true);
        toast.success(data.message);
      } else if (type === "preview") setPreview(true);
      else if (type === "import") setImporting(true);
      else toast.success("Form draft saved.");
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  const updateCategory = (i, key, value) =>
    change(
      "categories",
      form.categories.map((c, j) => (i === j ? { ...c, [key]: value } : c)),
    );
  return (
    <div className="space-y-5">
      <div className="ev-toolbar justify-between">
        <Button
          disabled={busy}
          onClick={() => {
            if (!dirty || window.confirm("Discard unsaved form changes?"))
              onBack();
          }}
        >
          Back to forms
        </Button>
        <span role="status">
          {editable
            ? dirty
              ? "Unsaved changes"
              : "Draft saved"
            : `Version ${form.version} · ${form.status} · read-only`}
        </span>
        {editable && (
          <Button disabled={busy} onClick={() => action("delete")}>
            Delete draft
          </Button>
        )}
      </div>
      <ErrorBox message={error} />
      {importing ? (
        <CsvImport
          form={form}
          onClose={() => setImporting(false)}
          onImported={(updated) => {
            setForm(updated);
            setDirty(false);
            setImporting(false);
          }}
        />
      ) : preview ? (
        <>
          <ScaleGuide form={form} />
          <Rubric form={form} preview />
          {editable && (
            <div className="ev-toolbar">
              <Button onClick={() => setPreview(false)}>Back to edit</Button>
              <Button primary disabled={busy} onClick={() => action("publish")}>
                Publish version
              </Button>
            </div>
          )}
        </>
      ) : (
        <fieldset disabled={busy} className="space-y-5">
          <section className="ev-panel space-y-4">
            <h2 className="text-lg font-bold">
              Form settings · Version {form.version}
            </h2>
            <p>
              {form.program?.code} · {periodLabel(form.academic_period)} · Whole
              Semester
            </p>
            <Field label="Form name">
              <input
                className="ev-input"
                maxLength={160}
                value={form.name}
                onChange={(e) => change("name", e.target.value)}
              />
            </Field>
            <Field label="Better performance">
              <select
                className="ev-input"
                value={form.rating_direction || ""}
                onChange={(e) =>
                  change("rating_direction", e.target.value || null)
                }
              >
                <option value="">Choose direction</option>
                <option value="lower">Lower number is better</option>
                <option value="higher">Higher number is better</option>
              </select>
            </Field>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.allow_no_rating}
                onChange={(e) => change("allow_no_rating", e.target.checked)}
              />
              Allow explicit No Rating for unobserved criteria
            </label>
            <h3 className="font-bold">Rating scale</h3>
            {form.rating_options.map((o, i) => (
              <div className="grid gap-2 sm:grid-cols-[120px_1fr_auto]" key={i}>
                <Field label="Number">
                  <input
                    className="ev-input"
                    type="number"
                    step="0.001"
                    value={o.numeric_value}
                    onChange={(e) =>
                      change(
                        "rating_options",
                        form.rating_options.map((v, j) =>
                          i === j ? { ...v, numeric_value: e.target.value } : v,
                        ),
                      )
                    }
                  />
                </Field>
                <Field label="Meaning">
                  <input
                    className="ev-input"
                    maxLength={500}
                    value={o.label}
                    onChange={(e) =>
                      change(
                        "rating_options",
                        form.rating_options.map((v, j) =>
                          i === j ? { ...v, label: e.target.value } : v,
                        ),
                      )
                    }
                  />
                </Field>
                <Button
                  onClick={() =>
                    change(
                      "rating_options",
                      form.rating_options.filter((_, j) => i !== j),
                    )
                  }
                >
                  Remove rating
                </Button>
              </div>
            ))}
            <Button
              onClick={() =>
                change("rating_options", [
                  ...form.rating_options,
                  { numeric_value: "", label: "" },
                ])
              }
            >
              Add rating
            </Button>
          </section>
          <section className="ev-panel space-y-3">
            <h3 className="font-bold">Official computation</h3>
            <Field label="Computation mode">
              <select
                className="ev-input"
                value={form.computation_method || ""}
                onChange={(e) => change("computation_method", e.target.value)}
              >
                <option value="">Select mode</option>
                <option value="ratings_only">
                  Ratings only — no overall grade
                </option>
                <option value="rated_item_mean">
                  Mean of numerically rated criteria
                </option>
                <option value="category_mean">
                  Unweighted mean of category averages
                </option>
              </select>
            </Field>
            <p className="text-sm text-slate-500">
              Choose the method specified by your program. CSV result labels do
              not define a grading formula.
            </p>
            {form.computation_method &&
              form.computation_method !== "ratings_only" && (
                <div className="grid gap-3 sm:grid-cols-3">
                  <Field label="Display decimal places">
                    <select
                      className="ev-input"
                      value={form.computation_settings?.decimal_places ?? ""}
                      onChange={(e) =>
                        change("computation_settings", {
                          ...form.computation_settings,
                          decimal_places: Number(e.target.value),
                          rounding: "half_up",
                        })
                      }
                    >
                      <option value="">Select precision</option>
                      {[0, 1, 2, 3].map((n) => (
                        <option key={n}>{n}</option>
                      ))}
                    </select>
                  </Field>
                  <p className="text-sm self-center">Rounding: Half up</p>
                  {form.computation_method === "category_mean" && (
                    <Field label="Category with no numeric ratings">
                      <select
                        className="ev-input"
                        value={form.computation_settings?.empty_category || ""}
                        onChange={(e) =>
                          change("computation_settings", {
                            ...form.computation_settings,
                            empty_category: e.target.value,
                          })
                        }
                      >
                        <option value="">Choose policy</option>
                        <option value="exclude">
                          Exclude from category mean
                        </option>
                        <option value="require_numeric">
                          Require a numeric rating in each category
                        </option>
                      </select>
                    </Field>
                  )}
                </div>
              )}
          </section>
          <div className="ev-toolbar">
            <h3 className="font-bold flex-1">Categories and criteria</h3>
            <Button onClick={() => action("import")}>Import CSV</Button>
          </div>
          {form.categories.map((c, i) => (
            <section key={i} className="ev-panel space-y-4">
              <Field label={`Category ${i + 1}`}>
                <input
                  className="ev-input"
                  maxLength={500}
                  value={c.title}
                  onChange={(e) => updateCategory(i, "title", e.target.value)}
                />
              </Field>
              <OrderButtons
                index={i}
                items={form.categories}
                setItems={(items) => change("categories", items)}
                label="category"
              />
              <CriteriaEditor
                criteria={c.criteria}
                onChange={(items) => updateCategory(i, "criteria", items)}
              />
              {c.subgroups.map((g, j) => (
                <div
                  key={j}
                  className="space-y-3 border-l-2 border-amber-300 pl-4"
                >
                  <Field label="Subgroup title">
                    <input
                      className="ev-input"
                      maxLength={500}
                      value={g.title}
                      onChange={(e) =>
                        updateCategory(
                          i,
                          "subgroups",
                          c.subgroups.map((s, k) =>
                            j === k ? { ...s, title: e.target.value } : s,
                          ),
                        )
                      }
                    />
                  </Field>
                  <OrderButtons
                    index={j}
                    items={c.subgroups}
                    setItems={(items) => updateCategory(i, "subgroups", items)}
                    label="subgroup"
                  />
                  <CriteriaEditor
                    criteria={g.criteria}
                    onChange={(items) =>
                      updateCategory(
                        i,
                        "subgroups",
                        c.subgroups.map((s, k) =>
                          j === k ? { ...s, criteria: items } : s,
                        ),
                      )
                    }
                  />
                </div>
              ))}
              <Button
                onClick={() =>
                  updateCategory(i, "subgroups", [
                    ...c.subgroups,
                    { title: "", criteria: [] },
                  ])
                }
              >
                Add subgroup
              </Button>
            </section>
          ))}
          <Button
            onClick={() =>
              change("categories", [...form.categories, categoryTemplate()])
            }
          >
            Add category
          </Button>
          <div className="ev-sticky ev-toolbar">
            <span className="flex-1 text-sm" role="status">
              {dirty ? "Unsaved changes" : "Draft saved"}
            </span>
            <Button onClick={() => action("save")}>Save draft</Button>
            <Button primary onClick={() => action("preview")}>
              Save and preview
            </Button>
          </div>
        </fieldset>
      )}
    </div>
  );
}

export default function EvaluationForms() {
  const [tab, setTab] = useState("forms");
  const [data, setData] = useState(null);
  const [form, setForm] = useState(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState("");
  const [period, setPeriod] = useState("");
  const [page, setPage] = useState(1);
  const load = useCallback(async () => {
    try {
      setData(
        (await api.get("/coordinator/evaluation-forms", { params: { page } }))
          .data,
      );
      setError("");
    } catch (e) {
      setError(errorText(e));
    }
  }, [page]);
  useEffect(() => {
    load();
  }, [load]);
  const run = async (action) => {
    setBusy(true);
    setError("");
    try {
      await action();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="evaluation-workspace space-y-5">
      <PageIntro
        eyebrow="Program evaluation"
        title="Evaluations"
        description="Manage whole-semester rubrics and view submitted results."
        icon={StarIcon}
      />
      {form ? (
        <Builder
          key={form.id}
          initial={form}
          onBack={() => {
            setForm(null);
            load();
          }}
        />
      ) : (
        <>
          <div className="ev-toolbar">
            <Button primary={tab === "forms"} onClick={() => setTab("forms")}>
              Evaluation Forms
            </Button>
            <Button
              primary={tab === "results"}
              onClick={() => setTab("results")}
            >
              Evaluation Results
            </Button>
          </div>
          {tab === "results" ? (
            <EvaluationResults embedded />
          ) : (
            <>
              <ErrorBox message={error} />
              <form
                className="ev-panel space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  run(async () => {
                    const response = await api.post(
                      "/coordinator/evaluation-forms",
                      { name, academic_period_id: Number(period) },
                    );
                    setForm(response.data.form);
                  });
                }}
              >
                <h2 className="font-bold">
                  Create form · {data?.program?.code}
                </h2>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="Form name">
                    <input
                      className="ev-input"
                      required
                      maxLength={160}
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                    />
                  </Field>
                  <Field label="Academic period">
                    <select
                      required
                      className="ev-input"
                      value={period}
                      onChange={(e) => setPeriod(e.target.value)}
                    >
                      <option value="">Select period</option>
                      {data?.periods.map((p) => (
                        <option key={p.id} value={p.id}>
                          {periodLabel(p)}
                        </option>
                      ))}
                    </select>
                  </Field>
                </div>
                <button className="ev-button ev-primary" disabled={busy}>
                  Create draft
                </button>
              </form>
              {!data && <p role="status">Loading forms…</p>}
              {data?.forms.data.map((f) => (
                <article className="ev-panel space-y-3" key={f.id}>
                  <div className="ev-toolbar justify-between">
                    <h2 className="font-bold">{f.name}</h2>
                    <span className="ev-status">{f.status}</span>
                  </div>
                  <p className="text-sm">
                    {periodLabel(f.academic_period)} · Version {f.version} ·{" "}
                    {f.criteria_count} criteria
                  </p>
                  <div className="ev-toolbar">
                    <Button
                      disabled={busy}
                      onClick={() =>
                        run(async () =>
                          setForm(
                            (
                              await api.get(
                                `/coordinator/evaluation-forms/${f.id}`,
                              )
                            ).data.form,
                          ),
                        )
                      }
                    >
                      {f.status === "draft"
                        ? "Edit draft / Preview / Publish"
                        : "View form"}
                    </Button>
                    <Button
                      disabled={busy}
                      onClick={() =>
                        run(async () =>
                          setForm(
                            (
                              await api.post(
                                `/coordinator/evaluation-forms/${f.id}/duplicate`,
                                period
                                  ? { academic_period_id: Number(period) }
                                  : {},
                              )
                            ).data.form,
                          ),
                        )
                      }
                    >
                      Duplicate
                      {period && Number(period) !== f.academic_period_id
                        ? " to selected period"
                        : ""}
                    </Button>
                    {f.status === "draft" && (
                      <Button
                        disabled={busy}
                        onClick={() =>
                          run(async () => {
                            if (await deleteDraft(f)) await load();
                          })
                        }
                      >
                        Delete draft
                      </Button>
                    )}
                    {f.status === "published" && (
                      <Button
                        disabled={busy}
                        onClick={() => {
                          if (
                            window.confirm(
                              "Archive this form for new starts? Existing evaluations will be preserved.",
                            )
                          )
                            run(async () => {
                              await api.post(
                                `/coordinator/evaluation-forms/${f.id}/archive`,
                                { row_version: f.row_version },
                              );
                              await load();
                            });
                        }}
                      >
                        Archive
                      </Button>
                    )}
                  </div>
                </article>
              ))}
              {data?.forms.data.length === 0 && (
                <p className="ev-panel">No evaluation forms yet.</p>
              )}
              <Pagination page={data?.forms} onPage={setPage} />
            </>
          )}
        </>
      )}
    </div>
  );
}
