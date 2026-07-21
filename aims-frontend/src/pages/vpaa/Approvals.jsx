import { CheckCircleIcon } from '@heroicons/react/24/outline';

function VPAAApprovals() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-800">Approval Management</h1>
      <div className="bg-white rounded-xl shadow-sm p-6 text-center py-12">
        <CheckCircleIcon className="w-12 h-12 mx-auto mb-4 text-gray-300" />
        <p className="text-gray-500">No pending approvals</p>
      </div>
    </div>
  );
}

export default VPAAApprovals;
