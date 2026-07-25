import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from './context/AuthContext';
import { ThemeProvider, useTheme } from './context/ThemeContext';
import PrivateRoute from './routes/PrivateRoute';
import RoleBasedRoute from './routes/RoleBasedRoute';

// Auth Pages
import LoginPage from './pages/auth/LoginPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import RegisterPage from './pages/auth/RegisterPage';
import LandingPage from './pages/public/LandingPage';

// Layout
import DashboardLayout from './layouts/DashboardLayout';

// Student Pages
import StudentDashboard from './pages/student/Dashboard';
import StudentAttendance from './pages/student/Attendance';
import StudentRequirements from './pages/student/Requirements';
import StudentTravel from './pages/student/Travel';
import StudentAnnouncements from './pages/student/Announcements';
import StudentProfile from './pages/student/Profile';

// Coordinator Pages
import CoordinatorDashboard from './pages/coordinator/Dashboard';
import CoordinatorStudents from './pages/coordinator/StudentManagement';
import CoordinatorAttendance from './pages/coordinator/AttendanceManagement';
import CoordinatorHTE from './pages/coordinator/HTEManagement';
import CoordinatorTravel from './pages/coordinator/TravelMonitoring';
import CoordinatorReports from './pages/coordinator/Reports';
import CoordinatorAnnouncements from './pages/coordinator/Announcements';
import CoordinatorProfile from './pages/coordinator/Profile';

// Program Head Pages
import ProgramHeadDashboard from './pages/program-head/Dashboard';
import ProgramHeadDocuments from './pages/program-head/DocumentReview';
import ProgramHeadTravel from './pages/program-head/TravelMonitoringManagement';
import ProgramHeadReports from './pages/program-head/Reports';
import ProgramHeadAnnouncements from './pages/program-head/Announcements';
import ProgramHeadStudentMonitoring from './pages/program-head/StudentMonitoring';

// VPAA Pages
import VPAADashboard from './pages/vpaa/Dashboard';
import VPAAApprovals from './pages/vpaa/Approvals';
import VPAAMOA from './pages/vpaa/MOAApproval';
import VPAAAnnouncements from './pages/vpaa/Announcements';
import VPAAReports from './pages/vpaa/Reports';

// Admin Pages
import AdminDashboard from './pages/admin/Dashboard';
import AdminUsers from './pages/admin/UserManagement';
import AdminAcademic from './pages/admin/AcademicManagement';
import AdminSystem from './pages/admin/SystemSettings';
import AdminAudit from './pages/admin/AuditLogs';
import AdminReports from './pages/admin/Reports';

// Supervisor Pages
import SupervisorDashboard from './pages/supervisor/Dashboard';
import SupervisorProgress from './pages/supervisor/StudentProgress';
import SupervisorEvaluations from './pages/supervisor/Evaluations';
import SupervisorAttendance from './pages/supervisor/AttendanceReview';
import SupervisorAnnouncements from './pages/supervisor/Announcements';
import Notifications from './pages/shared/Notifications';

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

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <Router>
          <ThemedToaster />
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
        </Router>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;
