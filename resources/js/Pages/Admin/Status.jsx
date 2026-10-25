import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import FloorArt from '../../Components/Admin/FloorArt';
import AdminShell from '../../Components/Admin/Shell';
import { kioskEcho } from '../../lib/kioskEcho';

const PILL = {
    up: { label: 'Up', className: 'bg-emerald-50 text-emerald-800' },
    down: { label: 'Down', className: 'bg-rose-50 text-rose-700' },
    quiet: { label: 'Resting', className: 'bg-amber-50 text-amber-800' },
};

export default function Status({ email = '', checked_at: checkedAt = '', signals = [], machines = [] }) {
    useEffect(() => {
        const echo = kioskEcho();
        if (!echo) {
            return undefined;
        }

        const refresh = () => {
            router.reload({ only: ['signals', 'machines', 'checked_at'], preserveScroll: true });
        };
        const presence = echo.join('kiosk-pcs');
        presence.here(refresh);
        presence.joining(refresh);
        presence.leaving(refresh);
        echo.private('admin-kiosks').listen('.KioskPcChanged', refresh);

        return () => {
            echo.disconnect();
        };
    }, []);

    const counts = signals.reduce((bag, signal) => {
        bag[signal.state] = (bag[signal.state] || 0) + 1;
        return bag;
    }, {});
    const live = machines.filter((machine) => machine.live);
    const earlier = machines.filter((machine) => !machine.live);

    return (
        <AdminShell
            title="Floor status"
            subtitle={`Read at ${checkedAt} · ${counts.up || 0} up · ${counts.quiet || 0} resting · ${counts.down || 0} down`}
            active="status"
            email={email}
        >
            <section className="grid gap-4 md:grid-cols-2">
                {signals.map((signal) => (
                    <SignalCard key={signal.key} signal={signal} />
                ))}
            </section>

            <section className="mt-8">
                <h2 className="text-[15px] font-semibold">On the floor</h2>
                <p className="mt-0.5 text-[13px] text-slate">The kiosk the mini PC is using now.</p>
                <div className="mt-3 grid gap-4">
                    {live.length === 0 && (
                        <article className="rounded-2xl border border-black/5 bg-white px-5 py-6 text-[13px] text-slate shadow-sm">
                            No kiosk has checked in yet.
                        </article>
                    )}
                    {live.map((machine) => (
                        <MachineCard key={machine.kiosk_id} machine={machine} />
                    ))}
                </div>
            </section>

            {earlier.length > 0 && (
                <section className="mt-8">
                    <h2 className="text-[15px] font-semibold">Earlier installs</h2>
                    <p className="mt-0.5 text-[13px] text-slate">
                        These ids showed up as offline before. They are old tablet installs, not broken machines.
                    </p>
                    <div className="mt-3 overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
                        {earlier.map((machine) => (
                            <div key={machine.kiosk_id} className="flex items-start gap-4 border-b border-black/5 px-5 py-4 last:border-0">
                                <FloorArt kind="socket" state="quiet" />
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="truncate font-mono text-[12px] font-semibold">{machine.kiosk_id}</p>
                                        <Pill state="quiet" label="Retired" />
                                    </div>
                                    <p className="mt-1 text-[13px] leading-5 text-slate">{machine.reason}</p>
                                    <p className="mt-2 text-[12px] font-semibold text-ink/70">{machine.meter}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </AdminShell>
    );
}

function SignalCard({ signal }) {
    return (
        <article className="flex gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
            <FloorArt kind={signal.key} state={signal.state} />
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-[15px] font-semibold">{signal.name}</h2>
                    <Pill state={signal.state} />
                </div>
                <p className="mt-1 text-[14px] font-medium">{signal.headline}</p>
                <p className="mt-1 text-[13px] leading-5 text-slate">{signal.reason}</p>
                <p className="mt-3 text-[12px] font-semibold tracking-wide text-ink/70">{signal.meter}</p>
            </div>
        </article>
    );
}

function MachineCard({ machine }) {
    return (
        <article className="flex gap-4 rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
            <FloorArt kind="socket" state={machine.state} />
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="truncate font-mono text-[13px] font-semibold">{machine.kiosk_id}</h3>
                    <Pill state={machine.state} />
                </div>
                <p className="mt-2 text-[13px] leading-5 text-slate">{machine.reason}</p>
                <p className="mt-3 text-[12px] font-semibold tracking-wide text-ink/70">{machine.meter}</p>
            </div>
        </article>
    );
}

function Pill({ state, label }) {
    const pill = PILL[state] || PILL.quiet;

    return (
        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${pill.className}`}>
            {label || pill.label}
        </span>
    );
}
