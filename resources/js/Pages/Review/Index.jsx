import { router } from '@inertiajs/react';

export default function ReviewIndex({ articles }) {
    function approve(id) {
        if (confirm('Approve this article for publishing?')) {
            router.post(`/review/${id}/approve`);
        }
    }

    function reject(id) {
        const reason = prompt('Reason for rejection (optional):') || '';
        router.post(`/review/${id}/reject`, { reject_reason: reason });
    }

    return (
        <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 900, margin: '0 auto' }}>
            <h1>Review Queue</h1>

            {articles.length === 0 && <p>Nothing awaiting review.</p>}

            {articles.map((article) => (
                <div key={article.id} style={{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem', marginBottom: '1.5rem' }}>
                    <h2>{article.title || '(untitled)'}</h2>
                    <p><strong>Site:</strong> {article.site?.domain}</p>
                    <p>
                        <strong>Source:</strong>{' '}
                        <a href={article.rssItem?.url} target="_blank" rel="noreferrer">
                            {article.rssItem?.title}
                        </a>
                    </p>

                    <details>
                        <summary>Quality flags ({(article.quality_flags || []).length})</summary>
                        <ul>
                            {(article.quality_flags || []).map((flag, i) => (
                                <li key={i}>
                                    <strong>{flag.outcome?.toUpperCase()}</strong> — {flag.filter}
                                    {flag.reason ? `: ${flag.reason}` : ''}
                                </li>
                            ))}
                        </ul>
                    </details>

                    <details open>
                        <summary>Body</summary>
                        <div dangerouslySetInnerHTML={{ __html: article.body }} />
                    </details>

                    <div style={{ marginTop: '1rem' }}>
                        <button onClick={() => approve(article.id)}>Approve</button>{' '}
                        <button onClick={() => reject(article.id)}>Reject</button>
                    </div>
                </div>
            ))}
        </div>
    );
}
