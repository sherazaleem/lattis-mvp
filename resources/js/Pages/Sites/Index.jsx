import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

const CREDENTIAL_STYLES = {
    active: 'bg-green-100 text-green-700 ring-green-600/20',
    failed: 'bg-red-100 text-red-700 ring-red-600/10',
    unverified: 'bg-slate-100 text-slate-600 ring-slate-500/10',
};

function CredentialBadge({ status }) {
    const style = CREDENTIAL_STYLES[status] ?? CREDENTIAL_STYLES.unverified;

    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${style}`}>
            {status ?? 'no credential'}
        </span>
    );
}

export default function SitesIndex({ sites }) {
    return (
        <AuthenticatedLayout>
            <div className="mb-6 flex items-baseline justify-between">
                <h1 className="text-2xl font-semibold text-slate-900">Active Sites</h1>
                <span className="text-sm text-slate-500">
                    {sites.length} site{sites.length === 1 ? '' : 's'}
                </span>
            </div>

            {sites.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500">
                    No active sites configured.
                </div>
            ) : (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Domain</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Cluster</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Stack</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Auto-publish</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Credential</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Published</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {sites.map((site) => (
                                <tr key={site.id}>
                                    <td className="px-4 py-3 font-medium text-slate-900">{site.domain}</td>
                                    <td className="px-4 py-3 text-slate-600">{site.cluster?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-600">{site.stack_type}</td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {site.effective_auto_publish ? (
                                            'Yes'
                                        ) : (
                                            <span title={site.auto_publish ? 'Forced off by mandatory-review cluster' : ''}>
                                                No
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <CredentialBadge status={site.credential_status} />
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{site.published_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
