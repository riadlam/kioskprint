<?php

namespace App\Services\SofizPay;

use Illuminate\Support\Facades\Http;
use Throwable;

class SofizPayClient
{
    /**
     * Official SofizPay RSA public key. It only verifies callbacks.
     */
    private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1N+bDPxpqeB9QB0affr/
02aeRXAAnqHuLrgiUlVNdXtF7t+2w8pnEg+m9RRlc+4YEY6UyKTUjVe6k7v2p8Jj
UItk/fMNOEg/zY222EbqsKZ2mF4hzqgyJ3QHPXjZEEqABkbcYVv4ZyV2Wq0x0ykI
+Hy/5YWKeah4RP2uEML1FlXGpuacnMXpW6n36dne3fUN+OzILGefeRpmpnSGO5+i
JmpF2mRdKL3hs9WgaLSg6uQyrQuJA9xqcCpUmpNbIGYXN9QZxjdyRGnxivTE8awx
THV3WRcKrP2krz3ruRGF6yP6PVHEuPc0YDLsYjV5uhfs7JtIksNKhRRAQ16bAsj/
9wIDAQAB
-----END PUBLIC KEY-----
PEM;

    public function isConfigured(): bool
    {
        return $this->merchantAccount() !== '' && (bool) config('services.sofizpay.enabled', true);
    }

    public function merchantAccount(): string
    {
        $account = config('services.sofizpay.merchant_account');

        return is_string($account) ? trim($account) : '';
    }

    public function minAmount(): float
    {
        return (float) config('services.sofizpay.min_amount_dzd', 75);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function create(array $query): array
    {
        return $this->get($this->path('/make-cib-transaction/'), $query, true);
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function check(string $orderNumber): array
    {
        $result = $this->get($this->path('/cib-transaction-check/'), [
            'order_number' => $orderNumber,
        ], false);

        if (isset($result['data']['error'])) {
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function isPaid(array $data): bool
    {
        $response = (string) ($data['respCode'] ?? '');
        $error = $data['errorCode'] ?? null;
        $status = $data['orderStatus'] ?? null;

        return $response === '00'
            && ($error === 0 || $error === '0')
            && ($status === 2 || $status === '2');
    }

    /**
     * A stable reason for a check that is not a confirmed payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function failureCode(array $data): string
    {
        $response = (string) ($data['respCode'] ?? '');
        $orderStatus = $data['orderStatus'] ?? null;
        $status = $orderStatus === null || $orderStatus === '' ? '' : (string) $orderStatus;

        if ($response === '17' || $status === '3' || $status === '4') {
            return 'canceled';
        }

        $byCode = match ($response) {
            '51' => 'insufficient',
            '33', '54' => 'expired',
            '55', '75' => 'pin',
            '61', '65' => 'limit',
            '14' => 'invalid_card',
            '91', '96' => 'unavailable',
            '04', '05', '41', '43', '57', '62' => 'declined',
            default => null,
        };

        if ($byCode !== null) {
            return $byCode;
        }

        if ($status === '6' || ($response !== '' && $response !== '00')) {
            return 'declined';
        }

        $error = $data['errorCode'] ?? null;

        if ($error !== null && $error !== '' && $error !== 0 && $error !== '0') {
            return 'declined';
        }

        return 'not_completed';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function paidAmount(array $data): ?float
    {
        $raw = $data['Amount'] ?? $data['amount'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return round((float) $raw, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function destination(array $data): ?string
    {
        $account = $data['destination_account'] ?? null;

        return is_string($account) && $account !== '' ? $account : null;
    }

    public function signatureIsValid(string $message, string $signature): bool
    {
        $encoded = strtr($signature, '-_', '+/');
        $remainder = strlen($encoded) % 4;

        if ($remainder !== 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }

        $raw = base64_decode($encoded, true);
        $key = openssl_pkey_get_public(self::PUBLIC_KEY);

        if ($raw === false || $key === false) {
            return false;
        }

        $valid = openssl_verify($message, $raw, $key, OPENSSL_ALGO_SHA256);

        return $valid === 1;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function get(string $url, array $query, bool $requireSuccessFlag): array
    {
        try {
            $response = Http::timeout((int) config('services.sofizpay.timeout', 30))
                ->acceptJson()
                ->withoutRedirecting()
                ->get($url, $query);
            $data = $response->json();
            $payload = is_array($data) ? $data : [];
            $flag = ! $requireSuccessFlag || ($payload['success'] ?? false) === true;

            return [
                'success' => $response->successful() && $flag,
                'data' => $payload,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['success' => false, 'data' => []];
        }
    }

    private function path(string $path): string
    {
        $base = rtrim((string) config('services.sofizpay.base_url', 'https://sofizpay.com'), '/');
        $sandbox = filter_var(config('services.sofizpay.sandbox'), FILTER_VALIDATE_BOOL);

        return $base.($sandbox ? '/sandbox' : '').$path;
    }
}
