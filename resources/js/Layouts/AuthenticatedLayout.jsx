import { Link, router, usePage } from '@inertiajs/react';

function NavLink({ href, current, children }) {
    return (
        <Link
            href={href}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors ' +
                (current
                    ? 'border-indigo-500 text-slate-900'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700')
            }
        >
            {children}
        </Link>
    );
}

export default function AuthenticatedLayout({ children }) {
    const { url, props } = usePage();
    const user = props.auth?.user;

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    return (
        <div className="min-h-screen bg-slate-50">
            <nav className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-8">
                        <span className="text-lg font-semibold tracking-tight text-slate-900">
                            ATLAS
                        </span>
                        <div className="flex gap-6">
                            <NavLink href="/" current={url === '/'}>
                                Dashboard
                            </NavLink>
                            <NavLink href="/review" current={url.startsWith('/review')}>
                                Review Queue
                            </NavLink>
                            <NavLink href="/articles/published" current={url.startsWith('/articles')}>
                                Published
                            </NavLink>
                            <NavLink href="/sites" current={url.startsWith('/sites')}>
                                Sites
                            </NavLink>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        {user && <span className="text-sm text-slate-500">{user.email}</span>}
                        <button
                            onClick={logout}
                            className="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                        >
                            Log out
                        </button>
                    </div>
                </div>
            </nav>

            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">{children}</main>
        </div>
    );
}
