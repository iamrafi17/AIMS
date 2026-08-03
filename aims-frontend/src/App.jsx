import { lazy, Suspense } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from './context/AuthContext';
import { ThemeProvider } from './context/ThemeContext';
import useTheme from './context/useTheme';
import PrivateRoute from './routes/PrivateRoute';
import RoleBasedRoute from './routes/RoleBasedRoute';

// Auth Pages
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const ForgotPasswordPage = lazy(() => import('./pages/auth/ForgotPasswordPage'));
const RegisterPage = lazy(() => import('./pages/auth/RegisterPage'));
const LandingPage = lazy(() => import('./pages/public/LandingPage'));

// Layout
import DashboardLayout from './layouts/DashboardLayout';

// Student Pages
const StudentDashboard = lazy(() => import('./pages/student/Dashboard'));
const StudentAttendance = lazy(() => import('./pages/student/Attendance'));
const StudentRequirements = lazy(() => import('./pages/student/Requirements'));
const StudentTravel = lazy(() => import('./pages/student/Travel'));
const StudentAnnouncements = lazy(() => import('./pages/student/Announcements'));
const StudentProfile = lazy(() => import('./pages/student/Profile'));

// Coordinator Pages
const CoordinatorDashboard = lazy(() => import('./pages/coordinator/Dashboard'));
const CoordinatorStudents = lazy(() => import('./pages/coordinator/StudentManagement'));
const CoordinatorAttendance = lazy(() => import('./pages/coordinator/AttendanceManagement'));
const CoordinatorHTE = lazy(() => import('./pages/coordinator/HTEManagement'));
const CoordinatorTravel = lazy(() => import('./pages/coordinator/TravelMonitoring'));
const CoordinatorReports = lazy(() => import('./pages/coordinator/Reports'));
const CoordinatorAnnouncements = lazy(() => import('./pages/coordinator/Announcements'));
const CoordinatorProfile = lazy(() => import('./pages/coordinator/Profile'));

// Program Head Pages
const ProgramHeadDashboard = lazy(() => import('./pages/program-head/Dashboard'));
const ProgramHeadDocuments = lazy(() => import('./pages/program-head/DocumentReview'));
const ProgramHeadTravel = lazy(() => import('./pages/program-head/TravelMonitoringManagement'));
const ProgramHeadReports = lazy(() => import('./pages/program-head/Reports'));
const ProgramHeadAnnouncements = lazy(() => import('./pages/program-head/Announcements'));
const ProgramHeadStudentMonitoring = lazy(() => import('./pages/program-head/StudentMonitoring'));

// VPAA Pages
const VPAADashboard = lazy(() => import('./pages/vpaa/Dashboard'));
const VPAAApprovals = lazy(() => import('./pages/vpaa/Approvals'));
const VPAAMOA = lazy(() => import('./pages/vpaa/MOAApproval'));
const VPAAAnnouncements = lazy(() => import('./pages/vpaa/Announcements'));
const VPAAReports = lazy(() => import('./pages/vpaa/Reports'));

// Admin Pages
const AdminDashboard = lazy(() => import('./pages/admin/Dashboard'));
const AdminUsers = lazy(() => import('./pages/admin/UserManagement'));
const AdminAcademic = lazy(() => import('./pages/admin/AcademicManagement'));
const AdminSystem = lazy(() => import('./pages/admin/SystemSettings'));
const AdminAudit = lazy(() => import('./pages/admin/AuditLogs'));
const AdminReports = lazy(() => import('./pages/admin/Reports'));

// Supervisor Pages
const SupervisorDashboard = lazy(() => import('./pages/supervisor/Dashboard'));
const SupervisorProgress = lazy(() => import('./pages/supervisor/StudentProgress'));
const SupervisorEvaluations = lazy(() => import('./pages/supervisor/Evaluations'));
const SupervisorAttendance = lazy(() => import('./pages/supervisor/AttendanceReview'));
const SupervisorAnnouncements = lazy(() => import('./pages/supervisor/Announcements'));
const Notifications = lazy(() => import('./pages/shared/Notifications'));

function ThemedToaster() {
  const { darkMode } = useTheme();

  return (
    <Toaster
      position="top-right"
      toastOptions={{
        style: {
          background: darkMode ? '#111827' : '#ffffff',
          color: darkMode ? '#f9fafb' : '#1f2937',
          border: `1px solid ${darkMode ? '#374151' : '#e5e7eb'}`,
        },
      }}
    />
  );
}

function PageLoader() {
  return (
    <div className="grid min-h-screen place-items-center bg-slate-50 dark:bg-gray-950">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-[#800000]" />
    </div>
  );
}

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <Router>
          <ThemedToaster />
          <Suspense fallback={<PageLoader />}>
            <Routes>
            {/* Public Routes */}
            <Route path="/login" element={<LoginPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/" element={<LandingPage />} />

            {/* Protected Routes */}
            <Route element={<PrivateRoute />}>
              <Route element={<DashboardLayout />}>
                {/* Student Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['student']} />}>
                  <Route path="/student/dashboard" element={<StudentDashboard />} />
                  <Route path="/student/attendance" element={<StudentAttendance />} />
                  <Route path="/student/requirements" element={<StudentRequirements />} />
                  <Route path="/student/travel" element={<StudentTravel />} />
                  <Route path="/student/announcements" element={<StudentAnnouncements />} />
                  <Route path="/student/profile" element={<StudentProfile />} />
                  <Route path="/student/notifications" element={<Notifications />} />
                </Route>

                {/* Coordinator Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['coordinator']} />}>
                  <Route path="/coordinator/dashboard" element={<CoordinatorDashboard />} />
                  <Route path="/coordinator/students" element={<CoordinatorStudents />} />
                  <Route path="/coordinator/attendance" element={<CoordinatorAttendance />} />
                  <Route path="/coordinator/htes" element={<CoordinatorHTE />} />
                  <Route path="/coordinator/travel" element={<CoordinatorTravel />} />
                  <Route path="/coordinator/reports" element={<CoordinatorReports />} />
                  <Route path="/coordinator/announcements" element={<CoordinatorAnnouncements />} />
                  <Route path="/coordinator/profile" element={<CoordinatorProfile />} />
                  <Route path="/coordinator/notifications" element={<Notifications />} />
                </Route>

                {/* Program Head Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['program_head']} />}>
                  <Route path="/program-head/dashboard" element={<ProgramHeadDashboard />} />
                  <Route path="/program-head/documents" element={<ProgramHeadDocuments />} />
                  <Route path="/program-head/students" element={<ProgramHeadStudentMonitoring />} />
                  <Route path="/program-head/travel" element={<ProgramHeadTravel />} />
                  <Route path="/program-head/reports" element={<ProgramHeadReports />} />
                  <Route path="/program-head/announcements" element={<ProgramHeadAnnouncements />} />
                  <Route path="/program-head/profile" element={<CoordinatorProfile />} />
                  <Route path="/program-head/notifications" element={<Notifications />} />
                </Route>

                {/* VPAA Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['vpaa']} />}>
                  <Route path="/vpaa/dashboard" element={<VPAADashboard />} />
                  <Route path="/vpaa/approvals" element={<VPAAApprovals />} />
                  <Route path="/vpaa/moas" element={<VPAAMOA />} />
                  <Route path="/vpaa/announcements" element={<VPAAAnnouncements />} />
                  <Route path="/vpaa/reports" element={<VPAAReports />} />
                  <Route path="/vpaa/travel" element={<ProgramHeadTravel apiBase="/vpaa/travel" />} />
                  <Route path="/vpaa/profile" element={<CoordinatorProfile />} />
                  <Route path="/vpaa/notifications" element={<Notifications />} />
                </Route>

                {/* Admin Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['admin']} />}>
                  <Route path="/admin/dashboard" element={<AdminDashboard />} />
                  <Route path="/admin/users" element={<AdminUsers />} />
                  <Route path="/admin/academic" element={<AdminAcademic />} />
                  <Route path="/admin/system" element={<AdminSystem />} />
                  <Route path="/admin/audit" element={<AdminAudit />} />
                  <Route path="/admin/reports" element={<AdminReports />} />
                  <Route path="/admin/profile" element={<CoordinatorProfile />} />
                  <Route path="/admin/notifications" element={<Notifications />} />
                  <Route path="/admin/announcements" element={<StudentAnnouncements />} />
                </Route>

                {/* Supervisor Routes */}
                <Route element={<RoleBasedRoute allowedRoles={['supervisor']} />}>
                  <Route path="/supervisor/dashboard" element={<SupervisorDashboard />} />
                  <Route path="/supervisor/progress" element={<SupervisorProgress />} />
                  <Route path="/supervisor/evaluations" element={<SupervisorEvaluations />} />
                  <Route path="/supervisor/attendance" element={<SupervisorAttendance />} />
                  <Route path="/supervisor/announcements" element={<SupervisorAnnouncements />} />
                  <Route path="/supervisor/profile" element={<CoordinatorProfile />} />
                  <Route path="/supervisor/notifications" element={<Notifications />} />
                </Route>
              </Route>
            </Route>

            {/* 404 */}
            <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Suspense>
        </Router>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;
