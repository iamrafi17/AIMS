import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  EyeIcon,
  EyeSlashIcon,
  KeyIcon,
  LockClosedIcon,
  QuestionMarkCircleIcon,
  ShieldCheckIcon,
  UserCircleIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { usePasswordMinimum } from '../../utils/passwordStrength';
import marsuLogo from '../../assets/marsu-logo.png';

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 outline-none transition focus:border-[#7D001D] focus:ring-4 focus:ring-[#7D001D]/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white';

function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  return errors
    ? Object.values(errors).flat()[0]
    : error.response?.data?.message || fallback;
}

export default function ForgotPasswordPage() {
  const passwordMinimum = usePasswordMinimum();
  const [step, setStep] = useState('identify');
  const [userId, setUserId] = useState('');
  const [questions, setQuestions] = useState([]);
  const [challengeToken, setChallengeToken] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [loading, setLoading] = useState(false);

  const requestQuestions = async (event) => {
    event.preventDefault();
    setLoading(true);

    try {
      const response = await api.post('/forgot-password', { user_id: userId.trim() });
      setChallengeToken(response.data.challenge_token);
      setQuestions((response.data.questions || []).map((question) => ({ ...question, answer: '' })));
      setStep('questions');
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to find this account.'));
    } finally {
      setLoading(false);
    }
  };

  const updateAnswer = (questionId, answer) => {
    setQuestions((current) => current.map((question) => (
      question.id === questionId ? { ...question, answer } : question
    )));
  };

  const verifyAnswers = async (event) => {
    event.preventDefault();

    if (questions.some((question) => !question.answer.trim())) {
      toast.error('Answer all three security questions.');
      return;
    }

    setLoading(true);
    try {
      const response = await api.post('/forgot-password/verify', {
        user_id: userId.trim(),
        challenge_token: challengeToken,
        answers: questions.map((question) => ({
          security_question_id: question.id,
          answer: question.answer,
        })),
      });
      setResetToken(response.data.reset_token);
      setQuestions((current) => current.map((question) => ({ ...question, answer: '' })));
      setChallengeToken('');
      setStep('reset');
      toast.success('Security answers verified.');
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to verify security answers.'));
    } finally {
      setLoading(false);
    }
  };

  const resetPassword = async (event) => {
    event.preventDefault();

    if (password !== passwordConfirmation) {
      toast.error('Password confirmation does not match.');
      return;
    }

    setLoading(true);
    try {
      const response = await api.post('/reset-password', {
        user_id: userId.trim(),
        token: resetToken,
        password,
        password_confirmation: passwordConfirmation,
      });
      setPassword('');
      setPasswordConfirmation('');
      setResetToken('');
      setStep('success');
      toast.success(response.data.message);
    } catch (error) {
      toast.error(errorMessage(error, 'Unable to reset password.'));
    } finally {
      setLoading(false);
    }
  };

  const startOver = () => {
    setStep('identify');
    setUserId('');
    setQuestions([]);
    setChallengeToken('');
    setResetToken('');
    setPassword('');
    setPasswordConfirmation('');
    setShowPassword(false);
    setShowPasswordConfirmation(false);
  };

  const page = {
    identify: {
      icon: UserCircleIcon,
      title: 'Forgot your password?',
      description: 'Enter your existing Student ID or Username to continue.',
    },
    questions: {
      icon: QuestionMarkCircleIcon,
      title: 'Answer your security questions',
      description: 'All three answers must match the answers saved during registration.',
    },
    reset: {
      icon: KeyIcon,
      title: 'Create a new password',
      description: 'Use at least eight characters and enter the same password twice.',
    },
    success: {
      icon: CheckCircleIcon,
      title: 'Password reset complete',
      description: 'Your password has been successfully reset.',
    },
  }[step];
  const StepIcon = page.icon;

  return (
    <main className="auth-shell recovery-shell grid min-h-screen bg-[#f7f4ef] lg:grid-cols-[.9fr_1.1fr] dark:bg-gray-950">
      <section className="relative hidden overflow-hidden bg-[#690016] p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-[#d4af37]/15 blur-3xl" />
        <div className="relative flex items-center gap-4">
          <div className="grid h-16 w-16 place-items-center rounded-full bg-white p-2 shadow-sm">
            <img src={marsuLogo} className="h-full w-full object-contain" alt="MarSU seal" />
          </div>
          <div>
            <p className="text-2xl font-black">AIMS</p>
            <p className="text-sm text-white/65">Marinduque State University</p>
          </div>
        </div>
        <div className="relative max-w-xl">
          <ShieldCheckIcon className="h-16 w-16 text-[#d4af37]" />
          <h1 className="mt-6 text-5xl font-black leading-tight">Secure account recovery.</h1>
          <p className="mt-5 text-lg leading-8 text-white/70">
            Verify the three private questions selected during registration before creating a new password. Saved answers are never displayed or stored as plain text.
          </p>
        </div>
        <p className="relative text-sm text-white/45">Academic Internship Monitoring System · Santa Cruz Campus</p>
      </section>

      <section className="recovery-panel flex min-w-0 items-center justify-center p-5 sm:p-10">
        <div className="recovery-card w-full min-w-0 max-w-xl rounded-xl border border-white bg-white p-7 shadow-sm shadow-slate-900/10 dark:border-gray-700 dark:bg-gray-900 sm:p-10">
          <Link to="/login" className="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-[#7D001D]">
            <ArrowLeftIcon className="h-4 w-4" />
            Back to login
          </Link>

          <div className="mt-8 grid h-14 w-14 place-items-center rounded-xl bg-[#7D001D]/10 text-[#7D001D] dark:text-red-300">
            <StepIcon className="h-7 w-7" />
          </div>
          <h2 className="mt-5 text-3xl font-black text-slate-900 dark:text-white">{page.title}</h2>
          <p className="mt-2 leading-7 text-slate-500 dark:text-slate-400">{page.description}</p>

          {step === 'identify' && (
            <form onSubmit={requestQuestions} className="mt-8 space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-bold">Username</span>
                <div className="relative">
                  <UserCircleIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                  <input
                    required
                    type="text"
                    value={userId}
                    onChange={(event) => setUserId(event.target.value)}
                    className={`${inputClass} pl-12`}
                    placeholder="Student ID or Username"
                    autoComplete="username"
                  />
                </div>
              </label>
              <button disabled={loading} className="w-full rounded-xl bg-[#7D001D] px-5 py-3.5 font-black text-white disabled:opacity-50">
                {loading ? 'Checking...' : 'Continue'}
              </button>
            </form>
          )}

          {step === 'questions' && (
            <form onSubmit={verifyAnswers} className="mt-8 space-y-5">
              {questions.map((question, index) => (
                <label key={question.id} className="block rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                  <span className="mb-3 block text-sm font-black text-slate-800 dark:text-white">
                    Q{index + 1}: {question.question}
                  </span>
                  <input
                    required
                    type="text"
                    value={question.answer}
                    onChange={(event) => updateAnswer(question.id, event.target.value)}
                    className={inputClass}
                    maxLength={255}
                    autoComplete="off"
                    placeholder="Your answer"
                  />
                  {question.answer_case_sensitive && <span className="mt-2 block text-xs text-slate-500">Case-sensitive: use the same uppercase and lowercase letters as your saved answer.</span>}
                </label>
              ))}
              <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <button type="button" onClick={startOver} className="rounded-xl border border-slate-200 px-5 py-3.5 font-bold dark:border-gray-700 sm:flex-1">
                  Start over
                </button>
                <button disabled={loading} className="rounded-xl bg-[#7D001D] px-5 py-3.5 font-black text-white disabled:opacity-50 sm:flex-1">
                  {loading ? 'Verifying...' : 'Verify Answers'}
                </button>
              </div>
            </form>
          )}

          {step === 'reset' && (
            <form onSubmit={resetPassword} className="mt-8 space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-bold">New Password</span>
                <div className="relative">
                  <LockClosedIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                  <input
                    required
                    minLength={passwordMinimum}
                    maxLength={255}
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    className={`${inputClass} pl-12 pr-12`}
                    autoComplete="new-password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((visible) => !visible)}
                    className="absolute right-4 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-[#7D001D] focus:outline-none focus:ring-2 focus:ring-[#7D001D]/30"
                    aria-label={showPassword ? 'Hide new password' : 'Show new password'}
                    aria-pressed={showPassword}
                  >
                    {showPassword ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                  </button>
                </div>
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-bold">Confirm New Password</span>
                <div className="relative">
                  <LockClosedIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                  <input
                    required
                    minLength={passwordMinimum}
                    maxLength={255}
                    type={showPasswordConfirmation ? 'text' : 'password'}
                    value={passwordConfirmation}
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                    className={`${inputClass} pl-12 pr-12`}
                    autoComplete="new-password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPasswordConfirmation((visible) => !visible)}
                    className="absolute right-4 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-[#7D001D] focus:outline-none focus:ring-2 focus:ring-[#7D001D]/30"
                    aria-label={showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'}
                    aria-pressed={showPasswordConfirmation}
                  >
                    {showPasswordConfirmation ? <EyeSlashIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                  </button>
                </div>
              </label>
              <button disabled={loading} className="w-full rounded-xl bg-[#7D001D] px-5 py-3.5 font-black text-white disabled:opacity-50">
                {loading ? 'Resetting...' : 'Reset Password'}
              </button>
            </form>
          )}

          {step === 'success' && (
            <div className="mt-8">
              <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                Your password has been successfully reset. You can now sign in using your new password.
              </div>
              <Link to="/login" className="mt-5 block w-full rounded-xl bg-[#7D001D] px-5 py-3.5 text-center font-black text-white">
                Back to Login
              </Link>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
