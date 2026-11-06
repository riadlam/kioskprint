import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminShell from '../../Components/Admin/Shell';

const STATUS = {
    reviewing: { label: 'Reviewing', tone: 'bg-slate-100 text-slate-700' },
    awaiting_payment: { label: 'Awaiting payment', tone: 'bg-amber-50 text-amber-800' },
    paid: { label: 'Paid', tone: 'bg-sky-50 text-sky-800' },
    printing: { label: 'Printing', tone: 'bg-indigo-50 text-indigo-800' },
    printed: { label: 'Printed', tone: 'bg-emerald-50 text-emerald-800' },
    failed: { label: 'Failed', tone: 'bg-red-50 text-red-700' },
};

function statusOf(value) {
    return STATUS[value] || { label: value || 'Unknown', tone: 'bg-slate-100 text-slate-700' };
}

function money(amount) {
    return new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 }).format(amount || 0);
}

export default function Dashboard({ authed, email = '', summary = {}, jobs = [], tonerSave = false, priceBands = [] }) {
    if (!authed) {
        return <Login />;
    }

    return (
        <AdminShell
            title="Dashboard"
            subtitle="Jobs and takings"
            active="overview"
            email={email}
        >
                        <section id="overview" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Stat label="Print jobs" value={summary.jobs ?? 0} note="All time" />
                            <Stat label="Printed" value={summary.printed ?? 0} note="Completed" />
                            <Stat label="Still open" value={summary.open ?? 0} note="Waiting to finish" />
                            <Stat label="Collected" value={`${money(summary.revenue)} DA`} note="Printed jobs" />
                        </section>

                        <TonerSaver enabled={tonerSave} />
                        <PriceBands bands={priceBands} />

                        <Link href="/admin/status" className="mt-6 flex items-center justify-between gap-4 rounded-xl border border-black/5 bg-[#101513] px-5 py-4 text-white shadow-sm">
                            <span>
                                <span className="block text-[15px] font-semibold">Floor status</span>
                                <span className="mt-0.5 block text-[13px] text-white/60">What is up, what is resting, and why. Uptime included.</span>
                            </span>
                            <span className="text-[13px] font-semibold text-emerald-300">Open</span>
                        </Link>

                        <section id="jobs" className="mt-6 overflow-hidden rounded-xl border border-black/5 bg-white shadow-sm">
                            <div className="flex items-end justify-between gap-4 border-b border-black/5 px-5 py-4">
                                <div>
                                    <h2 className="text-[15px] font-semibold">Recent print jobs</h2>
                                    <p className="mt-0.5 text-[13px] text-slate">The latest jobs across every kiosk.</p>
                                </div>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[860px] text-left text-[13px]">
                                    <thead className="text-[11px] uppercase tracking-wider text-slate">
                                        <tr className="border-b border-black/5">
                                            <th className="px-5 py-3 font-semibold">When</th>
                                            <th className="px-5 py-3 font-semibold">Status</th>
                                            <th className="px-5 py-3 font-semibold">Kiosk</th>
                                            <th className="px-5 py-3 font-semibold">Pages</th>
                                            <th className="px-5 py-3 font-semibold">Amount</th>
                                            <th className="px-5 py-3 font-semibold">Files</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jobs.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="px-5 py-12 text-slate">No print jobs yet.</td>
                                            </tr>
                                        )}
                                        {jobs.map((job) => {
                                            const status = statusOf(job.status);
                                            return (
                                                <tr key={job.id} className="border-b border-black/5 last:border-0">
                                                    <td className="px-5 py-3.5 whitespace-nowrap text-slate">{job.created_at}</td>
                                                    <td className="px-5 py-3.5">
                                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${status.tone}`}>
                                                            {status.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-3.5 font-mono text-[12px] text-slate">{job.kiosk_id || '—'}</td>
                                                    <td className="px-5 py-3.5">{job.pages || '—'}</td>
                                                    <td className="px-5 py-3.5 whitespace-nowrap">
                                                        {job.amount != null ? `${money(job.amount)} ${job.currency || 'DA'}` : '—'}
                                                    </td>
                                                    <td className="max-w-[280px] truncate px-5 py-3.5" title={job.files.join(', ')}>
                                                        {job.files.length ? job.files.join(', ') : '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </section>
        </AdminShell>
    );
}

function TonerSaver({ enabled }) {
    const form = useForm({});

    function toggle() {
        form.transform(() => ({ toner_save: !enabled }));
        form.post('/admin/print-settings', { preserveScroll: true });
    }

    return (
        <section className="mt-6 flex items-center justify-between gap-4 rounded-xl border border-black/5 bg-white px-5 py-4 shadow-sm">
            <div>
                <h2 className="text-[15px] font-semibold">Toner saver</h2>
                <p className="mt-0.5 max-w-xl text-[13px] text-slate">Lighter prints on the Canon, so a cartridge lasts longer. Pages look a little lighter. Off until you turn it on.</p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={enabled}
                disabled={form.processing}
                onClick={toggle}
                className={`relative h-7 w-12 shrink-0 rounded-full transition disabled:opacity-60 ${
                    enabled ? 'bg-emerald-600' : 'bg-slate-200'
                }`}
            >
                <span className="sr-only">Toner saver</span>
                <span className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition ${enabled ? 'start-6' : 'start-1'}`} />
            </button>
        </section>
    );
}

function PriceBands({ bands }) {
    const form = useForm({
        bands: bands.map((band) => ({
            id: band.id,
            min_pages: band.min_pages,
            max_pages: band.max_pages,
            price_per_page: band.price_per_page,
        })),
    });
    const errors = usePage().props.errors || {};

    function save(event) {
        event.preventDefault();
        form.post('/admin/price-bands', { preserveScroll: true });
    }

    if (form.data.bands.length === 0) {
        return null;
    }

    return (
        <form onSubmit={save} className="mt-6 rounded-xl border border-black/5 bg-white px-5 py-4 shadow-sm">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <h2 className="text-[15px] font-semibold">Page prices</h2>
                    <p className="mt-0.5 max-w-xl text-[13px] text-slate">Dinars per page for a customer’s own files. Ranges must start at page 1 and touch, with no gaps. The last page is the maximum one order can hold. Ready-made forms stay at their own price.</p>
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="h-9 shrink-0 rounded-lg bg-emerald-700 px-3 text-[13px] font-semibold text-white disabled:opacity-60"
                >
                    Save prices
                </button>
            </div>
            {errors.bands && (
                <p className="mt-3 text-[13px] font-medium text-red-700">{errors.bands}</p>
            )}
            <div className="mt-4 grid gap-3">
                {form.data.bands.map((band, index) => (
                    <div key={band.id} className="grid grid-cols-3 gap-2">
                        <label className="text-[12px] font-medium text-slate">
                            From page
                            <input
                                type="number"
                                min="1"
                                value={band.min_pages}
                                onChange={(event) => form.setData('bands', form.data.bands.map((row, rowIndex) => (
                                    rowIndex === index ? { ...row, min_pages: Number(event.target.value) } : row
                                )))}
                                className="mt-1 h-10 w-full rounded-lg border border-black/10 px-3 text-[14px] text-black"
                            />
                        </label>
                        <label className="text-[12px] font-medium text-slate">
                            To page
                            <input
                                type="number"
                                min="1"
                                value={band.max_pages}
                                onChange={(event) => form.setData('bands', form.data.bands.map((row, rowIndex) => (
                                    rowIndex === index ? { ...row, max_pages: Number(event.target.value) } : row
                                )))}
                                className="mt-1 h-10 w-full rounded-lg border border-black/10 px-3 text-[14px] text-black"
                            />
                        </label>
                        <label className="text-[12px] font-medium text-slate">
                            DA / page
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={band.price_per_page}
                                onChange={(event) => form.setData('bands', form.data.bands.map((row, rowIndex) => (
                                    rowIndex === index ? { ...row, price_per_page: event.target.value } : row
                                )))}
                                className="mt-1 h-10 w-full rounded-lg border border-black/10 px-3 text-[14px] text-black"
                            />
                        </label>
                    </div>
                ))}
            </div>
        </form>
    );
}

function Stat({ label, value, note }) {
    return (
        <article className="rounded-xl border border-black/5 bg-white px-4 py-4 shadow-sm">
            <p className="text-[12px] font-medium text-slate">{label}</p>
            <p className="mt-2 text-[1.65rem] font-semibold tracking-tight">{value}</p>
            <p className="mt-1 text-[12px] text-slate">{note}</p>
        </article>
    );
}

function Login() {
    const { errors } = usePage().props;
    const form = useForm({ email: '', password: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/admin/login');
    }

    return (
        <div className="grid min-h-screen bg-white lg:grid-cols-[minmax(0,0.9fr)_minmax(420px,1fr)]">
            <Head title="Sign in" />
            <section className="hidden flex-col justify-between bg-[#101513] px-12 py-10 text-white lg:flex">
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-300">Metbati</p>
                    <h1 className="mt-10 max-w-sm text-4xl font-semibold leading-tight tracking-tight">Print operations, in one place.</h1>
                    <p className="mt-4 max-w-sm text-[15px] leading-7 text-white/65">Recent jobs, payments, and the health of the kiosk.</p>
                </div>
                <p className="text-[12px] text-white/40">Operator access only</p>
            </section>
            <section className="flex items-center justify-center px-6 py-12">
                <form onSubmit={submit} className="w-full max-w-[380px]">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary lg:hidden">Metbati</p>
                    <h2 className="mt-3 text-2xl font-semibold tracking-tight lg:mt-0">Sign in</h2>
                    <p className="mt-2 text-sm text-slate">Use the operator email and password.</p>

                    <label className="mt-8 block text-[13px] font-medium" htmlFor="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        autoComplete="username"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                        autoFocus
                        required
                    />

                    <label className="mt-4 block text-[13px] font-medium" htmlFor="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                        required
                    />

                    {errors.email && (
                        <p className="mt-3 text-[13px] font-medium text-red-700">{errors.email}</p>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-6 h-11 w-full rounded-lg bg-primary text-sm font-semibold text-white disabled:opacity-60"
                    >
                        Continue
                    </button>
                </form>
            </section>
        </div>
    );
}
