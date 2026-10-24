import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PhoneShell from '../../Components/Print/PhoneShell';
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
        job.status === 'paid' || job.status === 'printed',
    );
    const required = Number(quote.amount || 0);
    const remaining = Math.max(0, Math.round((required - inserted) * 100) / 100);
    const progress = required > 0 ? Math.min(1, inserted / required) : 0;

    useEffect(() => {
        if (!monnayeurActive || complete) {
            return undefined;
        }

        let cancelled = false;

        async function poll() {
            try {
                const res = await fetch(`/print/${job.id}/pay/status`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok || cancelled) {
                    return;
                }
                const data = await res.json();
                if (cancelled) {
                    return;
                }
                setInserted((current) => Math.max(current, Number(data.inserted || 0)));
                if (data.complete) {
                    setComplete(true);
                }
            } catch {
                // keep polling; machine may reconnect
            }
        }

        poll();
        const id = window.setInterval(poll, 1000);

        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [monnayeurActive, complete, job.id]);

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function pay(methodId) {
        if (selected === methodId && form.processing) {
            return;
        }
        setComplete(false);
        setInserted(0);
        form.transform(() => ({ payment_method: methodId }));
        form.post(`/print/${job.id}/pay`, { preserveScroll: true });
    }

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

                <section className="flex flex-1 flex-col px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                    <p className="mb-3 text-[15px] font-extrabold text-ink">{t.payChoose}</p>

                    <div className="space-y-3">
                        {methods.map((method) => {
                            const info = meta[method.id];
                            const active = selected === method.id;
                            const gold = info.tone === 'gold';

                            return (
                                <button
                                    key={method.id}
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => pay(method.id)}
                                    className={`flex w-full items-center gap-4 rounded-2xl border px-4 py-4 text-start transition active:scale-[0.99] ${
                                        active
                                            ? gold
                                                ? 'border-[#1e2a78] bg-white shadow-md ring-1 ring-[#1e2a78]/20'
                                                : 'border-primary bg-white shadow-md ring-1 ring-primary/20'
                                            : 'border-emerald-900/10 bg-white'
                                    }`}
                                >
                                    <span
                                        className={`flex h-[4.25rem] w-[4.25rem] shrink-0 items-center justify-center rounded-2xl ${
                                            active
                                                ? gold
                                                    ? 'bg-[#fff8db]'
                                                    : 'bg-emerald-50'
                                                : 'bg-[#f3f5f4]'
                                        }`}
                                    >
                                        <img
                                            src={method.icon}
                                            alt=""
                                            className={`object-contain ${gold ? 'h-14 w-14' : 'h-11 w-11'}`}
                                        />
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[1.05rem] font-extrabold leading-snug text-ink">
                                            {info.title}
                                        </span>
                                        <span className="mt-1 block text-[13px] font-medium leading-snug text-slate">
                                            {info.hint}
                                        </span>
                                    </span>

                                    <span
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-[15px] font-black ${
                                            active
                                                ? gold
                                                    ? 'bg-[#1e2a78] text-white'
                                                    : 'bg-primary text-white'
                                                : 'bg-[#eef2f0] text-slate'
                                        }`}
                                    >
                                        {active ? '✓' : '›'}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Status under options — same place as old “selected” strip */}
                    {monnayeurActive && (
                        <div className="mt-4 rounded-2xl border border-emerald-200 bg-white px-4 py-4">
                            <div className="flex items-center gap-3">
                                {!complete ? (
                                    <span className="relative flex h-11 w-11 shrink-0 items-center justify-center">
                                        <span className="absolute inset-0 animate-ping rounded-full bg-primary/15" />
                                        <span className="relative flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                                            <svg
                                                className="h-5 w-5 animate-spin text-primary"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                aria-hidden
                                            >
                                                <circle
                                                    className="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    strokeWidth="3"
                                                />
                                                <path
                                                    className="opacity-90"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"
                                                />
                                            </svg>
                                        </span>
                                    </span>
                                ) : (
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-lg font-black text-white">
                                        ✓
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="text-[14px] font-extrabold text-ink">
                                        {complete ? t.payComplete : t.payInsertCash}
                                    </p>
                                    <p className="mt-0.5 text-[12px] font-medium text-slate">
                                        {complete ? t.paySelected : t.payListening}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-3 grid grid-cols-2 gap-2">
                                <div className="rounded-xl bg-emerald-50 px-3 py-2.5 text-center">
                                    <p className="text-[11px] font-bold text-primary">{t.payInserted}</p>
                                    <p className="mt-0.5 text-[1.05rem] font-black tabular-nums text-ink">
                                        {formatMoney(inserted, locale)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-[#f4faf7] px-3 py-2.5 text-center">
                                    <p className="text-[11px] font-bold text-slate">{t.payRemaining}</p>
                                    <p className="mt-0.5 text-[1.05rem] font-black tabular-nums text-ink">
                                        {formatMoney(remaining, locale)}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-3">
                                <div className="mb-1 flex items-center justify-between text-[11px] font-semibold text-slate">
                                    <span>
                                        {t.payDue}: {formatMoney(required, locale)}
                                    </span>
                                    <span>{Math.round(progress * 100)}%</span>
                                </div>
                                <div className="h-2.5 overflow-hidden rounded-full bg-emerald-100">
                                    <div
                                        className={`h-full rounded-full transition-all duration-500 ${
                                            complete ? 'bg-primary' : 'bg-primary animate-pulse'
                                        }`}
                                        style={{ width: `${Math.max(progress * 100, complete ? 100 : 4)}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {posteActive && (
                        <div className="mt-4 rounded-2xl border border-[#1e2a78]/20 bg-white px-4 py-3">
                            <p className="text-[13px] font-extrabold text-ink">
                                {postePaid ? t.payComplete : t.paySelected}
                            </p>
                            <p className="mt-1 text-[13px] font-medium text-slate">
                                {postePaid ? t.payComplete : meta.algerie_poste.waiting}
                            </p>
                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-[#eef0f8]">
                                <div
                                    className={`h-full rounded-full bg-[#1e2a78] ${
                                        postePaid ? 'w-full' : 'w-2/3 animate-pulse'
                                    }`}
                                />
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </PhoneShell>
    );
}
