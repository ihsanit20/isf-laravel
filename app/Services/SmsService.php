<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    public function send(string $phone, string $message, ?Model $smsable = null): bool
    {
        $url = (string) config('services.sms.url');
        $apiKey = (string) config('services.sms.api_key');
        $senderId = (string) config('services.sms.sender_id');
        $normalizedPhone = $this->normalizePhone($phone);

        if ($url === '' || $apiKey === '' || $senderId === '') {
            Log::warning('SMS credentials are not configured.');

            $this->createLog(
                phone: $phone,
                normalizedPhone: $normalizedPhone,
                message: $message,
                status: SmsLog::STATUS_FAILED,
                smsable: $smsable,
                errorMessage: 'SMS credentials are not configured.',
            );

            return false;
        }

        if ($normalizedPhone === null) {
            Log::warning('SMS skipped because the phone number is invalid.', [
                'phone' => $phone,
            ]);

            $this->createLog(
                phone: $phone,
                normalizedPhone: null,
                message: $message,
                status: SmsLog::STATUS_SKIPPED,
                smsable: $smsable,
                errorMessage: 'SMS skipped because the phone number is invalid.',
            );

            return false;
        }

        try {
            $response = Http::asForm()->post($url, [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => $normalizedPhone,
                'senderid' => $senderId,
                'message' => $message,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('SMS request failed.', [
                'phone' => $normalizedPhone,
                'error' => $throwable->getMessage(),
            ]);

            $this->createLog(
                phone: $phone,
                normalizedPhone: $normalizedPhone,
                message: $message,
                status: SmsLog::STATUS_FAILED,
                smsable: $smsable,
                errorMessage: $throwable->getMessage(),
            );

            return false;
        }

        $responseBody = $response->body();
        $providerCode = $this->extractProviderCode($responseBody);

        if (! $response->successful() || $providerCode !== '202') {
            Log::warning('SMS provider returned an unsuccessful response.', [
                'phone' => $normalizedPhone,
                'status' => $response->status(),
                'provider_code' => $providerCode,
            ]);

            $this->createLog(
                phone: $phone,
                normalizedPhone: $normalizedPhone,
                message: $message,
                status: SmsLog::STATUS_FAILED,
                smsable: $smsable,
                providerCode: $providerCode,
                httpStatus: $response->status(),
                responseBody: $responseBody,
                errorMessage: 'SMS provider returned an unsuccessful response.',
            );

            return false;
        }

        $this->createLog(
            phone: $phone,
            normalizedPhone: $normalizedPhone,
            message: $message,
            status: SmsLog::STATUS_SENT,
            smsable: $smsable,
            providerCode: $providerCode,
            httpStatus: $response->status(),
            responseBody: $responseBody,
        );

        return true;
    }

    private function extractProviderCode(string $responseBody): ?string
    {
        $trimmedBody = trim($responseBody);

        if ($trimmedBody === '') {
            return null;
        }

        $decoded = json_decode($trimmedBody, true);

        if (is_array($decoded)) {
            $responseCode = $decoded['response_code'] ?? $decoded['code'] ?? null;

            return is_scalar($responseCode) ? (string) $responseCode : null;
        }

        if (preg_match('/^\d{3,10}$/', $trimmedBody) === 1) {
            return $trimmedBody;
        }

        return null;
    }

    private function createLog(
        string $phone,
        ?string $normalizedPhone,
        string $message,
        string $status,
        ?Model $smsable = null,
        ?string $providerCode = null,
        ?int $httpStatus = null,
        ?string $responseBody = null,
        ?string $errorMessage = null,
    ): SmsLog {
        return SmsLog::query()->create([
            'recipient_phone' => $phone !== '' ? $phone : null,
            'normalized_phone' => $normalizedPhone,
            'message' => $message,
            'status' => $status,
            'provider_code' => $providerCode,
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'smsable_type' => $smsable?->getMorphClass(),
            'smsable_id' => $smsable?->getKey(),
        ]);
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '88' . $digits;
        }

        return null;
    }
}
