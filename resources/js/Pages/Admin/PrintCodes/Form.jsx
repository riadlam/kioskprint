import { Link, useForm } from '@inertiajs/react';
import AdminShell from '../../../Components/Admin/Shell';

export default function Form({ code = null, email, role, pricePerPage = 20 }) {
    const editing = Boolean(code?.id);
    const form = useForm({
        title: code?.title || '',
        file: null,
        regenerate_pin: false,
    });

    const onSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            form.post(`/admin/print-codes/${code.id}`, { forceFormData: true });
            return;
        }
        form.post('/admin/print-codes', { forceFormData: true });
    };

    return (
        <AdminShell
            title={editing ? 'تعديل مهمة الطباعة' : 'مهمة طباعة جديدة'}
            subtitle="ارفع المستند وحدد الاسم — يُحسب السعر تلقائيًا"
            active="print-codes"
            email={email}
            role={role}
        >
            <form onSubmit={onSubmit} className="mx-auto max-w-2xl rounded-2xl border border-black/5 bg-white p-6 shadow-sm md:p-8">
                <div className="space-y-5">
                    <label className="block">
                        <span className="mb-1.5 block text-[13px] font-semibold text-ink">اسم المستند</span>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className="w-full rounded-xl border border-black/10 px-4 py-3 text-[15px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="مثال: امتحان الوحدة 1"
                            required
                        />
                        {form.errors.title && <p className="mt-1 text-[13px] text-red-600">{form.errors.title}</p>}
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-[13px] font-semibold text-ink">
                            الملف {editing ? '(اختياري للتغيير)' : ''}
                        </span>
                        <input
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg,.webp"
                            onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                            className="w-full rounded-xl border border-dashed border-black/15 bg-[#f8faf9] px-4 py-8 text-[14px]"
                            required={!editing}
                        />
                        {editing && (
                            <p className="mt-2 text-[13px] text-slate">
                                الحالي: {code.original_name} — {code.page_count} صفحة — {code.amount} {code.currency}
                            </p>
                        )}
                        {form.errors.file && <p className="mt-1 text-[13px] text-red-600">{form.errors.file}</p>}
                    </label>

                    {editing && (
                        <label className="flex items-center gap-3 rounded-xl border border-black/5 bg-[#f8faf9] px-4 py-3">
                            <input
                                type="checkbox"
                                checked={form.data.regenerate_pin}
                                onChange={(e) => form.setData('regenerate_pin', e.target.checked)}
                            />
                            <span className="text-[14px] font-medium text-ink">توليد رمز PIN جديد</span>
                        </label>
                    )}

                    <p className="rounded-xl bg-emerald-50 px-4 py-3 text-[13px] text-emerald-900">
                        السعر = عدد الصفحات × {pricePerPage} DA. يُنشأ رمز من 4 أرقام عند الحفظ.
                    </p>
                </div>

                <div className="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link
                        href={editing ? `/admin/print-codes/${code.id}` : '/admin/print-codes'}
                        className="inline-flex items-center justify-center rounded-xl border border-black/10 px-5 py-3 text-[14px] font-semibold text-ink"
                    >
                        إلغاء
                    </Link>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 text-[14px] font-semibold text-white disabled:opacity-60"
                    >
                        {form.processing ? 'جارٍ الحفظ…' : editing ? 'حفظ التغييرات' : 'حفظ وإنشاء الرمز'}
                    </button>
                </div>
            </form>
        </AdminShell>
    );
}
