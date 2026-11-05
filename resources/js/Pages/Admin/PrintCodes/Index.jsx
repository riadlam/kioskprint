import { Link, router } from '@inertiajs/react';
import AdminShell from '../../../Components/Admin/Shell';

export default function Index({ codes = [], email, role, pricePerPage = 20 }) {
    return (
        <AdminShell
            title="رموز الطباعة"
            subtitle="أنشئ مستندًا وشارك الرمز مع الطلبة"
            active="print-codes"
            email={email}
            role={role}
        >
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-[14px] text-slate">
                    السعر: <span className="font-semibold text-ink">{pricePerPage} DA</span> لكل صفحة
                </p>
                <Link
                    href="/admin/print-codes/create"
                    className="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 text-[14px] font-semibold text-white"
                >
                    مهمة طباعة جديدة
                </Link>
            </div>

            <div className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-[14px]">
                        <thead className="bg-[#f8faf9] text-[11px] uppercase tracking-wider text-slate">
                            <tr>
                                <th className="px-5 py-3 font-semibold">الاسم</th>
                                <th className="px-5 py-3 font-semibold">الرمز</th>
                                <th className="px-5 py-3 font-semibold">الصفحات</th>
                                <th className="px-5 py-3 font-semibold">المبلغ</th>
                                <th className="px-5 py-3 font-semibold">طُبع</th>
                                <th className="px-5 py-3 font-semibold" />
                            </tr>
                        </thead>
                        <tbody>
                            {codes.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-14 text-center text-slate">
                                        لا توجد رموز بعد. أنشئ أول مهمة طباعة.
                                    </td>
                                </tr>
                            )}
                            {codes.map((code) => (
                                <tr key={code.id} className="border-t border-black/5">
                                    <td className="px-5 py-4">
                                        <p className="font-semibold text-ink">{code.title}</p>
                                        <p className="mt-0.5 text-[12px] text-slate">{code.original_name}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className="rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-[16px] font-bold tracking-[0.2em] text-primary">
                                            {code.pin || '—'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">{code.page_count}</td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        {code.amount} {code.currency}
                                    </td>
                                    <td className="px-5 py-4 font-semibold">{code.print_count}</td>
                                    <td className="px-5 py-4">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Link
                                                href={`/admin/print-codes/${code.id}`}
                                                className="rounded-lg px-3 py-1.5 text-[13px] font-semibold text-primary"
                                            >
                                                عرض
                                            </Link>
                                            <Link
                                                href={`/admin/print-codes/${code.id}/edit`}
                                                className="rounded-lg px-3 py-1.5 text-[13px] font-semibold text-slate"
                                            >
                                                تعديل
                                            </Link>
                                            <button
                                                type="button"
                                                className="rounded-lg px-3 py-1.5 text-[13px] font-semibold text-red-600"
                                                onClick={() => {
                                                    if (confirm('حذف هذا الرمز؟ لن يتمكن الطلبة من استخدامه.')) {
                                                        router.delete(`/admin/print-codes/${code.id}`);
                                                    }
                                                }}
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminShell>
    );
}
