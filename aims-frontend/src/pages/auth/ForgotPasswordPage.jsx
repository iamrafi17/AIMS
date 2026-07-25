import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  EnvelopeIcon,
  KeyIcon,
  LockClosedIcon,
  ShieldCheckIcon,
} from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';
import api from '../../services/api';
import marsuLogo from '../../assets/marsu-logo.png';

export default function ForgotPasswordPage() {
  const navigate = useNavigate();
  const [step, setStep] = useState('request');
  const [form, setForm] = useState({ email: '', token: '', password: '', password_confirmation: '' });
  const [loading, setLoading] = useState(false);
  const requestReset = async (event) => {
    event.preventDefault(); setLoading(true);
    try {
      const response = await api.post('/forgot-password', { email: form.email });
      if (response.data.reset_token) {
        setForm((current) => ({ ...current, token: response.data.reset_token }));
        setStep('reset');
        toast.success('Account verified. Create your new password.');
      } else {
        setStep('sent');
        toast.success(response.data.message);
      }
    } catch (error) { toast.error(error.response?.data?.message || 'Unable to request password reset.'); }
    finally { setLoading(false); }
  };
  const reset = async (event) => {
    event.preventDefault();
    if (form.password !== form.password_confirmation) return toast.error('Password confirmation does not match.');
    setLoading(true);
    try { const response = await api.post('/reset-password', form); toast.success(response.data.message); navigate('/login'); }
    catch (error) { const errors = error.response?.data?.errors; toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'Unable to reset password.'); }
    finally { setLoading(false); }
  };
  return <main className="grid min-h-screen bg-[#f7f4ef] lg:grid-cols-[.9fr_1.1fr] dark:bg-gray-950">
    <section className="relative hidden overflow-hidden bg-gradient-to-br from-[#4c0000] via-[#800000] to-[#a21d1d] p-12 text-white lg:flex lg:flex-col lg:justify-between"><div className="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-[#d4af37]/15 blur-3xl" /><div className="relative flex items-center gap-4"><div className="grid h-16 w-16 place-items-center rounded-full bg-white p-2 shadow-xl"><img src={marsuLogo} className="h-full w-full object-contain" alt="MarSU seal" /></div><div><p className="text-2xl font-black">AIMS</p><p className="text-sm text-white/65">Marinduque State University</p></div></div><div className="relative max-w-xl"><ShieldCheckIcon className="h-16 w-16 text-[#d4af37]" /><h1 className="mt-6 text-5xl font-black leading-tight">Secure account recovery.</h1><p className="mt-5 text-lg leading-8 text-white/70">Reset access without exposing your current password. Reset tokens expire after 60 minutes and existing API sessions are revoked.</p></div><p className="relative text-sm text-white/45">Academic Internship Monitoring System · Santa Cruz Campus</p></section>
    <section className="flex items-center justify-center p-5 sm:p-10"><div className="w-full max-w-xl rounded-[2rem] border border-white bg-white p-7 shadow-2xl shadow-slate-900/10 dark:border-gray-700 dark:bg-gray-900 sm:p-10"><Link to="/login" className="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-[#800000]"><ArrowLeftIcon className="h-4 w-4" />Back to login</Link><div className="mt-8 grid h-14 w-14 place-items-center rounded-2xl bg-[#800000]/10 text-[#800000] dark:text-red-300">{step === 'request' ? <EnvelopeIcon className="h-7 w-7" /> : step === 'sent' ? <CheckCircleIcon className="h-7 w-7" /> : <KeyIcon className="h-7 w-7" />}</div><h2 className="mt-5 text-3xl font-black text-slate-900 dark:text-white">{step === 'request' ? 'Forgot your password?' : step === 'sent' ? 'Check your email' : 'Create a new password'}</h2><p className="mt-2 leading-7 text-slate-500 dark:text-slate-400">{step === 'request' ? 'Enter the email address registered to your AIMS account.' : step === 'sent' ? `If ${form.email} is registered, a secure reset link has been sent.` : 'Use at least eight characters for your new secure password.'}</p>
      {step === 'request' && <form onSubmit={requestReset} className="mt-8 space-y-5"><label className="block"><span className="mb-2 block text-sm font-bold">Email address</span><div className="relative"><EnvelopeIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} className="w-full rounded-2xl border border-slate-200 py-3.5 pl-12 pr-4 dark:border-gray-700" placeholder="name@marsu.edu.ph" /></div></label><button disabled={loading} className="w-full rounded-2xl bg-[#800000] px-5 py-3.5 font-black text-white">{loading ? 'Verifying...' : 'Continue securely'}</button></form>}
      {step === 'sent' && <Link to="/login" className="mt-8 block w-full rounded-2xl bg-[#800000] px-5 py-3.5 text-center font-black text-white">Return to login</Link>}
      {step === 'reset' && <form onSubmit={reset} className="mt-8 space-y-5"><label className="block"><span className="mb-2 block text-sm font-bold">New password</span><div className="relative"><LockClosedIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input required minLength="8" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} className="w-full rounded-2xl border border-slate-200 py-3.5 pl-12 pr-4 dark:border-gray-700" /></div></label><label className="block"><span className="mb-2 block text-sm font-bold">Confirm new password</span><div className="relative"><LockClosedIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input required minLength="8" type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} className="w-full rounded-2xl border border-slate-200 py-3.5 pl-12 pr-4 dark:border-gray-700" /></div></label><button disabled={loading} className="w-full rounded-2xl bg-[#800000] px-5 py-3.5 font-black text-white">{loading ? 'Resetting...' : 'Reset password'}</button></form>}
    </div></section>
  </main>;
}
