export function PageIntro({ eyebrow, title, description, icon: Icon, actions }) {
  return (
    <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-[#5b0000] via-[#800000] to-[#9b1c1c] p-6 text-white shadow-xl shadow-[#800000]/15 sm:p-8">
      <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div className="max-w-3xl">
          {Icon && <div className="mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/20"><Icon className="h-7 w-7 text-[#f1ce68]" /></div>}
          <p className="text-xs font-black uppercase tracking-[0.24em] text-[#f1ce68]">{eyebrow}</p>
          <h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{title}</h1>
          <p className="mt-3 max-w-2xl text-sm leading-6 text-white/75 sm:text-base">{description}</p>
        </div>
        {actions && <div className="flex flex-wrap gap-3">{actions}</div>}
      </div>
    </section>
  );
}

export function MetricCard({ label, value, detail, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#800000]/10 text-[#800000] dark:bg-red-400/10 dark:text-red-300',
    gold: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
  };
  return (
    <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-700 dark:bg-slate-900">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-black text-slate-900 dark:text-white">{value ?? 0}</p>
          {detail && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{detail}</p>}
        </div>
        {Icon && <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${tones[tone]}`}><Icon className="h-6 w-6" /></div>}
      </div>
    </article>
  );
}

export function LoadingPanel() {
  return <div className="flex min-h-64 items-center justify-center rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"><div className="h-11 w-11 animate-spin rounded-full border-4 border-[#800000]/20 border-t-[#800000]" /></div>;
}

export function EmptyPanel({ icon: Icon, title, description }) {
  return (
    <div className="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
      {Icon && <Icon className="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />}
      <h3 className="mt-4 font-black text-slate-800 dark:text-white">{title}</h3>
      <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
    </div>
  );
}
