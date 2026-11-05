import { Head, Link, router, usePage } from '@inertiajs/react';

const ADMIN_LINKS = [
    { id: 'overview', href: '/admin', label: 'Overview', roles: ['admin'] },
    { id: 'status', href: '/admin/status', label: 'Status', roles: ['admin'] },
    { id: 'coins', href: '/admin/coins', label: 'Coin probe', roles: ['admin'] },
    { id: 'jobs', href: '/admin#jobs', label: 'Print jobs', roles: ['admin'] },
    { id: 'screen', href: '/admin/screen', label: 'Screen', roles: ['admin'] },
    { id: 'templates', href: '/admin/templates', label: 'Forms', roles: ['admin'] },
    { id: 'professors', href: '/admin/professors', label: 'Professors', roles: ['admin'] },
    { id: 'print-codes', href: '/admin/print-codes', label: 'Print codes', roles: ['admin', 'professor'] },
];

export default function AdminShell({ title, subtitle, active, email, role, children }) {
    const page = usePage();
    const authUser = page.props.auth?.user;
    const resolvedRole = role || authUser?.role || 'admin';
    const resolvedEmail = email || authUser?.email || '';
    const links = ADMIN_LINKS.filter((link) => link.roles.includes(resolvedRole));
    const roleLabel = resolvedRole === 'professor' ? 'Professor' : 'Operator';

    return (
        <div className="min-h-screen bg-[#f3f5f4] text-ink">
            <Head title={title} />
            <div className="flex min-h-screen">
                <aside className="hidden w-[248px] shrink-0 flex-col bg-[#101513] text-white md:flex">
                    <div className="px-6 pb-6 pt-7">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-300">Metbati</p>
                        <p className="mt-1 text-[15px] font-semibold tracking-tight">
                            {resolvedRole === 'professor' ? 'مطبعتـي أساتذة' : 'Operations'}
                        </p>
                    </div>
                    <nav className="flex flex-1 flex-col gap-1 px-3">
                        {links.map((link) => (
                            <NavLink key={link.id} href={link.href} label={link.label} active={active === link.id} />
                        ))}
                    </nav>
                    <div className="border-t border-white/10 p-4">
                        <p className="truncate text-[13px] font-medium">{resolvedEmail}</p>
                        <p className="mt-0.5 text-[11px] text-white/50">{roleLabel}</p>
                        <button
                            type="button"
                            onClick={() => router.post('/admin/logout')}
                            className="mt-3 text-[12px] font-semibold text-emerald-300"
                        >
                            Sign out
                        </button>
                    </div>
                </aside>

                <div className="min-w-0 flex-1">
                    <header className="flex items-center justify-between border-b border-black/5 bg-white px-5 py-4 md:px-8">
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
                            {subtitle && <p className="text-[13px] text-slate">{subtitle}</p>}
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/admin/logout')}
                            className="text-[13px] font-semibold text-primary md:hidden"
                        >
                            Sign out
                        </button>
                    </header>
                    <nav className="flex gap-2 overflow-x-auto border-b border-black/5 bg-white px-5 py-2 md:hidden">
                        {links.map((link) => (
                            <NavLink key={link.id} href={link.href} label={link.label} active={active === link.id} compact />
                        ))}
                    </nav>
                    <main className="px-5 py-6 md:px-8 md:py-8">{children}</main>
                </div>
            </div>
        </div>
    );
}

function NavLink({ href, label, active, compact = false }) {
    return (
        <Link
            href={href}
            className={
                compact
                    ? `shrink-0 rounded-full px-3 py-1.5 text-[12px] font-semibold ${active ? 'bg-[#101513] text-white' : 'bg-[#f3f5f4] text-slate'}`
                    : `rounded-lg px-3 py-2 text-[13px] font-medium ${active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white'}`
            }
        >
            {label}
        </Link>
    );
}
