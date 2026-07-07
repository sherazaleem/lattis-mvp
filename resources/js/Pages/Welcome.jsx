import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';

function StatCard({ label, value, href }) {
    return (
        <Link
            href={href}
            className="block rounded-lg border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md"
        >
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-slate-900">{value}</p>
        </Link>
    );
}

export default function Welcome({ stats }) {
    return (
        <AuthenticatedLayout>
            <h1 className="text-2xl font-semibold text-slate-900">Dashboard</h1>
            <p className="mt-1 text-sm text-slate-500">
                RSS ingestion, AI generation, and publishing running on autopilot — HOLD-flagged articles wait here for you.
            </p>

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Awaiting review" value={stats.reviewCount} href="/review" />
                <StatCard label="Published" value={stats.publishedCount} href="/articles/published" />
                <StatCard label="Active sites" value={stats.activeSites} href="/sites" />
            </div>

            {stats.reviewCount > 0 && (
                <div className="mt-6 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm text-amber-800">
                        {stats.reviewCount} article{stats.reviewCount === 1 ? '' : 's'} waiting for your review.
                    </p>
                    <Link
                        href="/review"
                        className="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700"
                    >
                        Review now
                    </Link>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
