import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  FiArrowLeft,
  FiArrowRight,
  FiBookOpen,
  FiBriefcase,
  FiCheck,
  FiCheckCircle,
  FiEye,
  FiEyeOff,
  FiKey,
  FiShield,
  FiUser,
  FiUsers,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import api from '../../services/api';
import useAuth from '../../context/useAuth';
import marsuLogo from '../../assets/marsu-logo.png';
import ContactNumberInput from '../../components/forms/ContactNumberInput';
import { getPasswordStrength } from '../../utils/passwordStrength';

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#7D001D]/45 focus:ring-4 focus:ring-[#7D001D]/8 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

const steps = [
  { number: 1, title: 'Personal', description: 'Tell us about yourself', icon: FiUser },
  { number: 2, title: 'Academic', description: 'Your university program', icon: FiBookOpen },
  { number: 3, title: 'Guardian', description: 'Emergency contact details', icon: FiUsers },
  { number: 4, title: 'Internship', description: 'Placement preferences', icon: FiBriefcase },
  { number: 5, title: 'Consent', description: 'Terms and data privacy', icon: FiShield },
  { number: 6, title: 'Account', description: 'Create your credentials', icon: FiKey },
];

function Field({ label, hint, htmlFor, className = '', children }) {
  return (
    <div className={className}>
      <div className="mb-2 flex items-center justify-between gap-3">
        <label htmlFor={htmlFor} className="block text-sm font-extrabold text-slate-700">{label}</label>
        {hint && <span className="text-xs font-medium text-slate-400">{hint}</span>}
      </div>
      {children}
    </div>
  );
}

function StepHeader({ eyebrow, title, description }) {
  return (
    <div className="mb-7 border-b border-slate-100 pb-6">
      <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-[#a8750b]">{eyebrow}</p>
      <h2 className="mt-2 text-2xl font-black tracking-tight text-[#690016] sm:text-3xl">{title}</h2>
      <p className="mt-2 text-sm leading-6 text-slate-500">{description}</p>
    </div>
  );
}

function RegisterPage() {
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(false);
  const [enrollmentLoading, setEnrollmentLoading] = useState(false);
  const [enrollmentVerified, setEnrollmentVerified] = useState(false);
  const [dataLoading, setDataLoading] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [colleges, setColleges] = useState([]);
  const [programs, setPrograms] = useState([]);
  const [htes, setHtes] = useState([]);
  const [securityQuestions, setSecurityQuestions] = useState([]);
  const { register } = useAuth();
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    student_id: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    gender: '',
    birth_date: '',
    address: '',
    contact_number: '',
    email: '',
    college_id: '',
    program_id: '',
    year_level: '',
    section: '',
    parent_name: '',
    parent_relationship: '',
    parent_address: '',
    parent_contact_number: '',
    hte_id: '',
    internship_semester: '',
    internship_year: '',
    agree_terms: false,
    agree_privacy: false,
    password: '',
    password_confirmation: '',
    security_questions: [
      { security_question_id: '', answer: '' },
      { security_question_id: '', answer: '' },
      { security_question_id: '', answer: '' },
    ],
  });

  useEffect(() => {
    Promise.all([
      api.get('/colleges'),
      api.get('/security-questions'),
    ])
      .then(([collegeResponse, questionResponse]) => {
        setColleges(collegeResponse.data);
        setSecurityQuestions(questionResponse.data.questions || []);
      })
      .catch(() => toast.error('Unable to load registration options. Please refresh the page.'))
      .finally(() => setDataLoading(false));
  }, []);

  useEffect(() => {
    if (!formData.college_id) {
      setPrograms([]);
      setHtes([]);
      return;
    }

    Promise.all([
      api.get(`/colleges/${formData.college_id}/programs`),
      api.get(`/colleges/${formData.college_id}/htes`, { params: { program_id: formData.program_id || undefined } }),
    ])
      .then(([programResponse, hteResponse]) => {
        setPrograms(programResponse.data);
        setHtes(hteResponse.data);
      })
      .catch(() => toast.error('Unable to load programs or partner HTEs.'));
  }, [formData.college_id, formData.program_id]);

  const handleChange = (event) => {
    const { name, value, type, checked } = event.target;

    setFormData((current) => ({
      ...current,
      ...(name === 'college_id' ? { program_id: '', hte_id: '' } : {}),
      ...(name === 'student_id' ? { first_name: '', middle_name: '', last_name: '', year_level: '', section: '', college_id: '', program_id: '', hte_id: '', internship_semester: '', internship_year: '' } : {}),
      [name]: type === 'checkbox' ? checked : value,
    }));
    if (name === 'student_id') setEnrollmentVerified(false);
  };

  const updateSecurityQuestion = (index, field, value) => {
    setFormData((current) => ({
      ...current,
      security_questions: current.security_questions.map((item, itemIndex) => (
        itemIndex === index ? { ...item, [field]: value } : item
      )),
    }));
  };

  const verifyEnrollment = async () => {
    const schoolId = formData.student_id.trim();
    if (!schoolId) return toast.error('Enter your School No. first.');

    setEnrollmentLoading(true);
    setEnrollmentVerified(false);
    try {
      const response = await api.get(`/registration/enrollment/${encodeURIComponent(schoolId)}`);
      setFormData((current) => ({
        ...current,
        student_id: response.data.student_id,
        first_name: response.data.first_name,
        last_name: response.data.last_name,
        year_level: response.data.academic_section?.year_level || '',
        section: response.data.section,
        college_id: response.data.college?.id || '',
        program_id: response.data.program?.id || '',
        internship_semester: response.data.internship_semester || '',
        internship_year: response.data.internship_year || '',
        hte_id: '',
      }));
      setEnrollmentVerified(true);
      toast.success('Student found in the official student list.');
    } catch (error) {
      setFormData((current) => ({ ...current, first_name: '', middle_name: '', last_name: '', year_level: '', section: '', college_id: '', program_id: '', hte_id: '', internship_semester: '', internship_year: '' }));
      toast.error(error.response?.data?.message || 'Your School No. was not found in the official student list.');
    } finally {
      setEnrollmentLoading(false);
    }
  };

  const nextStep = (event) => {
    event.preventDefault();
    if (step === 1 && !enrollmentVerified) {
      toast.error('Verify your School ID before continuing.');
      return;
    }
    setStep((current) => Math.min(current + 1, steps.length));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const previousStep = () => {
    setStep((current) => Math.max(current - 1, 1));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (formData.password.length < 8 || formData.password.length > 12) {
      toast.error('Your password must be 8–12 characters.');
      return;
    }

    if (formData.password !== formData.password_confirmation) {
      toast.error('Your password confirmation does not match.');
      return;
    }

    const incompleteQuestion = formData.security_questions.some(
      (item) => !item.security_question_id || !item.answer.trim(),
    );
    if (incompleteQuestion) {
      toast.error('Select all three security questions and provide every answer.');
      return;
    }

    const selectedQuestionIds = formData.security_questions.map((item) => item.security_question_id);
    if (new Set(selectedQuestionIds).size !== 3) {
      toast.error('Please select three different security questions.');
      return;
    }

    setLoading(true);

    try {
      await register(formData);
      toast.success('Registration successful. You can now log in.');
      navigate('/login', { replace: true });
    } catch (error) {
      const validationErrors = error.response?.data?.errors;
      const firstError = validationErrors ? Object.values(validationErrors).flat()[0] : null;
      toast.error(firstError || error.response?.data?.message || 'Registration could not be submitted.');
    } finally {
      setLoading(false);
    }
  };

  const selectedCollege = colleges.find((college) => String(college.id) === String(formData.college_id));
  const selectedProgram = programs.find((program) => String(program.id) === String(formData.program_id));
  const selectedHte = htes.find((hte) => String(hte.id) === String(formData.hte_id));
  const passwordStrength = getPasswordStrength(formData.password);
  const passwordRequirements = [
    { label: '8–12 characters', met: formData.password.length >= 8 && formData.password.length <= 12 },
    { label: 'Matches Confirm Password', met: Boolean(formData.password) && formData.password === formData.password_confirmation },
  ];

  const renderStep = () => {
    switch (step) {
      case 1:
        return (
          <>
            <StepHeader eyebrow="Step 1 of 6" title="Personal information" description="Use the same information that appears in your official university records." />
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="School No.">
                <div className="flex gap-2">
                  <input name="student_id" value={formData.student_id} onChange={handleChange} required className={inputClass} placeholder="e.g. 23S0160" autoComplete="username" />
                  <button type="button" onClick={verifyEnrollment} disabled={enrollmentLoading} className="shrink-0 rounded-xl bg-[#7D001D] px-4 text-xs font-extrabold text-white disabled:opacity-55">
                    {enrollmentLoading ? 'Checking...' : 'Verify'}
                  </button>
                </div>
              </Field>
              <Field label="Email Address">
                <input type="email" name="email" value={formData.email} onChange={handleChange} required className={inputClass} placeholder="student@marsu.edu.ph" autoComplete="email" />
              </Field>
              <div className="grid gap-5 sm:col-span-2 sm:grid-cols-3">
                <Field label="Last Name" hint="Verified">
                  <input value={formData.last_name} readOnly required className={inputClass} placeholder="Verify School No." autoComplete="family-name" />
                </Field>
                <Field label="First Name" hint="Verified">
                  <input value={formData.first_name} readOnly required className={inputClass} placeholder="Verify School No." autoComplete="given-name" />
                </Field>
                <Field label="Middle Name" hint="Optional">
                  <input name="middle_name" value={formData.middle_name} onChange={handleChange} className={inputClass} maxLength={100} placeholder="Type middle name" autoComplete="additional-name" />
                </Field>
              </div>
              <Field label="Gender">
                <select name="gender" value={formData.gender} onChange={handleChange} required className={inputClass}>
                  <option value="">Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </Field>
              <Field label="Birth Date">
                <input type="date" name="birth_date" value={formData.birth_date} onChange={handleChange} required className={inputClass} autoComplete="bday" />
              </Field>
              <Field label="Contact Number">
                <ContactNumberInput value={formData.contact_number} onValueChange={(contact_number) => setFormData((current) => ({ ...current, contact_number }))} required className={inputClass} placeholder="09XX XXX XXXX" autoComplete="tel" />
              </Field>
              <Field label="Complete Address" className="sm:col-span-2">
                <textarea name="address" value={formData.address} onChange={handleChange} required rows={3} className={inputClass} placeholder="House number, street, barangay, municipality, province" autoComplete="street-address" />
              </Field>
            </div>
          </>
        );
      case 2:
        return (
          <>
            <StepHeader eyebrow="Step 2 of 6" title="Academic information" description="Your official college, program, year level, and section are loaded from the coordinator's OJT enrollment list." />
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Department / College" className="sm:col-span-2">
                <select name="college_id" value={formData.college_id} onChange={handleChange} required className={inputClass} disabled>
                  <option value="">{dataLoading ? 'Loading colleges...' : 'Select your college'}</option>
                  {colleges.map((college) => <option key={college.id} value={college.id}>{college.name}</option>)}
                </select>
              </Field>
              <Field label="Program" className="sm:col-span-2">
                <select name="program_id" value={formData.program_id} onChange={handleChange} required className={inputClass} disabled>
                  <option value="">{formData.college_id ? 'Select your program' : 'Select a college first'}</option>
                  {programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                </select>
              </Field>
              <Field label="Year Level" hint="Verified">
                <input value={formData.year_level ? `Year ${formData.year_level}` : ''} readOnly required className={inputClass} placeholder="Loaded from your OJT enrollment" />
              </Field>
              <Field label="Section">
                <input name="section" value={formData.section} readOnly required className={inputClass} placeholder="Loaded from your OJT enrollment" />
              </Field>
            </div>
          </>
        );
      case 3:
        return (
          <>
            <StepHeader eyebrow="Step 3 of 6" title="Parent or guardian" description="Provide the person the university should contact when needed." />
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Parent / Guardian Name" className="sm:col-span-2">
                <input name="parent_name" value={formData.parent_name} onChange={handleChange} required className={inputClass} placeholder="Complete name" />
              </Field>
              <Field label="Relationship">
                <select name="parent_relationship" value={formData.parent_relationship} onChange={handleChange} required className={inputClass}>
                  <option value="">Select relationship</option>
                  <option value="Mother">Mother</option>
                  <option value="Father">Father</option>
                  <option value="Legal Guardian">Legal Guardian</option>
                  <option value="Other">Other</option>
                </select>
              </Field>
              <Field label="Contact Number">
                <ContactNumberInput value={formData.parent_contact_number} onValueChange={(parent_contact_number) => setFormData((current) => ({ ...current, parent_contact_number }))} required className={inputClass} placeholder="09XX XXX XXXX" />
              </Field>
              <Field label="Complete Address" className="sm:col-span-2">
                <textarea name="parent_address" value={formData.parent_address} onChange={handleChange} required rows={3} className={inputClass} placeholder="Parent or guardian’s complete address" />
              </Field>
            </div>
          </>
        );
      case 4:
        return (
          <>
            <StepHeader eyebrow="Step 4 of 6" title="Internship information" description="Your semester and school year come from your official academic period. You may indicate your preferred placement below." />
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Internship Semester" hint="From academic period">
                <input type="text" name="internship_semester" aria-label="Internship Semester" value={formData.internship_semester} readOnly className={`${inputClass} bg-slate-50`} />
              </Field>
              <Field label="Internship School Year" hint="From academic period">
                <input type="text" name="internship_year" aria-label="Internship School Year" value={formData.internship_year} readOnly className={`${inputClass} bg-slate-50`} />
              </Field>
              <Field label="Preferred Host Training Establishment" hint="Optional" className="sm:col-span-2">
                <select name="hte_id" value={formData.hte_id} onChange={handleChange} className={inputClass} disabled={!formData.college_id}>
                  <option value="">No preference / To be assigned</option>
                  {htes.map((hte) => <option key={hte.id} value={hte.id}>{hte.name} — {hte.address}</option>)}
                </select>
                <p className="mt-2 text-xs leading-5 text-slate-400">Final placement remains subject to coordinator review and HTE availability.</p>
              </Field>
            </div>
          </>
        );
      case 5:
        return (
          <>
            <StepHeader eyebrow="Step 5 of 6" title="Consent and verification" description="Review the policies that protect you, the university, and your internship records." />
            <div className="space-y-5">
              <div className="rounded-xl border border-slate-100 bg-[#faf8f5] p-5">
                <h3 className="flex items-center gap-2 font-black text-[#690016]"><FiCheckCircle className="text-[#a8750b]" /> Terms and Conditions</h3>
                <p className="mt-3 text-sm leading-6 text-slate-600">I understand that my AIMS account must be used responsibly and that all attendance, journal, document, and monitoring records I submit must be accurate. I agree to follow MarSU internship policies and the rules of my assigned host training establishment.</p>
              </div>
              <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition has-checked:border-[#7D001D]/35 has-checked:bg-[#7D001D]/[0.025]">
                <input type="checkbox" name="agree_terms" checked={formData.agree_terms} onChange={handleChange} required className="mt-0.5 h-4 w-4 shrink-0 accent-[#7D001D]" />
                <span className="text-sm font-bold leading-6 text-slate-700">I have read and agree to the Terms and Conditions.</span>
              </label>
              <div className="rounded-xl border border-slate-100 bg-[#faf8f5] p-5">
                <h3 className="flex items-center gap-2 font-black text-[#690016]"><FiShield className="text-[#a8750b]" /> Data Privacy Act of 2012</h3>
                <p className="mt-3 text-sm leading-6 text-slate-600">I consent to Marinduque State University collecting, using, and securely storing the information required to administer and monitor my internship, in accordance with Republic Act No. 10173 and applicable university policies.</p>
              </div>
              <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition has-checked:border-[#7D001D]/35 has-checked:bg-[#7D001D]/[0.025]">
                <input type="checkbox" name="agree_privacy" checked={formData.agree_privacy} onChange={handleChange} required className="mt-0.5 h-4 w-4 shrink-0 accent-[#7D001D]" />
                <span className="text-sm font-bold leading-6 text-slate-700">I agree to the collection and processing of my data under the Data Privacy Act of 2012.</span>
              </label>
            </div>
          </>
        );
      case 6:
        return (
          <>
            <StepHeader eyebrow="Step 6 of 6" title="Account credentials" description="Your Student ID will be your AIMS username once registration is complete." />
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Username" hint="Student ID" className="sm:col-span-2">
                <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-bold text-slate-600"><FiUser /> {formData.student_id || 'Complete Step 1'}</div>
              </Field>
              <Field label="Password" hint="8–12 characters" htmlFor="student-registration-password">
                <div className="relative">
                  <input id="student-registration-password" type={showPassword ? 'text' : 'password'} name="password" value={formData.password} onChange={handleChange} required minLength={8} maxLength={12} className={`${inputClass} pr-11`} autoComplete="new-password" aria-describedby="student-password-strength student-password-requirements" />
                  <button type="button" onClick={() => setShowPassword((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#7D001D]" aria-label={showPassword ? 'Hide password' : 'Show password'} aria-pressed={showPassword}>{showPassword ? <FiEyeOff /> : <FiEye />}</button>
                </div>
                <div id="student-password-strength" role="status" aria-live="polite" aria-atomic="true" className="mt-2">
                  {passwordStrength && <>
                    <div aria-hidden="true" className="flex gap-1.5">
                      {[1, 2, 3].map((segment) => <span key={segment} className={`h-1.5 flex-1 rounded-full ${segment <= passwordStrength.segments ? passwordStrength.barClass : 'bg-slate-200'}`} />)}
                    </div>
                    <span className={`mt-1 block text-xs font-bold ${passwordStrength.textClass}`}>Password strength: {passwordStrength.label}</span>
                  </>}
                </div>
                <div id="student-password-requirements" className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5">
                  <p className="font-bold text-slate-700">Password requirements</p>
                  <ul className="mt-1 space-y-1">
                    {passwordRequirements.map((requirement) => <li key={requirement.label} className={`flex items-start gap-2 ${requirement.met ? 'text-green-600' : 'text-slate-500'}`}>
                      {requirement.met ? <FiCheckCircle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" /> : <span aria-hidden="true" className="mt-1 h-3 w-3 shrink-0 rounded-full border border-current" />}
                      <span><span className="sr-only">{requirement.met ? 'Met: ' : 'Not yet met: '}</span>{requirement.label}</span>
                    </li>)}
                  </ul>
                  <p className="mt-2 text-slate-500">Passwords are case-sensitive.</p>
                  <p className="mt-2 text-slate-500">For a stronger password, use 12 characters with uppercase and lowercase letters, numbers, and symbols.</p>
                </div>
              </Field>
              <Field label="Confirm Password" htmlFor="student-registration-password-confirmation">
                <div className="relative">
                  <input id="student-registration-password-confirmation" type={showPasswordConfirmation ? 'text' : 'password'} name="password_confirmation" value={formData.password_confirmation} onChange={handleChange} required minLength={8} maxLength={12} className={`${inputClass} pr-11`} autoComplete="new-password" />
                  <button type="button" onClick={() => setShowPasswordConfirmation((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#7D001D]" aria-label={showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'} aria-pressed={showPasswordConfirmation}>{showPasswordConfirmation ? <FiEyeOff /> : <FiEye />}</button>
                </div>
              </Field>
            </div>
            <div className="mt-7 rounded-xl border border-[#7D001D]/15 bg-[#7D001D]/[0.025] p-5 sm:p-6">
              <div className="flex items-start gap-3">
                <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#7D001D]/10 text-[#7D001D]"><FiShield /></div>
                <div>
                  <h3 className="font-black text-[#690016]">Security questions</h3>
                  <p className="mt-1 text-sm leading-6 text-slate-500">Select three different questions. Answers are case-sensitive, so remember the uppercase and lowercase letters you use. Your answers will be securely hashed and used only if you need to reset your password.</p>
                </div>
              </div>
              <div className="mt-5 space-y-5">
                {formData.security_questions.map((item, index) => {
                  const selectedByOtherRows = formData.security_questions
                    .filter((_, itemIndex) => itemIndex !== index)
                    .map((question) => String(question.security_question_id))
                    .filter(Boolean);

                  return (
                    <div key={`security-question-${index + 1}`} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
                      <Field label={`Q${index + 1}: Select Security Question`}>
                        <select
                          required
                          value={item.security_question_id}
                          onChange={(event) => updateSecurityQuestion(index, 'security_question_id', event.target.value)}
                          className={inputClass}
                        >
                          <option value="">Select a question</option>
                          {securityQuestions.map((question) => (
                            <option
                              key={question.id}
                              value={question.id}
                              disabled={selectedByOtherRows.includes(String(question.id))}
                            >
                              {question.question}
                            </option>
                          ))}
                        </select>
                      </Field>
                      <Field label="Answer">
                        <input
                          required
                          type="text"
                          value={item.answer}
                          onChange={(event) => updateSecurityQuestion(index, 'answer', event.target.value)}
                          className={inputClass}
                          maxLength={255}
                          autoComplete="off"
                          placeholder="Enter an answer you will remember"
                        />
                      </Field>
                    </div>
                  );
                })}
              </div>
            </div>
            <div className="mt-7 rounded-xl border border-[#d4af37]/25 bg-[#fffaf0] p-5">
              <h3 className="font-black text-[#690016]">Registration summary</h3>
              <dl className="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                {[
                  ['Student', [formData.last_name ? `${formData.last_name},` : '', formData.first_name, formData.middle_name].filter(Boolean).join(' ') || 'Not provided'],
                  ['Student ID', formData.student_id || 'Not provided'],
                  ['College', selectedCollege?.name || 'Not selected'],
                  ['Program', selectedProgram?.name || 'Not selected'],
                  ['Internship term', formData.internship_semester && formData.internship_year ? `${formData.internship_semester}, ${formData.internship_year}` : 'Not selected'],
                  ['Preferred HTE', selectedHte?.name || 'To be assigned'],
                ].map(([label, value]) => (
                  <div key={label}><dt className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</dt><dd className="mt-1 font-bold text-slate-700">{value}</dd></div>
                ))}
              </dl>
            </div>
          </>
        );
      default:
        return null;
    }
  };

  return (
    <main className="auth-shell registration-shell min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.14),transparent_28%),linear-gradient(135deg,#fbfaf8,#f6eeee)]">
      <header className="border-b border-black/5 bg-white/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-5 lg:px-8">
          <Link to="/" className="flex min-w-0 items-center gap-2.5 sm:gap-3">
            <img src={marsuLogo} alt="Marinduque State University seal" className="h-11 w-11 shrink-0 object-contain" />
            <div className="min-w-0"><p className="max-w-[185px] truncate text-sm font-black text-[#720b0b] sm:max-w-none">Marinduque State University</p><p className="truncate text-xs text-slate-500">AIMS Student Registration</p></div>
          </Link>
          <Link to="/login" className="shrink-0 text-sm font-extrabold text-[#7D001D]"><span className="hidden sm:inline">Already registered? </span>Login</Link>
        </div>
      </header>

      <div className="registration-layout mx-auto grid max-w-7xl gap-5 px-3 py-5 sm:px-5 sm:py-8 lg:grid-cols-[300px_1fr] lg:items-start lg:gap-7 lg:px-8 lg:py-12">
        <aside className="registration-steps min-w-0 overflow-hidden rounded-xl bg-[#650707] p-5 text-white shadow-sm shadow-[#650707]/10 lg:sticky lg:top-6 lg:p-6">
          <Link to="/" className="mb-6 inline-flex items-center gap-2 text-xs font-bold text-white/55 hover:text-white"><FiArrowLeft /> Back to AIMS home</Link>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e4c65e]">Student application</p>
          <h1 className="mt-2 text-2xl font-black tracking-tight">Join the AIMS community</h1>
          <p className="mt-3 text-sm leading-6 text-white/60">Complete all six steps. You can review previous steps before submission.</p>

          <div className="registration-step-list mt-7 flex max-w-full gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
            {steps.map((item) => {
              const Icon = item.icon;
              const active = step === item.number;
              const complete = step > item.number;
              return (
                <button key={item.number} type="button" onClick={() => complete && setStep(item.number)} disabled={!complete && !active} className={`flex min-w-[54px] items-center gap-3 rounded-xl p-2.5 text-left transition lg:w-full ${active ? 'bg-white text-[#5c0000]' : complete ? 'text-white hover:bg-white/10' : 'text-white/35'}`}>
                  <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${active ? 'bg-[#7D001D] text-white' : complete ? 'bg-[#d4af37] text-[#690016]' : 'bg-white/8'}`}>
                    {complete ? <FiCheck /> : <Icon />}
                  </span>
                  <span className="hidden lg:block"><strong className="block text-sm">{item.title}</strong><span className={`text-xs ${active ? 'text-slate-500' : 'text-white/40'}`}>{item.description}</span></span>
                </button>
              );
            })}
          </div>

          <div className="mt-7 hidden rounded-xl border border-white/10 bg-white/[0.055] p-4 text-xs leading-5 text-white/55 lg:block">
            <strong className="mb-1 block text-white">What happens next?</strong>
            Your official eligible list entry allows you to register. You can log in once your account is complete.
          </div>
        </aside>

        <section className="registration-form min-w-0 overflow-hidden rounded-xl border border-black/[0.055] bg-white shadow-sm shadow-slate-900/5">
          <div className="h-1.5 bg-slate-100"><div className="h-full bg-gradient-to-r from-[#7D001D] to-[#d4af37] transition-all duration-500" style={{ width: `${(step / steps.length) * 100}%` }} /></div>
          <form onSubmit={step === steps.length ? handleSubmit : nextStep} className="p-4 sm:p-7 lg:p-10">
            {renderStep()}
            <div className="mt-9 flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between">
              <button type="button" onClick={previousStep} disabled={step === 1} className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold text-slate-600 transition hover:bg-slate-50 disabled:invisible min-[420px]:w-auto"><FiArrowLeft /> Previous</button>
              {step < steps.length ? (
                <button type="submit" className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-[#7D001D]/15 transition hover:bg-[#650000] min-[420px]:w-auto">Next step <FiArrowRight /></button>
              ) : (
                <button type="submit" disabled={loading || !formData.agree_terms || !formData.agree_privacy} className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#7D001D] px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-[#7D001D]/15 transition hover:bg-[#650000] disabled:cursor-not-allowed disabled:opacity-55 min-[420px]:w-auto">
                  {loading ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/35 border-t-white" /> Submitting...</> : <><FiCheckCircle /> Submit Registration</>}
                </button>
              )}
            </div>
          </form>
        </section>
      </div>
    </main>
  );
}

export default RegisterPage;
