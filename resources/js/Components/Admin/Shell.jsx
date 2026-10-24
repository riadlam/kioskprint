import { Head, Link, router } from '@inertiajs/react';

const LINKS = [
    { id: 'overview', href: '/admin', label: 'Overview' },
    { id: 'jobs', href: '/admin#jobs', label: 'Print jobs' },
    { id: 'health', href: '/admin#health', label: 'System health' },
    { id: 'screen', href: '/admin/screen', label: 'Screen' },
];

export default function AdminShell({ title, subtitle, active, email, children }) {
    return (
        <div className="min-h-screen bg-[#f3f5f4] text-ink">
            <Head title={title} />
            <div className="flex min-h-screen">
                <aside className="hidden w-[248px] shrink-0 flex-col bg-[#101513] text-white md:flex">
                    <div className="px-6 pb-6 pt-7">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-300">Metbati</p>
                        <p className="mt-1 text-[15px] font-semibold tracking-tight">Operations</p>
                    </div>
                    <nav className="flex flex-1 flex-col gap-1 px-3">
                        {LINKS.map((link) => (
                            <NavLink key={link.id} href={link.href} label={link.label} active={active === link.id} />
                        ))}
                    </nav>
                    <div className="border-t border-white/10 p-4">
                        <p className="truncate text-[13px] font-medium">{email}</p>
                        <p className="mt-0.5 text-[11px] text-white/50">Operator</p>
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
                        {LINKS.map((link) => (
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
