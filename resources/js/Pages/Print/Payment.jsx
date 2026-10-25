import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PhoneShell from '../../Components/Print/PhoneShell';
import { kioskEcho } from '../../lib/kioskEcho';
import { copy, formatMoney } from '../../lib/printCopy';

const LOCALE_KEY = 'metbati.print.locale';

export default function Payment({ job, quote, methods }) {
    const { flash } = usePage().props;
    const [locale, setLocale] = useState(() => sessionStorage.getItem(LOCALE_KEY) || job.locale || 'ar');
    const form = useForm({ payment_method: '' });
    const t = copy[locale];
    const selected = flash?.payment_started || job.payment_method || '';
    const monnayeurActive = selected === 'monnayeur';
    const posteActive = selected === 'algerie_poste';
    const postePaid = posteActive && ['paid', 'printing', 'printed'].includes(job.status);

    const [inserted, setInserted] = useState(() => Number(job.amount_inserted || 0));
    const [complete, setComplete] = useState(
        job.status === 'paid' || job.status === 'printing' || job.status === 'printed',
    );
    const [onChooser, setOnChooser] = useState(false);
    const required = Number(quote.amount || 0);
    const remaining = Math.max(0, Math.round((required - inserted) * 100) / 100);
    const progress = required > 0 ? Math.min(1, inserted / required) : 0;

    useEffect(() => {
        if (!monnayeurActive || complete) {
            return undefined;
        }

        if (!job.kiosk_id) {
            return undefined;
        }

        const echo = kioskEcho();
        if (!echo) {
            return undefined;
        }

        const name = `kiosk.${job.kiosk_id}`;
        const channel = echo.channel(name);

        function apply(event) {
            if (!event || event.job_id !== job.id) {
                return;
            }
            if (event.inserted !== undefined) {
                setInserted((current) => Math.max(current, Number(event.inserted || 0)));
            }
            if (['paid', 'printing', 'printed'].includes(event.status)) {
                setInserted((current) => Math.max(current, required));
                setComplete(true);
            }
        }

        channel.listen('.PrintJobPaymentProgress', apply);
        channel.listen('.PrintJobPaid', apply);

        return () => {
            echo.leave(name);
            echo.disconnect();
        };
    }, [monnayeurActive, complete, job.id, job.kiosk_id, required]);

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function pay(methodId) {
        if (selected === methodId && form.processing) {
            return;
        }
        setOnChooser(false);
        setComplete(false);
        setInserted(0);
        form.transform(() => ({ payment_method: methodId }));
        form.post(`/print/${job.id}/pay`, { preserveScroll: true });
    }

    const paid = complete || postePaid;
    const screen = onChooser || (!monnayeurActive && !posteActive) ? 'choose' : monnayeurActive ? 'cash' : 'poste';

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

                                return (
                                    <button
                                        key={method.id}
                                        type="button"
                                        disabled={form.processing}
                                        onClick={() => pay(method.id)}
                                        className="flex w-full items-center gap-4 rounded-2xl border border-emerald-900/10 bg-white px-4 py-4 text-start transition active:scale-[0.99]"
                                    >
                                        <span className={`flex h-[4.25rem] w-[4.25rem] shrink-0 items-center justify-center rounded-2xl ${gold ? 'bg-[#fff8db]' : 'bg-emerald-50'}`}>
                                            <img src={method.icon} alt="" className={`object-contain ${gold ? 'h-14 w-14' : 'h-11 w-11'}`} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-[1.05rem] font-extrabold leading-snug text-ink">{info.title}</span>
                                            <span className="mt-1 block text-[13px] font-medium leading-snug text-slate">{info.hint}</span>
                                        </span>
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#eef2f0] text-[15px] font-black text-slate">›</span>
                                    </button>
                                );
                            })}
                        </div>
                        {form.errors.payment_method && (
                            <p className="mt-3 text-[13px] font-semibold text-red-700">{form.errors.payment_method}</p>
                        )}
                    </section>
                ) : (
                    <section className="flex flex-1 flex-col px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                        <button
                            type="button"
                            onClick={() => setOnChooser(true)}
                            className="mb-4 self-start text-[13px] font-bold text-slate"
                        >
                            {t.payChangeMethod}
                        </button>

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
                                <div className="mt-4">
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
                                    <div className={`h-full rounded-full bg-[#1e2a78] ${postePaid ? 'w-full' : 'w-2/3 animate-pulse'}`} />
                                </div>
                            </div>
                        )}
                    </section>
                )}
            </div>
            {paid && <PaidDialog title={t.payComplete} body={t.payDoneBody} amount={formatMoney(quote.amount, locale)} />}
        </PhoneShell>
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
