import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function Published({ articles }) {
    return (
        <AuthenticatedLayout>
            <div className="mb-6 flex items-baseline justify-between">
                <h1 className="text-2xl font-semibold text-slate-900">Published Articles</h1>
                <span className="text-sm text-slate-500">
                    {articles.length} article{articles.length === 1 ? '' : 's'}
                </span>
            </div>

            {articles.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500">
                    Nothing published yet.
                </div>
            ) : (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Title</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Site</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">Published</th>
                                <th className="px-4 py-3 text-left font-medium text-slate-500">URL</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {articles.map((article) => (
                                <tr key={article.id}>
                                    <td className="px-4 py-3 font-medium text-slate-900">{article.title}</td>
                                    <td className="px-4 py-3 text-slate-600">{article.site?.domain}</td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {article.published_at ? new Date(article.published_at).toLocaleString() : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {article.external_url ? (
                                            <a
                                                href={article.external_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-indigo-600 hover:underline"
                                            >
                                                View live
                                            </a>
                                        ) : (
                                            <span className="text-slate-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
