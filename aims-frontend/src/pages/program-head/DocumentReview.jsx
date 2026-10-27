import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import {
  FiActivity,
  FiAlertTriangle,
  FiBriefcase,
  FiCheck,
  FiCheckCircle,
  FiClock,
  FiDownload,
  FiEye,
  FiFileText,
  FiFilter,
  FiRefreshCw,
  FiSearch,
  FiShield,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { groupStudentRequirements } from './studentRequirementGroups';
import ProgramHeadMoaReview from './ProgramHeadMoaReview';
import DeploymentReview from '../../components/deployments/DeploymentReview';
import { useSearchParams } from 'react-router-dom';

const emptyData = {
  summary: {
    submitted_requirements: 0,
    pending_requirements: 0,
    approved_requirements: 0,
    rejected_requirements: 0,
    total_moas: 0,
    pending_moas: 0,
    approved_moas: 0,
    expiring_moas: 0,
  },
  requirements: [],
  folder_readiness: {},
  moas: [],
};

const tabs = [
  { id: 'requirements', label: 'Requirement Review', icon: FiFileText },
  { id: 'moas', label: 'MOA Review', icon: FiBriefcase },
  { id: 'deployments', label: 'Deployment Approval', icon: FiCheckCircle },
  { id: 'monitoring', label: 'Document Monitoring', icon: FiActivity },
];

const statusStyles = {
  pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
  approved: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
  rejected: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
  returned: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
  endorsed: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',
  reviewed: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',
  expired: 'border-slate-200 bg-slate-100 text-slate-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-300 focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
const PdfPages = lazy(() => import('../../components/PdfPages'));
const formatDocumentDate = (value, options = { month: 'short', day: 'numeric', year: 'numeric' }) => value
  ? new Date(value).toLocaleDateString('en-PH', options) : '—';

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${statusStyles[status] || statusStyles.pending}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${status === 'approved' ? 'bg-emerald-500' : status === 'rejected' || status === 'returned' ? 'bg-rose-500' : status === 'expired' ? 'bg-slate-500' : 'bg-amber-500'}`} />
      {status}
    </span>
  );
}

function MetricCard({ label, value, note, icon: Icon, tone = 'maroon' }) {
  const tones = {
    maroon: 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
  };

  return (
    <div className="portal-metric-card rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div><p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p><p className="mt-2 text-3xl font-black text-[#690016] dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-400">{note}</p></div>
        <div className={`grid h-11 w-11 place-items-center rounded-2xl text-xl ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className = '' }) {
  return (
    <section className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 ${className}`}>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-lg text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><Icon /></div>
          <div><h2 className="font-black text-[#690016] dark:text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p></div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function ProgressBar({ value, color = 'bg-[#7D001D]' }) {
  const safeValue = Math.min(Math.max(Number(value) || 0, 0), 100);
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${safeValue}%` }} /></div>;
}

function Modal({ title, subtitle, onClose, children, size = 'max-w-3xl' }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-2 backdrop-blur-sm sm:p-4" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div role="dialog" aria-modal="true" aria-label={title} className={`max-h-[calc(100dvh-1rem)] w-full overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-gray-800 sm:max-h-[92dvh] ${size}`}>
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
          <div><h2 className="text-lg font-black text-[#690016] dark:text-white">{title}</h2><p className="mt-1 text-xs text-slate-400">{subtitle}</p></div>
          <button type="button" onClick={onClose} aria-label="Close dialog" className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-200"><FiX /></button>
        </div>
        {children}
      </div>
    </div>
  );
}

function DocumentReview() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab = tabs.some((tab) => tab.id === searchParams.get('tab')) ? searchParams.get('tab') : 'requirements';
  const setActiveTab = (tab) => setSearchParams({ tab }, { replace: true });
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [typeFilter, setTypeFilter] = useState('all');
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [selectedStudentKey, setSelectedStudentKey] = useState(null);
  const [requirementPreview, setRequirementPreview] = useState(null);
  const requirementPreviewAbort = useRef(null);
  const requirementPreviewUrl = useRef(null);
  const [decision, setDecision] = useState(null);
  const [endorsement, setEndorsement] = useState(null);
  const [feedback, setFeedback] = useState('');
  const [busy, setBusy] = useState(false);
  const reviewInFlight = useRef(false);

  const loadDocuments = async (quiet = false) => {
    if (quiet) setRefreshing(true); else setLoading(true);
    try {
      const response = await api.get('/program-head/documents');
      setData({ ...emptyData, ...response.data });
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load document records.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    let mounted = true;
    api.get('/program-head/documents')
      .then((response) => {
        if (mounted) setData({ ...emptyData, ...response.data });
      })
      .catch((error) => {
        if (mounted) toast.error(error.response?.data?.message || 'Unable to load document records.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => { mounted = false; };
  }, []);

  useEffect(() => () => {
    requirementPreviewAbort.current?.abort();
    if (requirementPreviewUrl.current) URL.revokeObjectURL(requirementPreviewUrl.current);
  }, []);

  const normalizedSearch = search.trim().toLowerCase();
  const filteredRequirements = data.requirements.filter((item) => {
    const matchesSearch = !normalizedSearch || `${item.requirement_name} ${item.student?.name} ${item.student?.student_id} ${item.student?.program}`.toLowerCase().includes(normalizedSearch);
    const matchesStatus = statusFilter === 'all' || item.status === statusFilter;
    const matchesType = typeFilter === 'all' || item.file_type === typeFilter;
    return matchesSearch && matchesStatus && matchesType;
  });
  const studentGroups = groupStudentRequirements(data.requirements);
  const filteredStudentGroups = studentGroups.filter((group) => group.requirements.some((item) => filteredRequirements.includes(item)));
  const selectedStudent = studentGroups.find((group) => group.key === selectedStudentKey);

  const closeDocument = () => {
    requirementPreviewAbort.current?.abort();
    requirementPreviewAbort.current = null;
    if (requirementPreviewUrl.current) URL.revokeObjectURL(requirementPreviewUrl.current);
    requirementPreviewUrl.current = null;
    setRequirementPreview(null);
    setSelectedDocument(null);
  };

  const openRequirement = async (requirement) => {
    requirementPreviewAbort.current?.abort();
    if (requirementPreviewUrl.current) URL.revokeObjectURL(requirementPreviewUrl.current);
    requirementPreviewUrl.current = null;
    setSelectedDocument({ kind: 'requirement', document: requirement });
    const isPdf = requirement.file_type?.toLowerCase() === 'pdf' || /\.pdf$/i.test(requirement.file_path || '');
    const isImage = /^image(?:\/|$)/i.test(requirement.file_type || '') || /\.(?:png|jpe?g|gif|webp)$/i.test(requirement.file_path || '');
    if (!isPdf && !isImage) {
      setRequirementPreview({ file: null, loading: false });
      return;
    }

    const controller = new AbortController();
    requirementPreviewAbort.current = controller;
    setRequirementPreview({ file: null, loading: true });
    try {
      const response = await api.get(`/program-head/documents/requirements/${requirement.id}/download`, {
        responseType: 'blob',
        signal: controller.signal,
      });
      if (!controller.signal.aborted) {
        const imageUrl = isImage ? URL.createObjectURL(response.data) : null;
        requirementPreviewUrl.current = imageUrl;
        setRequirementPreview({ file: isPdf ? response.data : null, imageUrl, loading: false });
      }
    } catch (error) {
      if (!controller.signal.aborted) {
        setRequirementPreview({ file: null, loading: false, error: error.response?.data?.message || 'Unable to preview this file.' });
      }
    } finally {
      if (requirementPreviewAbort.current === controller) requirementPreviewAbort.current = null;
    }
  };

  const openDecision = (kind, document, nextDecision) => {
    closeDocument();
    setFeedback('');
    setDecision({ kind, document, decision: nextDecision });
  };

  const submitDecision = async (event) => {
    event.preventDefault();
    if (!decision || reviewInFlight.current) return;
    if (decision.kind === 'requirement' && decision.decision === 'rejected' && !feedback.trim()) {
      toast.error('Feedback is required when rejecting a requirement.');
      return;
    }

    reviewInFlight.current = true;
    setBusy(true);
    try {
      const endpoint = decision.kind === 'requirement'
        ? `/program-head/documents/requirements/${decision.document.id}/review`
        : `/program-head/documents/moas/${decision.document.id}/review`;
      const payload = { decision: decision.decision, feedback: feedback.trim() || null };
      const response = await api.post(endpoint, payload);

      if (decision.kind === 'requirement') {
        setData((current) => ({
          ...current,
          requirements: current.requirements.map((item) => item.id === decision.document.id ? response.data.requirement : item),
          summary: {
            ...current.summary,
            pending_requirements: Math.max(current.summary.pending_requirements - (decision.document.status === 'pending' ? 1 : 0), 0),
            approved_requirements: current.summary.approved_requirements + (decision.decision === 'approved' && decision.document.status !== 'approved' ? 1 : 0) - (decision.document.status === 'approved' && decision.decision !== 'approved' ? 1 : 0),
            rejected_requirements: current.summary.rejected_requirements + (decision.decision === 'rejected' && decision.document.status !== 'rejected' ? 1 : 0) - (decision.document.status === 'rejected' && decision.decision !== 'rejected' ? 1 : 0),
          },
        }));
      } else {
        setData((current) => ({
          ...current,
          moas: current.moas.map((item) => item.id === decision.document.id ? response.data.moa : item),
          summary: {
            ...current.summary,
            pending_moas: Math.max(current.summary.pending_moas - 1, 0),
          },
        }));
      }

      toast.success(response.data.message);
      setDecision(null);
      setFeedback('');
      if (decision.kind === 'requirement') await loadDocuments(true);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to save the review decision.');
    } finally {
      reviewInFlight.current = false;
      setBusy(false);
    }
  };

  const downloadDocument = async (kind, document) => {
    try {
      const endpoint = kind === 'requirement'
        ? `/program-head/documents/requirements/${document.id}/download`
        : `/program-head/documents/moas/${document.id}/download`;
      const response = await api.get(endpoint, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const anchor = window.document.createElement('a');
      anchor.href = url;
      anchor.download = document.file_path?.split('/').pop() || `${kind}-${document.id}.file`;
      window.document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to download this document.');
    }
  };

  const submitEndorsement = async () => {
    if (!endorsement) return;
    setBusy(true);
    try {
      const action = endorsement.status === 'returned' ? 're-endorse' : 'endorse';
      const response = await api.post(`/program-head/student-internships/${endorsement.key}/${action}`);
      toast.success(response.data.message);
      setEndorsement(null);
      await loadDocuments(true);
    } catch (error) {
      toast.error(error.response?.data?.errors?.folder?.[0] || error.response?.data?.message || 'Unable to endorse this folder.');
    } finally {
      setBusy(false);
    }
  };

  const summary = data.summary || emptyData.summary;
  const totalRequirementDecisions = summary.approved_requirements + summary.rejected_requirements + summary.pending_requirements;
  const requirementApprovalRate = totalRequirementDecisions ? Math.round((summary.approved_requirements / totalRequirementDecisions) * 100) : 0;
  const moaApprovalRate = summary.total_moas ? Math.round((summary.approved_moas / summary.total_moas) * 100) : 0;

  const programMonitoring = Object.values(data.requirements.reduce((groups, requirement) => {
    const program = requirement.student?.program || 'Unassigned';
    if (!groups[program]) groups[program] = { program, total: 0, approved: 0, pending: 0, rejected: 0 };
    groups[program].total += 1;
    groups[program][requirement.status] += 1;
    return groups;
  }, {}));

  const recentActivity = [
    ...data.requirements.filter((item) => item.reviewed_at).map((item) => ({
      id: `requirement-${item.id}`,
      title: item.requirement_name,
      subject: `${item.student?.name} · ${item.student?.program || 'No program'}`,
      status: item.status,
      reviewer: item.reviewer,
      reviewedAt: item.reviewed_at,
      kind: 'Requirement',
    })),
    ...data.moas.filter((item) => item.reviewed_at).map((item) => ({
      id: `moa-${item.id}`,
      title: item.hte,
      subject: `${item.college || 'No college'} · Memorandum of Agreement`,
      status: item.status,
      reviewer: item.reviewer,
      reviewedAt: item.reviewed_at,
      kind: 'MOA',
    })),
  ].sort((a, b) => new Date(b.reviewedAt) - new Date(a.reviewedAt)).slice(0, 8);

  if (loading) {
    return <div className="grid min-h-[420px] place-items-center"><div className="text-center"><div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#7D001D]/15 border-b-[#7D001D]" /><p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading documents</p></div></div>;
  }

  return (
    <div className="program-document-review portal-card-tables space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#690016] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiShield /> Academic Compliance</div>
            <h1 className="text-2xl font-black sm:text-3xl">Document Review</h1>
            <p className="mt-2 max-w-xl text-sm leading-6 text-white/65">Review student requirements, assess MOAs, record decisions, and monitor document compliance.</p>
          </div>
          <button type="button" onClick={() => loadDocuments(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50"><FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Documents</button>
        </div>
      </header>

      <section className="portal-metric-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Pending requirements" value={summary.pending_requirements} note="Awaiting academic review" icon={FiClock} tone="amber" />
        <MetricCard label="Approved requirements" value={summary.approved_requirements} note={`${requirementApprovalRate}% decision approval rate`} icon={FiCheckCircle} tone="green" />
        <MetricCard label="Pending MOAs" value={summary.pending_moas} note={`${summary.total_moas} agreements monitored`} icon={FiBriefcase} tone="blue" />
        <MetricCard label="Expiring MOAs" value={summary.expiring_moas} note="Expiring within 60 days" icon={FiAlertTriangle} />
      </section>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-label="Document review sections">
        <div className="portal-mobile-tabs flex min-w-max gap-1">
          {tabs.map((tab) => (
            <button key={tab.id} type="button" onClick={() => { setActiveTab(tab.id); setStatusFilter('all'); setTypeFilter('all'); }} className={`inline-flex items-center gap-2 rounded-xl px-5 py-3 text-xs font-black transition ${activeTab === tab.id ? 'bg-[#7D001D] text-white shadow-md shadow-[#7D001D]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#7D001D] dark:text-gray-300 dark:hover:bg-gray-700'}`}>
              <tab.icon className="text-base" /> {tab.label}
              {tab.id === 'requirements' && summary.pending_requirements > 0 && <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[9px] ${activeTab === tab.id ? 'bg-white text-[#7D001D]' : 'bg-amber-100 text-amber-700'}`}>{summary.pending_requirements}</span>}
              {tab.id === 'moas' && summary.pending_moas > 0 && <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[9px] ${activeTab === tab.id ? 'bg-white text-[#7D001D]' : 'bg-amber-100 text-amber-700'}`}>{summary.pending_moas}</span>}
            </button>
          ))}
        </div>
      </nav>

      {activeTab === 'requirements' && (
        <div className="portal-filter-panel flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:flex-row">
          <label className="portal-filter-search relative flex-1"><FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={activeTab === 'requirements' ? 'Search student, ID, program, or requirement...' : 'Search HTE, college, or status...'} className={`${inputClass} pl-10`} /></label>
          <div className="flex gap-2">
            <label className="portal-filter-field--icon relative flex-1 md:w-44"><FiFilter className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className={`${inputClass} appearance-none pl-9`}><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label>
            {activeTab === 'requirements' && <select value={typeFilter} onChange={(event) => setTypeFilter(event.target.value)} className={`${inputClass} flex-1 md:w-40`}><option value="all">All file types</option><option value="pdf">PDF</option><option value="image">Image</option><option value="document">Document</option></select>}
          </div>
        </div>
      )}

      {activeTab === 'requirements' && (
        <Panel title="Requirement Review" subtitle={`${filteredStudentGroups.length} students with submitted requirements shown`} icon={FiFileText}>
          <div className="overflow-x-auto">
            <table className="portal-card-table portal-card-table--document-review w-full min-w-[700px] text-left">
              <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Submitted</th><th className="pb-3 font-black">Status</th><th className="pb-3 font-black">Reviewer</th><th className="pb-3 text-right font-black">Action</th></tr></thead>
              <tbody>{filteredStudentGroups.map((group) => (
                <tr key={group.key} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40">
                  <td className="py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{group.student.name || 'Unnamed student'}</p><p className="mt-1 text-[10px] text-slate-400">{group.student.student_id} · {group.student.program || 'No program'}</p></td>
                  <td className="py-4 text-xs text-slate-500">{formatDocumentDate(group.submittedAt)}</td>
                  <td className="py-4"><span className={`inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black ${statusStyles[group.status] || statusStyles.pending}`}>{group.statusLabel}</span></td>
                  <td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{group.reviewer || '—'}</td>
                  <td className="py-4 text-right"><button type="button" onClick={() => setSelectedStudentKey(group.key)} className="inline-flex items-center gap-1.5 rounded-lg bg-[#7D001D]/10 px-3 py-2 text-xs font-black text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><FiEye /> View</button></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
          {!filteredStudentGroups.length && <div className="py-16 text-center"><FiFileText className="mx-auto text-4xl text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-400">No students with submitted requirements match these filters.</p></div>}
        </Panel>
      )}

      {activeTab === 'moas' && <ProgramHeadMoaReview moas={data.moas} onChanged={() => loadDocuments(true)} />}
      {activeTab === 'deployments' && <DeploymentReview role="program_head" />}

      {activeTab === 'monitoring' && (
        <div className="space-y-6">
          <div className="grid gap-6 xl:grid-cols-2">
            <Panel title="Requirement Monitoring" subtitle="Review status across all submitted student requirements" icon={FiFileText}>
              <div className="mb-6 flex items-end justify-between gap-3"><div><p className="text-4xl font-black text-[#7D001D] dark:text-rose-300">{requirementApprovalRate}%</p><p className="mt-1 text-xs text-slate-400">Requirement approval rate</p></div><p className="text-sm font-black text-slate-500">{summary.approved_requirements}/{summary.submitted_requirements} approved</p></div>
              <ProgressBar value={requirementApprovalRate} />
              <div className="mt-6 grid grid-cols-3 gap-3 text-center">
                <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">{summary.approved_requirements}</p><p className="text-[9px] font-black uppercase text-slate-400">Approved</p></div>
                <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p className="text-xl font-black text-amber-600">{summary.pending_requirements}</p><p className="text-[9px] font-black uppercase text-slate-400">Pending</p></div>
                <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">{summary.rejected_requirements}</p><p className="text-[9px] font-black uppercase text-slate-400">Rejected</p></div>
              </div>
            </Panel>

            <Panel title="MOA Monitoring" subtitle="Agreement validity, decisions, and expiration risk" icon={FiBriefcase}>
              <div className="mb-6 flex items-end justify-between gap-3"><div><p className="text-4xl font-black text-emerald-600">{moaApprovalRate}%</p><p className="mt-1 text-xs text-slate-400">MOA approval coverage</p></div><p className="text-sm font-black text-slate-500">{summary.approved_moas}/{summary.total_moas} approved</p></div>
              <ProgressBar value={moaApprovalRate} color="bg-emerald-500" />
              <div className="mt-6 grid grid-cols-3 gap-3 text-center">
                <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p className="text-xl font-black text-emerald-600">{summary.approved_moas}</p><p className="text-[9px] font-black uppercase text-slate-400">Approved</p></div>
                <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p className="text-xl font-black text-amber-600">{summary.pending_moas}</p><p className="text-[9px] font-black uppercase text-slate-400">Pending</p></div>
                <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/30"><p className="text-xl font-black text-rose-600">{summary.expiring_moas}</p><p className="text-[9px] font-black uppercase text-slate-400">Expiring</p></div>
              </div>
            </Panel>
          </div>

          <Panel title="Program Document Compliance" subtitle="Submitted requirement review progress by academic program" icon={FiShield}>
            {programMonitoring.length ? <div className="space-y-5">{programMonitoring.map((program) => { const completion = program.total ? Math.round((program.approved / program.total) * 100) : 0; return <div key={program.program}><div className="mb-2 flex flex-wrap items-end justify-between gap-2"><div><p className="text-sm font-black text-slate-700 dark:text-white">{program.program}</p><p className="mt-1 text-[10px] text-slate-400">{program.approved} approved · {program.pending} pending · {program.rejected} rejected</p></div><p className="text-xs font-black text-[#7D001D] dark:text-rose-300">{completion}% approved</p></div><ProgressBar value={completion} color={completion >= 90 ? 'bg-emerald-500' : completion >= 70 ? 'bg-blue-500' : 'bg-amber-500'} /></div>; })}</div> : <div className="py-12 text-center text-sm font-bold text-slate-400">No submitted requirements are available for monitoring.</div>}
          </Panel>

          <Panel title="Recent Review Activity" subtitle="Latest requirement and MOA decisions" icon={FiActivity}>
            {recentActivity.length ? <div className="space-y-3">{recentActivity.map((activity) => <div key={activity.id} className="flex flex-col gap-3 rounded-2xl border border-slate-100 p-4 sm:flex-row sm:items-center dark:border-gray-700"><div className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${activity.kind === 'MOA' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300'}`}>{activity.kind === 'MOA' ? <FiBriefcase /> : <FiFileText />}</div><div className="min-w-0 flex-1"><p className="truncate text-sm font-black text-slate-800 dark:text-white">{activity.title}</p><p className="mt-1 truncate text-[11px] text-slate-400">{activity.subject}</p></div><div className="flex items-center justify-between gap-4 sm:justify-end"><StatusBadge status={activity.status} /><div className="text-right"><p className="text-[10px] font-bold text-slate-500">{activity.reviewer || 'Reviewer'}</p><p className="mt-1 text-[9px] text-slate-400">{new Date(activity.reviewedAt).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}</p></div></div></div>)}</div> : <div className="py-12 text-center text-sm font-bold text-slate-400">No review activity has been recorded yet.</div>}
          </Panel>
        </div>
      )}

      {selectedStudent && !selectedDocument && !decision && !endorsement && (
        <Modal title={selectedStudent.student.name || 'Student requirements'} subtitle={`${selectedStudent.student.program || 'No program'} · ${selectedStudent.student.student_id || 'No student ID'}`} onClose={() => setSelectedStudentKey(null)} size="max-w-4xl">
          <div className="space-y-5 p-5 sm:p-6">
            <div className="flex flex-wrap justify-between gap-4 rounded-2xl bg-slate-50 p-4 dark:bg-gray-900">
              <div><p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Submitted</p><p className="mt-1 text-sm font-bold text-slate-700 dark:text-gray-200">{formatDocumentDate(selectedStudent.submittedAt, { month: 'long', day: 'numeric', year: 'numeric' })}</p></div>
              <div><p className="text-[10px] font-black uppercase tracking-wider text-slate-400">Overall status</p><p className="mt-1 text-sm font-black uppercase text-[#7D001D] dark:text-rose-300">{selectedStudent.statusLabel}</p></div>
            </div>
            <div>
              <h3 className="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-white">Requirements</h3>
              <div className="mt-3 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 dark:divide-gray-700 dark:border-gray-700">
                {selectedStudent.requirements.map((requirement) => (
                  <div key={requirement.id} className="flex flex-wrap items-center gap-3 p-3 sm:p-4">
                    <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-full ${requirement.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : requirement.status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}`}>{requirement.status === 'approved' ? <FiCheck /> : requirement.status === 'rejected' ? <FiX /> : <FiClock />}</span>
                    <div className="min-w-40 flex-1"><p className="text-sm font-bold text-slate-800 dark:text-white">{requirement.requirement_name}</p>{requirement.feedback && <p className="mt-1 text-xs text-rose-600">{requirement.feedback}</p>}</div>
                    <StatusBadge status={requirement.status} />
                    <div className="ml-auto flex gap-2">
                      <button type="button" onClick={() => openRequirement(requirement)} className="inline-flex items-center gap-1 rounded-lg bg-[#7D001D]/10 px-3 py-2 text-xs font-bold text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-300"><FiEye /> View</button>
                      <button type="button" onClick={() => downloadDocument('requirement', requirement)} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 dark:bg-gray-700 dark:text-gray-200"><FiDownload /> Download</button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div className="border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-gray-700 dark:text-gray-300"><p><span className="font-black">Reviewed by:</span> {selectedStudent.reviewer || '—'}</p><p className="mt-1"><span className="font-black">Reviewed:</span> {formatDocumentDate(selectedStudent.reviewedAt, { month: 'long', day: 'numeric', year: 'numeric' })}</p></div>
            {data.folder_readiness?.[selectedStudent.key] && (() => {
              const readiness = data.folder_readiness[selectedStudent.key];
              const locked = ['for_dean_review', 'for_review', 'reviewed', 'final_approved'].includes(readiness.approval_status);
              return <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-gray-700"><p className="text-sm font-black text-slate-700 dark:text-white">{readiness.approved}/{readiness.total} required documents approved{readiness.approval_status ? ` · ${readiness.approval_status.replaceAll('_', ' ')}` : ''}</p><button type="button" disabled={!readiness.complete || locked || busy} onClick={() => setEndorsement({ key: selectedStudent.key, student: selectedStudent.student, status: readiness.approval_status, approved: readiness.approved, total: readiness.total })} className="rounded-xl bg-[#7D001D] px-4 py-3 text-xs font-black text-white disabled:cursor-not-allowed disabled:opacity-40">{readiness.approval_status === 'returned' ? 'Re-Endorse to Dean' : 'Endorse to Dean'}</button></div>;
            })()}
          </div>
        </Modal>
      )}

      {endorsement && <Modal title={endorsement.status === 'returned' ? 'Re-Endorse Student Folder' : 'Endorse Student Folder'} subtitle={`${endorsement.student.name} · ${endorsement.student.student_id}`} onClose={() => !busy && setEndorsement(null)} size="max-w-lg"><div className="p-6"><p className="text-sm text-slate-600 dark:text-gray-300">{endorsement.approved}/{endorsement.total} requirements approved. Send this complete student folder to the Dean for final approval?</p><div className="mt-6 flex justify-end gap-2"><button type="button" disabled={busy} onClick={() => setEndorsement(null)} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold">Cancel</button><button type="button" disabled={busy} onClick={submitEndorsement} className="rounded-xl bg-[#7D001D] px-4 py-2 text-xs font-bold text-white">{busy ? 'Sending...' : 'Endorse'}</button></div></div></Modal>}

      {selectedDocument?.kind === 'requirement' && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm sm:p-5" onMouseDown={(event) => event.target === event.currentTarget && closeDocument()}>
          <div role="dialog" aria-modal="true" aria-label={`Review file: ${selectedDocument.document.requirement_name}`} className="flex h-[min(88dvh,800px)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
            <div className="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-gray-700">
              <div className="min-w-0">
                <h2 className="break-words text-lg font-bold text-slate-900 dark:text-white">{selectedDocument.document.requirement_name}</h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-300">{selectedDocument.document.student?.name} · {selectedDocument.document.student?.student_id} · {selectedDocument.document.student?.program}</p>
              </div>
              <button type="button" onClick={closeDocument} aria-label="Close file review" className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-200"><FiX /></button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto bg-slate-100 p-3 dark:bg-gray-900">
              {requirementPreview?.loading ? <p className="py-12 text-center text-sm text-slate-600 dark:text-slate-300">Loading file...</p> : requirementPreview?.file ? (
                <Suspense fallback={<p className="py-12 text-center text-sm text-slate-600">Rendering PDF...</p>}><PdfPages file={requirementPreview.file} /></Suspense>
              ) : requirementPreview?.imageUrl ? <img src={requirementPreview.imageUrl} alt={selectedDocument.document.requirement_name} className="mx-auto max-h-full max-w-full object-contain" /> : <p className="py-12 text-center text-sm text-slate-600 dark:text-slate-300">{requirementPreview?.error || 'Preview is available for PDF and image files. You can download this submission.'}</p>}
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-gray-700">
              {selectedDocument.document.can_program_head_review === false && <p className="mr-auto text-xs font-bold text-amber-700 dark:text-amber-300">Awaiting coordinator approval</p>}
              {selectedDocument.document.program_head_endorsed && <p className="mr-auto text-xs font-bold text-emerald-700 dark:text-emerald-300">Endorsed for Dean approval</p>}
              {selectedDocument.document.program_head_approved && !selectedDocument.document.program_head_endorsed && <p role="status" className="mr-auto inline-flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-300"><FiCheckCircle />Approved by Program Head</p>}
              <button type="button" onClick={() => downloadDocument('requirement', selectedDocument.document)} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-[#7D001D] dark:border-gray-600 dark:text-rose-300"><FiDownload /> Download File</button>
              {selectedDocument.document.can_program_head_review !== false && !selectedDocument.document.program_head_endorsed && !selectedDocument.document.program_head_approved && <button type="button" onClick={() => openDecision('requirement', selectedDocument.document, 'approved')} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white"><FiCheck /> Approve Requirement</button>}
              {selectedDocument.document.can_program_head_review !== false && !selectedDocument.document.program_head_endorsed && selectedDocument.document.status !== 'rejected' && <button type="button" onClick={() => openDecision('requirement', selectedDocument.document, 'rejected')} className="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"><FiX /> Reject with Feedback</button>}
            </div>
          </div>
        </div>
      )}

      {decision && (
        <Modal title={`${decision.kind === 'moa' ? (decision.decision === 'endorsed' ? 'Endorse' : 'Return') : (decision.decision === 'approved' ? 'Approve' : 'Reject')} ${decision.kind === 'requirement' ? 'Requirement' : 'MOA'}`} subtitle="Review decisions are recorded with your account and timestamp" onClose={() => !busy && setDecision(null)} size="max-w-lg">
          <form onSubmit={submitDecision} className="p-6">
            <div className={`grid h-14 w-14 place-items-center rounded-2xl text-2xl ${decision.decision === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'}`}>{decision.decision === 'approved' ? <FiCheckCircle /> : <FiXCircle />}</div>
            <h3 className="mt-4 font-black text-slate-800 dark:text-white">{decision.kind === 'requirement' ? decision.document.requirement_name : decision.document.hte}</h3>
            <p className="mt-2 text-xs leading-5 text-slate-400">{decision.kind === 'moa' ? decision.decision === 'endorsed' ? 'Send this MOA to the Dean for final approval.' : 'Return this MOA to the coordinator.' : decision.decision === 'approved' ? 'This requirement will be marked approved.' : 'This requirement will be returned for corrective action.'}</p>
            {(decision.decision === 'rejected' || decision.decision === 'returned') && <label className="mt-5 block"><span className="mb-2 block text-xs font-black text-slate-600 dark:text-gray-200">Feedback {decision.kind === 'requirement' && <span className="text-rose-500">*</span>}</span><textarea required={decision.kind === 'requirement'} rows="4" value={feedback} onChange={(event) => setFeedback(event.target.value)} placeholder="Explain what must be corrected or resubmitted..." className={inputClass} /></label>}
            <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-700"><button type="button" onClick={() => setDecision(null)} disabled={busy} className="rounded-xl border border-slate-200 px-5 py-3 text-xs font-black text-slate-600 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200">Cancel</button><button type="submit" disabled={busy} className={`inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-xs font-black text-white disabled:opacity-50 ${decision.decision === 'approved' ? 'bg-emerald-600' : 'bg-rose-600'}`}>{busy ? <FiRefreshCw className="animate-spin" /> : decision.decision === 'approved' ? <FiCheck /> : <FiX />} Confirm {decision.kind === 'moa' ? decision.decision === 'endorsed' ? 'Endorsement' : 'Return' : decision.decision === 'approved' ? 'Approval' : 'Rejection'}</button></div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default DocumentReview;
