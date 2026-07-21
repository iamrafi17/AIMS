import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  FiCheck,
  FiChevronLeft,
  FiChevronRight,
  FiDownload,
  FiEdit2,
  FiEye,
  FiFileText,
  FiPlus,
  FiSearch,
  FiTrash2,
  FiUpload,
  FiUserCheck,
  FiUsers,
  FiX,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';

const emptyForm = {
  student_id: '', first_name: '', middle_name: '', last_name: '', email: '', password: '', gender: 'female', birth_date: '', address: '', phone: '',
  college_id: '', program_id: '', year_level: 4, section: '', parent_name: '', parent_relationship: 'Parent', parent_address: '', parent_phone: '', hte_id: '',
  internship_semester: '', internship_year: '', registration_status: 'pending', internship_status: 'pending', consent_status: 'pending', schedule_status: 'pending',
  ojt_start_date: '', ojt_end_date: '', required_ojt_hours: 486, allow_past_attendance: false,
};

const badge = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', completed: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300', dropped: 'bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300',
};

function StatusBadge({ value }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${badge[value] || badge.pending}`}>{value || 'pending'}</span>;
}

function Modal({ title, subtitle, onClose, children, size = 'default' }) {
  const maxWidths = { default: '42rem', form: '60rem', wide: '72rem' };

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previousOverflow; };
  }, []);

  return <div className="fixed inset-0 z-[80] flex items-center justify-center overflow-hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-4"><div role="dialog" aria-modal="true" aria-label={title} style={{ maxWidth: maxWidths[size] || maxWidths.default, maxHeight: 'calc(100vh - 1.5rem)' }} className="flex w-full flex-col overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"><div className="flex shrink-0 items-start justify-between border-b border-slate-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800"><div className="min-w-0 pr-4"><h2 className="truncate text-lg font-black text-[#430909] dark:text-white">{title}</h2>{subtitle && <p className="mt-1 text-xs text-slate-400">{subtitle}</p>}</div><button type="button" onClick={onClose} className="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-700" aria-label="Close dialog"><FiX /></button></div><div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">{children}</div></div></div>;
}

function Input({ label, name, form, setForm, type = 'text', required = false, ...props }) {
  return <label><span className="mb-1.5 block text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span><input type={type} name={name} value={form[name] ?? ''} onChange={(event) => setForm((current) => ({ ...current, [name]: event.target.value }))} required={required} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 focus:ring-4 focus:ring-[#800000]/5 dark:border-gray-600 dark:bg-gray-900 dark:text-white" {...props} /></label>;
}

function Select({ label, name, form, setForm, children, required = false, ...props }) {
  return <label><span className="mb-1.5 block text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{label}{required && ' *'}</span><select name={name} value={form[name] ?? ''} onChange={(event) => setForm((current) => ({ ...current, [name]: event.target.value }))} required={required} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-[#800000]/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white" {...props}>{children}</select></label>;
}

function Progress({ value, color = 'bg-[#800000]' }) {
  return <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-700"><div className={`h-full rounded-full ${color}`} style={{ width: `${Math.min(Math.max(Number(value) || 0, 0), 100)}%` }} /></div>;
}

function StudentManagement() {
  const [students, setStudents] = useState([]);
  const [summary, setSummary] = useState({});
  const [options, setOptions] = useState({ colleges: [], htes: [] });
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [internshipStatus, setInternshipStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [editor, setEditor] = useState(null);
  const [editorStep, setEditorStep] = useState(0);
  const [form, setForm] = useState(emptyForm);
  const [selected, setSelected] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importResult, setImportResult] = useState(null);
  const [busy, setBusy] = useState(false);
  const importInput = useRef(null);

  const fetchStudents = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/coordinator/students', { params: { search, status, internship_status: internshipStatus, page } });
      setStudents(response.data.data || []); setSummary(response.data.summary || {}); setLastPage(response.data.last_page || 1); setTotal(response.data.total || 0);
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to load students.'); } finally { setLoading(false); }
  }, [internshipStatus, page, search, status]);

  useEffect(() => { api.get('/coordinator/students/options').then((response) => setOptions(response.data)).catch(() => toast.error('Unable to load form options.')); }, []);
  useEffect(() => { const timer = window.setTimeout(fetchStudents, 250); return () => window.clearTimeout(timer); }, [fetchStudents]);
  useEffect(() => { setPage(1); }, [search, status, internshipStatus]);

  const programs = useMemo(() => options.colleges?.find((college) => String(college.id) === String(form.college_id))?.programs || [], [options, form.college_id]);
  const errorMessage = (error, fallback) => { const errors = error.response?.data?.errors; return errors ? Object.values(errors)[0]?.[0] : error.response?.data?.message || fallback; };

  const openCreate = () => { setForm(emptyForm); setEditorStep(0); setEditor({ mode: 'create' }); };
  const openEdit = (student) => {
    setForm({ ...emptyForm, ...student, email: student.user?.email || '', password: '', college_id: student.college_id || student.college?.id || '', program_id: student.program_id || student.program?.id || '', hte_id: student.hte_id || student.hte?.id || '', birth_date: student.birth_date?.slice(0, 10) || '', ojt_start_date: student.ojt_start_date?.slice(0, 10) || '', ojt_end_date: student.ojt_end_date?.slice(0, 10) || '' });
    setSelected(null);
    setEditorStep(0);
    setEditor({ mode: 'edit', student });
  };

  const nextEditorStep = () => {
    const requiredByStep = [
      ['student_id', 'first_name', 'last_name', 'email', 'gender', 'birth_date', 'phone', 'address'],
      ['college_id', 'program_id', 'year_level', 'section', 'required_ojt_hours'],
      ['parent_name', 'parent_phone', 'parent_address'],
    ];
    const missing = requiredByStep[editorStep].some((field) => String(form[field] ?? '').trim() === '');
    if (missing) return toast.error('Please complete all required fields on this step.');
    setEditorStep((current) => Math.min(current + 1, 2));
  };

  const saveStudent = async (event) => {
    event.preventDefault(); setBusy(true);
    try {
      const payload = { ...form, college_id: Number(form.college_id), program_id: Number(form.program_id), hte_id: form.hte_id ? Number(form.hte_id) : null, year_level: Number(form.year_level), required_ojt_hours: Number(form.required_ojt_hours), allow_past_attendance: Boolean(form.allow_past_attendance) };
      if (!payload.password) delete payload.password;
      const response = editor.mode === 'create' ? await api.post('/coordinator/students', payload) : await api.put(`/coordinator/students/${editor.student.id}`, payload);
      toast.success(response.data.message);
      if (response.data.temporary_password) toast(`Temporary password: ${response.data.temporary_password}`, { icon: '🔑', duration: 12000 });
      setEditor(null); fetchStudents();
    } catch (error) { toast.error(errorMessage(error, 'Unable to save student.')); } finally { setBusy(false); }
  };

  const viewStudent = async (id) => {
    try { const response = await api.get(`/coordinator/students/${id}`); setSelected(response.data); } catch (error) { toast.error(errorMessage(error, 'Unable to load student details.')); }
  };

  const decideRegistration = async (student, decision) => {
    let reason = '';
    if (decision === 'reject') { reason = window.prompt('Reason for rejecting this registration:') || ''; if (!reason.trim()) return; }
    setBusy(true);
    try { const response = await api.post(`/coordinator/students/${student.id}/${decision}`, decision === 'reject' ? { reason } : {}); toast.success(response.data.message); await fetchStudents(); if (selected?.id === student.id) viewStudent(student.id); } catch (error) { toast.error(errorMessage(error, 'Unable to review registration.')); } finally { setBusy(false); }
  };

  const reviewRequirement = async (requirement, decision) => {
    let feedback = '';
    if (decision === 'rejected') { feedback = window.prompt('Feedback required for rejection:') || ''; if (!feedback.trim()) return; }
    setBusy(true);
    try { const response = await api.post(`/coordinator/students/${selected.id}/requirements/${requirement.id}/review`, { decision, feedback: feedback || null }); toast.success(response.data.message); viewStudent(selected.id); fetchStudents(); } catch (error) { toast.error(errorMessage(error, 'Unable to review requirement.')); } finally { setBusy(false); }
  };

  const downloadRequirement = async (requirement) => {
    try { const response = await api.get(`/coordinator/students/${selected.id}/requirements/${requirement.id}/download`, { responseType: 'blob' }); const url = URL.createObjectURL(response.data); const link = document.createElement('a'); link.href = url; link.download = requirement.file_path?.split('/').pop() || `${requirement.requirement_name}.file`; link.click(); URL.revokeObjectURL(url); } catch { toast.error('Unable to download the requirement file.'); }
  };

  const deleteStudent = async (student) => {
    if (!window.confirm(`Delete ${student.first_name} ${student.last_name}? This also deletes the login account, attendance, and submitted records.`)) return;
    try { const response = await api.delete(`/coordinator/students/${student.id}`); toast.success(response.data.message); setSelected(null); fetchStudents(); } catch (error) { toast.error(errorMessage(error, 'Unable to delete student.')); }
  };

  const importCsv = async () => {
    if (!importFile) return toast.error('Choose a CSV file first.');
    const payload = new FormData(); payload.append('file', importFile); setBusy(true);
    try { const response = await api.post('/coordinator/students/import', payload, { headers: { 'Content-Type': 'multipart/form-data' } }); setImportResult(response.data); toast.success(response.data.message); fetchStudents(); } catch (error) { toast.error(errorMessage(error, 'CSV import failed.')); } finally { setBusy(false); }
  };

  const downloadTemplate = () => {
    const headers = 'student_id,first_name,middle_name,last_name,email,password,gender,birth_date,address,phone,college_code,program_code,year_level,section,parent_name,parent_relationship,parent_address,parent_phone,hte_name,internship_semester,internship_year,registration_status,internship_status\n';
    const sample = '2026-0002,Juan,,Dela Cruz,juan.delacruz@example.com,ChangeMe123!,male,2002-01-01,"Santa Cruz, Marinduque",09170000000,CICS,BSIT,4,A,Parent Name,Parent,"Santa Cruz, Marinduque",09180000000,Tech Solutions Inc.,First Semester,2026-2027,pending,pending\n';
    const url = URL.createObjectURL(new Blob([headers + sample], { type: 'text/csv' })); const link = document.createElement('a'); link.href = url; link.download = 'aims-student-import-template.csv'; link.click(); URL.revokeObjectURL(url);
  };

  const downloadCredentials = () => {
    const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const rows = importResult.credentials.map((credential) => [credential.student_id, credential.email, credential.temporary_password].map(quote).join(','));
    const url = URL.createObjectURL(new Blob([`student_id,email,temporary_password\n${rows.join('\n')}\n`], { type: 'text/csv' }));
    const link = document.createElement('a'); link.href = url; link.download = 'aims-imported-student-credentials.csv'; link.click(); URL.revokeObjectURL(url);
  };

  return <div className="space-y-6">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-extrabold uppercase tracking-[0.16em] text-[#a8750b]">Coordinator Portal</p><h1 className="mt-1 text-2xl font-black text-[#430909] dark:text-white">Student Management</h1><p className="mt-1 text-sm text-slate-400">Records, approvals, requirements, and internship progress</p></div><div className="flex gap-2"><button onClick={() => { setImportOpen(true); setImportResult(null); setImportFile(null); }} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-[#800000] dark:border-gray-700 dark:bg-gray-800 dark:text-rose-300"><FiUpload /> Import CSV</button><button onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-[#800000] px-4 py-2.5 text-xs font-black text-white"><FiPlus /> Create Student</button></div></div>

    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">{[
      ['Total Students', summary.total, FiUsers, 'text-[#800000]'], ['Pending Review', summary.pending, FiFileText, 'text-amber-600'], ['Approved', summary.approved, FiCheck, 'text-emerald-600'], ['Rejected', summary.rejected, FiX, 'text-rose-600'], ['Active Interns', summary.active, FiUserCheck, 'text-blue-600'],
    ].map(([label, value, Icon, color]) => <div key={label} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="flex items-center gap-3"><div className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 dark:bg-gray-700"><Icon className={color} /></div><div><p className="text-2xl font-black text-[#430909] dark:text-white">{value || 0}</p><p className="text-[10px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p></div></div></div>)}</div>

    <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div className="grid gap-3 lg:grid-cols-[1fr_180px_180px]"><label className="relative"><FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, student ID, or email..." className="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-3 text-sm outline-none dark:border-gray-600 dark:bg-gray-900" /></label><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-slate-200 px-3 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="">All registrations</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><select value={internshipStatus} onChange={(event) => setInternshipStatus(event.target.value)} className="rounded-xl border border-slate-200 px-3 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="">All internships</option><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option><option value="dropped">Dropped</option></select></div></section>

    <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="overflow-x-auto"><table className="w-full min-w-[1050px] text-left"><thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 dark:bg-gray-900"><tr><th className="px-5 py-4 font-extrabold">Student</th><th className="px-4 py-4 font-extrabold">Academic / HTE</th><th className="px-4 py-4 font-extrabold">Registration</th><th className="px-4 py-4 font-extrabold">OJT Progress</th><th className="px-4 py-4 font-extrabold">Requirements</th><th className="px-5 py-4 text-right font-extrabold">Actions</th></tr></thead><tbody>{loading ? <tr><td colSpan="6" className="py-16 text-center text-slate-400">Loading students...</td></tr> : students.length ? students.map((student) => <tr key={student.id} className="border-t border-slate-100 hover:bg-slate-50/60 dark:border-gray-700 dark:hover:bg-gray-900/50"><td className="px-5 py-4"><p className="text-sm font-black text-slate-800 dark:text-white">{student.last_name}, {student.first_name}</p><p className="mt-1 text-xs text-slate-400">{student.student_id} · {student.user?.email}</p></td><td className="px-4 py-4"><p className="text-xs font-bold text-slate-700 dark:text-gray-200">{student.program?.code} · Year {student.year_level}-{student.section}</p><p className="mt-1 max-w-48 truncate text-[11px] text-slate-400">{student.hte?.name || 'No HTE assigned'}</p></td><td className="px-4 py-4"><StatusBadge value={student.registration_status} /><div className="mt-1.5"><StatusBadge value={student.internship_status} /></div></td><td className="w-48 px-4 py-4"><div className="mb-1.5 flex justify-between text-[11px]"><span className="font-bold text-slate-500">{student.progress?.rendered_hours || 0}/{student.progress?.required_hours || 486}h</span><span className="font-black text-[#800000] dark:text-rose-300">{student.progress?.percent || 0}%</span></div><Progress value={student.progress?.percent} /><p className="mt-1.5 text-[10px] text-slate-400">{student.progress?.attendance_days || 0} days · {student.progress?.journal_entries || 0} journals</p></td><td className="w-40 px-4 py-4"><div className="mb-1.5 flex justify-between text-[11px]"><span className="text-slate-500">{student.progress?.requirements_approved || 0}/{student.progress?.requirements_total || 0}</span><span className="font-black text-blue-600">{student.progress?.requirements_percent || 0}%</span></div><Progress value={student.progress?.requirements_percent} color="bg-blue-500" /></td><td className="px-5 py-4"><div className="flex justify-end gap-1.5">{student.registration_status === 'pending' && <><button onClick={() => decideRegistration(student, 'approve')} className="rounded-lg bg-emerald-100 p-2 text-emerald-700" title="Approve"><FiCheck /></button><button onClick={() => decideRegistration(student, 'reject')} className="rounded-lg bg-rose-100 p-2 text-rose-700" title="Reject"><FiX /></button></>}<button onClick={() => viewStudent(student.id)} className="rounded-lg bg-blue-100 p-2 text-blue-700" title="Review"><FiEye /></button><button onClick={() => openEdit(student)} className="rounded-lg bg-amber-100 p-2 text-amber-700" title="Edit"><FiEdit2 /></button><button onClick={() => deleteStudent(student)} className="rounded-lg bg-rose-100 p-2 text-rose-700" title="Delete"><FiTrash2 /></button></div></td></tr>) : <tr><td colSpan="6" className="py-16 text-center text-slate-400">No student records match your filters.</td></tr>}</tbody></table></div>
      <div className="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-xs dark:border-gray-700"><p className="text-slate-400">{total} record(s) · Page {page} of {lastPage}</p><div className="flex gap-2"><button onClick={() => setPage((current) => Math.max(current - 1, 1))} disabled={page === 1} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600"><FiChevronLeft /></button><button onClick={() => setPage((current) => Math.min(current + 1, lastPage))} disabled={page === lastPage} className="rounded-lg border border-slate-200 p-2 disabled:opacity-30 dark:border-gray-600"><FiChevronRight /></button></div></div>
    </section>

    {editor && <Modal title={editor.mode === 'create' ? 'Create Student Record' : 'Update Student Record'} subtitle="Complete the three short steps below. No browser zooming needed." onClose={() => setEditor(null)} size="form">
      <form onSubmit={saveStudent}>
        <div className="border-b border-slate-100 bg-slate-50/80 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/50">
          <div className="mx-auto flex max-w-2xl items-center">
            {['Personal & Account', 'Academic & OJT', 'Guardian & Status'].map((label, index) => <div key={label} className="flex min-w-0 flex-1 items-center last:flex-none">
              <div className="flex min-w-0 items-center gap-2">
                <span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-black ${index <= editorStep ? 'bg-[#800000] text-white' : 'bg-slate-200 text-slate-500 dark:bg-gray-700'}`}>{index + 1}</span>
                <span className={`hidden truncate text-[11px] font-black sm:block ${index === editorStep ? 'text-[#800000] dark:text-rose-300' : 'text-slate-400'}`}>{label}</span>
              </div>
              {index < 2 && <span className={`mx-3 h-0.5 min-w-4 flex-1 ${index < editorStep ? 'bg-[#800000]' : 'bg-slate-200 dark:bg-gray-700'}`} />}
            </div>)}
          </div>
        </div>

        <div className="p-5 sm:p-6">
          {editorStep === 0 && <>
            <h3 className="mb-4 text-sm font-black text-[#800000] dark:text-rose-300">Personal & Account Information</h3>
            <div className="grid gap-3 md:grid-cols-3">
              <Input label="Student ID" name="student_id" form={form} setForm={setForm} required />
              <Input label="First Name" name="first_name" form={form} setForm={setForm} required />
              <Input label="Middle Name" name="middle_name" form={form} setForm={setForm} />
              <Input label="Last Name" name="last_name" form={form} setForm={setForm} required />
              <Input label="Email" name="email" type="email" form={form} setForm={setForm} required />
              <Input label={editor.mode === 'create' ? 'Password (blank = generated)' : 'New Password (optional)'} name="password" type="password" form={form} setForm={setForm} />
              <Select label="Gender" name="gender" form={form} setForm={setForm} required><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></Select>
              <Input label="Birth Date" name="birth_date" type="date" form={form} setForm={setForm} required />
              <Input label="Contact Number" name="phone" form={form} setForm={setForm} required />
              <div className="md:col-span-3"><Input label="Address" name="address" form={form} setForm={setForm} required /></div>
            </div>
          </>}

          {editorStep === 1 && <>
            <h3 className="mb-4 text-sm font-black text-[#800000] dark:text-rose-300">Academic & Internship Information</h3>
            <div className="grid gap-3 md:grid-cols-3">
              <Select label="College" name="college_id" form={form} setForm={(updater) => setForm((current) => ({ ...updater(current), program_id: '' }))} required><option value="">Select college</option>{options.colleges?.map((college) => <option key={college.id} value={college.id}>{college.code} — {college.name}</option>)}</Select>
              <Select label="Program" name="program_id" form={form} setForm={setForm} required><option value="">Select program</option>{programs.map((program) => <option key={program.id} value={program.id}>{program.code} — {program.name}</option>)}</Select>
              <Select label="HTE" name="hte_id" form={form} setForm={setForm}><option value="">Not assigned</option>{options.htes?.map((hte) => <option key={hte.id} value={hte.id}>{hte.name}</option>)}</Select>
              <Select label="Year Level" name="year_level" form={form} setForm={setForm} required>{[1, 2, 3, 4, 5].map((year) => <option key={year} value={year}>{year}</option>)}</Select>
              <Input label="Section" name="section" form={form} setForm={setForm} required />
              <Input label="Required OJT Hours" name="required_ojt_hours" type="number" min="1" form={form} setForm={setForm} required />
              <Input label="Semester" name="internship_semester" form={form} setForm={setForm} />
              <Input label="Academic Year" name="internship_year" placeholder="2026-2027" form={form} setForm={setForm} />
              <div className="hidden md:block" />
              <Input label="OJT Start Date" name="ojt_start_date" type="date" form={form} setForm={setForm} />
              <Input label="OJT End Date" name="ojt_end_date" type="date" form={form} setForm={setForm} />
            </div>
          </>}

          {editorStep === 2 && <>
            <h3 className="mb-4 text-sm font-black text-[#800000] dark:text-rose-300">Guardian & Record Status</h3>
            <div className="grid gap-3 md:grid-cols-3">
              <Select label="Registration Status" name="registration_status" form={form} setForm={setForm}><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></Select>
              <Select label="Internship Status" name="internship_status" form={form} setForm={setForm}><option value="pending">Pending</option><option value="active">Active</option><option value="completed">Completed</option><option value="dropped">Dropped</option></Select>
              <Select label="Consent Status" name="consent_status" form={form} setForm={setForm}><option value="pending">Pending</option><option value="done">Done</option><option value="rejected">Rejected</option></Select>
              <Select label="Schedule Status" name="schedule_status" form={form} setForm={setForm}><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></Select>
              <label className="flex items-center gap-3 self-end rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold dark:border-gray-600"><input type="checkbox" checked={Boolean(form.allow_past_attendance)} onChange={(event) => setForm((current) => ({ ...current, allow_past_attendance: event.target.checked }))} /> Allow past attendance</label>
              <div className="hidden md:block" />
              <Input label="Guardian Name" name="parent_name" form={form} setForm={setForm} required />
              <Input label="Relationship" name="parent_relationship" form={form} setForm={setForm} />
              <Input label="Contact Number" name="parent_phone" form={form} setForm={setForm} required />
              <div className="md:col-span-3"><Input label="Guardian Address" name="parent_address" form={form} setForm={setForm} required /></div>
            </div>
          </>}
        </div>

        <div className="flex shrink-0 items-center justify-between border-t border-slate-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
          <button type="button" onClick={() => editorStep === 0 ? setEditor(null) : setEditorStep((current) => current - 1)} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold dark:border-gray-600">{editorStep === 0 ? 'Cancel' : 'Previous'}</button>
          <span className="text-xs font-bold text-slate-400">Step {editorStep + 1} of 3</span>
          {editorStep < 2
            ? <button type="button" onClick={nextEditorStep} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white">Next</button>
            : <button type="submit" disabled={busy} className="rounded-xl bg-[#800000] px-6 py-2.5 text-sm font-black text-white disabled:opacity-50">{busy ? 'Saving...' : editor.mode === 'create' ? 'Create Student' : 'Save Changes'}</button>}
        </div>
      </form>
    </Modal>}

    {selected && <Modal title={`${selected.first_name} ${selected.last_name}`} subtitle={`${selected.student_id} · ${selected.user?.email}`} onClose={() => setSelected(null)} size="wide"><div className="grid gap-6 p-6 lg:grid-cols-3"><div className="space-y-5"><div className="rounded-2xl bg-slate-50 p-5 dark:bg-gray-900"><div className="flex flex-wrap gap-2"><StatusBadge value={selected.registration_status} /><StatusBadge value={selected.internship_status} /></div>{selected.registration_feedback && <p className="mt-3 text-xs leading-5 text-rose-600">Review feedback: {selected.registration_feedback}</p>}{selected.registration_status === 'pending' && <div className="mt-4 grid grid-cols-2 gap-2"><button onClick={() => decideRegistration(selected, 'approve')} className="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white">Approve</button><button onClick={() => decideRegistration(selected, 'reject')} className="rounded-xl bg-rose-600 px-3 py-2 text-xs font-black text-white">Reject</button></div>}</div><div><div className="mb-2 flex justify-between text-xs"><span>OJT Progress</span><b>{selected.progress?.percent || 0}%</b></div><Progress value={selected.progress?.percent} /><p className="mt-2 text-xs text-slate-400">{selected.progress?.rendered_hours || 0} / {selected.progress?.required_hours || 486} hours · {selected.progress?.attendance_days || 0} attendance days</p></div><div><div className="mb-2 flex justify-between text-xs"><span>Requirements</span><b>{selected.progress?.requirements_percent || 0}%</b></div><Progress value={selected.progress?.requirements_percent} color="bg-blue-500" /><p className="mt-2 text-xs text-slate-400">{selected.progress?.requirements_approved || 0} of {selected.progress?.requirements_total || 0} approved</p></div><button onClick={() => openEdit(selected)} className="w-full rounded-xl border border-slate-200 py-3 text-sm font-black text-[#800000] dark:border-gray-600 dark:text-rose-300">Edit Student Record</button></div><div className="lg:col-span-2"><div className="mb-4 flex items-center justify-between"><h3 className="font-black text-[#430909] dark:text-white">Student Requirements</h3><span className="text-xs text-slate-400">{selected.requirements?.length || 0} record(s)</span></div>{selected.requirements?.length ? <div className="space-y-3">{selected.requirements.map((requirement) => <div key={requirement.id} className="rounded-2xl border border-slate-200 p-4 dark:border-gray-700"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-sm font-black text-slate-800 dark:text-white">{requirement.requirement_name}</p><p className="mt-1 text-[11px] text-slate-400">{requirement.file_type?.toUpperCase()} · Submitted {new Date(requirement.created_at).toLocaleDateString()}</p>{requirement.feedback && <p className="mt-2 text-xs text-rose-600">Feedback: {requirement.feedback}</p>}</div><StatusBadge value={requirement.status} /></div><div className="mt-4 flex flex-wrap gap-2"><button onClick={() => downloadRequirement(requirement)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-gray-600"><FiDownload /> Download</button><button onClick={() => reviewRequirement(requirement, 'approved')} disabled={busy} className="rounded-lg bg-emerald-100 px-3 py-2 text-xs font-black text-emerald-700">Approve</button><button onClick={() => reviewRequirement(requirement, 'rejected')} disabled={busy} className="rounded-lg bg-rose-100 px-3 py-2 text-xs font-black text-rose-700">Reject</button></div></div>)}</div> : <div className="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-gray-600">No requirements submitted.</div>}</div></div></Modal>}

    {importOpen && <Modal title="Import Students via CSV" subtitle="Create multiple student records and login accounts in one upload." onClose={() => setImportOpen(false)}><div className="p-6"><button onClick={downloadTemplate} className="inline-flex items-center gap-2 text-xs font-black text-[#800000] dark:text-rose-300"><FiDownload /> Download CSV Template</button><button onClick={() => importInput.current?.click()} className="mt-5 flex w-full flex-col items-center rounded-2xl border-2 border-dashed border-slate-300 p-8 text-center hover:border-[#800000]/40 dark:border-gray-600"><FiUpload className="text-3xl text-[#800000] dark:text-rose-300" /><p className="mt-3 text-sm font-black">{importFile?.name || 'Choose a CSV file'}</p><p className="mt-1 text-xs text-slate-400">Maximum file size: 5 MB</p></button><input ref={importInput} type="file" accept=".csv,text/csv" className="hidden" onChange={(event) => { setImportFile(event.target.files?.[0] || null); setImportResult(null); }} />{importResult && <div className="mt-5 rounded-2xl bg-slate-50 p-4 dark:bg-gray-900"><p className="text-sm font-black text-slate-800 dark:text-white">{importResult.message}</p>{importResult.errors?.length > 0 && <div className="mt-3 max-h-36 overflow-y-auto text-xs text-rose-600">{importResult.errors.map((error) => <p key={`${error.row}-${error.student_id}`}>Row {error.row} ({error.student_id || 'unknown'}): {error.message}</p>)}</div>}{importResult.credentials?.length > 0 && <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/40"><p className="text-xs text-amber-800 dark:text-amber-200">Temporary credentials were generated for {importResult.credentials.length} record(s). Download them before closing this dialog.</p><button onClick={downloadCredentials} className="mt-2 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-xs font-black text-white"><FiDownload /> Download Credentials CSV</button></div>}</div>}<div className="mt-6 flex justify-end gap-3"><button onClick={() => setImportOpen(false)} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold dark:border-gray-600">Close</button><button onClick={importCsv} disabled={busy || !importFile} className="rounded-xl bg-[#800000] px-5 py-3 text-sm font-black text-white disabled:opacity-50">{busy ? 'Importing...' : 'Import Students'}</button></div></div></Modal>}
  </div>;
}

export default StudentManagement;
