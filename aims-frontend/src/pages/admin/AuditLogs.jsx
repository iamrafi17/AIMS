import { useCallback, useEffect, useState } from 'react';
import {
  ClipboardDocumentCheckIcon,
  ClockIcon,
  ComputerDesktopIcon,
  MagnifyingGlassIcon,
  ShieldCheckIcon,
  UserCircleIcon,
} from '@heroicons/react/24/outline';
import api from '../../services/api';
import { EmptyPanel, LoadingPanel, PageIntro } from '../../components/common/PortalUI';

function readableAction(action = '') {
  const [method, ...path] = action.split(' ');
  const verbs = { post: 'Created / submitted', put: 'Updated', patch: 'Changed', delete: 'Deleted' };
  return `${verbs[method] || method.toUpperCase()} · ${path.join(' ').replace('api/', '').replaceAll('/', ' › ')}`;
}

export default function AdminAudit() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [selected, setSelected] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/admin/audit', { params: { search: search || undefined, role: role || undefined } });
      setLogs(response.data.data || []);
    } finally { setLoading(false); }
  }, [role, search]);
  useEffect(() => { const timer = setTimeout(load, 250); return () => clearTimeout(timer); }, [load]);

  return (
    <div className="space-y-6">
      <PageIntro eyebrow="Accountability & security" title="System Audit Trail" description="Review authenticated changes across registration, attendance, approvals, records, settings, and account administration." icon={ShieldCheckIcon} />
      <section className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="flex flex-col gap-3 md:flex-row">
          <label className="relative flex-1"><MagnifyingGlassIcon className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-2xl border border-slate-200 py-3 pl-11 pr-4 dark:border-slate-700" placeholder="Search action, person, or record type..." /></label>
          <select value={role} onChange={(event) => setRole(event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 md:min-w-52"><option value="">All roles</option><option value="admin">Administrator</option><option value="coordinator">Coordinator</option><option value="program_head">Program head</option><option value="vpaa">VPAA</option><option value="supervisor">Supervisor</option><option value="student">Student</option></select>
        </div>
      </section>
      {loading ? <LoadingPanel /> : logs.length === 0 ? <EmptyPanel icon={ClipboardDocumentCheckIcon} title="No matching activity" description="Authenticated system changes will appear in this audit trail." /> : (
        <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div className="overflow-x-auto"><table className="w-full min-w-[900px]">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/70"><tr><th className="px-6 py-4">User</th><th className="px-6 py-4">Action</th><th className="px-6 py-4">Record</th><th className="px-6 py-4">Network</th><th className="px-6 py-4">Time</th></tr></thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{logs.map((log) => <tr key={log.id} onClick={() => setSelected(log)} className="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td className="px-6 py-4"><div className="flex items-center gap-3"><div className="grid h-9 w-9 place-items-center rounded-full bg-[#800000]/10 text-[#800000] dark:text-red-300"><UserCircleIcon className="h-5 w-5" /></div><div><p className="font-bold text-slate-900 dark:text-white">{log.user?.name || 'System'}</p><p className="text-xs capitalize text-slate-500">{log.user?.role?.replace('_', ' ') || 'automated'}</p></div></div></td>
              <td className="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{readableAction(log.action)}</td>
              <td className="px-6 py-4 text-sm text-slate-500">{log.subject_type}{log.subject_id ? ` #${log.subject_id}` : ''}</td>
              <td className="px-6 py-4 text-sm text-slate-500"><span className="inline-flex items-center gap-1.5"><ComputerDesktopIcon className="h-4 w-4" />{log.ip_address || 'Unknown'}</span></td>
              <td className="px-6 py-4 text-sm text-slate-500"><span className="inline-flex items-center gap-1.5"><ClockIcon className="h-4 w-4" />{new Date(log.created_at).toLocaleString()}</span></td>
            </tr>)}</tbody>
          </table></div>
        </section>
      )}
      {selected && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onMouseDown={() => setSelected(null)}><div onMouseDown={(event) => event.stopPropagation()} className="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900"><h2 className="text-2xl font-black text-slate-900 dark:text-white">Audit details</h2><p className="mt-2 text-sm text-slate-500">{readableAction(selected.action)} by {selected.user?.name || 'System'}</p><div className="mt-6 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><pre className="overflow-x-auto whitespace-pre-wrap text-xs text-slate-700 dark:text-slate-200">{JSON.stringify(selected.new_values || {}, null, 2)}</pre></div><button onClick={() => setSelected(null)} className="mt-6 w-full rounded-2xl bg-[#800000] px-5 py-3 font-bold text-white">Close</button></div></div>}
    </div>
  );
}
