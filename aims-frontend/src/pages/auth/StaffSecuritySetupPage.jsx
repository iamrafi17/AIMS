import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  ArrowRightIcon,
  ArrowRightOnRectangleIcon,
  CheckCircleIcon,
  EyeIcon,
  EyeSlashIcon,
  IdentificationIcon,
  KeyIcon,
  QuestionMarkCircleIcon,
  ShieldCheckIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import useAuth from '../../context/useAuth';
import api from '../../services/api';
import marsuLogo from '../../assets/marsu-logo.png';
import ContactNumberInput from '../../components/forms/ContactNumberInput';
import PsgcAddressSelect from '../../components/forms/PsgcAddressSelect';
import { getPasswordStrength, usePasswordMinimum } from '../../utils/passwordStrength';

const roleRoutes = {
  admin: '/admin/dashboard',
  coordinator: '/coordinator/dashboard',
  program_head: '/program-head/dashboard',
  vpaa: '/vpaa/dashboard',
  dean: '/dean/dashboard',
  supervisor: '/supervisor/dashboard',
};

const roleLabels = {
  admin: 'Admin',
  coordinator: 'Coordinator',
  program_head: 'Program Head',
  vpaa: 'VPAA',
  dean: 'Dean',
  supervisor: 'Supervisor',
};

const steps = [
  { key: 'complete_profile', label: 'Profile', icon: IdentificationIcon },
  { key: 'security_setup', label: 'Security & Password', icon: ShieldCheckIcon },
];

const emptyQuestions = () => Array.from({ length: 3 }, () => ({
  security_question_id: '',
  answer: '',
}));

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 outline-none transition focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:disabled:bg-slate-800';

function firstError(error, fallback) {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat()[0] : error.response?.data?.message || fallback;
}

function deriveStep(user) {
  if (user?.onboarding_step) return user.onboarding_step;
  if (user?.requires_profile_setup) return 'complete_profile';
  if (user?.requires_security_setup) return 'security_setup';
  if (user?.requires_password_change) return 'change_password';
  return null;
}

function assignmentLabel(assignment, fallback) {
  if (!assignment) return fallback;
  return [assignment.code, assignment.name].filter(Boolean).join(' — ');
}

export default function StaffSecuritySetupPage() {
  const passwordMinimum = usePasswordMinimum();
  const { user, logout, refreshUser } = useAuth();
  const navigate = useNavigate();
  const [currentStep, setCurrentStep] = useState(() => deriveStep(user));
  const [availableQuestions, setAvailableQuestions] = useState([]);
  const [securityQuestions, setSecurityQuestions] = useState(emptyQuestions);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [profile, setProfile] = useState({
    email: user?.email || '',
    contact_number: user?.contact_number || '',
    address: user?.address || '',
  });
  const [loadingQuestions, setLoadingQuestions] = useState(true);
  const [saving, setSaving] = useState(false);
  const [addressValid, setAddressValid] = useState(false);

  useEffect(() => {
    setCurrentStep(deriveStep(user));
    setProfile((current) => ({
      email: user?.email || current.email,
      contact_number: user?.contact_number || current.contact_number,
      address: user?.address || current.address,
    }));
  }, [user]);

  useEffect(() => {
    let active = true;
    api.get('/security-questions')
      .then((response) => {
        if (active) setAvailableQuestions(response.data.questions || []);
      })
      .catch(() => toast.error('Unable to load the security questions.'))
      .finally(() => {
        if (active) setLoadingQuestions(false);
      });
    return () => { active = false; };
  }, []);

  const continueTo = async (nextStep, message) => {
    const refreshed = await refreshUser();
    const actualStep = nextStep || deriveStep(refreshed?.user);
    toast.success(message);

    if (actualStep) {
      setCurrentStep(actualStep);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    navigate(roleRoutes[user.role] || '/', { replace: true });
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  const submitProfile = async (event) => {
    event.preventDefault();
    if (!addressValid) {
      toast.error('Select a province, municipality or city, and barangay.');
      return;
    }
    setSaving(true);
    try {
      const response = await api.post('/staff/profile-setup', {
        email: profile.email.trim(),
        contact_number: profile.contact_number.trim(),
        address: profile.address.trim(),
      });
      await continueTo(response.data.onboarding_step, response.data.message);
    } catch (error) {
      toast.error(firstError(error, 'Unable to save your profile.'));
    } finally {
      setSaving(false);
    }
  };

  const updateQuestion = (index, field, value) => {
    setSecurityQuestions((current) => current.map((item, itemIndex) => (
      itemIndex === index ? { ...item, [field]: value } : item
    )));
  };

  const validatePassword = () => {
    if (password !== passwordConfirmation) {
      toast.error('Password confirmation does not match.');
      return false;
    }
    return true;
  };

  const submitSecuritySetup = async (event) => {
    event.preventDefault();
    if (securityQuestions.some((item) => !item.security_question_id || !item.answer.trim())) {
      toast.error('Select all three security questions and provide every answer.');
      return;
    }
    if (new Set(securityQuestions.map((item) => item.security_question_id)).size !== 3) {
      toast.error('Please select three different security questions.');
      return;
    }
    if (!validatePassword()) return;

    setSaving(true);
    try {
      const response = await api.post('/staff/security-setup', {
        password,
        password_confirmation: passwordConfirmation,
        security_questions: securityQuestions.map((item) => ({
          security_question_id: Number(item.security_question_id),
          answer: item.answer.trim(),
        })),
      });
      await continueTo(response.data.onboarding_step, 'Account setup completed successfully.');
    } catch (error) {
      toast.error(firstError(error, 'Unable to complete your security setup.'));
    } finally {
      setSaving(false);
    }
  };

  const submitPasswordOnly = async (event) => {
    event.preventDefault();
    if (!validatePassword()) return;
    setSaving(true);
    try {
      const response = await api.post('/staff/password-setup', {
        password,
        password_confirmation: passwordConfirmation,
      });
      await continueTo(response.data.onboarding_step, response.data.message);
    } catch (error) {
      toast.error(firstError(error, 'Unable to save your personal password.'));
    } finally {
      setSaving(false);
    }
  };

  const passwordStrength = getPasswordStrength(password);
  const passwordRequirements = [
    { label: `At least ${passwordMinimum} characters`, met: password.length >= passwordMinimum && password.length <= 255 },
    { label: 'Matches Confirm new password', met: Boolean(password) && password === passwordConfirmation },
  ];
  const passwordFields = (
    <div className="grid gap-4 sm:grid-cols-2">
      <div>
        <label htmlFor="staff-new-password" className="mb-2 block text-sm font-bold">New password</label>
        <div className="relative">
          <input id="staff-new-password" required minLength={passwordMinimum} maxLength={255} type={showPassword ? 'text' : 'password'} autoComplete="new-password" aria-describedby="new-password-strength new-password-requirements" value={password} onChange={(event) => setPassword(event.target.value)} className={`${inputClass} pr-12`} />
          <button type="button" onClick={() => setShowPassword((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#7D001D]" aria-label={showPassword ? 'Hide new password' : 'Show new password'} aria-pressed={showPassword}>
            {showPassword ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
          </button>
        </div>
        <div id="new-password-strength" role="status" aria-live="polite" aria-atomic="true" className="mt-2">
          {passwordStrength && <>
            <div aria-hidden="true" className="flex gap-1.5">
              {[1, 2, 3].map((segment) => <span key={segment} className={`h-1.5 flex-1 rounded-full ${segment <= passwordStrength.segments ? passwordStrength.barClass : 'bg-slate-200 dark:bg-slate-700'}`} />)}
            </div>
            <span className={`mt-1 block text-xs font-bold ${passwordStrength.textClass}`}>Password strength: {passwordStrength.label}</span>
          </>}
        </div>
        <div id="new-password-requirements" className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 dark:border-slate-700 dark:bg-slate-800/50">
          <p className="font-bold text-slate-700 dark:text-slate-200">Password requirements</p>
          <ul className="mt-1 space-y-1">
            {passwordRequirements.map((requirement) => <li key={requirement.label} className={`flex items-start gap-2 ${requirement.met ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400'}`}>
              {requirement.met ? <CheckCircleIcon aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" /> : <span aria-hidden="true" className="mt-1 h-3 w-3 shrink-0 rounded-full border border-current" />}
              <span><span className="sr-only">{requirement.met ? 'Met: ' : 'Not yet met: '}</span>{requirement.label}</span>
            </li>)}
          </ul>
          <p className="mt-2 text-slate-500 dark:text-slate-400">Your new password must be different from your temporary password.</p>
          <p className="mt-2 text-slate-500 dark:text-slate-400">For a stronger password, use uppercase and lowercase letters, numbers, and symbols.</p>
        </div>
      </div>
      <label>
        <span className="mb-2 block text-sm font-bold">Confirm new password</span>
        <div className="relative">
          <input required minLength={passwordMinimum} maxLength={255} type={showPasswordConfirmation ? 'text' : 'password'} autoComplete="new-password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} className={`${inputClass} pr-12`} />
          <button type="button" onClick={() => setShowPasswordConfirmation((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#7D001D]" aria-label={showPasswordConfirmation ? 'Hide confirm new password' : 'Show confirm new password'} aria-pressed={showPasswordConfirmation}>
            {showPasswordConfirmation ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
          </button>
        </div>
      </label>
    </div>
  );

  return (
    <main className="auth-shell onboarding-shell min-h-screen bg-[#F6F7FB] px-4 py-6 dark:bg-slate-950 sm:px-6 lg:grid lg:grid-cols-[0.72fr_1.28fr] lg:p-0">
      <section className="relative hidden min-h-screen overflow-hidden bg-[#690016] p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[#d4af37]/15 blur-3xl" />
        <div className="relative flex items-center gap-4">
          <div className="grid h-16 w-16 place-items-center rounded-full bg-white p-2 shadow-sm"><img src={marsuLogo} className="h-full w-full object-contain" alt="MarSU seal" /></div>
          <div><p className="text-2xl font-black">AIMS</p><p className="text-sm text-white/65">Marinduque State University</p></div>
        </div>
        <div className="relative max-w-lg">
          <ShieldCheckIcon className="h-16 w-16 text-[#d4af37]" />
          <p className="mt-6 text-sm font-black uppercase tracking-[0.2em] text-[#e5c351]">First login setup</p>
          <h1 className="mt-4 text-5xl font-black leading-tight">Complete your account.</h1>
          <p className="mt-5 text-lg leading-8 text-white/70">Confirm your profile first, then protect the account with recovery questions and a personal password.</p>
        </div>
        <p className="relative text-sm text-white/45">Academic Internship Monitoring System · Santa Cruz Campus</p>
      </section>

      <section className="flex min-h-[calc(100vh-3rem)] items-center justify-center lg:min-h-screen lg:px-10 lg:py-12">
        <div className="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-8">
          <div className="flex flex-col gap-5 border-b border-slate-200 pb-6 dark:border-slate-700 sm:flex-row sm:items-start sm:justify-between">
            <div><p className="text-xs font-black uppercase tracking-[0.16em] text-[#a8750b]">Required account setup</p><h2 className="mt-1 text-2xl font-black">Welcome, {user?.name}</h2><p className="mt-1 text-sm text-slate-500">{roleLabels[user?.role] || 'Staff'} · {user?.username}</p></div>
            <button type="button" onClick={handleLogout} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300"><ArrowRightOnRectangleIcon className="h-5 w-5" />Sign out</button>
          </div>

          {currentStep !== 'change_password' && (
            <ol className="my-7 grid grid-cols-2 gap-3" aria-label="Account setup progress">
              {steps.map((step, index) => {
                const Icon = step.icon;
                const completed = index === 0 && !user?.requires_profile_setup;
                const active = step.key === currentStep;
                const canVisit = step.key === 'complete_profile' || !user?.requires_profile_setup;
                return <li key={step.key}><button
                  type="button"
                  disabled={!canVisit || saving}
                  aria-current={active ? 'step' : undefined}
                  onClick={() => setCurrentStep(step.key)}
                  className={`w-full rounded-xl border p-3 text-center transition disabled:cursor-not-allowed ${active ? 'border-[#7D001D] bg-[#7D001D]/5 text-[#7D001D] dark:text-rose-300' : completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' : 'border-slate-200 text-slate-400 dark:border-slate-700'} ${canVisit && !active ? 'hover:border-[#7D001D]' : ''}`}
                >{completed ? <CheckCircleIcon className="mx-auto h-5 w-5" /> : <Icon className="mx-auto h-5 w-5" />}<span className="mt-1 block text-xs font-black sm:text-sm">{index + 1}. {step.label}</span></button></li>;
              })}
            </ol>
          )}

          {currentStep === 'complete_profile' && (
            <form onSubmit={submitProfile} className="space-y-6">
              <div><h3 className="text-xl font-black">Complete personal information</h3><p className="mt-2 text-sm text-slate-500">Review the assigned information and complete all required contact fields.</p></div>
              <div className="grid gap-4 sm:grid-cols-2">
                <ReadOnlyField label="Full name" value={user?.name || ''} />
                <ReadOnlyField label="Role" value={roleLabels[user?.role] || 'Staff'} />
                {user?.role === 'supervisor' ? <>
                  <ReadOnlyField label="HTE Name" value={user?.supervisor_assignment?.hte?.name || 'Not assigned'} />
                  <ReadOnlyField label="Department / Unit" value={user?.supervisor_assignment?.department?.department_name || 'Not assigned'} />
                  <div className="sm:col-span-2"><ReadOnlyField label="HTE Address" value={user?.supervisor_assignment?.hte?.address || 'Not available'} /></div>
                </> : <>
                  <ReadOnlyField label="College" value={assignmentLabel(user?.college, user?.role === 'vpaa' ? 'University-wide' : 'Not assigned')} />
                  <ReadOnlyField label="Program" value={assignmentLabel(user?.program, user?.role === 'vpaa' ? 'University-wide' : 'Not assigned')} />
                </>}
                <label><span className="mb-2 block text-sm font-bold">Email address</span><input required type="email" maxLength="255" autoComplete="email" value={profile.email} onChange={(event) => setProfile({ ...profile, email: event.target.value })} className={inputClass} /></label>
                <label><span className="mb-2 block text-sm font-bold">Contact Number</span><ContactNumberInput required autoComplete="tel" value={profile.contact_number} onValueChange={(contact_number) => setProfile((current) => ({ ...current, contact_number }))} className={inputClass} placeholder="09XX XXX XXXX" /></label>
                <div className="sm:col-span-2"><p className="mb-2 text-sm font-bold">Personal Address</p><PsgcAddressSelect value={profile.address} onChange={(address) => setProfile((current) => ({ ...current, address }))} onValidityChange={setAddressValid} className={inputClass} /></div>
              </div>
              <SubmitRow saving={saving} savingText="Saving profile..." label={user?.requires_profile_setup ? 'Save Profile & Next' : 'Save Profile & Return to Security'} />
            </form>
          )}

          {currentStep === 'security_setup' && (
            <form onSubmit={submitSecuritySetup} className="space-y-7">
              <section>
                <div className="flex gap-3"><QuestionMarkCircleIcon className="h-6 w-6 shrink-0 text-[#7D001D]" /><div><h3 className="text-xl font-black">Set Up Security Questions</h3><p className="mt-1 text-sm text-slate-500">Choose three different questions for password recovery. Answers are case-sensitive. Remember the uppercase and lowercase letters you use.</p></div></div>
                <div className="mt-5 space-y-4">
                  {securityQuestions.map((item, index) => {
                    const selectedElsewhere = securityQuestions.filter((_, itemIndex) => itemIndex !== index).map((question) => String(question.security_question_id));
                    return (
                      <div key={`security-${index + 1}`} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/50 sm:grid-cols-[1.15fr_0.85fr]">
                        <label><span className="mb-2 block text-xs font-black uppercase">Question {index + 1}</span><select required value={item.security_question_id} disabled={loadingQuestions} onChange={(event) => updateQuestion(index, 'security_question_id', event.target.value)} className={inputClass}><option value="">{loadingQuestions ? 'Loading questions...' : 'Select a question'}</option>{availableQuestions.map((question) => <option key={question.id} value={question.id} disabled={selectedElsewhere.includes(String(question.id))}>{question.question}</option>)}</select></label>
                        <label><span className="mb-2 block text-xs font-black uppercase">Your answer</span><input required minLength="2" maxLength="255" autoComplete="off" value={item.answer} onChange={(event) => updateQuestion(index, 'answer', event.target.value)} className={inputClass} /></label>
                      </div>
                    );
                  })}
                </div>
              </section>
              <section className="border-t border-slate-200 pt-7 dark:border-slate-700">
                <div className="flex gap-3"><KeyIcon className="h-6 w-6 shrink-0 text-[#7D001D]" /><div><h3 className="text-xl font-black">Change Password</h3><p className="mt-1 text-sm text-slate-500">Replace the temporary password with your personal password.</p></div></div>
                <div className="mt-5">{passwordFields}</div>
              </section>
              <SubmitRow saving={saving || loadingQuestions} savingText={loadingQuestions ? 'Loading questions...' : 'Completing setup...'} label="Done & Open Dashboard" />
            </form>
          )}

          {currentStep === 'change_password' && (
            <form onSubmit={submitPasswordOnly} className="space-y-6">
              <div><h3 className="text-xl font-black">Change temporary password</h3><p className="mt-2 text-sm text-slate-500">Enter a new personal password to restore dashboard access.</p></div>
              {passwordFields}
              <SubmitRow saving={saving} savingText="Saving password..." label="Save Password & Open Dashboard" />
            </form>
          )}
        </div>
      </section>
    </main>
  );
}

function ReadOnlyField({ label, value }) {
  return <label><span className="mb-2 block text-sm font-bold">{label}</span><input readOnly value={value} className={inputClass} /></label>;
}

function SubmitRow({ saving, savingText, label }) {
  return <div className="flex justify-end border-t border-slate-200 pt-6 dark:border-slate-700"><button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-xl bg-[#7D001D] px-6 py-3.5 text-sm font-black text-white disabled:opacity-50">{saving ? savingText : label}{!saving && <ArrowRightIcon className="h-5 w-5" />}</button></div>;
}
