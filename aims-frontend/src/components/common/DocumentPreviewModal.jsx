import { XMarkIcon } from '@heroicons/react/24/outline';

export function DocumentDetail({ label, value }) {
  return <div className="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</dt><dd className="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-white">{value || 'Not set'}</dd></div>;
}

export default function DocumentPreviewModal({ kicker, title, subtitle, onClose, preview, details, footer, compact = false }) {
  return <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/65 p-2 backdrop-blur-sm sm:p-5" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
    <div role="dialog" aria-modal="true" aria-label={title} className={`flex h-[min(96dvh,960px)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900 ${compact ? 'moa-preview-dialog' : ''}`}>
      <header className="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
        <div className="min-w-0"><p className="text-xs font-semibold uppercase tracking-widest text-[#7D001D] dark:text-rose-300">{kicker}</p><h2 className="mt-1 text-xl font-semibold text-slate-900 dark:text-white">{title}</h2><p className="mt-1 text-sm text-slate-500">{subtitle}</p></div>
        <button type="button" onClick={onClose} aria-label="Close document details" className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"><XMarkIcon className="h-5 w-5" /></button>
      </header>
      <div className={`document-preview-body grid min-h-0 flex-1 ${compact ? 'lg:grid-cols-[minmax(0,1fr)_17rem]' : 'lg:grid-cols-[minmax(0,1fr)_19rem]'}`}>
        <div className="document-preview-file min-h-[20rem] min-w-0 overflow-y-auto bg-slate-100 p-3 dark:bg-slate-950 sm:p-5">{preview}</div>
        <aside className="document-preview-details min-h-0 overflow-y-auto border-t border-slate-200 p-5 dark:border-slate-700 lg:border-l lg:border-t-0">{details}</aside>
      </div>
      {footer && <footer className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">{footer}</footer>}
    </div>
  </div>;
}
