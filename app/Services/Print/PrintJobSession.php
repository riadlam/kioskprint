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
        $given = $request->query('kiosk', $request->input('kiosk'));

        if (is_string($given) && trim($given) !== '') {
            $this->captureKiosk($given);

            return;
        }

        if ($this->kioskId() !== null) {
            return;
        }

        $public = config('print.public_kiosk_id');

        if (is_string($public)) {
            $this->captureKiosk($public);
        }
    }

    public function kioskId(): ?string
    {
        $id = session('print_kiosk_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function grantDesk(string $kioskId): void
    {
        session(['print_desk' => ['kiosk_id' => $kioskId, 'open' => true]]);
    }

    public function hasDeskGrant(): bool
    {
        $desk = session('print_desk');
        $kioskId = $this->kioskId();

        return $kioskId !== null
            && is_array($desk)
            && ($desk['kiosk_id'] ?? '') === $kioskId
            && ($desk['open'] ?? false) === true;
    }

    public function consumeDesk(): void
    {
        $desk = session('print_desk', []);

        if (! is_array($desk)) {
            return;
        }

        $desk['open'] = false;
        session(['print_desk' => $desk]);
    }
}
