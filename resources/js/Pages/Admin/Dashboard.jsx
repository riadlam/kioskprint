import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminShell from '../../Components/Admin/Shell';
import { kioskEcho } from '../../lib/kioskEcho';

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

export default function Dashboard({ authed, email = '', health = [], summary = {}, jobs = [], kiosks = [] }) {
    if (!authed) {
        return <Login />;
    }

    const open = health.filter((check) => check.ok).length;

    return (
        <AdminShell
            title="Dashboard"
            subtitle={`${open} of ${health.length} systems healthy`}
            active="overview"
            email={email}
        >
                        <section id="overview" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Stat label="Print jobs" value={summary.jobs ?? 0} note="All time" />
                            <Stat label="Printed" value={summary.printed ?? 0} note="Completed" />
                            <Stat label="Still open" value={summary.open ?? 0} note="Waiting to finish" />
                            <Stat label="Collected" value={`${money(summary.revenue)} DA`} note="Printed jobs" />
                        </section>

                        <KioskPcs initial={kiosks} />

                        <section id="health" className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {health.map((check) => (
                                <article key={check.name} className="rounded-xl border border-black/5 bg-white px-4 py-4 shadow-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <h2 className="text-[13px] font-semibold">{check.name}</h2>
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${check.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'}`}>
                                            {check.ok ? 'Healthy' : 'Down'}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-[13px] leading-5 text-slate">{check.detail}</p>
                                </article>
                            ))}
                        </section>

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

function KioskPcs({ initial }) {
    const [rows, setRows] = useState(initial);
    const [online, setOnline] = useState([]);
    const [link, setLink] = useState('checking');

    useEffect(() => {
        const echo = kioskEcho();

        if (!echo) {
            setLink('missing');
            return undefined;
        }

        const connection = echo.connector.pusher.connection;
        const onOpen = () => setLink('open');
        const onDown = () => setLink('down');
        connection.bind('connected', onOpen);
        connection.bind('unavailable', onDown);
        connection.bind('failed', onDown);
        connection.bind('disconnected', onDown);

        const presence = echo.join('kiosk-pcs');
        const idsOf = (members) => {
            const found = [];
            const take = (member) => {
                const id = member?.id;
                if (id && id !== 'desk') {
                    found.push(id);
                }
            };
            if (Array.isArray(members)) {
                members.forEach(take);
            } else if (members && typeof members.each === 'function') {
                members.each(take);
            }
            return found;
        };
        const addRow = (id) => {
            setRows((current) => (current.some((row) => row.kiosk_id === id)
                ? current
                : [...current, { kiosk_id: id, last_error: null }]));
        };

        presence.here((members) => {
            const ids = idsOf(members);
            setOnline(ids);
            ids.forEach(addRow);
        });
        presence.joining((member) => {
            if (!member?.id || member.id === 'desk') {
                return;
            }
            addRow(member.id);
            setOnline((current) => (current.includes(member.id) ? current : [...current, member.id]));
        });
        presence.leaving((member) => {
            if (!member?.id) {
                return;
            }
            setOnline((current) => current.filter((id) => id !== member.id));
        });

        echo.private('admin-kiosks').listen('.KioskPcChanged', (event) => {
            setRows((current) => {
                const next = current.map((row) => (row.kiosk_id === event.kiosk_id
                    ? { ...row, last_error: event.last_error }
                    : row));
                if (next.some((row) => row.kiosk_id === event.kiosk_id)) {
                    return next;
                }
                return [...next, { kiosk_id: event.kiosk_id, last_error: event.last_error }];
            });
        });

        return () => {
            connection.unbind('connected', onOpen);
            connection.unbind('unavailable', onDown);
            connection.unbind('failed', onDown);
            connection.unbind('disconnected', onDown);
            echo.disconnect();
        };
    }, []);

    return (
        <section id="kiosks" className="mt-6">
            <div className="mb-3 flex items-end justify-between gap-4">
                <div>
                    <h2 className="text-[15px] font-semibold">Kiosk PCs</h2>
                    <p className="mt-0.5 text-[13px] text-slate">Live from the mini PC socket.</p>
                </div>
                <LinkNote link={link} />
            </div>
            {rows.length === 0 && (
                <article className="rounded-xl border border-black/5 bg-white px-4 py-4 text-[13px] text-slate shadow-sm">
                    No kiosk PC has connected yet.
                </article>
            )}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {rows.map((row) => {
                    const connected = link === 'open' && online.includes(row.kiosk_id);
                    const label = link === 'open' ? (connected ? 'Online' : 'Offline') : 'Waiting';
                    const tone = connected ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700';
                    return (
                        <article key={row.kiosk_id} className="rounded-xl border border-black/5 bg-white px-4 py-4 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="truncate font-mono text-[12px] font-semibold">{row.kiosk_id}</h3>
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${tone}`}>{label}</span>
                            </div>
                            <p className="mt-2 text-[13px] leading-5 text-slate">{row.last_error || 'No error'}</p>
                        </article>
                    );
                })}
            </div>
        </section>
    );
}

function LinkNote({ link }) {
    const text = {
        checking: 'Connecting',
        open: 'Live',
        down: 'Live link down',
        missing: 'Live key missing',
    }[link];

    return <p className="text-[12px] text-slate">{text}</p>;
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
