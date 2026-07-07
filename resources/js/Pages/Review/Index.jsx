import { router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

const FLAG_STYLES = {
    hold: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    fail: 'bg-red-100 text-red-700 ring-red-600/10',
    pass: 'bg-green-100 text-green-700 ring-green-600/20',
    skip: 'bg-slate-100 text-slate-600 ring-slate-500/10',
};

function FlagBadge({ flag }) {
    const style = FLAG_STYLES[flag.outcome] ?? FLAG_STYLES.skip;

    return (
        <span
            title={flag.reason ?? ''}
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${style}`}
        >
            {flag.outcome?.toUpperCase()} · {flag.filter}
        </span>
    );
}

function ArticleCard({ article }) {
    const [expanded, setExpanded] = useState(false);
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
    const flags = article.quality_flags || [];
    const holdFlags = flags.filter((f) => f.outcome === 'hold');

    function approve() {
        router.post(`/review/${article.id}/approve`);
    }

    function confirmReject() {
        router.post(`/review/${article.id}/reject`, { reject_reason: reason });
    }

    return (
        <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">{article.title || '(untitled)'}</h2>
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                {article.site?.domain}
                            </span>
                            {article.rssItem && (
                                <a
                                    href={article.rssItem.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-indigo-600 hover:underline"
                                >
                                    Source: {article.rssItem.title}
                                </a>
                            )}
                        </div>
                    </div>
                </div>

                {holdFlags.length > 0 && (
                    <p className="mt-3 text-sm font-medium text-amber-700">
                        Held for mandatory review — this cannot auto-publish regardless of the site's settings.
                    </p>
                )}

                {flags.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {flags.map((flag, i) => (
                            <FlagBadge key={i} flag={flag} />
                        ))}
                    </div>
                )}
            </div>

            <div className="p-5">
                <button
                    onClick={() => setExpanded(!expanded)}
                    className="text-sm font-medium text-indigo-600 hover:underline"
                >
                    {expanded ? 'Hide article body' : 'Show article body'}
                </button>

                {expanded && (
                    <div
                        className="[&_p]:mb-3 mt-3 max-h-96 overflow-y-auto rounded-md bg-slate-50 p-4 text-sm leading-relaxed text-slate-700"
                        dangerouslySetInnerHTML={{ __html: article.body }}
                    />
                )}
            </div>

            <div className="flex items-center gap-3 border-t border-slate-100 bg-slate-50 p-4">
                {!rejecting ? (
                    <>
                        <button
                            onClick={approve}
                            className="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Approve
                        </button>
                        <button
                            onClick={() => setRejecting(true)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            Reject
                        </button>
                    </>
                ) : (
                    <div className="flex w-full items-center gap-2">
                        <input
                            type="text"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Reason for rejection (optional)"
                            autoFocus
                            className="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <button
                            onClick={confirmReject}
                            className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Confirm reject
                        </button>
                        <button
                            onClick={() => setRejecting(false)}
                            className="rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100"
                        >
                            Cancel
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function ReviewIndex({ articles }) {
    return (
        <AuthenticatedLayout>
            <div className="mb-6 flex items-baseline justify-between">
                <h1 className="text-2xl font-semibold text-slate-900">Review Queue</h1>
                <span className="text-sm text-slate-500">
                    {articles.length} article{articles.length === 1 ? '' : 's'} awaiting review
                </span>
            </div>

            {articles.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500">
                    Nothing awaiting review right now.
                </div>
            ) : (
                <div className="space-y-4">
                    {articles.map((article) => (
                        <ArticleCard key={article.id} article={article} />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
