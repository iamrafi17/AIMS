import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FiAlertTriangle, FiCheck, FiChevronLeft, FiChevronRight, FiEdit2, FiEye, FiFileText, FiPlus, FiSearch, FiSettings, FiTrash2, FiUpload, FiUserCheck, FiUsers, FiX } from 'react-icons/fi';
import toast from 'react-hot-toast';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../services/api';
import RequirementManagerModal from './RequirementManagerModal';
import ContactNumberInput from '../../components/forms/ContactNumberInput';

const emptyForm = {
  student_id: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  gender: 'female',
  birth_date: '',
  address: '',
  contact_number: '',
  college_id: '',
  program_id: '',
  academic_section_id: '',
  year_level: 4,
  section: '',
  parent_name: '',
  parent_relationship: 'Parent',
  parent_address: '',
  parent_contact_number: '',
  hte_id: '',
  hte_department_id: '',
  internship_semester: '',
  internship_year: '',
  internship_status: 'pending',
  consent_status: 'pending',
  schedule_status: 'pending',
  ojt_start_date: '',
  ojt_end_date: '',
  required_ojt_hours: 486,
  allow_past_attendance: false,
};

const enrollmentPageSizes = [10, 25, 50, 100];

function studentAcademicLabel(student) {
  const program = String(student.program?.code || '').trim();
  const section = String(student.section || '').trim();
  const fullSection = program && section
    && section.toLowerCase() !== program.toLowerCase()
    && !section.toLowerCase().startsWith(`${program.toLowerCase()} `)
    ? `${program} ${section}`
    : section || program;
  return [student.year_level ? `Year ${student.year_level}` : null, fullSection]
    .filter(Boolean).join(' · ');
}

const badge = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  completed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  registered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  missing: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
  dropped: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
};

function StatusBadge({ value }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${badge[value] || badge.pending}`}>{value || 'pending'}</span>;
}

function Modal({ title, subtitle, onClose, children, size = 'default', height, footer }) {
  const maxWidths = { confirm: '30rem', default: '42rem', form: '40rem', wide: '56rem', detail: '36rem' };

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, []);

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center overflow-hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5">
      <div role="dialog" aria-modal="true" aria-label={title} style={{ maxWidth: maxWidths[size] || maxWidths.default, ...(height ? { height } : {}) }} className="flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-3xl border border-white/20 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800 sm:max-h-[calc(100dvh-2.5rem)]">
        <div className="flex shrink-0 items-start justify-between border-b border-slate-100 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800 sm:px-5">
          <div className="min-w-0 pr-4">
            <h2 className="truncate text-lg font-black text-[#690016] dark:text-white">{title}</h2>
            {subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}
          </div>
          <button type="button" onClick={onClose} className="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close dialog">
            <FiX />
          </button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">{children}</div>
        {footer && <div className="flex shrink-0 justify-end gap-3 border-t border-slate-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">{footer}</div>}
      </div>
    </div>
  );
}

function Input({ label, name, form, setForm, type = 'text', required = false, ...props }) {
  const inputClassName = 'w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#7D001D]/50 focus:ring-4 focus:ring-[#7D001D]/5 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-600 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800';
  const isContactNumber = name === 'contact_number' || name === 'parent_contact_number';

  return (
    <label>
      <span className="mb-1.5 block text-[11px] font-extrabold uppercase tracking-wide text-slate-400">
        {label}
        {required && ' *'}
      </span>
      {isContactNumber ? (
        <ContactNumberInput value={form[name] ?? ''} onValueChange={(value) => setForm((current) => ({ ...current, [name]: value }))} required={required} className={inputClassName} placeholder="09XX XXX XXXX" {...props} />
      ) : (
        <input type={type} name={name} value={form[name] ?? ''} onChange={(event) => setForm((current) => ({ ...current, [name]: event.target.value }))} required={required} className={inputClassName} {...props} />
      )}
    </label>
  );
}

function Select({ label, name, form, setForm, children, required = false, ...props }) {
  return (
    <label>
      <span className="mb-1.5 block text-[11px] font-extrabold uppercase tracking-wide text-slate-400">
        {label}
        {required && ' *'}
      </span>
      <select name={name} value={form[name] ?? ''} onChange={(event) => setForm((current) => ({ ...current, [name]: event.target.value }))} required={required} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#7D001D]/50 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-600 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800" {...props}>
        {children}
      </select>
    </label>
  );
}

function Progress({ value, color = 'bg-[#7D001D]' }) {
  return (
    <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700">
      <div className={`h-full rounded-full ${color}`} style={{ width: `${Math.min(Math.max(Number(value) || 0, 0), 100)}%` }} />
    </div>
  );
}

function RequirementSummary({ requirements = [], studentId }) {
  const counts = requirements.reduce((result, item) => {
    const status = item.file_path ? item.status : 'missing';
    result[status] = (result[status] || 0) + 1;
    return result;
  }, { missing: 0, pending: 0, approved: 0, rejected: 0 });
  const percent = requirements.length ? Math.round(counts.approved / requirements.length * 100) : 0;
  return <section className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
    <div className="flex justify-between gap-3"><h3 className="font-black text-[#690016] dark:text-white">Internship Requirements</h3><b>{counts.approved}/{requirements.length} approved · {percent}%</b></div>
    <Progress value={percent} />
    <p className="mt-3 text-xs text-slate-500">Missing {counts.missing} · For review {counts.pending} · Approved {counts.approved} · Rejected {counts.rejected}</p>
    <Link to={`/coordinator/internship-requirements?student=${studentId}`} className="mt-4 inline-block rounded-lg bg-[#7D001D] px-4 py-2 text-sm font-bold text-white">View Requirements →</Link>
  </section>;
}
function StudentManagement() {
  const [urlParams] = useSearchParams();
  const [students, setStudents] = useState([]);
  const [enrollments, setEnrollments] = useState([]);
  const [enrollmentSummary, setEnrollmentSummary] = useState({});
  const [enrollmentPage, setEnrollmentPage] = useState(1);
  const [enrollmentPerPage, setEnrollmentPerPage] = useState(10);
  const [enrollmentPagination, setEnrollmentPagination] = useState({ currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 });
  const [summary, setSummary] = useState({});
  const [options, setOptions] = useState({ colleges: [], htes: [], sections: [] });
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [internshipStatus, setInternshipStatus] = useState(urlParams.get('internship_status') || '');
  const [registrationFilter, setRegistrationFilter] = useState(urlParams.get('registration') || '');
  const [sectionFilter, setSectionFilter] = useState('');
  const [page, setPage] = useState(1);
  const [studentPerPage, setStudentPerPage] = useState(10);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [editor, setEditor] = useState(null);
  const [editorStep, setEditorStep] = useState(0);
  const [form, setForm] = useState(emptyForm);
  const [selected, setSelected] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [requirementsOpen, setRequirementsOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importResult, setImportResult] = useState(null);
  const [importSectionId, setImportSectionId] = useState('');
  const [importFilePreview, setImportFilePreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const importInput = useRef(null);
  const importPreviewTrigger = useRef(null);

  const fetchStudents = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/students', { params: { search, internship_status: internshipStatus, academic_section_id: sectionFilter || undefined, page, per_page: studentPerPage } });
      setStudents(response.data.data || []);
      setSummary(response.data.summary || {});
      setLastPage(response.data.last_page || 1);
      setTotal(response.data.total || 0);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load students.');
    } finally {
      setLoading(false);
    }
  }, [internshipStatus, page, search, sectionFilter, studentPerPage]);

  const fetchEnrollments = useCallback(async () => {
    try {
      const response = await api.get('/coordinator/students/enrollments', {
        params: { search, academic_section_id: sectionFilter || undefined, registration: registrationFilter || undefined, page: enrollmentPage, per_page: enrollmentPerPage },
      });
      setEnrollments(response.data.data || []);
      setEnrollmentSummary(response.data.summary || {});
      setEnrollmentPagination({
        currentPage: response.data.current_page || 1,
        lastPage: response.data.last_page || 1,
        total: response.data.total || 0,
        from: response.data.from || 0,
        to: response.data.to || 0,
      });
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load the OJT enrollment list.'));
    }
  }, [enrollmentPage, enrollmentPerPage, search, sectionFilter, registrationFilter]);

  useEffect(() => {
    api
      .get('/coordinator/students/options')
      .then((response) => setOptions(response.data))
      .catch(() => toast.error('Unable to load form options.'));
  }, []);
  useEffect(() => {
    const timer = window.setTimeout(fetchStudents, 250);
    return () => window.clearTimeout(timer);
  }, [fetchStudents]);
  useEffect(() => {
    const timer = window.setTimeout(fetchEnrollments, 250);
    return () => window.clearTimeout(timer);
  }, [fetchEnrollments]);
  useEffect(() => {
    setPage(1);
  }, [search, internshipStatus, sectionFilter]);
  useEffect(() => {
    setEnrollmentPage(1);
  }, [search, sectionFilter]);

  const programs = useMemo(() => options.colleges?.find((college) => String(college.id) === String(form.college_id))?.programs || [], [options, form.college_id]);
  const errorMessage = (error, fallback) => {
    const errors = error.response?.data?.errors;
    return errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || fallback;
  };
  const openCreate = () => {
    setForm(emptyForm);
    setEditorStep(0);
    setEditor({ mode: 'create' });
  };
  const openEdit = (student) => {
    setForm({ ...emptyForm, ...student, email: student.user?.email || '', college_id: student.college_id || student.college?.id || '', program_id: student.program_id || student.program?.id || '', hte_id: student.hte_id || student.hte?.id || '', birth_date: student.birth_date?.slice(0, 10) || '', required_ojt_hours: student.official_ojt_setting?.required_hours ?? student.required_ojt_hours ?? '', ojt_start_date: student.official_ojt_setting?.ojt_start_date?.slice(0, 10) || student.ojt_start_date?.slice(0, 10) || '', ojt_end_date: student.ojt_end_date?.slice(0, 10) || '' });
    setSelected(null);
    setEditorStep(0);
    setEditor({ mode: 'edit', student });
  };

  const validateEditorStep = (step = editorStep) => {
    const requiredByStep = [
      ['student_id', 'first_name', 'last_name', 'email'],
      ['college_id', 'program_id', 'year_level', 'section'],
      ['parent_name', 'parent_contact_number', 'parent_address'],
    ];
    const missing = requiredByStep[step].some((field) => String(form[field] ?? '').trim() === '');
    if (missing) {
      toast.error('Please complete all required fields on this step.');
      return false;
    }
    return true;
  };

  const nextEditorStep = () => {
    if (!validateEditorStep()) return;
    setEditorStep((current) => Math.min(current + 1, 2));
  };

  const saveStudent = async (event) => {
    event?.preventDefault();
    if (editor.mode === 'edit' && (editorStep !== 2 || !validateEditorStep(2))) return;
    setBusy(true);
    try {
      const payload = editor.mode === 'create' ? { first_name: form.first_name.trim(), middle_name: form.middle_name.trim() || null, last_name: form.last_name.trim(), school_id: form.student_id.trim(), academic_section_id: Number(form.academic_section_id) } : { ...form, college_id: Number(form.college_id), program_id: Number(form.program_id), hte_id: form.hte_id ? Number(form.hte_id) : null, year_level: Number(form.year_level), required_ojt_hours: Number(form.required_ojt_hours), allow_past_attendance: Boolean(form.allow_past_attendance) };
      delete payload.registration_status;
      delete payload.password;
      delete payload.gender;
      delete payload.birth_date;
      delete payload.contact_number;
      delete payload.address;
      const response = editor.mode === 'create' ? await api.post('/coordinator/students', payload) : await api.put(`/coordinator/students/${editor.student.id}`, payload);
      toast.success(response.data.message);
      setEditor(null);
      if (editor.mode === 'create') fetchEnrollments();
      else fetchStudents();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to save student.'));
    } finally {
      setBusy(false);
    }
  };

  const viewStudent = async (id) => {
    try {
      const response = await api.get(`/coordinator/students/${id}`);
      setSelected(response.data);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to load student details.'));
    }
  };

  const deleteStudent = async () => {
    if (!deleteTarget) return;
    setBusy(true);
    try {
      const response = await api.delete(`/coordinator/students/${deleteTarget.id}`);
      toast.success(response.data.message);
      setDeleteTarget(null);
      setSelected(null);
      fetchStudents();
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to delete student.'));
    } finally {
      setBusy(false);
    }
  };

  const previewCsvImport = async () => {
    if (!importFile) return toast.error('Choose a CSV file first.');
    if (!importSectionId) return toast.error('Choose the section for this student list.');
    const payload = new FormData();
    payload.append('file', importFile);
    payload.append('academic_section_id', importSectionId);
    setBusy(true);
    try {
      const response = await api.post('/coordinator/students/import', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      setImportResult(response.data);
      toast.success(response.data.message);
    } catch (error) {
      setImportResult(null);
      toast.error(errorMessage(error, 'CSV preview failed.'));
    } finally {
      setBusy(false);
    }
  };

  const confirmCsvImport = async () => {
    if (!importFile || !importResult || !importSectionId) return;
    const payload = new FormData();
    payload.append('file', importFile);
    payload.append('academic_section_id', importSectionId);
    setBusy(true);
    try {
      const response = await api.post('/coordinator/students/import/confirm', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      setImportResult((current) => ({ ...current, ...response.data, completed: true }));
      toast.success(`${response.data.message} ${response.data.detail}`);
      fetchEnrollments();
    } catch (error) {
      toast.error(errorMessage(error, 'Student import confirmation failed.'));
    } finally {
      setBusy(false);
    }
  };

  const viewSelectedCsv = async (trigger) => {
    if (!importFile) return;
    importPreviewTrigger.current = trigger;
    try {
      setImportFilePreview({ name: importFile.name, content: await importFile.text() });
    } catch {
      toast.error('Unable to open the selected CSV file.');
    }
  };

  const closeImportFilePreview = () => {
    setImportFilePreview(null);
    importPreviewTrigger.current?.focus();
  };

  const closeImport = () => {
    setImportOpen(false);
    setImportFile(null);
    setImportResult(null);
    setImportSectionId('');
    setImportFilePreview(null);
    if (importInput.current) importInput.current.value = '';
  };

  return (
    <div className="coordinator-students portal-card-tables space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Coordinator Portal</p>
          <h1 className="mt-1 text-2xl font-black text-[#690016] dark:text-white">Student Management</h1>
          <p className="mt-1 text-sm text-slate-400">Records, approvals, requirements, and internship progress</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button onClick={() => setRequirementsOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-[#d4af37]/50 bg-[#d4af37]/10 px-4 py-2.5 text-xs font-black text-[#6b4f00] dark:text-amber-300">
            <FiSettings /> Customize Requirements
          </button>
          <button
            onClick={() => {
              setImportOpen(true);
              setImportResult(null);
              setImportFile(null);
            }}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#7D001D] dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"
          >
            <FiUpload /> Import CSV
          </button>
          <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-4 py-2.5 text-xs font-black text-white">
            <FiPlus /> Add Manually
          </button>
        </div>
      </div>

      <div className="portal-summary-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {[
          ['Total Students', summary.total, FiUsers, 'text-[#7D001D]'],
          ['Pending Internship', summary.awaiting_deployment, FiFileText, 'text-amber-600'],
          ['Completed', summary.completed, FiCheck, 'text-emerald-600'],
          ['Dropped', summary.dropped, FiX, 'text-rose-600'],
          ['Active Interns', summary.active, FiUserCheck, 'text-blue-600'],
        ].map(([label, value, Icon, color]) => (
          <div key={label} className="portal-metric-card portal-summary-card rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex items-center gap-3">
              <div className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 dark:bg-gray-700">
                <Icon className={color} />
              </div>
              <div>
                <p className="text-2xl font-black text-[#690016] dark:text-white">{value || 0}</p>
                <p className="text-[10px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      <section id="ojt-enrollments" className="portal-table-panel overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-gray-700">
          <div>
            <h2 className="font-black text-[#690016] dark:text-white">OJT Enrollment List</h2>
            <p className="mt-1 text-xs text-slate-400">Students uploaded through CSV or added manually. They create their own account during registration.</p>
          </div>
          <div className="flex flex-wrap items-center justify-end gap-2">
            <select aria-label="Registration status" value={registrationFilter} onChange={(event) => { setRegistrationFilter(event.target.value); setEnrollmentPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:bg-gray-900"><option value="">All registration</option><option value="pending">Pending</option><option value="registered">Registered</option></select>
            <label className="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-gray-300">
              <span>Entries per page</span>
              <select
                value={enrollmentPerPage}
                onChange={(event) => {
                  setEnrollmentPerPage(Number(event.target.value));
                  setEnrollmentPage(1);
                }}
                aria-label="OJT enrollment entries per page"
                className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-700 outline-none focus:border-[#7D001D] focus:ring-2 focus:ring-[#7D001D]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
              >
                {enrollmentPageSizes.map((size) => (
                  <option key={size} value={size}>
                    {size}
                  </option>
                ))}
              </select>
            </label>
            <div className="flex gap-2 text-[10px] font-black uppercase">
              <span className="rounded-full bg-amber-100 px-3 py-1.5 text-amber-700 dark:bg-amber-950 dark:text-amber-300">{enrollmentSummary.awaiting_registration || 0} awaiting registration</span>
              <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{enrollmentSummary.registered || 0} registered</span>
            </div>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="portal-card-table portal-card-table--enrollment w-full min-w-[720px] text-left">
            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900">
              <tr>
                <th className="px-5 py-3">School ID</th>
                <th className="px-4 py-3">Student Name</th>
                <th className="px-4 py-3">Section</th>
                <th className="px-4 py-3">Added Through</th>
                <th className="px-5 py-3">Registration</th>
              </tr>
            </thead>
            <tbody>
              {enrollments.length ? (
                enrollments.map((enrollment) => (
                  <tr key={enrollment.id} className="border-t border-slate-100 dark:border-gray-700">
                    <td className="px-5 py-3 text-xs font-black text-slate-700 dark:text-gray-200">{enrollment.school_id}</td>
                    <td className="px-4 py-3 text-sm font-bold text-slate-800 dark:text-white">{[enrollment.first_name, enrollment.middle_name, enrollment.last_name].filter(Boolean).join(' ')}</td>
                    <td className="px-4 py-3 text-xs font-bold text-slate-600 dark:text-gray-300">{enrollment.section}</td>
                    <td className="px-4 py-3">
                      <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-500 dark:bg-gray-700 dark:text-gray-300">{enrollment.source}</span>
                    </td>
                    <td className="px-5 py-3">
                      <StatusBadge value={enrollment.registered_at ? 'registered' : 'pending'} />
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="5" className="py-12 text-center text-sm text-slate-400">
                    No students have been added to the OJT enrollment list.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-xs dark:border-gray-700">
          <p className="text-slate-400">{enrollmentPagination.total ? `Showing ${enrollmentPagination.from}-${enrollmentPagination.to} of ${enrollmentPagination.total} entries` : '0 entries'}</p>
          <div className="flex items-center gap-2">
            <span className="text-slate-400">
              Page {enrollmentPagination.currentPage} of {enrollmentPagination.lastPage}
            </span>
            <button type="button" onClick={() => setEnrollmentPage((current) => Math.max(current - 1, 1))} disabled={enrollmentPage === 1} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Previous OJT enrollment page">
              <FiChevronLeft />
            </button>
            <button type="button" onClick={() => setEnrollmentPage((current) => Math.min(current + 1, enrollmentPagination.lastPage))} disabled={enrollmentPage === enrollmentPagination.lastPage} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Next OJT enrollment page">
              <FiChevronRight />
            </button>
          </div>
        </div>
      </section>

      <section className="portal-filter-panel rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div className="portal-filter-grid grid gap-3 lg:grid-cols-[1fr_180px_220px]">
          <label className="portal-filter-search relative">
            <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, student ID, or email..." className="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-3 text-sm outline-none dark:border-gray-600 dark:bg-gray-900" />
          </label>
          <select value={internshipStatus} onChange={(event) => setInternshipStatus(event.target.value)} className="rounded-xl border border-slate-200 px-3 text-sm dark:border-gray-600 dark:bg-gray-900">
            <option value="">All internships</option>
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
          </select>
          <select value={sectionFilter} onChange={(event) => setSectionFilter(event.target.value)} className="rounded-xl border border-slate-200 px-3 text-sm dark:border-gray-600 dark:bg-gray-900">
            <option value="">All sections</option>
            {options.sections?.map((section) => (
              <option key={section.id} value={section.id}>
                {section.name} · Year {section.year_level} · AY {section.academic_year}
              </option>
            ))}
          </select>
        </div>
      </section>

      <section className="portal-table-panel overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div className="overflow-x-auto">
          <table className="portal-card-table portal-card-table--coordinator-students w-full min-w-[1050px] text-left">
            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900">
              <tr>
                <th className="px-5 py-4 font-extrabold">Student</th>
                <th className="px-4 py-4 font-extrabold">Academic / HTE</th>
                <th className="px-4 py-4 font-extrabold">Account / Internship</th>
                <th className="px-4 py-4 font-extrabold">OJT Progress</th>
                <th className="px-4 py-4 font-extrabold">Requirements</th>
                <th className="px-5 py-4 text-right font-extrabold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="6" className="py-16 text-center text-slate-400">
                    Loading students...
                  </td>
                </tr>
              ) : students.length ? (
                students.map((student) => (
                  <tr key={student.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/50">
                    <td className="px-5 py-4">
                      <p className="text-sm font-black text-slate-800 dark:text-white">
                        {student.first_name} {student.last_name}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        {student.student_id} · {student.user?.email}
                      </p>
                    </td>
                    <td className="px-4 py-4">
                      <p className="text-xs font-bold text-slate-700 dark:text-gray-200">
                        {studentAcademicLabel(student)}
                      </p>
                      <p className="mt-1 max-w-48 truncate text-[11px] text-slate-400">{student.hte?.name || 'No HTE assigned'}</p>
                    </td>
                    <td className="px-4 py-4">
                      <StatusBadge value="registered" />
                      <div className="mt-1.5">
                        <StatusBadge value={student.internship_status} />
                      </div>
                    </td>
                    <td className="w-48 px-4 py-4 align-top">
                      <div className="mb-1.5 flex justify-between text-[11px]">
                        <span className="font-bold text-slate-500">
                          {student.progress?.rendered_hours || 0}/{student.progress?.required_hours || 486}h
                        </span>
                        <span className="font-black text-[#7D001D] dark:text-rose-300">{student.progress?.percent || 0}%</span>
                      </div>
                      <Progress value={student.progress?.percent} />
                      <p className="mt-1.5 text-[10px] text-slate-400">
                        {student.progress?.attendance_days || 0} {(student.progress?.attendance_days || 0) === 1 ? 'day' : 'days'} · {student.progress?.journal_entries || 0} activity entries
                      </p>
                    </td>
                    <td className="w-40 px-4 py-4 align-top">
                      <div className="mb-1.5 flex justify-between text-[11px]">
                        <span className="text-slate-500">
                          {student.progress?.requirements_approved || 0}/{student.progress?.requirements_total || 0}
                        </span>
                        <span className="font-black text-blue-600">{student.progress?.requirements_percent || 0}%</span>
                      </div>
                      <Progress value={student.progress?.requirements_percent} color="bg-blue-500" />
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1.5">
                        <button onClick={() => viewStudent(student.id)} className="rounded-lg bg-blue-100 p-2 text-blue-700" title="Review">
                          <FiEye />
                        </button>
                        <button onClick={() => openEdit(student)} className="rounded-lg bg-amber-100 p-2 text-amber-700" title="Edit">
                          <FiEdit2 />
                        </button>
                        <button onClick={() => setDeleteTarget(student)} className="rounded-lg bg-rose-100 p-2 text-rose-700" title="Delete student">
                          <FiTrash2 />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="6" className="py-16 text-center text-slate-400">
                    No student records match your filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs dark:border-gray-700">
          <div className="flex items-center gap-3">
            <label className="flex items-center gap-2 font-bold text-slate-500 dark:text-gray-300">
              <span>Entries per page</span>
              <select
                value={studentPerPage}
                onChange={(event) => {
                  setStudentPerPage(Number(event.target.value));
                  setPage(1);
                }}
                aria-label="Student entries per page"
                className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-700 outline-none focus:border-[#7D001D] focus:ring-2 focus:ring-[#7D001D]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
              >
                {enrollmentPageSizes.map((size) => (
                  <option key={size} value={size}>
                    {size}
                  </option>
                ))}
              </select>
            </label>
            <p className="text-slate-400">
              {total} record(s) · Page {page} of {lastPage}
            </p>
          </div>
          <div className="flex gap-2">
            <button onClick={() => setPage((current) => Math.max(current - 1, 1))} disabled={page === 1} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Previous student page">
              <FiChevronLeft />
            </button>
            <button onClick={() => setPage((current) => Math.min(current + 1, lastPage))} disabled={page === lastPage} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600" aria-label="Next student page">
              <FiChevronRight />
            </button>
          </div>
        </div>
      </section>

      {editor?.mode === 'create' && (
        <Modal title="Add Student Manually" subtitle="Add one OJT student who was not included in the CSV file." onClose={() => setEditor(null)}>
          <form onSubmit={saveStudent}>
            <div className="space-y-4 p-5">
              <div className="grid gap-3 sm:grid-cols-3">
                <Input label="First Name" name="first_name" form={form} setForm={setForm} placeholder="e.g. Juan" autoFocus required />
                <Input label="Middle Name" name="middle_name" form={form} setForm={setForm} placeholder="Optional" />
                <Input label="Last Name" name="last_name" form={form} setForm={setForm} placeholder="e.g. Dela Cruz" required />
              </div>
              <Input label="School ID" name="student_id" form={form} setForm={setForm} placeholder="e.g. 2026-1001" required />
              <Select label="Section" name="academic_section_id" form={form} setForm={setForm} required>
                <option value="">Select section</option>
                {options.sections?.map((section) => (
                  <option key={section.id} value={section.id}>
                    {section.name} · Year {section.year_level} · AY {section.academic_year}
                  </option>
                ))}
              </Select>
              <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">This adds the student to the OJT enrollment list. The student will create their own email address and password on the Registration page.</div>
            </div>
            <div className="flex justify-end gap-3 border-t border-slate-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
              <button type="button" onClick={() => setEditor(null)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">
                Cancel
              </button>
              <button type="submit" disabled={busy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">
                {busy ? 'Adding...' : 'Add Student'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {editor?.mode === 'edit' && (
        <Modal title="Update Student Record" subtitle="Complete the three short steps below. No browser zooming needed." onClose={() => setEditor(null)} size="form" height="min(32rem, calc(100dvh - 2rem))">
          <form onSubmit={(event) => event.preventDefault()} className="flex h-full min-h-0 flex-col">
            <div className="shrink-0 border-b border-slate-100 bg-slate-50/80 px-4 py-2 dark:border-gray-700 dark:bg-gray-900/50">
              <div className="mx-auto flex max-w-2xl items-center">
                {['Personal & Account', 'Academic & OJT', 'Guardian & Status'].map((label, index) => (
                  <div key={label} className="flex min-w-0 flex-1 items-center last:flex-none">
                    <div className="flex min-w-0 items-center gap-2">
                      <span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-black ${index <= editorStep ? 'bg-[#7D001D] text-white' : 'bg-slate-200 text-slate-500 dark:bg-gray-700'}`}>{index + 1}</span>
                      <span className={`hidden truncate text-[11px] font-black sm:block ${index === editorStep ? 'text-[#7D001D] dark:text-rose-300' : 'text-slate-400'}`}>{label}</span>
                    </div>
                    {index < 2 && <span className={`mx-3 h-0.5 min-w-4 flex-1 ${index < editorStep ? 'bg-[#7D001D]' : 'bg-slate-200 dark:bg-gray-700'}`} />}
                  </div>
                ))}
              </div>
            </div>

            <div key={editorStep} className="min-h-0 flex-1 overflow-y-auto p-4">
              {editorStep === 0 && (
                <>
                  <h3 className="mb-4 text-sm font-black text-[#7D001D] dark:text-rose-300">Personal & Account Information</h3>
                  <div className="grid gap-3 md:grid-cols-3">
                    <Input label="Student ID" name="student_id" form={form} setForm={setForm} required />
                    <Input label="First Name" name="first_name" form={form} setForm={setForm} required />
                    <Input label="Middle Name" name="middle_name" form={form} setForm={setForm} />
                    <Input label="Last Name" name="last_name" form={form} setForm={setForm} required />
                    <Input label="Email" name="email" type="email" form={form} setForm={setForm} required />
                    <Select label="Gender" name="gender" form={form} setForm={setForm} disabled>
                      <option value="female">Female</option>
                      <option value="male">Male</option>
                    </Select>
                    <Input label="Birth Date" name="birth_date" type="date" form={form} setForm={setForm} disabled />
                    <Input label="Contact Number" name="contact_number" form={form} setForm={setForm} disabled />
                    <div className="md:col-span-3">
                      <Input label="Address" name="address" form={form} setForm={setForm} disabled />
                    </div>
                  </div>
                </>
              )}

              {editorStep === 1 && (
                <>
                  <h3 className="mb-4 text-sm font-black text-[#7D001D] dark:text-rose-300">Academic & Internship Information</h3>
                  <div className="grid gap-3 md:grid-cols-3">
                    <Select
                      label="College"
                      name="college_id"
                      form={form}
                      setForm={(updater) =>
                        setForm((current) => ({
                          ...updater(current),
                          program_id: '',
                        }))
                      }
                      required
                    >
                      <option value="">Select college</option>
                      {options.colleges?.map((college) => (
                        <option key={college.id} value={college.id}>
                          {college.code} — {college.name}
                        </option>
                      ))}
                    </Select>
                    <Select label="Program" name="program_id" form={form} setForm={setForm} required>
                      <option value="">Select program</option>
                      {programs.map((program) => (
                        <option key={program.id} value={program.id}>
                          {program.code} — {program.name}
                        </option>
                      ))}
                    </Select>
                    <p className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-gray-600 dark:bg-gray-900 dark:text-slate-300 md:col-span-2">HTE assignment and department are managed in <Link to="/coordinator/htes" className="font-bold text-[#7D001D] dark:text-rose-300">Student Deployments</Link>.</p>
                    <Select label="Year Level" name="year_level" form={form} setForm={setForm} required>
                      {[1, 2, 3, 4, 5].map((year) => (
                        <option key={year} value={year}>
                          {year}
                        </option>
                      ))}
                    </Select>
                    <Input label="Section" name="section" form={form} setForm={setForm} required />
                    <p className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-gray-600 dark:bg-gray-900 dark:text-slate-300 md:col-span-2">OJT start date, hours, and schedule are managed in <Link to="/coordinator/htes" className="font-bold text-[#7D001D] dark:text-rose-300">Student Deployments</Link>. Published Program Head settings apply there.</p>
                    <Input label="Semester" name="internship_semester" form={form} setForm={setForm} />
                    <Input label="Academic Year" name="internship_year" placeholder="2026-2027" form={form} setForm={setForm} />
                  </div>
                </>
              )}

              {editorStep === 2 && (
                <>
                  <h3 className="mb-4 text-sm font-black text-[#7D001D] dark:text-rose-300">Guardian & Record Status</h3>
                  <p className="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                    Review the final information, then click <b>Save Changes</b>. This step will not save automatically.
                  </p>
                  <div className="grid gap-3 md:grid-cols-3">
                    <Select label="Internship Status" name="internship_status" form={form} setForm={setForm}>
                      <option value="pending">Pending</option>
                      {form.internship_status === 'active' && <option value="active">Active</option>}
                      <option value="completed">Completed</option>
                      <option value="dropped">Dropped</option>
                    </Select>
                    <Select label="Consent Status" name="consent_status" form={form} setForm={setForm}>
                      <option value="pending">Pending</option>
                      <option value="done">Done</option>
                      <option value="rejected">Rejected</option>
                    </Select>
                    <p className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-gray-600 dark:bg-gray-900 dark:text-slate-300">Deployment approval: <strong className="capitalize">{form.schedule_status}</strong></p>
                    <Input label="Guardian Name" name="parent_name" form={form} setForm={setForm} required />
                    <Input label="Relationship" name="parent_relationship" form={form} setForm={setForm} />
                    <Input label="Contact Number" name="parent_contact_number" form={form} setForm={setForm} required />
                    <div className="md:col-span-3">
                      <Input label="Guardian Address" name="parent_address" form={form} setForm={setForm} required />
                    </div>
                  </div>
                </>
              )}
            </div>

            <div className="flex shrink-0 items-center justify-between border-t border-slate-100 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
              <button type="button" onClick={() => (editorStep === 0 ? setEditor(null) : setEditorStep((current) => current - 1))} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">
                {editorStep === 0 ? 'Cancel' : 'Previous'}
              </button>
              <span className="text-xs font-bold text-slate-400">Step {editorStep + 1} of 3</span>
              {editorStep < 2 ? (
                <button type="button" onClick={nextEditorStep} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white">
                  Next
                </button>
              ) : (
                <button type="button" onClick={() => saveStudent()} disabled={busy} className="rounded-xl bg-[#7D001D] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">
                  {busy ? 'Saving...' : 'Save Changes'}
                </button>
              )}
            </div>
          </form>
        </Modal>
      )}

      {selected && (
        <Modal title={`${selected.first_name} ${selected.last_name}`} subtitle={`${selected.student_id} · ${selected.user?.email}`} onClose={() => setSelected(null)} size="detail" height="min(32rem, calc(100dvh - 2rem))">
          <div className="space-y-3 p-3 sm:p-4">
            <aside className="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-center">
              <div className="rounded-xl bg-slate-50 p-3 dark:bg-gray-900">
                <div className="flex flex-wrap gap-2">
                  <StatusBadge value="registered" />
                  <StatusBadge value={selected.internship_status} />
                </div>
              </div>
              <div className="min-w-0 sm:col-span-2 sm:row-start-2">
                <div className="mb-2 flex justify-between text-xs">
                  <span>OJT Progress</span>
                  <b>{selected.progress?.percent || 0}%</b>
                </div>
                <Progress value={selected.progress?.percent} />
                <p className="mt-2 text-xs text-slate-400">
                  {selected.progress?.rendered_hours || 0} / {selected.progress?.required_hours || 486} hours · {selected.progress?.attendance_days || 0} attendance {(selected.progress?.attendance_days || 0) === 1 ? 'day' : 'days'}
                </p>
              </div>
              <button onClick={() => openEdit(selected)} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-[#7D001D] dark:border-gray-600 dark:text-rose-300 sm:col-start-2 sm:row-start-1">
                Edit Student Record
              </button>
            </aside>
            <RequirementSummary requirements={selected.requirements} studentId={selected.id} />
          </div>
        </Modal>
      )}

      {importOpen && (
        <Modal
          title="Import Student List"
          subtitle="Preview the official school CSV before saving eligible students."
          onClose={closeImport}
          size={importResult && !importResult.completed ? 'wide' : 'default'}
          height={importResult && !importResult.completed ? 'min(84dvh, 780px)' : undefined}
          footer={<>
            <button onClick={closeImport} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold dark:border-gray-600">
              {importResult?.completed ? 'Done' : 'Cancel'}
            </button>
            {!importResult && (
              <button onClick={previewCsvImport} disabled={busy || !importFile || !importSectionId} className="rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-black text-white disabled:opacity-50">
                {busy ? 'Reading CSV...' : 'Preview Import'}
              </button>
            )}
            {importResult && !importResult.completed && (
              <button onClick={confirmCsvImport} disabled={busy || !importResult.summary?.ready} className="rounded-xl bg-[#7D001D] px-5 py-3 text-sm font-black text-white disabled:opacity-50">
                {busy ? 'Importing...' : `Confirm Import (${importResult.summary?.ready || 0})`}
              </button>
            )}
          </>}
        >
          <div className="p-5">
            <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
              Upload the existing predefined school CSV without changing its layout. AIMS reads <b>Column E (School No.)</b> and <b>Column F (Full Name)</b>, then separates the last name and any trailing middle initial for review.
            </div>
            {!importResult?.completed && (
              <>
                <label className="mt-5 block">
                  <span className="mb-2 block text-sm font-black text-slate-700 dark:text-gray-200">Import into section</span>
                  <select
                    value={importSectionId}
                    onChange={(event) => {
                      setImportSectionId(event.target.value);
                      setImportResult(null);
                    }}
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-[#7D001D] dark:border-gray-600 dark:bg-gray-900"
                    required
                  >
                    <option value="">Select section</option>
                    {options.sections?.map((section) => (
                      <option key={section.id} value={section.id}>
                        {section.name} · Year {section.year_level} · AY {section.academic_year}
                      </option>
                    ))}
                  </select>
                </label>
                <button onClick={() => importInput.current?.click()} className="mt-4 flex w-full flex-col items-center rounded-2xl border-2 border-dashed border-slate-300 p-5 text-center transition hover:border-[#7D001D]/40 hover:bg-slate-50 dark:border-gray-600 dark:hover:bg-gray-900/40">
                  <FiUpload className="text-2xl text-[#7D001D] dark:text-rose-300" />
                  <p className="mt-2 text-sm font-black">{importFile?.name || 'Choose the predefined school CSV file'}</p>
                  <p className="mt-1 text-xs text-slate-400">CSV only, maximum 5 MB</p>
                </button>
                <input
                  ref={importInput}
                  type="file"
                  accept=".csv,text/csv"
                  className="hidden"
                  onChange={(event) => {
                    setImportFile(event.target.files?.[0] || null);
                    setImportResult(null);
                    setImportFilePreview(null);
                  }}
                />
                <button type="button" onClick={(event) => viewSelectedCsv(event.currentTarget)} disabled={!importFile || busy} className="mt-3 text-sm font-semibold text-[#7D001D] underline underline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:text-rose-300">
                  View selected file
                </button>
              </>
            )}

            {importResult && !importResult.completed && (
              <>
                <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                  {[
                    ['Students Found', importResult.summary?.students_found, 'text-slate-700 dark:text-slate-200'],
                    ['Ready', importResult.summary?.ready, 'text-emerald-600'],
                    ['Duplicates', importResult.summary?.duplicates, 'text-amber-600'],
                    ['Invalid', importResult.summary?.invalid, 'text-rose-600'],
                  ].map(([label, value, color]) => (
                    <div key={label} className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                      <p className={`text-2xl font-black ${color}`}>{value || 0}</p>
                      <p className="mt-1 text-[10px] font-black uppercase tracking-wide text-slate-400">{label}</p>
                    </div>
                  ))}
                </div>
                <div className="mt-5 overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-700">
                  <div className="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:border-gray-700 dark:bg-gray-900">
                    File: <b className="text-slate-700 dark:text-slate-200">{importResult.file_name}</b> · Section: <b className="text-slate-700 dark:text-slate-200">{importResult.section?.name}</b>
                  </div>
                  <div className="max-h-80 overflow-auto">
                    <table className="w-full min-w-[820px] text-left">
                      <thead className="sticky top-0 bg-white text-[10px] uppercase tracking-wide text-slate-400 dark:bg-gray-800">
                        <tr>
                          <th className="px-4 py-3">School No.</th>
                          <th className="px-4 py-3">First Name</th>
                          <th className="px-4 py-3">Middle Name</th>
                          <th className="px-4 py-3">Last Name</th>
                          <th className="px-4 py-3">Status</th>
                          <th className="px-4 py-3">Details</th>
                        </tr>
                      </thead>
                      <tbody>
                        {importResult.students?.map((student) => {
                          const statusStyle = student.status === 'Ready' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : student.status === 'Duplicate' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300';
                          return (
                            <tr key={`${student.row}-${student.school_no}`} className="border-t border-slate-100 dark:border-gray-700">
                              <td className="px-4 py-3 text-sm font-black text-slate-800 dark:text-white">{student.school_no || '—'}</td>
                              <td className="px-4 py-3 text-sm text-slate-700 dark:text-gray-200">{student.first_name || '—'}</td>
                              <td className="px-4 py-3 text-sm text-slate-700 dark:text-gray-200">{student.middle_name || '—'}</td>
                              <td className="px-4 py-3 text-sm text-slate-700 dark:text-gray-200">{student.last_name || '—'}</td>
                              <td className="px-4 py-3">
                                <span className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${statusStyle}`}>{student.status}</span>
                              </td>
                              <td className="px-4 py-3 text-xs text-slate-400">{student.reason || 'Ready to import'}</td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              </>
            )}

            {importResult?.completed && (
              <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center dark:border-emerald-900 dark:bg-emerald-950/30">
                <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                  <FiCheck />
                </div>
                <h3 className="mt-4 text-lg font-black text-emerald-800 dark:text-emerald-200">{importResult.message}</h3>
                <p className="mt-2 text-sm text-emerald-700 dark:text-emerald-300">{importResult.detail}</p>
                {importResult.skipped > 0 && <p className="mt-1 text-xs text-slate-500">{importResult.skipped} duplicate or invalid record(s) were skipped.</p>}
              </div>
            )}

          </div>
        </Modal>
      )}
      {importFilePreview && (
        <Modal title={`Selected CSV: ${importFilePreview.name}`} subtitle="Review the selected file before previewing the import." onClose={closeImportFilePreview}>
          <div className="p-4 sm:p-5">
            <pre className="max-h-[60dvh] overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-5 text-slate-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">{importFilePreview.content}</pre>
          </div>
        </Modal>
      )}
      {requirementsOpen && (
        <RequirementManagerModal
          onClose={() => setRequirementsOpen(false)}
          onChanged={() => {
            fetchStudents();
            if (selected) viewStudent(selected.id);
          }}
        />
      )}
      {deleteTarget && (
        <Modal
          title="Delete Student"
          subtitle="This action cannot be undone."
          onClose={() => !busy && setDeleteTarget(null)}
          size="confirm"
        >
          <div className="p-5">
            <div className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/70 dark:bg-rose-950/30">
              <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                <FiAlertTriangle className="text-lg" />
              </span>
              <div>
                <p className="font-black text-slate-800 dark:text-white">Delete {deleteTarget.first_name} {deleteTarget.last_name}?</p>
                <p className="mt-1.5 text-sm leading-6 text-slate-600 dark:text-slate-300">The student account and all related internship records will be permanently deleted.</p>
              </div>
            </div>
          </div>
          <div className="flex flex-col-reverse gap-2.5 border-t border-slate-100 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
            <button type="button" onClick={() => setDeleteTarget(null)} disabled={busy} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 disabled:opacity-50 dark:border-gray-600 dark:text-slate-200">
              Cancel
            </button>
            <button type="button" onClick={deleteStudent} disabled={busy} className="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-black text-white transition hover:bg-rose-700 disabled:opacity-50">
              <FiTrash2 /> {busy ? 'Deleting...' : 'Delete Student'}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

export default StudentManagement;
