import { useEffect, useMemo, useState } from 'react';
import {
  FiArchive,
  FiBriefcase,
  FiCheck,
  FiCheckCircle,
  FiClock,
  FiDownload,
  FiEye,
  FiFileText,
  FiFilter,
  FiMapPin,
  FiRefreshCw,
  FiSearch,
  FiShield,
  FiUsers,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyData = {
  summary: {
    pending_documents: 0,
    pending_deployments: 0,
    approved: 0,
    rejected: 0,
    decided_today: 0,
  },
  documents: [],
  deployments: [],
  history: [],
};

const tabs = [
  { id: 'documents', label: 'Endorsed Documents', icon: FiFileText },
  { id: 'deployments', label: 'Deployment Requests', icon: FiUsers },
  { id: 'history', label: 'Approval History', icon: FiArchive },
];

const statusStyles = {
  pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
  approved: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
  rejected: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-300 focus:border-[#800000] focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white';

function formatDate(value, includeTime = false) {
  if (!value) return '—';
  return new Date(value).toLocaleString('en-PH', includeTime
    ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }
    : { month: 'short', day: 'numeric', year: 'numeric' });
}

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${statusStyles[status] || statusStyles.pending}`}>
      {status === 'approved' ? <FiCheck /> : status === 'rejected' ? <FiX /> : <FiClock />}
      {status}
    </span>
  );
}

function MetricCard({ label, value, note, icon: Icon, tone }) {
  const tones = {
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
  };

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-black text-[#430909] dark:text-white">{value}</p>
          <p className="mt-1 text-xs text-slate-400">{note}</p>
        </div>
        <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-xl ${tones[tone]}`}><Icon /></div>
      </div>
    </div>
  );
}

function EmptyState({ icon: Icon, title, description }) {
  return (
    <div className="grid min-h-64 place-items-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-8 text-center dark:border-gray-600 dark:bg-gray-900/40">
      <div>
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-2xl text-slate-300 shadow-sm dark:bg-gray-800 dark:text-gray-500"><Icon /></div>
        <h3 className="mt-4 font-black text-slate-700 dark:text-gray-200">{title}</h3>
        <p className="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-400">{description}</p>
      </div>
    </div>
  );
}

function Modal({ title, subtitle, onClose, children }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-gray-800">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-5 py-5 sm:px-6 dark:border-gray-700 dark:bg-gray-800">
          <div>
            <h2 className="text-lg font-black text-[#430909] dark:text-white">{title}</h2>
            <p className="mt-1 text-xs text-slate-400">{subtitle}</p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close review dialog" className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200 dark:bg-gray-700 dark:text-gray-200"><FiX /></button>
        </div>
        {children}
      </div>
    </div>
  );
}

function StudentIdentity({ student }) {
  return (
    <div>
      <p className="text-sm font-black text-slate-800 dark:text-white">{student?.name || 'Unknown student'}</p>
      <p className="mt-1 text-[10px] text-slate-400">{student?.student_id || 'No ID'} · {student?.program || 'No program'} · Section {student?.section || '—'}</p>
    </div>
  );
}

function VPAAApprovals() {
  const [data, setData] = useState(emptyData);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState('documents');
  const [search, setSearch] = useState('');
  const [historyStatus, setHistoryStatus] = useState('all');
  const [review, setReview] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);

  const loadApprovals = async (quiet = false) => {
    if (quiet) setRefreshing(true); else setLoading(true);
    try {
      const response = await api.get('/vpaa/approvals');
      setData({ ...emptyData, ...response.data });
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load approval records.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    let active = true;
    api.get('/vpaa/approvals')
      .then((response) => {
        if (active) setData({ ...emptyData, ...response.data });
      })
      .catch((error) => {
        if (active) toast.error(error.response?.data?.message || 'Unable to load approval records.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, []);

  const normalizedSearch = search.trim().toLowerCase();
  const documents = useMemo(() => data.documents.filter((item) => (
    !normalizedSearch
    || `${item.document_name} ${item.student?.name} ${item.student?.student_id} ${item.student?.program} ${item.endorsed_by}`.toLowerCase().includes(normalizedSearch)
  )), [data.documents, normalizedSearch]);
  const deployments = useMemo(() => data.deployments.filter((item) => (
    !normalizedSearch
    || `${item.student?.name} ${item.student?.student_id} ${item.student?.program} ${item.hte?.name} ${item.requested_by}`.toLowerCase().includes(normalizedSearch)
  )), [data.deployments, normalizedSearch]);
  const history = useMemo(() => data.history.filter((item) => {
    const matchesSearch = !normalizedSearch
      || `${item.subject} ${item.student?.name} ${item.student?.student_id} ${item.decided_by} ${item.remarks}`.toLowerCase().includes(normalizedSearch);
    return matchesSearch && (historyStatus === 'all' || item.status === historyStatus);
  }), [data.history, historyStatus, normalizedSearch]);

  const openReview = (type, item) => {
    setRemarks('');
    setReview({ type, item });
  };

  const submitDecision = async (decision) => {
    if (decision === 'rejected' && !remarks.trim()) {
      toast.error('Remarks are required when rejecting a request.');
      return;
    }

    setBusy(true);
    try {
      const response = await api.put(`/vpaa/approvals/${review.item.approval_id}`, {
        decision,
        remarks: remarks.trim() || null,
      });
      toast.success(response.data.message);
      setReview(null);
      setRemarks('');
      await loadApprovals(true);
    } catch (error) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || 'Unable to save the approval decision.');
    } finally {
      setBusy(false);
    }
  };

  const downloadDocument = async (item) => {
    try {
      const response = await api.get(`/vpaa/approvals/${item.approval_id}/download`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const anchor = window.document.createElement('a');
      anchor.href = url;
      anchor.download = `${item.document_name || 'endorsed-document'}.${item.file_type === 'pdf' ? 'pdf' : 'file'}`;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to download the endorsed document.');
    }
  };

  const summary = data.summary || emptyData.summary;

  if (loading) {
    return (
      <div className="grid min-h-[420px] place-items-center">
        <div className="text-center">
          <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-[#800000]/15 border-b-[#800000]" />
          <p className="mt-4 text-xs font-black uppercase tracking-widest text-slate-400">Loading approval workspace</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#430909] via-[#690d0d] to-[#8d1717] p-6 text-white shadow-lg sm:p-8">
        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full border-[44px] border-white/5" />
        <div className="absolute bottom-0 right-28 h-28 w-28 rounded-full bg-[#f1c54c]/10 blur-2xl" />
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#f7d979]"><FiShield /> Executive Approval Control</div>
            <h1 className="text-2xl font-black sm:text-3xl">Approval Management</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-white/65">Review endorsed internship documents, decide student deployment requests, and maintain a complete decision history.</p>
          </div>
          <button type="button" onClick={() => loadApprovals(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-black transition hover:bg-white/15 disabled:opacity-50">
            <FiRefreshCw className={refreshing ? 'animate-spin' : ''} /> Refresh Approvals
          </button>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Endorsed Documents" value={summary.pending_documents} note="Awaiting final document decision" icon={FiFileText} tone="amber" />
        <MetricCard label="Deployment Requests" value={summary.pending_deployments} note="Awaiting deployment clearance" icon={FiUsers} tone="blue" />
        <MetricCard label="Approved Decisions" value={summary.approved} note={`${summary.decided_today} decision(s) completed today`} icon={FiCheckCircle} tone="green" />
        <MetricCard label="Rejected Decisions" value={summary.rejected} note="Returned with recorded remarks" icon={FiXCircle} tone="rose" />
      </section>

      <nav className="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800" aria-label="Approval sections">
        <div className="flex min-w-max gap-1">
          {tabs.map((tab) => {
            const count = tab.id === 'documents' ? summary.pending_documents : tab.id === 'deployments' ? summary.pending_deployments : data.history.length;
            return (
              <button key={tab.id} type="button" onClick={() => { setActiveTab(tab.id); setSearch(''); }} className={`inline-flex items-center gap-2 rounded-xl px-5 py-3 text-xs font-black transition ${activeTab === tab.id ? 'bg-[#800000] text-white shadow-md shadow-[#800000]/15' : 'text-slate-500 hover:bg-slate-50 hover:text-[#800000] dark:text-gray-300 dark:hover:bg-gray-700'}`}>
                <tab.icon className="text-base" /> {tab.label}
                <span className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[9px] ${activeTab === tab.id ? 'bg-white text-[#800000]' : 'bg-slate-100 text-slate-500 dark:bg-gray-700 dark:text-gray-300'}`}>{count}</span>
              </button>
            );
          })}
        </div>
      </nav>

      <div className="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:flex-row">
        <label className="relative flex-1">
          <FiSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={activeTab === 'documents' ? 'Search student, ID, program, document, or endorser...' : activeTab === 'deployments' ? 'Search student, ID, HTE, or requester...' : 'Search history, decision maker, or remarks...'} className={`${inputClass} pl-10`} />
        </label>
        {activeTab === 'history' && (
          <label className="relative md:w-48">
            <FiFilter className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <select value={historyStatus} onChange={(event) => setHistoryStatus(event.target.value)} className={`${inputClass} appearance-none pl-10`}>
              <option value="all">All decisions</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </label>
        )}
      </div>

      {activeTab === 'documents' && (
        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
          <div className="mb-5 flex items-start gap-3">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"><FiFileText /></div>
            <div><h2 className="font-black text-[#430909] dark:text-white">Review Endorsed Documents</h2><p className="mt-1 text-xs text-slate-400">Documents cleared and endorsed by the Program Head for VPAA decision.</p></div>
          </div>
          {documents.length === 0 ? (
            <EmptyState icon={FiFileText} title="No endorsed documents waiting" description="New Program Head endorsements will appear here automatically." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[900px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Document</th><th className="pb-3 font-black">Endorsed By</th><th className="pb-3 font-black">Date Endorsed</th><th className="pb-3 font-black">Status</th><th className="pb-3 text-right font-black">Actions</th></tr></thead>
                <tbody>{documents.map((item) => (
                  <tr key={item.approval_id} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/40">
                    <td className="py-4"><StudentIdentity student={item.student} /></td>
                    <td className="py-4"><p className="max-w-64 text-xs font-black text-slate-700 dark:text-gray-200">{item.document_name}</p><span className="mt-1.5 inline-flex rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-black uppercase text-slate-500 dark:bg-gray-700 dark:text-gray-300">{item.file_type || 'File'}</span></td>
                    <td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{item.endorsed_by || 'Program Head'}</td>
                    <td className="py-4 text-xs text-slate-500 dark:text-gray-400">{formatDate(item.endorsed_at, true)}</td>
                    <td className="py-4"><StatusBadge status="pending" /></td>
                    <td className="py-4"><div className="flex justify-end gap-2"><button type="button" onClick={() => downloadDocument(item)} title="Download endorsed document" className="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-slate-600 transition hover:bg-slate-200 dark:bg-gray-700 dark:text-gray-200"><FiDownload /></button><button type="button" onClick={() => openReview('document', item)} className="inline-flex items-center gap-1.5 rounded-lg bg-[#800000] px-3 py-2 text-xs font-black text-white"><FiEye /> Review</button></div></td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {activeTab === 'deployments' && (
        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
          <div className="mb-5 flex items-start gap-3">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300"><FiUsers /></div>
            <div><h2 className="font-black text-[#430909] dark:text-white">Student Deployment Requests</h2><p className="mt-1 text-xs text-slate-400">Verify the student, receiving HTE, official schedule, and required internship hours.</p></div>
          </div>
          {deployments.length === 0 ? (
            <EmptyState icon={FiUsers} title="No deployment requests waiting" description="Coordinator deployment submissions will appear here for clearance." />
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              {deployments.map((item) => (
                <article key={item.approval_id} className="rounded-2xl border border-slate-100 p-5 transition hover:border-[#800000]/20 hover:shadow-md dark:border-gray-700">
                  <div className="flex items-start justify-between gap-3">
                    <StudentIdentity student={item.student} />
                    <StatusBadge status="pending" />
                  </div>
                  <div className="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-gray-900/50">
                    <div className="flex items-start gap-3"><FiBriefcase className="mt-0.5 shrink-0 text-[#800000] dark:text-rose-300" /><div><p className="text-xs font-black text-slate-700 dark:text-gray-200">{item.hte?.name}</p><p className="mt-1 text-[10px] leading-4 text-slate-400">{item.hte?.address}</p></div></div>
                    <div className="mt-3 grid grid-cols-2 gap-3 border-t border-slate-200 pt-3 text-[10px] dark:border-gray-700">
                      <div><p className="font-black uppercase text-slate-400">OJT Period</p><p className="mt-1 font-bold text-slate-600 dark:text-gray-300">{formatDate(item.schedule?.start_date)} – {formatDate(item.schedule?.end_date)}</p></div>
                      <div><p className="font-black uppercase text-slate-400">Required Hours</p><p className="mt-1 font-bold text-slate-600 dark:text-gray-300">{item.schedule?.required_hours || 0} hours</p></div>
                    </div>
                  </div>
                  <div className="mt-4 flex items-center justify-between gap-3"><p className="text-[10px] text-slate-400">Requested {formatDate(item.requested_at, true)} by {item.requested_by || 'Coordinator'}</p><button type="button" onClick={() => openReview('deployment', item)} className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#800000] px-3 py-2 text-xs font-black text-white"><FiEye /> Review</button></div>
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {activeTab === 'history' && (
        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
          <div className="mb-5 flex items-start gap-3">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#800000]/10 text-[#800000] dark:bg-rose-950/50 dark:text-rose-300"><FiArchive /></div>
            <div><h2 className="font-black text-[#430909] dark:text-white">Approval History Tracking</h2><p className="mt-1 text-xs text-slate-400">A chronological ledger of final VPAA decisions and recorded remarks.</p></div>
          </div>
          {history.length === 0 ? (
            <EmptyState icon={FiArchive} title="No matching approval history" description="Completed document and deployment decisions will be retained here." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1000px] text-left">
                <thead><tr className="border-b border-slate-100 text-[9px] uppercase tracking-wider text-slate-400 dark:border-gray-700"><th className="pb-3 font-black">Type</th><th className="pb-3 font-black">Student</th><th className="pb-3 font-black">Subject</th><th className="pb-3 font-black">Decision</th><th className="pb-3 font-black">Decision Maker</th><th className="pb-3 font-black">Date</th><th className="pb-3 font-black">Remarks</th></tr></thead>
                <tbody>{history.map((item) => (
                  <tr key={item.id} className="border-b border-slate-100 last:border-0 dark:border-gray-700">
                    <td className="py-4"><span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-[9px] font-black uppercase text-slate-600 dark:bg-gray-700 dark:text-gray-200">{item.type === 'document' ? <FiFileText /> : <FiUsers />}{item.type}</span></td>
                    <td className="py-4"><StudentIdentity student={item.student} /></td>
                    <td className="py-4 max-w-56 text-xs font-bold text-slate-600 dark:text-gray-300">{item.subject}</td>
                    <td className="py-4"><StatusBadge status={item.status} /></td>
                    <td className="py-4 text-xs font-bold text-slate-600 dark:text-gray-300">{item.decided_by || '—'}</td>
                    <td className="py-4 text-xs text-slate-500 dark:text-gray-400">{formatDate(item.decided_at, true)}</td>
                    <td className="py-4"><p className="max-w-64 text-xs leading-5 text-slate-500 dark:text-gray-400">{item.remarks || 'No remarks provided.'}</p></td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {review && (
        <Modal title={review.type === 'document' ? 'Review Endorsed Document' : 'Review Deployment Request'} subtitle="Review the request details before recording the final VPAA decision." onClose={() => !busy && setReview(null)}>
          <div className="space-y-5 p-5 sm:p-6">
            <div className="rounded-2xl border border-slate-100 p-5 dark:border-gray-700">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <StudentIdentity student={review.item.student} />
                <StatusBadge status="pending" />
              </div>

              {review.type === 'document' ? (
                <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-gray-700">
                  <div><p className="text-[10px] font-black uppercase tracking-wide text-slate-400">Document</p><p className="mt-1 text-sm font-black text-slate-700 dark:text-gray-200">{review.item.document_name}</p></div>
                  <div><p className="text-[10px] font-black uppercase tracking-wide text-slate-400">Endorsement</p><p className="mt-1 text-sm font-bold text-slate-600 dark:text-gray-300">{review.item.endorsed_by || 'Program Head'} · {formatDate(review.item.endorsed_at)}</p></div>
                  <button type="button" onClick={() => downloadDocument(review.item)} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-xs font-black text-[#800000] sm:col-span-2 dark:border-gray-600 dark:text-rose-300"><FiDownload /> Download and Inspect Document</button>
                </div>
              ) : (
                <div className="mt-5 space-y-4 border-t border-slate-100 pt-5 dark:border-gray-700">
                  <div className="rounded-xl bg-slate-50 p-4 dark:bg-gray-900/50"><div className="flex items-start gap-3"><FiMapPin className="mt-0.5 shrink-0 text-[#800000] dark:text-rose-300" /><div><p className="text-sm font-black text-slate-700 dark:text-gray-200">{review.item.hte?.name}</p><p className="mt-1 text-xs text-slate-400">{review.item.hte?.address}</p><p className="mt-2 text-[10px] font-bold text-slate-500">Contact: {review.item.hte?.contact_person || 'Not provided'}</p></div></div></div>
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                      ['Start Date', formatDate(review.item.schedule?.start_date)],
                      ['End Date', formatDate(review.item.schedule?.end_date)],
                      ['Required Hours', `${review.item.schedule?.required_hours || 0} hours`],
                      ['Work Days', review.item.schedule?.work_days?.join(', ').toUpperCase() || '—'],
                      ['AM Schedule', `${review.item.schedule?.am_start || '—'} – ${review.item.schedule?.am_end || '—'}`],
                      ['PM Schedule', `${review.item.schedule?.pm_start || '—'} – ${review.item.schedule?.pm_end || '—'}`],
                    ].map(([label, value]) => <div key={label} className="rounded-xl border border-slate-100 p-3 dark:border-gray-700"><p className="text-[9px] font-black uppercase text-slate-400">{label}</p><p className="mt-1 text-xs font-bold text-slate-600 dark:text-gray-300">{value}</p></div>)}
                  </div>
                </div>
              )}
            </div>

            <label>
              <span className="mb-2 block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-gray-300">Decision Remarks <span className="font-semibold normal-case text-slate-400">(required when rejecting)</span></span>
              <textarea rows="4" value={remarks} onChange={(event) => setRemarks(event.target.value)} placeholder="Record the basis of the approval or the corrections required..." className={`${inputClass} resize-none`} maxLength="2000" />
              <span className="mt-1 block text-right text-[10px] text-slate-400">{remarks.length}/2000</span>
            </label>
          </div>
          <div className="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-slate-100 bg-white px-5 py-4 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700 dark:bg-gray-800">
            <button type="button" onClick={() => setReview(null)} disabled={busy} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-600 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300">Cancel</button>
            <button type="button" onClick={() => submitDecision('rejected')} disabled={busy} className="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-3 text-sm font-black text-white disabled:opacity-50"><FiXCircle /> {busy ? 'Saving...' : 'Reject Request'}</button>
            <button type="button" onClick={() => submitDecision('approved')} disabled={busy} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white disabled:opacity-50"><FiCheckCircle /> {busy ? 'Saving...' : 'Approve Request'}</button>
          </div>
        </Modal>
      )}
    </div>
  );
}

export default VPAAApprovals;
