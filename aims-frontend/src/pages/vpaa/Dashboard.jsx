import UniversityInternshipDashboard from '../../components/dashboard/UniversityInternshipDashboard';

function VPAADashboard() {
  return (
    <UniversityInternshipDashboard
      apiEndpoint="/vpaa/dashboard"
      audienceLabel="VPAA Executive Oversight"
      dashboardTitle="VPAA Internship Dashboard"
      dashboardSubtitle="A university-wide executive view of internship performance, compliance, attendance, approvals, and student travel."
      executive
      showMoaApprovals
      pageRoutes={{
        analytics: '/vpaa/reports',
        attendance: '/vpaa/reports',
        evaluations: '/vpaa/approvals',
        approvals: '/vpaa/approvals',
        moas: '/vpaa/moas',
      }}
    />
  );
}

export default VPAADashboard;
