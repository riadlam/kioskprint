import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PhoneShell from '../../Components/Print/PhoneShell';
import { copy } from '../../lib/printCopy';

const LOCALE_KEY = 'metbati.print.locale';

export default function Pin({ kioskId }) {
    const pageErrors = usePage().props.errors || {};
    const [locale, setLocale] = useState(() => sessionStorage.getItem(LOCALE_KEY) || 'ar');
    const form = useForm({ code: '', kiosk: kioskId });
    const t = copy[locale] || copy.ar;
    const codeError = form.errors.code || pageErrors.code || '';
    const message = codeError === 'limited' ? t.pinLimited : codeError ? t.pinWrong : '';

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function push(digit) {
        if (form.processing || form.data.code.length >= 3) {
            return;
        }
        const next = `${form.data.code}${digit}`;
        form.setData('code', next);
        if (next.length === 3) {
            form.transform(() => ({ code: next, kiosk: kioskId }));
            form.post('/print/desk', { preserveScroll: true });
        }
    }

    function backspace() {
        form.clearErrors();
        form.setData('code', form.data.code.slice(0, -1));
    }

    const slots = [0, 1, 2].map((index) => form.data.code[index] || '');

    return (
        <PhoneShell locale={locale} onLocale={changeLocale} step={1}>
            <Head title={t.pinTitle} />
            <div className="flex flex-1 flex-col bg-[#f4faf7] px-5 pb-8 pt-8">
                <p className="text-center text-[1.45rem] font-black leading-tight text-ink">{t.pinTitle}</p>
                <p className="mx-auto mt-3 max-w-xs text-center text-[14px] font-medium leading-6 text-slate">{t.pinHint}</p>

                <div className="mt-8 flex justify-center gap-3" dir="ltr">
                    {slots.map((slot, index) => (
                        <span
                            key={index}
                            className="flex h-16 w-14 items-center justify-center rounded-2xl bg-white text-[1.7rem] font-black text-ink shadow-sm"
                        >
                            {slot}
                        </span>
                    ))}
                </div>

                {message ? (
                    <p className="mt-4 text-center text-[13px] font-semibold text-red-600">{message}</p>
                ) : null}

                <div className="mx-auto mt-8 grid w-full max-w-xs grid-cols-3 gap-3" dir="ltr">
                    {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((digit) => (
                        <button
                            key={digit}
                            type="button"
                            className="h-16 rounded-2xl bg-white text-[1.4rem] font-black text-ink shadow-sm"
                            onClick={() => push(digit)}
                        >
                            {digit}
                        </button>
                    ))}
                    <span />
                    <button
                        type="button"
                        className="h-16 rounded-2xl bg-white text-[1.4rem] font-black text-ink shadow-sm"
                        onClick={() => push('0')}
                    >
                        0
                    </button>
                    <button
                        type="button"
                        className="h-16 rounded-2xl bg-emerald-900/5 text-[13px] font-bold text-slate"
                        onClick={backspace}
                    >
                        ⌫
                    </button>
                </div>
            </div>
        </PhoneShell>
    );
}
