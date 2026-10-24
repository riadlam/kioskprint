<?php

namespace App\Services\Print;

use RuntimeException;
use Symfony\Component\Process\Process;

class WindowsKioskPrinter implements KioskPrinter
{
    public function __construct(
        private string $sumatraPath,
        private string $printerName,
        private int $pageTimeoutSeconds = 90,
    ) {}

    public function send(string $pdfPath, bool $duplex, array $pageIds, ?callable $onSpooled = null): void
    {
        if (! is_file($this->sumatraPath)) {
            throw new RuntimeException('SumatraPDF was not found at '.$this->sumatraPath);
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Print file is missing.');
        }

        $before = $this->currentJobIds();
        $settings = $duplex ? 'fit,duplexlong' : 'fit';

        $print = new Process([
            $this->sumatraPath,
            '-silent',
            '-print-to',
            $this->printerName,
            '-print-settings',
            $settings,
            $pdfPath,
        ]);
        $print->setTimeout(max(30, $this->pageTimeoutSeconds));
        $print->run();

        if (! $print->isSuccessful()) {
            $detail = trim($print->getErrorOutput() ?: $print->getOutput());
            throw new RuntimeException($detail !== '' ? $detail : 'The printer rejected the document.');
        }

        if ($onSpooled) {
            $onSpooled();
        }

        $this->waitUntilDone(basename($pdfPath), $before);
    }

    private function currentJobIds(): string
    {
        $script = <<<'PS'
$ErrorActionPreference = 'SilentlyContinue'
Get-PrintJob -PrinterName $env:METBATI_PRINTER | ForEach-Object { Write-Output $_.Id }
PS;

        $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script]);
        $process->setTimeout(8);
        $process->setEnv($this->env(['METBATI_PRINTER' => $this->printerName]));
        $process->run();

        $ids = preg_split('/\R/', trim($process->getOutput())) ?: [];
        $ids = array_values(array_filter(array_map(trim(...), $ids), fn (string $id) => $id !== ''));

        return implode(',', $ids);
    }

    private function waitUntilDone(string $documentName, string $existingIds): void
    {
        $timeout = max(8, $this->pageTimeoutSeconds);
        $script = <<<'PS'
$ErrorActionPreference = 'Stop'
$name = $env:METBATI_PRINTER
$doc = $env:METBATI_DOC
$seconds = [int]$env:METBATI_TIMEOUT
$grace = [Math]::Max(1, [int]$env:METBATI_GRACE)
$known = @{}
if ($env:METBATI_BEFORE) {
    foreach ($id in ($env:METBATI_BEFORE -split ',')) {
        if ($id) { $known[$id] = $true }
    }
}
function Get-Ours {
    param($Printer, $Doc, $Known)
    @(Get-PrintJob -PrinterName $Printer -ErrorAction SilentlyContinue | Where-Object {
        $id = [string]$_.Id
        $named = $_.DocumentName -like ('*' + $Doc + '*')
        (-not $Known.ContainsKey($id)) -or $named
    })
}
$deadline = (Get-Date).AddSeconds($seconds)
$graceDeadline = (Get-Date).AddSeconds($grace)
$seen = $false
while ((Get-Date) -lt $deadline) {
    $jobs = @(Get-Ours -Printer $name -Doc $doc -Known $known)
    if ($jobs.Count -gt 0) {
        $seen = $true
        $alive = @()
        foreach ($job in $jobs) {
            $status = [string]$job.JobStatus
            if ($status -match 'Error|Blocked|Offline|PaperOut|UserIntervention') {
                Remove-PrintJob -PrinterName $name -ID $job.Id -ErrorAction SilentlyContinue
                Write-Output 'ERROR'
                exit 2
            }
            if ($status -notmatch 'Printed|Complete|Completed|Deleted|Deleting') {
                $alive += $job
            }
        }
        if ($alive.Count -eq 0) {
            Write-Output 'DONE'
            exit 0
        }
    } elseif ($seen) {
        Write-Output 'DONE'
        exit 0
    } elseif ((Get-Date) -gt $graceDeadline) {
        Write-Output 'UNSEEN'
        exit 0
    }
    Start-Sleep -Milliseconds 200
}
Get-PrintJob -PrinterName $name -ErrorAction SilentlyContinue |
    Where-Object { $_.DocumentName -like ('*' + $doc + '*') } |
    ForEach-Object { Remove-PrintJob -PrinterName $name -ID $_.Id -ErrorAction SilentlyContinue }
Write-Output 'TIMEOUT'
exit 3
PS;

        $wait = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script]);
        $wait->setTimeout($timeout + 15);
        $wait->setEnv($this->env([
            'METBATI_PRINTER' => $this->printerName,
            'METBATI_DOC' => $documentName,
            'METBATI_BEFORE' => $existingIds,
            'METBATI_GRACE' => '2',
            'METBATI_TIMEOUT' => (string) $timeout,
        ]));
        $wait->run();

        if ($wait->getExitCode() === 0) {
            return;
        }

        $output = trim($wait->getOutput());

        throw new RuntimeException($output !== '' ? $output : 'The printer did not finish the page.');
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function env(array $extra): array
    {
        $base = getenv();
        $clean = [];

        if (is_array($base)) {
            foreach ($base as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $clean[$key] = $value;
                }
            }
        }

        return array_merge($clean, $extra);
    }
}
