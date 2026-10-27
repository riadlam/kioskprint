import { router, useForm, usePage } from '@inertiajs/react';
import AdminShell from '../../Components/Admin/Shell';

export default function Templates({ email, templatePrice = 50, templates = [] }) {
    const { errors } = usePage().props;
    const create = useForm({
        title_ar: '',
        title_fr: '',
        file: null,
    });

    function addForm(event) {
        event.preventDefault();
        create.post('/admin/templates', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => create.reset(),
        });
    }

    return (
        <AdminShell
            title="Forms"
            subtitle="Ready forms shown on the phone and the tablet."
            active="templates"
            email={email}
        >
            <form onSubmit={addForm} className="rounded-xl border border-black/5 bg-white p-5 shadow-sm">
                <h2 className="text-[15px] font-semibold">Add a form</h2>
                <p className="mt-0.5 text-[13px] text-slate">Arabic and French names, plus a PDF. Price stays {templatePrice} DA per page.</p>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <label className="block text-[13px] font-medium">
                        Arabic name
                        <input
                            dir="rtl"
                            value={create.data.title_ar}
                            onChange={(event) => create.setData('title_ar', event.target.value)}
                            className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                        />
                    </label>
                    <label className="block text-[13px] font-medium">
                        French name
                        <input
                            dir="ltr"
                            value={create.data.title_fr}
                            onChange={(event) => create.setData('title_fr', event.target.value)}
                            className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                        />
                    </label>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-3">
                    <input
                        type="file"
                        accept="application/pdf,.pdf"
                        onChange={(event) => create.setData('file', event.target.files?.[0] ?? null)}
                        className="max-w-[280px] text-[13px]"
                    />
                    <button
                        type="submit"
                        disabled={create.processing || !create.data.file || !create.data.title_ar || !create.data.title_fr}
                        className="h-10 rounded-lg bg-[#101513] px-4 text-[13px] font-semibold text-white disabled:opacity-50"
                    >
                        {create.processing ? 'Saving' : 'Add form'}
                    </button>
                </div>
                {(errors.file || create.errors.file || create.errors.title_ar || create.errors.title_fr) && (
                    <p className="mt-3 text-[13px] font-medium text-red-700">
                        {errors.file || create.errors.file || create.errors.title_ar || create.errors.title_fr}
                    </p>
                )}
            </form>

            <section className="mt-6 overflow-hidden rounded-xl border border-black/5 bg-white shadow-sm">
                <div className="border-b border-black/5 px-5 py-4">
                    <h2 className="text-[15px] font-semibold">Current forms</h2>
                    <p className="mt-0.5 text-[13px] text-slate">{templates.length} forms. Replacing the PDF recounts the pages.</p>
                </div>
                {templates.length === 0 && (
                    <p className="px-5 py-10 text-[13px] text-slate">No forms yet.</p>
                )}
                <ul>
                    {templates.map((item) => (
                        <TemplateRow key={item.id} item={item} />
                    ))}
                </ul>
            </section>
        </AdminShell>
    );
}

function TemplateRow({ item }) {
    const form = useForm({
        title_ar: item.title_ar,
        title_fr: item.title_fr,
        file: null,
    });

    function save(event) {
        event.preventDefault();
        form.transform((data) => {
            const next = {
                title_ar: data.title_ar,
                title_fr: data.title_fr,
            };
            if (data.file) {
                next.file = data.file;
            }
            return next;
        });
        form.post(`/admin/templates/${item.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('file', null),
        });
    }

    return (
        <li className="border-t border-black/5 px-5 py-4">
            <form onSubmit={save} className="grid gap-3 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                <label className="block text-[13px] font-medium">
                    Arabic
                    <input
                        dir="rtl"
                        value={form.data.title_ar}
                        onChange={(event) => form.setData('title_ar', event.target.value)}
                        className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                    />
                </label>
                <label className="block text-[13px] font-medium">
                    French
                    <input
                        dir="ltr"
                        value={form.data.title_fr}
                        onChange={(event) => form.setData('title_fr', event.target.value)}
                        className="mt-1.5 h-11 w-full rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                    />
                </label>
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="h-10 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white disabled:opacity-60"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={() => router.delete(`/admin/templates/${item.id}`, { preserveScroll: true })}
                        className="h-10 px-2 text-[13px] font-semibold text-red-700"
                    >
                        Delete
                    </button>
                </div>
                <label className="text-[12px] text-slate lg:col-span-3">
                    {item.pages} {item.pages === 1 ? 'page' : 'pages'}
                    <input
                        type="file"
                        accept="application/pdf,.pdf"
                        onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                        className="mt-1 block max-w-[280px] text-[13px]"
                    />
                    {form.errors.file && <span className="mt-1 block font-medium text-red-700">{form.errors.file}</span>}
                </label>
            </form>
        </li>
    );
}
