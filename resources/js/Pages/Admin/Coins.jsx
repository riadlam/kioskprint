import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminShell from '../../Components/Admin/Shell';
import { kioskEcho } from '../../lib/kioskEcho';

const RESULT = {
    probe: { label: 'Seen (test)', className: 'bg-sky-50 text-sky-800' },
    credited: { label: 'Credited', className: 'bg-emerald-50 text-emerald-800' },
    paid: { label: 'Paid job', className: 'bg-emerald-50 text-emerald-800' },
    returned: { label: 'System return', className: 'bg-amber-50 text-amber-900' },
    ignored: { label: 'Ignored', className: 'bg-slate-100 text-slate-700' },
    unknown: { label: 'Unknown pulses', className: 'bg-rose-50 text-rose-800' },
    replay: { label: 'Replay', className: 'bg-slate-100 text-slate-600' },
};

export default function Coins({
    email = '',
    kiosk_id: kioskId = '',
    probing = false,
    probe_until: probeUntil = null,
    map = [],
    recent = [],
}) {
    const [live, setLive] = useState([]);
    const [connected, setConnected] = useState(false);

    useEffect(() => {
        const echo = kioskEcho();
        if (!echo || !kioskId) {
            return undefined;
        }

        const channel = echo.channel(`kiosk.${kioskId}`);
        channel.listen('.KioskCoinSeen', (payload) => {
            setLive((rows) => [payload, ...rows].slice(0, 80));
        });
        setConnected(true);

        return () => {
            echo.disconnect();
            setConnected(false);
        };
    }, [kioskId]);

    return (
        <AdminShell
            title="Coin probe"
            subtitle="Insert coins on the acceptor. Compare what the ESP reports with what the system maps."
            active="coins"
            email={email}
        >
            <section className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <article className="rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate">Kiosk</p>
                            <p className="mt-1 font-mono text-[14px] font-semibold">{kioskId}</p>
                            <p className="mt-2 text-[13px] text-slate">
                                {connected ? 'Websocket live' : 'Websocket offline'}
                                {probing && probeUntil ? ` · probe until ${formatTime(probeUntil)}` : ''}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {!probing ? (
                                <button
                                    type="button"
                                    onClick={() => router.post('/admin/coins/start', { minutes: 30 })}
                                    className="rounded-lg bg-[#101513] px-4 py-2 text-[13px] font-semibold text-white"
                                >
                                    Open acceptor 30 min
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => router.post('/admin/coins/stop')}
                                    className="rounded-lg bg-rose-700 px-4 py-2 text-[13px] font-semibold text-white"
                                >
                                    Close acceptor
                                </button>
                            )}
                        </div>
                    </div>

                    <div
                        className={`mt-4 rounded-xl px-4 py-3 text-[13px] ${
                            probing ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-950'
                        }`}
                    >
                        {probing
                            ? 'Acceptor is open for every coin (5–200 DA). Drop coins now — each one appears below live.'
                            : 'Acceptor is closed. Open it first or the ESP stays silent (MAX 0).'}
                    </div>

                    <h2 className="mt-6 text-[15px] font-semibold">Live inserts</h2>
                    <p className="mt-0.5 text-[13px] text-slate">
                        Left = what the acceptor / ESP sent. Right = what Metbati mapped and did.
                    </p>

                    <div className="mt-3 overflow-hidden rounded-xl border border-black/5">
                        {live.length === 0 ? (
                            <p className="px-4 py-8 text-center text-[13px] text-slate">Waiting for the next coin…</p>
                        ) : (
                            live.map((row) => <LiveRow key={`${row.event_key}-${row.at}`} row={row} />)
                        )}
                    </div>
                </article>

                <aside className="space-y-4">
                    <article className="rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
                        <h2 className="text-[15px] font-semibold">Pulse map</h2>
                        <p className="mt-0.5 text-[13px] text-slate">HX-916 pulse count → dinars.</p>
                        <div className="mt-3 overflow-hidden rounded-xl border border-black/5">
                            <table className="w-full text-left text-[13px]">
                                <thead className="bg-[#f3f5f4] text-[11px] uppercase tracking-wide text-slate">
                                    <tr>
                                        <th className="px-3 py-2 font-semibold">Pulses</th>
                                        <th className="px-3 py-2 font-semibold">Coin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {map.map((row) => (
                                        <tr key={row.pulses} className="border-t border-black/5">
                                            <td className="px-3 py-2 font-mono font-semibold">{row.pulses}</td>
                                            <td className="px-3 py-2 font-semibold">{row.dinars} DA</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article className="rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
                        <h2 className="text-[15px] font-semibold">Recent DB rows</h2>
                        <p className="mt-0.5 text-[13px] text-slate">Last stored coin_events for this kiosk.</p>
                        <div className="mt-3 max-h-[420px] space-y-2 overflow-y-auto">
                            {recent.length === 0 && <p className="text-[13px] text-slate">No rows yet.</p>}
                            {recent.map((row) => (
                                <div key={row.id} className="rounded-lg border border-black/5 px-3 py-2">
                                    <p className="font-semibold">
                                        {row.pulses} pulse{row.pulses === 1 ? '' : 's'} → {row.dinars} DA
                                    </p>
                                    <p className="mt-0.5 font-mono text-[11px] text-slate">{row.event_key}</p>
                                    <p className="mt-1 text-[12px] text-slate">{formatTime(row.at)}</p>
                                </div>
                            ))}
                        </div>
                    </article>
                </aside>
            </section>
        </AdminShell>
    );
}

function LiveRow({ row }) {
    const pill = RESULT[row.result] || RESULT.ignored;
    const expected = row.dinars == null ? 'unmapped' : `${row.dinars} DA`;
    const match =
        row.result === 'unknown'
            ? 'wrong / unknown pulse count'
            : row.acceptor === 'back' && row.result === 'returned'
              ? 'acceptor + system both returned'
              : row.acceptor === 'coin' && (row.result === 'credited' || row.result === 'paid' || row.result === 'probe')
                ? 'looks correct'
                : row.acceptor === 'back' && row.result === 'probe'
                  ? 'ESP sent BACK while probing'
                  : 'check wiring / map';

    return (
        <div className="grid gap-3 border-b border-black/5 px-4 py-3 last:border-0 md:grid-cols-2">
            <div>
                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate">Acceptor / ESP</p>
                <p className="mt-1 text-[14px] font-semibold">
                    {row.acceptor === 'back' ? 'BACK' : 'COIN'} · {row.pulses} pulse
                    {row.pulses === 1 ? '' : 's'}
                </p>
                <p className="mt-1 text-[12px] text-slate">{formatTime(row.at)}</p>
            </div>
            <div>
                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate">System</p>
                <div className="mt-1 flex flex-wrap items-center gap-2">
                    <p className="text-[14px] font-semibold">{expected}</p>
                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${pill.className}`}>{pill.label}</span>
                </div>
                <p className="mt-1 text-[12px] font-medium text-ink/80">{match}</p>
            </div>
        </div>
    );
}

function formatTime(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString();
    } catch {
        return String(value);
    }
}
