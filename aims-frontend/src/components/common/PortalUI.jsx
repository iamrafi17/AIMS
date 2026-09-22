export function PageIntro({ eyebrow, title, description, icon: Icon, actions }) {
  return (
    <section className="portal-page-intro border-b border-slate-200 pb-5 dark:border-slate-700">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="flex min-w-0 max-w-3xl flex-1 items-start gap-3">
          {Icon && <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-[#7D001D]/10 bg-[#7D001D]/5 text-[#7D001D] dark:border-rose-300/10 dark:bg-rose-400/10 dark:text-rose-300"><Icon className="h-5 w-5" /></div>}
          <div className="min-w-0">
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#7D001D] dark:text-rose-300">{eyebrow}</p>
            <h1 className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{title}</h1>
            <p className="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">{description}</p>
          </div>
        </div>
        {actions && <div className="portal-page-actions flex w-full shrink-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:[&>*]:w-auto sm:[&>*]:shrink-0 lg:w-auto lg:flex-nowrap">{actions}</div>}
      </div>
    </section>
  );
}

export function MetricCard({ label, value, detail, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-red-400/10 dark:text-red-300',
    gold: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
  };
  const metricValue = String(value ?? 0);
  const compactValue = typeof value === 'number' || /^[\d,.]+(?:\.\d+)?%?$/.test(metricValue);
  return (
    <article data-metric-size={compactValue ? 'compact' : 'wide'} className="portal-metric-card min-w-0 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500 dark:text-slate-400">{label}</p>
          <p className="mt-2 break-words text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{value ?? 0}</p>
          {detail && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{detail}</p>}
        </div>
        {Icon && <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${tones[tone]}`}><Icon className="h-[18px] w-[18px]" /></div>}
      </div>
    </article>
  );
}

export function LoadingPanel() {
  return <div className="flex min-h-64 items-center justify-center rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"><div className="h-10 w-10 animate-spin rounded-full border-4 border-[#7D001D]/20 border-t-[#7D001D]" /></div>;
}

export function EmptyPanel({ icon: Icon, title, description }) {
  return (
    <div className="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center dark:border-slate-700 dark:bg-slate-900 sm:p-10">
      {Icon && <Icon className="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />}
      <h3 className="mt-4 font-semibold text-slate-800 dark:text-white">{title}</h3>
      <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
    </div>
  );
}
