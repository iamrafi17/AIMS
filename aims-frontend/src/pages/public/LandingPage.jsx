import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiArrowRight,
  FiBarChart2,
  FiBookOpen,
  FiBriefcase,
  FiCalendar,
  FiCheckCircle,
  FiChevronRight,
  FiClipboard,
  FiClock,
  FiFacebook,
  FiFileText,
  FiGlobe,
  FiGrid,
  FiInstagram,
  FiMail,
  FiMapPin,
  FiMenu,
  FiMessageSquare,
  FiPhone,
  FiShield,
  FiTrendingUp,
  FiUserCheck,
  FiUsers,
  FiX,
} from 'react-icons/fi';
import api from '../../services/api';
import heroImage from '../../assets/aims-portal-hero.png';
import marsuLogo from '../../assets/marsu-logo.png';

const featureGroups = [
  {
    eyebrow: 'For student interns',
    title: 'Student Portal',
    description: 'Everything students need to complete internship requirements with confidence.',
    icon: FiBookOpen,
    items: [
      { icon: FiClock, label: 'Online Attendance' },
      { icon: FiClipboard, label: 'Daily Journal Submission' },
      { icon: FiTrendingUp, label: 'Internship Progress Monitoring' },
      { icon: FiMapPin, label: 'Travel Monitoring' },
    ],
  },
  {
    eyebrow: 'For internship offices',
    title: 'Coordinator Portal',
    description: 'Coordinate placements, verify records, and keep every student on track.',
    icon: FiUsers,
    items: [
      { icon: FiUserCheck, label: 'Student Management' },
      { icon: FiBriefcase, label: 'HTE Management' },
      { icon: FiClock, label: 'Attendance Monitoring' },
      { icon: FiFileText, label: 'Report Generation' },
    ],
  },
  {
    eyebrow: 'For industry mentors',
    title: 'Supervisor Portal',
    description: 'Provide timely feedback and monitor each intern’s workplace performance.',
    icon: FiUserCheck,
    items: [
      { icon: FiCheckCircle, label: 'Student Evaluation' },
      { icon: FiBarChart2, label: 'Performance Monitoring' },
    ],
  },
  {
    eyebrow: 'For university leaders',
    title: 'Administration',
    description: 'Make informed decisions using consistent workflows and campus-wide insights.',
    icon: FiGrid,
    items: [
      { icon: FiShield, label: 'Approval Workflows' },
      { icon: FiBarChart2, label: 'Analytics Dashboard' },
      { icon: FiFileText, label: 'System Reports' },
    ],
  },
];

const fallbackAnnouncements = [
  {
    id: 'advisory',
    title: 'Internship advisories and reminders',
    content: 'Important internship policies, deadlines, and campus updates will appear here.',
    published_at: null,
  },
  {
    id: 'orientation',
    title: 'Student pre-deployment orientation',
    content: 'Watch this space for the next orientation schedule and required documents.',
    published_at: null,
  },
  {
    id: 'partners',
    title: 'Partner HTE announcements',
    content: 'New placement opportunities and host training establishment updates are posted here.',
    published_at: null,
  },
];

function Reveal({ as: Tag = 'div', children, className = '', delay = 0 }) {
  const elementRef = useRef(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const element = elementRef.current;
    if (!element) return undefined;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
      setVisible(true);
      return undefined;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true);
          observer.unobserve(entry.target);
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -48px' },
    );

    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  return (
    <Tag
      ref={elementRef}
      className={`landing-reveal ${visible ? 'is-visible' : ''} ${className}`}
      style={{ '--reveal-delay': `${delay}ms` }}
    >
      {children}
    </Tag>
  );
}

function LandingPage() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [portalData, setPortalData] = useState({
    statistics: {
      student_interns: 0,
      partner_htes: 0,
      completed_internships: 0,
      active_supervisors: 0,
    },
    announcements: [],
  });

  useEffect(() => {
    api.get('/portal')
      .then((response) => setPortalData(response.data))
      .catch(() => {
        // The public page remains usable while the backend is being initialized.
      });
  }, []);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 12);
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const closeMenu = () => setMenuOpen(false);
  const announcements = portalData.announcements.length
    ? portalData.announcements
    : fallbackAnnouncements;

  const stats = [
    { label: 'Total Student Interns', value: portalData.statistics.student_interns, icon: FiUsers },
    { label: 'Partner HTEs', value: portalData.statistics.partner_htes, icon: FiBriefcase },
    { label: 'Completed Internships', value: portalData.statistics.completed_internships, icon: FiCheckCircle },
    { label: 'Active Supervisors', value: portalData.statistics.active_supervisors, icon: FiUserCheck },
  ];

  return (
    <div className="min-h-screen bg-[#fbfaf8] text-slate-900">
      <div className="bg-[#5c0000] px-4 py-2.5 text-center text-xs font-semibold tracking-wide text-white sm:text-sm">
        Official Internship Portal of Marinduque State University — Santa Cruz Campus
      </div>

      <header className={`sticky top-0 z-50 border-b border-black/5 bg-white/95 backdrop-blur-xl transition-[box-shadow,background-color] duration-300 ${scrolled ? 'shadow-lg shadow-slate-900/5' : 'shadow-none'}`}>
        <nav className="mx-auto flex max-w-7xl items-center justify-between px-5 py-3 lg:px-8" aria-label="Main navigation">
          <Link to="/" className="flex min-w-0 items-center gap-3" onClick={closeMenu}>
            <img src={marsuLogo} alt="Marinduque State University seal" className="h-12 w-12 shrink-0 object-contain" />
            <div className="min-w-0 leading-tight">
              <p className="truncate text-sm font-extrabold tracking-tight text-[#720b0b] sm:text-base">Marinduque State University</p>
              <p className="truncate text-[11px] font-medium text-slate-500 sm:text-xs">Santa Cruz Campus · AIMS</p>
            </div>
          </Link>

          <div className="hidden items-center gap-7 lg:flex">
            {[
              ['Home', '#home'],
              ['About AIMS', '#about'],
              ['Features', '#features'],
              ['Announcements', '#announcements'],
              ['Contact Us', '#contact'],
            ].map(([label, href]) => (
              <a key={href} href={href} className="landing-nav-link text-sm font-semibold text-slate-600 transition hover:text-[#800000]">
                {label}
              </a>
            ))}
            <Link to="/login" className="rounded-xl bg-[#800000] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#800000]/15 transition hover:-translate-y-0.5 hover:bg-[#650000]">
              Login
            </Link>
          </div>

          <button
            type="button"
            className="rounded-xl border border-slate-200 p-2.5 text-slate-700 lg:hidden"
            onClick={() => setMenuOpen((open) => !open)}
            aria-label="Toggle navigation menu"
            aria-expanded={menuOpen}
          >
            {menuOpen ? <FiX size={21} /> : <FiMenu size={21} />}
          </button>
        </nav>

        {menuOpen && (
          <div className="landing-mobile-menu border-t border-slate-100 bg-white px-5 py-5 shadow-xl lg:hidden">
            <div className="mx-auto flex max-w-7xl flex-col gap-1">
              {[
                ['Home', '#home'],
                ['About AIMS', '#about'],
                ['Features', '#features'],
                ['Announcements', '#announcements'],
                ['Contact Us', '#contact'],
              ].map(([label, href]) => (
                <a key={href} href={href} onClick={closeMenu} className="rounded-lg px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#800000]/5 hover:text-[#800000]">
                  {label}
                </a>
              ))}
              <Link to="/login" onClick={closeMenu} className="mt-2 rounded-xl bg-[#800000] px-4 py-3 text-center text-sm font-bold text-white">
                Login to AIMS
              </Link>
            </div>
          </div>
        )}
      </header>

      <main>
        <section id="home" className="relative overflow-hidden bg-[radial-gradient(circle_at_12%_20%,rgba(212,175,55,0.15),transparent_27%),linear-gradient(135deg,#fff_0%,#fffaf3_48%,#f8eeee_100%)] scroll-mt-24">
          <div className="absolute -left-20 top-24 h-72 w-72 rounded-full border-[42px] border-[#800000]/5" />
          <div className="mx-auto grid min-h-[calc(100vh-110px)] max-w-7xl items-center gap-12 px-5 py-16 lg:grid-cols-[0.88fr_1.12fr] lg:px-8 lg:py-20">
            <div className="landing-hero-copy relative z-10">
              <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d4af37]/35 bg-white/80 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-[#7b1818] shadow-sm">
                <FiShield className="text-[#c39219]" />
                One connected internship ecosystem
              </div>
              <h1 className="max-w-3xl text-4xl font-black leading-[1.05] tracking-[-0.045em] text-[#3b0808] sm:text-5xl lg:text-[4.35rem]">
                Academic Internship <span className="text-[#800000]">Monitoring System</span>{' '}
                <span className="inline-block align-middle text-[0.3em] tracking-[0.08em] text-[#a8750b]">(AIMS)</span>
              </h1>
              <p className="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">
                Empowering student internships through digital monitoring and collaboration—built for students, coordinators, supervisors, and university leaders.
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Link to="/login" className="group inline-flex items-center justify-center gap-2 rounded-xl bg-[#800000] px-6 py-3.5 text-sm font-extrabold text-white shadow-xl shadow-[#800000]/20 transition hover:-translate-y-0.5 hover:bg-[#650000]">
                  Login to AIMS <FiArrowRight className="transition group-hover:translate-x-1" />
                </Link>
                <Link to="/register" className="inline-flex items-center justify-center gap-2 rounded-xl border border-[#800000]/20 bg-white px-6 py-3.5 text-sm font-extrabold text-[#800000] shadow-sm transition hover:-translate-y-0.5 hover:border-[#800000]/40">
                  Register as Student <FiChevronRight />
                </Link>
              </div>
              <div className="mt-9 grid max-w-xl grid-cols-1 gap-3 text-sm text-slate-600 sm:grid-cols-3">
                {['Secure records', 'Real-time progress', 'Campus-wide access'].map((item) => (
                  <div key={item} className="flex items-center gap-2 font-semibold">
                    <FiCheckCircle className="shrink-0 text-[#b28212]" /> {item}
                  </div>
                ))}
              </div>
            </div>

            <div className="landing-hero-visual relative">
              <div className="landing-hero-glow absolute -inset-4 rounded-[2.5rem] bg-gradient-to-br from-[#800000]/12 to-[#d4af37]/20 blur-2xl" />
              <div className="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/70 p-2 shadow-2xl shadow-[#5c0000]/15 backdrop-blur">
                <img src={heroImage} alt="Student interns using the AIMS digital monitoring platform" className="aspect-[3/2] w-full rounded-[1.55rem] object-cover" />
              </div>
              <div className="landing-float absolute -bottom-5 left-4 flex items-center gap-3 rounded-2xl border border-white bg-white/95 p-3 pr-5 shadow-xl sm:left-[-1.5rem] sm:p-4 sm:pr-7">
                <div className="grid h-11 w-11 place-items-center rounded-xl bg-[#800000] text-white"><FiTrendingUp /></div>
                <div><p className="text-xs font-semibold text-slate-500">Internship journey</p><p className="text-sm font-black text-slate-900">Monitored. Guided. Achieved.</p></div>
              </div>
            </div>
          </div>
        </section>

        <section id="about" className="scroll-mt-24 bg-white py-20 sm:py-24">
          <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-[0.78fr_1.22fr] lg:items-center lg:px-8">
            <Reveal>
              <p className="text-sm font-extrabold uppercase tracking-[0.2em] text-[#a8750b]">About AIMS</p>
              <h2 className="mt-4 text-3xl font-black tracking-tight text-[#430909] sm:text-4xl">Designed around the complete internship journey.</h2>
            </Reveal>
            <Reveal delay={120} className="rounded-3xl border border-slate-100 bg-[#fbfaf8] p-7 shadow-sm sm:p-10">
              <p className="text-base leading-8 text-slate-600 sm:text-lg">
                The Academic Internship Monitoring System is a web-based platform developed for Marinduque State University to streamline internship management, student monitoring, attendance tracking, document submission, and communication between students, coordinators, supervisors, and university administrators.
              </p>
              <div className="mt-8 grid gap-5 sm:grid-cols-3">
                {[
                  [FiGlobe, 'Connected', 'One portal for every stakeholder'],
                  [FiShield, 'Accountable', 'Reliable records and approvals'],
                  [FiMessageSquare, 'Collaborative', 'Clear, timely communication'],
                ].map(([Icon, title, copy]) => (
                  <div key={title}>
                    <Icon className="mb-3 text-xl text-[#800000]" />
                    <p className="font-extrabold text-slate-900">{title}</p>
                    <p className="mt-1 text-sm leading-6 text-slate-500">{copy}</p>
                  </div>
                ))}
              </div>
            </Reveal>
          </div>
        </section>

        <section id="features" className="scroll-mt-24 bg-[#f7f5f2] py-20 sm:py-24">
          <div className="mx-auto max-w-7xl px-5 lg:px-8">
            <Reveal className="mx-auto max-w-2xl text-center">
              <p className="text-sm font-extrabold uppercase tracking-[0.2em] text-[#a8750b]">Key features</p>
              <h2 className="mt-4 text-3xl font-black tracking-tight text-[#430909] sm:text-4xl">One platform. Every internship role.</h2>
              <p className="mt-4 leading-7 text-slate-600">Purpose-built tools help each member of the internship community act with clarity.</p>
            </Reveal>
            <div className="mt-12 grid gap-6 lg:grid-cols-2">
              {featureGroups.map((group, index) => {
                const GroupIcon = group.icon;
                return (
                  <Reveal as="article" key={group.title} delay={(index % 2) * 110} className="group rounded-3xl border border-black/[0.06] bg-white p-7 shadow-sm transition-[transform,box-shadow,border-color] duration-500 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-[#800000]/5 sm:p-8">
                    <div className="flex items-start gap-4">
                      <div className="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-[#800000] text-xl text-white shadow-lg shadow-[#800000]/15">
                        <GroupIcon />
                      </div>
                      <div>
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#ad7f16]">{group.eyebrow}</p>
                        <h3 className="mt-1 text-2xl font-black text-[#430909]">{group.title}</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-500">{group.description}</p>
                      </div>
                    </div>
                    <div className="mt-7 grid gap-3 sm:grid-cols-2">
                      {group.items.map(({ icon: ItemIcon, label }) => (
                        <div key={label} className="flex items-center gap-3 rounded-xl bg-[#faf8f5] px-4 py-3 text-sm font-bold text-slate-700">
                          <ItemIcon className="shrink-0 text-[#9b1717]" /> {label}
                        </div>
                      ))}
                    </div>
                  </Reveal>
                );
              })}
            </div>
          </div>
        </section>

        <section className="bg-[#710808] py-16 text-white sm:py-20">
          <div className="mx-auto max-w-7xl px-5 lg:px-8">
            <Reveal className="mb-10 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
              <div>
                <p className="text-sm font-bold uppercase tracking-[0.2em] text-[#f1cb63]">AIMS at a glance</p>
                <h2 className="mt-3 text-3xl font-black tracking-tight">Internship impact, visible in real time.</h2>
              </div>
              <p className="max-w-lg text-sm leading-6 text-white/65">Live totals are sourced from the AIMS records maintained by the university internship community.</p>
            </Reveal>
            <div className="grid gap-px overflow-hidden rounded-3xl border border-white/10 bg-white/10 sm:grid-cols-2 lg:grid-cols-4">
              {stats.map(({ label, value, icon: Icon }, index) => (
                <Reveal key={label} delay={index * 90} className="landing-stat-card bg-[#710808] p-7 sm:p-8">
                  <Icon className="text-2xl text-[#f1cb63]" />
                  <p className="mt-6 text-4xl font-black tabular-nums">{Number(value).toLocaleString()}</p>
                  <p className="mt-2 text-sm font-semibold text-white/65">{label}</p>
                </Reveal>
              ))}
            </div>
          </div>
        </section>

        <section id="announcements" className="scroll-mt-24 bg-white py-20 sm:py-24">
          <div className="mx-auto max-w-7xl px-5 lg:px-8">
            <Reveal className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
              <div>
                <p className="text-sm font-extrabold uppercase tracking-[0.2em] text-[#a8750b]">Announcements</p>
                <h2 className="mt-4 text-3xl font-black tracking-tight text-[#430909] sm:text-4xl">Stay informed and internship-ready.</h2>
              </div>
              <Link to="/login" className="inline-flex items-center gap-2 text-sm font-extrabold text-[#800000]">View your portal <FiArrowRight /></Link>
            </Reveal>
            <div className="mt-10 grid gap-5 lg:grid-cols-3">
              {announcements.slice(0, 3).map((announcement, index) => (
                <Reveal as="article" key={announcement.id} delay={index * 100} className="rounded-3xl border border-slate-100 bg-[#fbfaf8] p-7 transition-[transform,box-shadow,border-color] duration-500 hover:-translate-y-1 hover:border-[#800000]/15 hover:shadow-lg">
                  <div className="flex items-center justify-between gap-4">
                    <span className="rounded-full bg-[#800000]/8 px-3 py-1 text-xs font-extrabold text-[#800000]">
                      {index === 0 ? 'Internship Advisory' : index === 1 ? 'University Update' : 'Upcoming Activity'}
                    </span>
                    <FiCalendar className="text-[#ba8b23]" />
                  </div>
                  <h3 className="mt-6 text-xl font-black leading-snug text-[#430909]">{announcement.title}</h3>
                  <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{announcement.content}</p>
                  <p className="mt-6 text-xs font-semibold text-slate-400">
                    {announcement.published_at
                      ? new Date(announcement.published_at).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })
                      : 'Details available in AIMS'}
                  </p>
                </Reveal>
              ))}
            </div>
          </div>
        </section>

        <section className="bg-[#f6f1ea] px-5 py-12 sm:py-16">
          <Reveal className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-7 rounded-[2rem] bg-[#430909] px-7 py-10 text-center text-white shadow-2xl shadow-[#430909]/15 sm:px-12 lg:flex-row lg:text-left">
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.18em] text-[#eac557]">Start your internship journey</p>
              <h2 className="mt-3 text-2xl font-black sm:text-3xl">Ready to connect with AIMS?</h2>
              <p className="mt-2 text-sm text-white/65">Register your student account or continue to your secure portal.</p>
            </div>
            <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
              <Link to="/register" className="rounded-xl bg-[#d4af37] px-6 py-3.5 text-sm font-black text-[#3b0808] transition hover:bg-[#e4c45f]">Register as Student</Link>
              <Link to="/login" className="rounded-xl border border-white/20 px-6 py-3.5 text-sm font-black text-white transition hover:bg-white/10">Login</Link>
            </div>
          </Reveal>
        </section>
      </main>

      <footer id="contact" className="scroll-mt-24 bg-[#260505] text-white">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-[1.35fr_1fr_1fr] lg:px-8">
          <div>
            <div className="flex items-center gap-3">
              <div className="rounded-full bg-white p-1"><img src={marsuLogo} alt="" className="h-12 w-12 object-contain" /></div>
              <div><p className="font-black">Marinduque State University</p><p className="text-sm text-white/55">Santa Cruz Campus</p></div>
            </div>
            <p className="mt-5 max-w-md text-sm leading-6 text-white/55">Academic Internship Monitoring System — supporting meaningful, accountable, and industry-connected student experiences.</p>
          </div>
          <div>
            <h3 className="font-extrabold">Contact information</h3>
            <div className="mt-5 space-y-3 text-sm text-white/60">
              <p className="flex items-start gap-3"><FiMapPin className="mt-0.5 shrink-0 text-[#d4af37]" /> Santa Cruz, Marinduque, Philippines</p>
              <a href="mailto:info@marsu.edu.ph" className="flex items-center gap-3 hover:text-white"><FiMail className="text-[#d4af37]" /> info@marsu.edu.ph</a>
              <p className="flex items-center gap-3"><FiPhone className="text-[#d4af37]" /> University trunk line</p>
            </div>
          </div>
          <div>
            <h3 className="font-extrabold">Connect with MarSU</h3>
            <div className="mt-5 flex gap-3">
              {[FiFacebook, FiInstagram, FiGlobe].map((Icon, index) => (
                <a key={index} href="#contact" aria-label={['Facebook', 'Instagram', 'University website'][index]} className="grid h-10 w-10 place-items-center rounded-xl bg-white/8 text-white/70 transition hover:bg-[#d4af37] hover:text-[#3b0808]"><Icon /></a>
              ))}
            </div>
            <a href="#privacy" className="mt-6 inline-block text-sm font-semibold text-white/55 hover:text-white">Data Privacy Notice</a>
          </div>
        </div>
        <div className="border-t border-white/8 px-5 py-5 text-center text-xs text-white/40">
          Copyright © {new Date().getFullYear()} Marinduque State University. All rights reserved.
        </div>
      </footer>
    </div>
  );
}

export default LandingPage;
