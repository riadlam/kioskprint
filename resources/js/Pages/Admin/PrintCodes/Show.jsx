import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminShell from '../../../Components/Admin/Shell';

export default function Show({ code, email, role }) {
    const flash = usePage().props.flash?.success;
    const [copied, setCopied] = useState(false);

    const copyPin = async () => {
        if (!code.pin) return;
        try {
            await navigator.clipboard.writeText(code.pin);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch (_) {}
    };

    return (
        <AdminShell
            title={code.title}
            subtitle="شارك الرمز مع الطلبة للطباعة من الكشك"
            active="print-codes"
            email={email}
            role={role}
        >
            {flash && (
                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[14px] text-emerald-900">
                    {flash}
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <section className="rounded-2xl border border-black/5 bg-white p-6 shadow-sm md:p-8">
                    <p className="text-[12px] font-semibold uppercase tracking-wider text-slate">رمز الطباعة</p>
                    <p className="mt-3 font-mono text-5xl font-black tracking-[0.35em] text-primary md:text-6xl">
                        {code.pin || '----'}
                    </p>
                    <button
                        type="button"
                        onClick={copyPin}
                        className="mt-5 inline-flex rounded-xl bg-primary px-5 py-3 text-[14px] font-semibold text-white"
                    >
                        {copied ? 'تم النسخ' : 'نسخ الرمز'}
                    </button>
                    <div className="mt-8 grid gap-3 sm:grid-cols-3">
                        <Stat label="الصفحات" value={code.page_count} />
                        <Stat label="المبلغ" value={`${code.amount} ${code.currency}`} />
                        <Stat label="مرات الطباعة" value={code.print_count} />
                    </div>
                </section>

                <section className="rounded-2xl border border-black/5 bg-white p-6 shadow-sm md:p-8">
                    <h2 className="text-[15px] font-semibold text-ink">التفاصيل</h2>
                    <dl className="mt-4 space-y-3 text-[14px]">
                        <Row label="الملف" value={code.original_name} />
                        <Row label="السعر / صفحة" value={`${code.unit_price} ${code.currency}`} />
                        <Row label="أُنشئ" value={code.created_at} />
                        <Row label="آخر تحديث" value={code.updated_at} />
                    </dl>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link
                            href={`/admin/print-codes/${code.id}/edit`}
                            className="rounded-xl border border-black/10 px-4 py-2.5 text-[14px] font-semibold text-ink"
                        >
                            تعديل
                        </Link>
                        <Link
                            href="/admin/print-codes"
                            className="rounded-xl px-4 py-2.5 text-[14px] font-semibold text-primary"
                        >
                            كل الرموز
                        </Link>
                    </div>
                </section>
            </div>
        </AdminShell>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-xl bg-[#f8faf9] px-4 py-3">
            <p className="text-[12px] text-slate">{label}</p>
            <p className="mt-1 text-[18px] font-bold text-ink">{value}</p>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-black/5 pb-3">
            <dt className="text-slate">{label}</dt>
            <dd className="text-end font-medium text-ink">{value}</dd>
        </div>
    );
}
