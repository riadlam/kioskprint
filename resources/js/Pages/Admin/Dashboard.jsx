import { Head, router, useForm, usePage } from '@inertiajs/react';

export default function Dashboard({ authed, health = [], jobs = [] }) {
    const { errors } = usePage().props;
    const form = useForm({ password: '' });

    function login(event) {
        event.preventDefault();
        form.post('/admin/login');
    }

    return (
        <div className="min-h-screen bg-[#f4f7f5] text-ink">
            <Head title="Admin" />
            <header className="border-b border-emerald-900/10 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">Metbati</p>
                        <h1 className="text-xl font-extrabold tracking-tight">Admin</h1>
                    </div>
                    {authed && (
                        <button
                            type="button"
                            onClick={() => router.post('/admin/logout')}
                            className="border border-emerald-900/15 px-3 py-2 text-[12px] font-semibold"
                        >
                            Log out
                        </button>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-8">
                {!authed ? (
                    <form onSubmit={login} className="mx-auto max-w-sm border border-emerald-900/10 bg-white p-6">
                        <h2 className="text-lg font-bold">Sign in</h2>
                        <p className="mt-1 text-sm text-slate">This page is only for the kiosk operator.</p>
                        <label className="mt-5 block text-[12px] font-semibold" htmlFor="password">Password</label>
                        <input
                            id="password"
                            type="password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            className="mt-1 h-11 w-full border border-emerald-900/15 px-3 text-sm outline-none focus:border-primary"
                            autoFocus
                        />
                        {errors.password && (
                            <p className="mt-2 text-[12px] font-medium text-red-700">{errors.password}</p>
                        )}
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="mt-4 h-11 w-full bg-primary text-sm font-semibold text-white disabled:opacity-60"
                        >
                            Open dashboard
                        </button>
                    </form>
                ) : (
                    <>
                        <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {health.map((check) => (
                                <article key={check.name} className="border border-emerald-900/10 bg-white p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <h2 className="text-sm font-bold">{check.name}</h2>
                                        <span className={`h-2.5 w-2.5 rounded-full ${check.ok ? 'bg-primary' : 'bg-red-500'}`} />
                                    </div>
                                    <p className="mt-2 text-[13px] text-slate">{check.detail}</p>
                                </article>
                            ))}
                        </section>

                        <section className="mt-8 border border-emerald-900/10 bg-white">
                            <div className="border-b border-emerald-900/10 px-4 py-3">
                                <h2 className="text-sm font-bold">Recent print jobs</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-left text-[13px]">
                                    <thead className="bg-[#f4f7f5] text-[11px] uppercase tracking-wider text-slate">
                                        <tr>
                                            <th className="px-4 py-2 font-semibold">When</th>
                                            <th className="px-4 py-2 font-semibold">Status</th>
                                            <th className="px-4 py-2 font-semibold">Kiosk</th>
                                            <th className="px-4 py-2 font-semibold">Pages</th>
                                            <th className="px-4 py-2 font-semibold">Amount</th>
                                            <th className="px-4 py-2 font-semibold">Files</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jobs.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="px-4 py-8 text-slate">No print jobs yet.</td>
                                            </tr>
                                        )}
                                        {jobs.map((job) => (
                                            <tr key={job.id} className="border-t border-emerald-900/10">
                                                <td className="px-4 py-3 whitespace-nowrap">{job.created_at}</td>
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold">{job.status}</span>
                                                    {job.payment_method && (
                                                        <span className="mt-0.5 block text-[11px] text-slate">{job.payment_method}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-[11px]">{job.kiosk_id || '—'}</td>
                                                <td className="px-4 py-3">{job.pages || '—'}</td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {job.amount != null ? `${job.amount} ${job.currency || 'DA'}` : '—'}
                                                </td>
                                                <td className="px-4 py-3 max-w-[280px] truncate" title={job.files.join(', ')}>
                                                    {job.files.length ? job.files.join(', ') : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </>
                )}
            </main>
        </div>
    );
}
