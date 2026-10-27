import { useEffect } from "react";

export const errorText = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join("\n") ||
  error.response?.data?.message ||
  "Unable to complete this request. Please try again.";
export const periodLabel = (period) =>
  period
    ? `${period.academic_year} · ${period.semester}`
    : "Period unavailable";
export const statusLabel = (status) =>
  ({ form_unavailable: "Form unavailable", not_started: "Not started" })[
    status
  ] || status;
export function useUnsavedWarning(dirty) {
  useEffect(() => {
    if (!dirty) return undefined;
    const warn = (event) => {
      event.preventDefault();
      event.returnValue = "";
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);
}
