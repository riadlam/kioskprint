<?php

namespace App\Services\Print;

interface KioskPrinter
{
    /**
     * Send one physical unit (a single page, or a duplex sheet) to the printer.
     *
     * @param  list<int>  $pageIds  Page ids in print order inside this unit.
     * @param  null|callable(): void  $onSpooled  Called once the unit is accepted, before the queue drain.
     */
    public function send(string $pdfPath, bool $duplex, array $pageIds, ?callable $onSpooled = null): void;
}
