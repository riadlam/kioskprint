import { useForm } from '@inertiajs/react';
import AdminShell from '../../../Components/Admin/Shell';

export default function Index({ professors = [], email, role }) {
    const createForm = useForm({
        name: '',
        email: '',
        password: '',
    });

    return (
        <AdminShell
            title="الأساتذة"
            subtitle="إنشاء حسابات الأساتذة لإدارة رموز الطباعة"
            active="professors"
            email={email}
            role={role}
        >
            <div className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        createForm.post('/admin/professors', {
                            onSuccess: () => createForm.reset('name', 'email', 'password'),
                        });
                    }}
                    className="rounded-2xl border border-black/5 bg-white p-6 shadow-sm"
                >
                    <h2 className="text-[15px] font-semibold">أستاذ جديد</h2>
                    <div className="mt-4 space-y-3">
                        <Field
                            label="الاسم"
                            value={createForm.data.name}
                            onChange={(v) => createForm.setData('name', v)}
                            error={createForm.errors.name}
                        />
                        <Field
                            label="البريد"
                            type="email"
                            value={createForm.data.email}
                            onChange={(v) => createForm.setData('email', v)}
                            error={createForm.errors.email}
                        />
                        <Field
                            label="كلمة المرور"
                            type="password"
                            value={createForm.data.password}
                            onChange={(v) => createForm.setData('password', v)}
                            error={createForm.errors.password}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={createForm.processing}
                        className="mt-5 w-full rounded-xl bg-primary py-3 text-[14px] font-semibold text-white disabled:opacity-60"
                    >
                        إنشاء الحساب
                    </button>
                </form>

                <div className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-left text-[14px]">
                            <thead className="bg-[#f8faf9] text-[11px] uppercase tracking-wider text-slate">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">الأستاذ</th>
                                    <th className="px-5 py-3 font-semibold">الرموز</th>
                                    <th className="px-5 py-3 font-semibold">الحالة</th>
                                    <th className="px-5 py-3 font-semibold" />
                                </tr>
                            </thead>
                            <tbody>
                                {professors.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-12 text-center text-slate">
                                            لا يوجد أساتذة بعد.
                                        </td>
                                    </tr>
                                )}
                                {professors.map((prof) => (
                                    <ProfessorRow key={prof.id} professor={prof} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}

function ProfessorRow({ professor }) {
    const form = useForm({
        name: professor.name,
        email: professor.email,
        password: '',
        is_active: professor.is_active,
    });

    return (
        <tr className="border-t border-black/5 align-top">
            <td className="px-5 py-4">
                <input
                    className="mb-2 w-full rounded-lg border border-black/10 px-3 py-2 text-[13px]"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                <input
                    className="w-full rounded-lg border border-black/10 px-3 py-2 text-[13px]"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
            </td>
            <td className="px-5 py-4 font-semibold">{professor.print_codes_count}</td>
            <td className="px-5 py-4">
                <label className="inline-flex items-center gap-2 text-[13px]">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                    />
                    نشط
                </label>
                <input
                    type="password"
                    placeholder="كلمة مرور جديدة"
                    className="mt-2 w-full rounded-lg border border-black/10 px-3 py-2 text-[13px]"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                />
            </td>
            <td className="px-5 py-4">
                <button
                    type="button"
                    className="rounded-lg bg-[#101513] px-3 py-2 text-[12px] font-semibold text-white"
                    onClick={() => form.post(`/admin/professors/${professor.id}`)}
                    disabled={form.processing}
                >
                    حفظ
                </button>
            </td>
        </tr>
    );
}

function Field({ label, value, onChange, error, type = 'text' }) {
    return (
        <label className="block">
            <span className="mb-1 block text-[12px] font-semibold text-slate">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-xl border border-black/10 px-3 py-2.5 text-[14px]"
                required
            />
            {error && <p className="mt-1 text-[12px] text-red-600">{error}</p>}
        </label>
    );
}
