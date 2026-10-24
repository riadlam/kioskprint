import { router, useForm, usePage } from '@inertiajs/react';
import AdminShell from '../../Components/Admin/Shell';

function formatSize(bytes) {
    if (!bytes) {
        return '0 MB';
    }
    const mb = bytes / (1024 * 1024);
    return `${mb >= 10 ? mb.toFixed(0) : mb.toFixed(1)} MB`;
}

export default function Screen({ email, settings, videos = [] }) {
    const { errors } = usePage().props;
    const form = useForm({
        enabled: settings.enabled,
        idle_seconds: settings.idle_seconds,
    });
    const upload = useForm({ video: null });

    function save(event) {
        event.preventDefault();
        form.put('/admin/screen', { preserveScroll: true });
    }

    function sendVideo(event) {
        event.preventDefault();
        upload.post('/admin/screen/videos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => upload.reset('video'),
        });
    }

    return (
        <AdminShell
            title="Screen"
            subtitle="Videos the tablet plays while the home screen is waiting."
            active="screen"
            email={email}
        >
            <form onSubmit={save} className="rounded-xl border border-black/5 bg-white p-5 shadow-sm">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(event) => form.setData('enabled', event.target.checked)}
                            className="h-4 w-4 accent-primary"
                        />
                        Play ads on the tablet
                    </label>
                    <label className="block text-[13px] font-medium">
                        Wait on the home screen
                        <span className="mt-1.5 flex items-center gap-2">
                            <input
                                type="number"
                                min={15}
                                max={3600}
                                value={form.data.idle_seconds}
                                onChange={(event) => form.setData('idle_seconds', event.target.value)}
                                className="h-11 w-28 rounded-lg border border-black/10 px-3 text-sm outline-none focus:border-primary"
                            />
                            <span className="text-slate">seconds</span>
                        </span>
                    </label>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="h-11 rounded-lg bg-primary px-4 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        Save
                    </button>
                </div>
                {form.errors.idle_seconds && (
                    <p className="mt-3 text-[13px] font-medium text-red-700">{form.errors.idle_seconds}</p>
                )}
                <p className="mt-4 text-[13px] leading-6 text-slate">
                    Each wait plays the next video once, then the tablet returns home. A coin or a print stops the video immediately.
                </p>
            </form>

            <section className="mt-6 overflow-hidden rounded-xl border border-black/5 bg-white shadow-sm">
                <div className="flex flex-wrap items-end justify-between gap-4 border-b border-black/5 px-5 py-4">
                    <div>
                        <h2 className="text-[15px] font-semibold">Videos</h2>
                        <p className="mt-0.5 text-[13px] text-slate">MP4 only, up to 80 MB. The tablet downloads each file once.</p>
                    </div>
                    <form onSubmit={sendVideo} className="flex flex-wrap items-center gap-2">
                        <input
                            type="file"
                            accept="video/mp4,.mp4"
                            onChange={(event) => upload.setData('video', event.target.files?.[0] ?? null)}
                            className="max-w-[240px] text-[13px]"
                        />
                        <button
                            type="submit"
                            disabled={upload.processing || !upload.data.video}
                            className="h-10 rounded-lg bg-[#101513] px-4 text-[13px] font-semibold text-white disabled:opacity-50"
                        >
                            {upload.processing ? 'Uploading' : 'Upload'}
                        </button>
                    </form>
                </div>
                {(errors.video || upload.errors.video) && (
                    <p className="px-5 pt-3 text-[13px] font-medium text-red-700">{errors.video || upload.errors.video}</p>
                )}
                <ul>
                    {videos.length === 0 && (
                        <li className="px-5 py-10 text-[13px] text-slate">No videos yet.</li>
                    )}
                    {videos.map((video, index) => (
                        <li key={video.id} className="flex flex-wrap items-center gap-3 border-t border-black/5 px-5 py-3">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-[14px] font-medium">{video.original_name}</p>
                                <p className="text-[12px] text-slate">{formatSize(video.bytes)}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => router.patch(`/admin/screen/videos/${video.id}`, { is_active: !video.is_active }, { preserveScroll: true })}
                                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${video.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}
                            >
                                {video.is_active ? 'On' : 'Off'}
                            </button>
                            <button
                                type="button"
                                disabled={index === 0}
                                onClick={() => router.post(`/admin/screen/videos/${video.id}/move`, { direction: 'up' }, { preserveScroll: true })}
                                className="text-[12px] font-semibold text-slate disabled:opacity-30"
                            >
                                Up
                            </button>
                            <button
                                type="button"
                                disabled={index === videos.length - 1}
                                onClick={() => router.post(`/admin/screen/videos/${video.id}/move`, { direction: 'down' }, { preserveScroll: true })}
                                className="text-[12px] font-semibold text-slate disabled:opacity-30"
                            >
                                Down
                            </button>
                            <button
                                type="button"
                                onClick={() => router.delete(`/admin/screen/videos/${video.id}`, { preserveScroll: true })}
                                className="text-[12px] font-semibold text-red-700"
                            >
                                Delete
                            </button>
                        </li>
                    ))}
                </ul>
            </section>
        </AdminShell>
    );
}
