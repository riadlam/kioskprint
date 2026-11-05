import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AdminShell from '../../../Components/Admin/Shell';
import Modal from '../../../Components/Admin/Modal';

export default function Index({ codes = [], email, role }) {
    const page = usePage();
    const flashSuccess = page.props.flash?.success;
    const flashShared = page.props.flash?.shared_code;

    const [query, setQuery] = useState('');
    const [createOpen, setCreateOpen] = useState(false);
    const [editCode, setEditCode] = useState(null);
    const [shareCode, setShareCode] = useState(null);
    const [deleteCode, setDeleteCode] = useState(null);
    const [banner, setBanner] = useState(null);

    useEffect(() => {
        if (flashSuccess) {
            setBanner(flashSuccess);
        }
        if (flashShared) {
            setShareCode(flashShared);
        }
    }, [flashSuccess, flashShared]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return codes;
        }
        return codes.filter((code) =>
            [code.title, code.original_name, code.pin]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(q)),
        );
    }, [codes, query]);

    return (
        <AdminShell
            title="مستنداتي"
            subtitle="ارفع المستند، احصل على رمز، وشاركه مع الطلبة للطباعة من الكشك"
            active="print-codes"
            email={email}
            role={role}
        >
            {banner && (
                <div className="mb-5 flex items-start justify-between gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-[14px] text-emerald-900">
                    <p>{banner}</p>
                    <button type="button" className="text-emerald-700/70 hover:text-emerald-900" onClick={() => setBanner(null)}>
                        إغلاق
                    </button>
                </div>
            )}

            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-sm">
                    <svg
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                            clipRule="evenodd"
                        />
                    </svg>
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="بحث بالاسم أو الرمز…"
                        className="w-full rounded-xl border border-black/8 bg-white py-2.5 pe-3 ps-10 text-[14px] outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                    />
                </div>
                <button
                    type="button"
                    onClick={() => setCreateOpen(true)}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[14px] font-semibold text-white shadow-sm transition hover:bg-primary-hover"
                >
                    <span className="text-[18px] leading-none">+</span>
                    مستند جديد
                </button>
            </div>

            {filtered.length === 0 ? (
                <EmptyState onCreate={() => setCreateOpen(true)} hasCodes={codes.length > 0} />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {filtered.map((code) => (
                        <article
                            key={code.id}
                            className="group flex flex-col rounded-2xl border border-black/10 bg-white p-5 shadow-[0_2px_8px_rgba(16,24,40,0.06)] transition hover:border-primary/25 hover:shadow-[0_10px_28px_rgba(16,24,40,0.08)]"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h3 className="truncate text-[16px] font-bold text-ink">{code.title}</h3>
                                    <p className="mt-1 truncate text-[12px] text-slate">{code.original_name}</p>
                                </div>
                                <span className="shrink-0 rounded-full bg-[#f3f5f4] px-2.5 py-1 text-[11px] font-semibold text-slate">
                                    {code.page_count} {code.page_count === 1 ? 'صفحة' : 'صفحات'}
                                </span>
                            </div>

                            <button
                                type="button"
                                onClick={() => setShareCode(code)}
                                className="mt-5 flex w-full items-center justify-between rounded-xl bg-[#ecfdf5] px-4 py-3 text-start transition hover:bg-emerald-100/70"
                            >
                                <div>
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-800/70">
                                        رمز الطباعة
                                    </p>
                                    <p className="mt-1 font-mono text-[28px] font-black tracking-[0.28em] text-primary">
                                        {code.pin || '----'}
                                    </p>
                                </div>
                                <span className="rounded-lg bg-white px-2.5 py-1.5 text-[12px] font-semibold text-primary shadow-sm">
                                    مشاركة
                                </span>
                            </button>

                            <div className="mt-4 flex items-center justify-between text-[13px] text-slate">
                                <span>
                                    طُبع <strong className="text-ink">{code.print_count}</strong> مرة
                                </span>
                                <span className="text-[12px]">{code.created_at}</span>
                            </div>

                            <div className="mt-5 flex gap-2 border-t border-black/5 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setEditCode(code)}
                                    className="flex-1 rounded-xl border border-black/8 px-3 py-2.5 text-[13px] font-semibold text-ink transition hover:bg-[#f8faf9]"
                                >
                                    تعديل
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDeleteCode(code)}
                                    className="rounded-xl px-3 py-2.5 text-[13px] font-semibold text-red-600 transition hover:bg-red-50"
                                >
                                    حذف
                                </button>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            <CodeFormModal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                mode="create"
            />
            <CodeFormModal
                open={Boolean(editCode)}
                onClose={() => setEditCode(null)}
                mode="edit"
                code={editCode}
            />
            <SharePinModal open={Boolean(shareCode)} code={shareCode} onClose={() => setShareCode(null)} />
            <DeleteConfirmModal
                open={Boolean(deleteCode)}
                code={deleteCode}
                onClose={() => setDeleteCode(null)}
            />
        </AdminShell>
    );
}

function EmptyState({ onCreate, hasCodes }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/15 bg-white px-6 py-16 text-center shadow-[0_2px_8px_rgba(16,24,40,0.04)]">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-7 w-7">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6M7 4h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                </svg>
            </div>
            <h3 className="mt-5 text-[18px] font-bold text-ink">
                {hasCodes ? 'لا نتائج مطابقة' : 'ابدأ بأول مستند'}
            </h3>
            <p className="mt-2 max-w-md text-[14px] leading-relaxed text-slate">
                {hasCodes
                    ? 'جرّب كلمة بحث أخرى أو امسح البحث لعرض كل المستندات.'
                    : 'ارفع ملف PDF أو صورة، ثم شارك الرمز الرباعي مع طلبتك للطباعة من كشك مطبعتـي.'}
            </p>
            {!hasCodes && (
                <button
                    type="button"
                    onClick={onCreate}
                    className="mt-6 inline-flex rounded-xl bg-primary px-5 py-2.5 text-[14px] font-semibold text-white"
                >
                    رفع مستند
                </button>
            )}
        </div>
    );
}

function CodeFormModal({ open, onClose, mode, code = null }) {
    const editing = mode === 'edit';
    const form = useForm({
        title: code?.title || '',
        file: null,
        regenerate_pin: false,
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        form.setData({
            title: code?.title || '',
            file: null,
            regenerate_pin: false,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, code?.id]);

    const submit = (event) => {
        event.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onClose(),
        };
        if (editing) {
            form.post(`/admin/print-codes/${code.id}`, options);
            return;
        }
        form.post('/admin/print-codes', options);
    };

    return (
        <Modal
            open={open}
            onClose={() => {
                if (!form.processing) {
                    onClose();
                }
            }}
            title={editing ? 'تعديل المستند' : 'مستند جديد'}
            subtitle={editing ? 'حدّث الاسم أو استبدل الملف عند الحاجة.' : 'أدخل اسمًا واضحًا وارفع الملف لإنشاء رمز الطباعة.'}
            wide
        >
            <form onSubmit={submit} className="space-y-5">
                <Field
                    label="اسم المستند"
                    error={form.errors.title}
                >
                    <input
                        type="text"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        className="field-input"
                        placeholder="مثال: محاضرة الأسبوع 3"
                        required
                    />
                </Field>

                <Field
                    label={editing ? 'استبدال الملف (اختياري)' : 'الملف'}
                    error={form.errors.file}
                    hint={editing ? `الحالي: ${code?.original_name} · ${code?.page_count} صفحة` : 'PDF أو صورة (PNG / JPG / WEBP)'}
                >
                    <label className="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-black/12 bg-[#f8faf9] px-4 py-8 text-center transition hover:border-primary/40 hover:bg-emerald-50/40">
                        <span className="text-[14px] font-semibold text-ink">
                            {form.data.file ? form.data.file.name : 'اختر ملفًا أو اسحبه هنا'}
                        </span>
                        <span className="mt-1 text-[12px] text-slate">حد أقصى مناسب للطباعة من الكشك</span>
                        <input
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg,.webp"
                            className="sr-only"
                            required={!editing}
                            onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                        />
                    </label>
                </Field>

                {editing && (
                    <label className="flex items-center gap-3 rounded-xl border border-black/6 bg-[#f8faf9] px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.regenerate_pin}
                            onChange={(e) => form.setData('regenerate_pin', e.target.checked)}
                            className="h-4 w-4 rounded border-black/20 text-primary focus:ring-primary"
                        />
                        <span className="text-[14px] font-medium text-ink">توليد رمز طباعة جديد</span>
                    </label>
                )}

                <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={form.processing}
                        className="rounded-xl border border-black/8 px-4 py-2.5 text-[14px] font-semibold text-ink disabled:opacity-50"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-primary px-5 py-2.5 text-[14px] font-semibold text-white disabled:opacity-60"
                    >
                        {form.processing ? 'جارٍ الحفظ…' : editing ? 'حفظ التغييرات' : 'إنشاء الرمز'}
                    </button>
                </div>
            </form>
            <style>{`
                .field-input {
                    width: 100%;
                    border-radius: 0.75rem;
                    border: 1px solid rgba(0,0,0,0.1);
                    padding: 0.75rem 1rem;
                    font-size: 15px;
                    outline: none;
                }
                .field-input:focus {
                    border-color: var(--color-primary);
                    box-shadow: 0 0 0 3px color-mix(in oklab, var(--color-primary) 18%, transparent);
                }
            `}</style>
        </Modal>
    );
}

function SharePinModal({ open, code, onClose }) {
    const [copied, setCopied] = useState(false);

    const copyPin = async () => {
        if (!code?.pin) {
            return;
        }
        try {
            await navigator.clipboard.writeText(code.pin);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch (_) {}
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="شارك رمز الطباعة"
            subtitle="يعرض الطلبة هذا الرمز على شاشة الكشك لطباعة المستند."
        >
            {code && (
                <div className="space-y-5">
                    <div className="rounded-2xl bg-[#ecfdf5] px-5 py-6 text-center">
                        <p className="text-[12px] font-semibold text-emerald-800/70">{code.title}</p>
                        <p className="mt-3 font-mono text-5xl font-black tracking-[0.35em] text-primary sm:text-6xl">
                            {code.pin || '----'}
                        </p>
                        <p className="mt-3 text-[13px] text-slate">
                            {code.page_count} {code.page_count === 1 ? 'صفحة' : 'صفحات'} · طُبع {code.print_count} مرة
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            onClick={copyPin}
                            className="flex-1 rounded-xl bg-primary px-4 py-3 text-[14px] font-semibold text-white"
                        >
                            {copied ? 'تم نسخ الرمز' : 'نسخ الرمز'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-black/8 px-4 py-3 text-[14px] font-semibold text-ink"
                        >
                            تم
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function DeleteConfirmModal({ open, code, onClose }) {
    const [busy, setBusy] = useState(false);

    const confirm = () => {
        if (!code) {
            return;
        }
        setBusy(true);
        router.delete(`/admin/print-codes/${code.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={() => {
                if (!busy) {
                    onClose();
                }
            }}
            title="حذف المستند؟"
            subtitle="سيتوقف الرمز فورًا. لن يتمكن الطلبة من استخدامه بعد الآن."
            danger
        >
            {code && (
                <div className="space-y-5">
                    <div className="rounded-xl border border-red-100 bg-red-50/50 px-4 py-3 text-[14px]">
                        <p className="font-semibold text-ink">{code.title}</p>
                        <p className="mt-1 font-mono tracking-[0.2em] text-slate">{code.pin}</p>
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={busy}
                            className="rounded-xl border border-black/8 px-4 py-2.5 text-[14px] font-semibold text-ink disabled:opacity-50"
                        >
                            إلغاء
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={busy}
                            className="rounded-xl bg-red-600 px-5 py-2.5 text-[14px] font-semibold text-white disabled:opacity-60"
                        >
                            {busy ? 'جارٍ الحذف…' : 'تأكيد الحذف'}
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function Field({ label, error, hint, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-[13px] font-semibold text-ink">{label}</span>
            {children}
            {hint && <p className="mt-1.5 text-[12px] text-slate">{hint}</p>}
            {error && <p className="mt-1.5 text-[13px] text-red-600">{error}</p>}
        </label>
    );
}
