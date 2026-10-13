import { lazy, Suspense } from 'react';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import DocumentPreviewModal, { DocumentDetail } from '../common/DocumentPreviewModal';

const PdfPages = lazy(() => import('../PdfPages'));
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

export default function RequirementFilePreview({ preview, onClose, downloadName }) {
  const { requirement, student } = preview;
  return <DocumentPreviewModal
    kicker="Internship Requirement"
    title={requirement.requirement_name}
    subtitle={`${student.name} · ${student.student_id} · ${student.section || 'No section'}`}
    onClose={onClose}
    preview={preview.loading ? <p className="py-20 text-center text-sm text-slate-500">Loading document...</p>
      : preview.type === 'pdf' ? <Suspense fallback={<p className="py-20 text-center text-sm text-slate-500">Rendering PDF...</p>}><PdfPages file={preview.file} /></Suspense>
        : preview.type === 'image' ? <img src={preview.url} alt={requirement.requirement_name} className="mx-auto max-w-full object-contain" />
          : <p className="py-20 text-center text-sm text-slate-500">Preview is unavailable for this file type. Use Download file to open it.</p>}
    details={<><dl className="grid grid-cols-2 gap-3 lg:grid-cols-1">
      <DocumentDetail label="Student" value={student.name} /><DocumentDetail label="Student ID" value={student.student_id} /><DocumentDetail label="Section" value={student.section} />
      <DocumentDetail label="Requirement" value={requirement.requirement_name} /><DocumentDetail label="File type" value={preview.type?.toUpperCase() || 'Document'} />
      <DocumentDetail label="Submitted" value={formatDate(requirement.updated_at || requirement.created_at)} />
      <DocumentDetail label="Status" value={requirement.status === 'pending' ? 'For Review' : requirement.status} />
      <DocumentDetail label="Reviewed by" value={requirement.reviewer?.name} /><DocumentDetail label="Reviewed on" value={formatDate(requirement.reviewed_at)} />
    </dl>
    {requirement.feedback && <p className="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">Feedback: {requirement.feedback}</p>}
    {preview.url && <a href={preview.url} download={downloadName} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200"><ArrowDownTrayIcon className="h-4 w-4" />Download file</a>}</>}
  />;
}
