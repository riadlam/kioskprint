import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminShell from '../../../Components/Admin/Shell';
import Modal from '../../../Components/Admin/Modal';

export default function Index({ professors = [], email, role }) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editProfessor, setEditProfessor] = useState(null);

    return (
        <AdminShell
            title="الأساتذة"
            subtitle="إنشاء حسابات الأساتذة لإدارة مستندات الطباعة ورموزها"
            active="professors"
            email={email}
            role={role}
        >
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-[14px] text-slate">
                    {professors.length} {professors.length === 1 ? 'أستاذ' : 'أساتذة'}
                </p>
                <button
                    type="button"
                    onClick={() => setCreateOpen(true)}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[14px] font-semibold text-white"
                >
                    <span className="text-[18px] leading-none">+</span>
                    أستاذ جديد
                </button>
            </div>

            {professors.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-black/10 bg-white px-6 py-16 text-center">
                    <h3 className="text-[18px] font-bold text-ink">لا يوجد أساتذة بعد</h3>
                    <p className="mt-2 text-[14px] text-slate">أنشئ حسابًا ليمكّن الأستاذ من رفع مستنداته ومشاركة الرموز.</p>
                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="mt-6 rounded-xl bg-primary px-5 py-2.5 text-[14px] font-semibold text-white"
                    >
                        إضافة أستاذ
                    </button>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {professors.map((prof) => (
                        <article key={prof.id} className="rounded-2xl border border-black/6 bg-white p-5 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h3 className="truncate text-[16px] font-bold text-ink">{prof.name}</h3>
                                    <p className="mt-1 truncate text-[13px] text-slate">{prof.email}</p>
                                </div>
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                                        prof.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {prof.is_active ? 'نشط' : 'موقوف'}
                                </span>
                            </div>
                            <div className="mt-4 flex items-center justify-between text-[13px] text-slate">
                                <span>
                                    <strong className="text-ink">{prof.print_codes_count}</strong> مستند
                                </span>
                                <span className="text-[12px]">{prof.created_at}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setEditProfessor(prof)}
                                className="mt-5 w-full rounded-xl border border-black/8 px-3 py-2.5 text-[13px] font-semibold text-ink transition hover:bg-[#f8faf9]"
                            >
                                إدارة الحساب
                            </button>
                        </article>
                    ))}
                </div>
            )}

            <ProfessorFormModal open={createOpen} onClose={() => setCreateOpen(false)} mode="create" />
            <ProfessorFormModal
                open={Boolean(editProfessor)}
                onClose={() => setEditProfessor(null)}
                mode="edit"
                professor={editProfessor}
            />
        </AdminShell>
    );
}

function ProfessorFormModal({ open, onClose, mode, professor = null }) {
    const editing = mode === 'edit';
    const form = useForm({
        name: professor?.name || '',
        email: professor?.email || '',
        password: '',
        is_active: professor?.is_active ?? true,
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        form.setData({
            name: professor?.name || '',
            email: professor?.email || '',
            password: '',
            is_active: professor?.is_active ?? true,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, professor?.id]);

    const submit = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('password');
                onClose();
            },
        };
        if (editing) {
            form.post(`/admin/professors/${professor.id}`, options);
            return;
        }
        form.post('/admin/professors', {
            ...options,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={form.processing ? () => {} : onClose}
            title={editing ? 'تعديل حساب الأستاذ' : 'أستاذ جديد'}
            subtitle={editing ? 'حدّث البيانات أو أوقف الحساب عند الحاجة.' : 'سيستخدم الأستاذ بريده وكلمة المرور لدخول لوحة المستندات.'}
        >
            <form onSubmit={submit} className="space-y-4">
                <Field label="الاسم" error={form.errors.name}>
                    <input
                        className="w-full rounded-xl border border-black/10 px-4 py-2.5 text-[14px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                </Field>
                <Field label="البريد" error={form.errors.email}>
                    <input
                        type="email"
                        className="w-full rounded-xl border border-black/10 px-4 py-2.5 text-[14px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        required
                    />
                </Field>
                <Field
                    label={editing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'}
                    error={form.errors.password}
                >
                    <input
                        type="password"
                        className="w-full rounded-xl border border-black/10 px-4 py-2.5 text-[14px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        required={!editing}
                        minLength={8}
                    />
                </Field>
                {editing && (
                    <label className="flex items-center gap-3 rounded-xl border border-black/6 bg-[#f8faf9] px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="h-4 w-4 rounded border-black/20 text-primary focus:ring-primary"
                        />
                        <span className="text-[14px] font-medium text-ink">الحساب نشط</span>
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
                        {form.processing ? 'جارٍ الحفظ…' : editing ? 'حفظ' : 'إنشاء الحساب'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-[13px] font-semibold text-ink">{label}</span>
            {children}
            {error && <p className="mt-1.5 text-[13px] text-red-600">{error}</p>}
        </label>
    );
}
