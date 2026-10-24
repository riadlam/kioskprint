<?php

namespace App\Services\Print;

use App\Models\PrintJob;
use Illuminate\Http\Request;

class PrintJobSession
{
    public function remember(PrintJob $job): void
    {
        $ids = session('print_job_ids', []);
        $ids[] = $job->id;
        session(['print_job_ids' => array_values(array_unique($ids))]);
    }

    public function owns(PrintJob $job): bool
    {
        return in_array($job->id, session('print_job_ids', []), true);
    }

    public function captureKiosk(?string $kioskId): void
    {
        $kioskId = $kioskId !== null ? trim($kioskId) : '';

        if ($kioskId === '' || strlen($kioskId) > 64) {
            return;
        }

        session(['print_kiosk_id' => $kioskId]);
    }

    public function captureKioskFromRequest(Request $request): void
    {
        $this->captureKiosk($request->query('kiosk', $request->input('kiosk')));
    }

    public function kioskId(): ?string
    {
        $id = session('print_kiosk_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
