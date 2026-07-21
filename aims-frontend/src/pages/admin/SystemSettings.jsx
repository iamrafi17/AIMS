import { CogIcon } from '@heroicons/react/24/outline';

function AdminSystem() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-800">System Settings</h1>
      <div className="bg-white rounded-xl shadow-sm p-6 text-center py-12">
        <CogIcon className="w-12 h-12 mx-auto mb-4 text-gray-300" />
        <p className="text-gray-500">System Settings module coming soon</p>
      </div>
    </div>
  );
}

export default AdminSystem;
