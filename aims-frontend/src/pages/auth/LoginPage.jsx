import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiArrowLeft, FiCheckCircle, FiEye, FiEyeOff, FiLock, FiUser } from 'react-icons/fi';
import toast from 'react-hot-toast';
import { useAuth } from '../../context/AuthContext';
import heroImage from '../../assets/aims-portal-hero.png';
import marsuLogo from '../../assets/marsu-logo.png';

function LoginPage() {
  const [identifier, setIdentifier] = useState(() => localStorage.getItem('remembered_login') || '');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(Boolean(localStorage.getItem('remembered_login')));
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);

    try {
      const data = await login(identifier, password);

      if (remember) {
        localStorage.setItem('remembered_login', identifier);
      } else {
        localStorage.removeItem('remembered_login');
      }

      const roleRoutes = {
        student: '/student/dashboard',
        coordinator: '/coordinator/dashboard',
        program_head: '/program-head/dashboard',
        vpaa: '/vpaa/dashboard',
        admin: '/admin/dashboard',
        supervisor: '/supervisor/dashboard',
      };

      toast.success(`Welcome back, ${data.user.name.split(' ')[0]}!`);
      navigate(roleRoutes[data.user.role] || '/');
    } catch (error) {
      const message = error.response?.data?.errors?.login?.[0]
        || error.response?.data?.message
        || 'We could not sign you in. Please check your credentials.';
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="min-h-screen bg-[#f7f3ee] lg:grid lg:grid-cols-[1.08fr_0.92fr]">
      <section className="relative hidden min-h-screen overflow-hidden bg-[#5a0707] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
        <div className="absolute -right-32 -top-32 h-96 w-96 rounded-full border-[62px] border-white/[0.035]" />
        <div className="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-[#d4af37]/10 blur-3xl" />

        <Link to="/" className="relative z-10 flex items-center gap-3 self-start">
          <span className="rounded-full bg-white p-1.5 shadow-lg"><img src={marsuLogo} alt="Marinduque State University seal" className="h-12 w-12 object-contain" /></span>
          <span><strong className="block text-sm font-extrabold">Marinduque State University</strong><span className="text-xs text-white/60">Santa Cruz Campus</span></span>
        </Link>

        <div className="relative z-10 mx-auto my-10 w-full max-w-2xl">
          <div className="overflow-hidden rounded-[2rem] border border-white/15 bg-white/10 p-2 shadow-2xl backdrop-blur">
            <img src={heroImage} alt="Students collaborating through AIMS" className="aspect-[16/10] w-full rounded-[1.55rem] object-cover" />
          </div>
          <div className="mt-8 max-w-xl">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-[#e5c351]">Academic Internship Monitoring System</p>
            <h1 className="mt-4 text-4xl font-black leading-tight tracking-tight xl:text-5xl">Welcome to a more connected internship experience.</h1>
            <p className="mt-4 text-base leading-7 text-white/65">Track progress, manage requirements, and collaborate securely from deployment to completion.</p>
          </div>
        </div>

        <div className="relative z-10 flex gap-6 text-xs font-semibold text-white/55">
          <span className="flex items-center gap-2"><FiCheckCircle className="text-[#e5c351]" /> Secure university portal</span>
          <span className="flex items-center gap-2"><FiCheckCircle className="text-[#e5c351]" /> Role-based access</span>
        </div>
      </section>

      <section className="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
        <div className="w-full max-w-md">
          <div className="mb-8 flex items-center justify-between lg:hidden">
            <Link to="/" className="flex items-center gap-3">
              <img src={marsuLogo} alt="Marinduque State University seal" className="h-12 w-12 object-contain" />
              <span><strong className="block text-sm text-[#720b0b]">Marinduque State University</strong><span className="text-xs text-slate-500">AIMS · Santa Cruz Campus</span></span>
            </Link>
          </div>

          <Link to="/" className="mb-9 inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-[#800000]">
            <FiArrowLeft /> Back to home
          </Link>

          <div>
            <p className="text-sm font-extrabold uppercase tracking-[0.18em] text-[#a8750b]">Secure portal access</p>
            <h2 className="mt-3 text-3xl font-black tracking-tight text-[#430909] sm:text-4xl">Welcome back</h2>
            <p className="mt-3 text-sm leading-6 text-slate-500">Enter your university email or Student ID to continue to AIMS.</p>
          </div>

          <form onSubmit={handleSubmit} className="mt-9 space-y-5">
            <div>
              <label htmlFor="login" className="mb-2 block text-sm font-extrabold text-slate-700">Username / Student ID</label>
              <div className="relative">
                <FiUser className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  id="login"
                  name="login"
                  type="text"
                  autoComplete="username"
                  value={identifier}
                  onChange={(event) => setIdentifier(event.target.value)}
                  required
                  className="w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-11 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#800000]/45 focus:ring-4 focus:ring-[#800000]/8"
                  placeholder="Email address or Student ID"
                />
              </div>
            </div>

            <div>
              <label htmlFor="password" className="mb-2 block text-sm font-extrabold text-slate-700">Password</label>
              <div className="relative">
                <FiLock className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  required
                  className="w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-11 pr-12 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#800000]/45 focus:ring-4 focus:ring-[#800000]/8"
                  placeholder="Enter your password"
                />
                <button type="button" onClick={() => setShowPassword((visible) => !visible)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-[#800000]" aria-label={showPassword ? 'Hide password' : 'Show password'}>
                  {showPassword ? <FiEyeOff /> : <FiEye />}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between gap-4">
              <label className="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-[#800000]" />
                Remember me
              </label>
              <Link to="/forgot-password" className="text-sm font-extrabold text-[#800000] hover:text-[#5c0000]">Forgot password?</Link>
            </div>

            <button type="submit" disabled={loading} className="flex w-full items-center justify-center rounded-xl bg-[#800000] px-5 py-3.5 text-sm font-extrabold text-white shadow-xl shadow-[#800000]/15 transition hover:-translate-y-0.5 hover:bg-[#650000] disabled:cursor-not-allowed disabled:opacity-60">
              {loading ? <><span className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/35 border-t-white" /> Signing in...</> : 'Login to AIMS'}
            </button>
          </form>

          <div className="mt-8 border-t border-slate-200 pt-7 text-center">
            <p className="text-sm text-slate-500">New student intern? <Link to="/register" className="font-extrabold text-[#800000] hover:text-[#5c0000]">Register as Student</Link></p>
          </div>

          <p className="mt-10 text-center text-xs leading-5 text-slate-400">By continuing, you agree to the university’s acceptable use and data privacy policies.</p>
        </div>
      </section>
    </main>
  );
}

export default LoginPage;
