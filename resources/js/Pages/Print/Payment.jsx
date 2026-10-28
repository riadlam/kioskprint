import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PayErrorDialog from '../../Components/Print/PayErrorDialog';
import PhoneShell from '../../Components/Print/PhoneShell';
import { kioskEcho } from '../../lib/kioskEcho';
import { copy, formatMoney } from '../../lib/printCopy';

const LOCALE_KEY = 'metbati.print.locale';

export default function Payment({ job, quote, methods, cardMinimum = 75 }) {
    const { flash } = usePage().props;
    const [locale, setLocale] = useState(() => sessionStorage.getItem(LOCALE_KEY) || job.locale || 'ar');
    const form = useForm({ payment_method: '' });
    const t = copy[locale];
    const selected = flash?.payment_started || job.payment_method || '';
    const monnayeurActive = selected === 'monnayeur';
    const posteActive = selected === 'algerie_poste';
    const [inserted, setInserted] = useState(() => Number(job.amount_inserted || 0));
    const [phase, setPhase] = useState(() => phaseOf(job.status));
    const [pagesDone, setPagesDone] = useState(() => Number(job.printed_pages || 0));
    const [pageTotal, setPageTotal] = useState(() => Number(quote.page_count || job.page_count || 0));
    const [onChooser, setOnChooser] = useState(false);
    const [paymentEnded, setPaymentEnded] = useState('');
    const [holdSeconds, setHoldSeconds] = useState(20);
    const required = Number(quote.amount || 0);
    const remaining = Math.max(0, Math.round((required - inserted) * 100) / 100);
    const progress = required > 0 ? Math.min(1, inserted / required) : 0;
    const printPercent = pageTotal > 0 ? Math.min(100, Math.round((pagesDone / pageTotal) * 100)) : 0;
    const paymentError = flash?.payment_error
        || (form.errors.payment_method === 'card_unavailable' ? 'unavailable' : '')
        || (form.errors.payment_method === 'card_minimum' ? 'minimum' : '');

    useEffect(() => {
        if (!job.kiosk_id || (!monnayeurActive && !posteActive)) {
            return undefined;
        }

        const echo = kioskEcho();
        if (!echo) {
            return undefined;
        }

        const name = `kiosk.${job.kiosk_id}`;
        const channel = echo.channel(name);

        function sameJob(event) {
            return event && event.job_id === job.id;
        }

        channel.listen('.PrintJobPaymentProgress', (event) => {
            if (!sameJob(event) || event.inserted === undefined) {
                return;
            }
            setInserted((current) => Math.max(current, Number(event.inserted || 0)));
        });
        channel.listen('.PrintJobPaid', (event) => {
            if (!sameJob(event)) {
                return;
            }
            setInserted((current) => Math.max(current, required));
            setPhase((current) => (current === 'done' || current === 'failed' ? current : 'printing'));
        });
        channel.listen('.PrintJobProgress', (event) => {
            if (!sameJob(event)) {
                return;
            }
            const next = Number(event.current_page || 0);
            const total = Number(event.total_pages || 0);
            setPagesDone((current) => Math.max(current, next));
            if (total > 0) {
                setPageTotal(total);
            }
            setPhase((current) => (current === 'done' || current === 'failed' ? current : 'printing'));
        });
        channel.listen('.PrintJobCompleted', (event) => {
            if (!sameJob(event)) {
                return;
            }
            const total = Number(event.page_count || 0);
            if (total > 0) {
                setPageTotal(total);
                setPagesDone(total);
            }
            setPhase('done');
        });
        channel.listen('.PrintJobFailed', (event) => {
            if (!sameJob(event)) {
                return;
            }
            setPaymentEnded(event.reason === 'abandoned' ? 'abandoned' : 'print');
            setPhase('failed');
        });

        return () => {
            echo.leave(name);
            echo.disconnect();
        };
    }, [monnayeurActive, posteActive, job.id, job.kiosk_id, required]);

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function pay(methodId) {
        if (selected === methodId && form.processing) {
            return;
        }
        setOnChooser(false);
        setPhase('pay');
        setPagesDone(0);
        setInserted(0);
        form.transform(() => ({ payment_method: methodId }));
        form.post(`/print/${job.id}/pay`, { preserveScroll: true });
    }

    const screen = phase === 'printing' || phase === 'done' || phase === 'failed'
        ? phase
        : onChooser || (!monnayeurActive && !posteActive)
            ? 'choose'
            : monnayeurActive
                ? 'cash'
                : 'poste';

    useEffect(() => {
        if (screen !== 'cash') {
            return undefined;
        }

        setHoldSeconds(20);
        const timer = setInterval(() => {
            setHoldSeconds((current) => (current <= 1 ? 0 : current - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [screen, inserted]);

    const meta = {
        monnayeur: {
            title: t.payMonnayeur,
            hint: t.payMonnayeurHint,
            waiting: t.payWaitingMonnayeur,
            tone: 'green',
        },
        algerie_poste: {
            title: t.payPoste,
            hint: t.payPosteHint,
            waiting: t.payWaitingPoste,
            tone: 'gold',
        },
    };

    return (
        <PhoneShell locale={locale} onLocale={changeLocale} step={2} dense>
            <Head title={t.payTitle} />

            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain bg-[#f4faf7]">
                <section className="shrink-0 border-b border-emerald-900/8 bg-white px-4 pb-4 pt-3">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-[12px] font-bold text-primary">{t.paySecure}</p>
                        <Link
                            href={`/print/${job.id}`}
                            className="text-[13px] font-bold text-slate underline-offset-2 hover:text-ink hover:underline"
                        >
                            {t.payBack}
                        </Link>
                    </div>

                    <div className="mt-4 text-center">
                        <p className="text-[13px] font-semibold text-slate">{t.payTotal}</p>
                        <p className="mt-1.5 text-[2.4rem] font-black leading-none tracking-tight text-ink">
                            {formatMoney(quote.amount, locale)}
                        </p>
                        <div className="mx-auto mt-3 h-1 w-12 rounded-full bg-primary" />
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] font-semibold text-ink/80">
                        <span>
                            {quote.page_count} {t.payPages}
                        </span>
                        <span className="h-1 w-1 rounded-full bg-emerald-300" />
                        <span>
                            {formatMoney(quote.unit_price, locale)} / {t.payUnitShort}
                        </span>
                        {job.duplex && (
                            <>
                                <span className="h-1 w-1 rounded-full bg-emerald-300" />
                                <span className="text-primary">{t.duplexOn}</span>
                            </>
                        )}
                    </div>

                    <p className="mt-3 text-center text-[13px] font-medium leading-snug text-slate">{t.payHint}</p>
                </section>

                {screen === 'choose' ? (
                    <section className="flex flex-1 flex-col px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                        <p className="mb-3 text-[15px] font-extrabold text-ink">{t.payChoose}</p>
                        <div className="space-y-3">
                            {methods.map((method) => {
                                const info = meta[method.id];
                                const gold = info.tone === 'gold';
                                const cardBlocked = method.id === 'algerie_poste' && Number(quote.amount) < Number(cardMinimum);

                                return (
                                    <button
                                        key={method.id}
                                        type="button"
                                        disabled={form.processing || cardBlocked}
                                        onClick={() => pay(method.id)}
                                        className={`flex w-full items-center gap-4 rounded-2xl border border-emerald-900/10 bg-white px-4 py-4 text-start transition active:scale-[0.99] disabled:active:scale-100 ${cardBlocked ? 'opacity-45' : ''}`}
                                    >
                                        <span className={`flex h-[4.25rem] w-[4.25rem] shrink-0 items-center justify-center rounded-2xl ${gold ? 'bg-[#fff8db]' : 'bg-emerald-50'}`}>
                                            <img src={method.icon} alt="" className={`object-contain ${gold ? 'h-14 w-14' : 'h-11 w-11'}`} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-[1.05rem] font-extrabold leading-snug text-ink">{info.title}</span>
                                            <span className="mt-1 block text-[13px] font-medium leading-snug text-slate">{cardBlocked ? t.payCardMinimum : info.hint}</span>
                                        </span>
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#eef2f0] text-[15px] font-black text-slate">›</span>
                                    </button>
                                );
                            })}
                        </div>
                        {form.errors.payment_method && !paymentError && (
                            <p className="mt-3 text-[13px] font-semibold text-red-700">{form.errors.payment_method}</p>
                        )}
                    </section>
                ) : (
                    <section className="flex flex-1 flex-col px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                        {(screen === 'cash' || screen === 'poste') && (
                            <button
                                type="button"
                                onClick={() => setOnChooser(true)}
                                className="mb-4 self-start text-[13px] font-bold text-slate"
                            >
                                {t.payChangeMethod}
                            </button>
                        )}

                        {screen === 'cash' && (
                            <div className="rounded-3xl border border-emerald-200 bg-white px-5 py-5">
                                <p className="text-[1.15rem] font-black text-ink">{t.payInsertCash}</p>
                                <p className="mt-1 text-[13px] font-medium text-slate">{t.payListening}</p>
                                <div className="mt-4 grid grid-cols-2 gap-2">
                                    <div className="rounded-xl bg-emerald-50 px-3 py-3 text-center">
                                        <p className="text-[11px] font-bold text-primary">{t.payInserted}</p>
                                        <p className="mt-0.5 text-[1.15rem] font-black tabular-nums text-ink">{formatMoney(inserted, locale)}</p>
                                    </div>
                                    <div className="rounded-xl bg-[#f4faf7] px-3 py-3 text-center">
                                        <p className="text-[11px] font-bold text-slate">{t.payRemaining}</p>
                                        <p className="mt-0.5 text-[1.15rem] font-black tabular-nums text-ink">{formatMoney(remaining, locale)}</p>
                                    </div>
                                </div>
                                <p className="mt-4 text-center text-[15px] font-black tabular-nums text-ink">{t.payHold(holdSeconds)}</p>
                                <div className="mt-3">
                                    <div className="mb-1 flex items-center justify-between text-[11px] font-semibold text-slate">
                                        <span>{t.payDue}: {formatMoney(required, locale)}</span>
                                        <span>{Math.round(progress * 100)}%</span>
                                    </div>
                                    <div className="h-2.5 overflow-hidden rounded-full bg-emerald-100">
                                        <div
                                            className="h-full rounded-full bg-primary transition-all duration-500"
                                            style={{ width: `${Math.max(progress * 100, 4)}%` }}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {screen === 'poste' && (
                            <div className="rounded-3xl border border-[#1e2a78]/15 bg-white px-5 py-5">
                                <p className="text-[1.15rem] font-black text-ink">{meta.algerie_poste.title}</p>
                                <p className="mt-1 text-[13px] font-medium text-slate">{meta.algerie_poste.waiting}</p>
                                <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-[#eef0f8]">
                                    <div className="h-full w-2/3 animate-pulse rounded-full bg-[#1e2a78]" />
                                </div>
                            </div>
                        )}

                        {(screen === 'printing' || screen === 'done') && (
                            <PrintMonitor
                                title={t.payPrintingTitle}
                                hint={t.payPrintingHint}
                                pagesLabel={t.payPrintingPages}
                                paidLabel={t.payPrintingPaid}
                                current={screen === 'done' ? pageTotal : pagesDone}
                                total={pageTotal}
                                percent={screen === 'done' ? 100 : printPercent}
                                amount={formatMoney(quote.amount, locale)}
                            />
                        )}

                        {screen === 'failed' && (
                            <div className="rounded-3xl bg-white px-6 py-8 text-center shadow-sm">
                                <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-600 text-white">
                                    <svg viewBox="0 0 24 24" className="h-10 w-10" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                                        <path d="M6 9V5h12v4" />
                                        <path d="M6 18h12v-5H6v5z" />
                                        <path d="M6 14H4a2 2 0 01-2-2v-1a2 2 0 012-2h16a2 2 0 012 2v1a2 2 0 01-2 2h-2" />
                                        <path d="M4 4l16 16" />
                                    </svg>
                                </span>
                                <p className="mt-4 text-[1.25rem] font-black text-ink">{paymentEnded === 'abandoned' ? t.payHoldExpiredTitle : t.payFailedTitle}</p>
                                <p className="mt-2 text-[13px] font-medium leading-5 text-slate">{paymentEnded === 'abandoned' ? t.payHoldExpiredBody : t.payFailedBody}</p>
                                <Link
                                    href="/print"
                                    className="mt-5 inline-flex h-12 items-center justify-center rounded-2xl bg-primary px-5 text-[15px] font-black text-white"
                                >
                                    {t.pinRestart}
                                </Link>
                            </div>
                        )}
                    </section>
                )}
            </div>
            {phase === 'done' && (
                <PaidDialog title={t.paySuccessTitle} body={t.payDoneBody} amount={formatMoney(quote.amount, locale)} />
            )}
            {phase !== 'done' && <PayErrorDialog code={paymentError} copy={t} />}
        </PhoneShell>
    );
}

function phaseOf(status) {
    if (status === 'printed') {
        return 'done';
    }
    if (status === 'failed') {
        return 'failed';
    }
    if (status === 'paid' || status === 'printing') {
        return 'printing';
    }
    return 'pay';
}

function PrintMonitor({ title, hint, pagesLabel, paidLabel, current, total, percent, amount }) {
    const spinning = percent <= 0;
    const turn = Math.min(100, Math.max(0, percent));

    return (
        <div className="flex flex-col items-center px-2 pt-4">
            <span className="relative flex h-[88px] w-[88px] items-center justify-center text-primary">
                <svg viewBox="0 0 88 88" className={`absolute inset-0 h-full w-full ${spinning ? 'animate-spin' : '-rotate-90'}`} aria-hidden>
                    <circle cx="44" cy="44" r="36" fill="none" stroke="currentColor" strokeOpacity="0.15" strokeWidth="7" />
                    <circle
                        cx="44"
                        cy="44"
                        r="36"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="7"
                        strokeLinecap="round"
                        strokeDasharray={spinning ? '48 180' : `${(turn / 100) * 226} 226`}
                    />
                </svg>
                <svg viewBox="0 0 24 24" className="relative h-9 w-9" fill="currentColor" aria-hidden>
                    <path d="M6 9V3h12v6H6zm12 2H6a3 3 0 00-3 3v4h4v3h10v-3h4v-4a3 3 0 00-3-3zm-2 8H8v-4h8v4z" />
                </svg>
            </span>
            <p className="mt-5 text-center text-[1.6rem] font-black tracking-tight text-ink">{title}</p>
            <p className="mt-2 text-center text-[14px] font-medium text-slate">{hint}</p>
            <div className="mt-5 w-full rounded-[20px] border border-emerald-200 bg-white px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-[14px] font-semibold text-slate">{pagesLabel}</p>
                    <p className="text-[1.15rem] font-black tabular-nums text-ink">{current} / {total}</p>
                </div>
                <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-emerald-100">
                    <div
                        className={`h-full rounded-full bg-primary ${spinning ? 'w-1/3 animate-pulse' : ''}`}
                        style={spinning ? undefined : { width: `${Math.max(turn, 4)}%` }}
                    />
                </div>
                <div className="mt-3 flex items-center justify-between gap-3">
                    <p className="text-[14px] font-semibold text-slate">{paidLabel}</p>
                    <p className="text-[1.15rem] font-black text-primary">{amount}</p>
                </div>
            </div>
        </div>
    );
}

function PaidDialog({ title, body, amount }) {
    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-[#101513]/45 px-6">
            <div className="w-full max-w-[320px] rounded-3xl bg-white px-6 py-8 text-center shadow-2xl" style={{ animation: 'metbati-pop 360ms ease-out' }}>
                <style>{`@keyframes metbati-pop { from { transform: scale(.82); opacity: 0 } to { transform: scale(1); opacity: 1 } } @keyframes metbati-draw { to { stroke-dashoffset: 0 } }`}</style>
                <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50">
                    <svg viewBox="0 0 52 52" className="h-14 w-14 text-primary" aria-hidden>
                        <circle cx="26" cy="26" r="22" fill="none" stroke="currentColor" strokeWidth="3" />
                        <path
                            d="M15 27l7 7 15-16"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="3.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeDasharray="48"
                            strokeDashoffset="48"
                            style={{ animation: 'metbati-draw 420ms 160ms ease-out forwards' }}
                        />
                    </svg>
                </span>
                <p className="mt-5 text-[1.25rem] font-black text-ink">{title}</p>
                <p className="mt-1 text-[1.05rem] font-black text-primary">{amount}</p>
                <p className="mt-2 text-[13px] font-medium leading-5 text-slate">{body}</p>
            </div>
        </div>
    );
}
