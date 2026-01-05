<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VictoryLinkSmsService
{
    public const STATUS_SUCCESS = 0;

    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $defaultSender;
    private string $defaultLang;
    private ?string $phonePrefix;
    private bool $useDlr;
    private ?string $dlrUrl;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string)($config['base_url'] ?? 'https://smsvas.vlserv.com'), '/');
        $this->username = (string)($config['username'] ?? '');
        $this->password = (string)($config['password'] ?? '');
        $this->defaultSender = $config['sender'] ?? null;
        $this->defaultLang = strtoupper((string)($config['lang'] ?? 'E'));
        $this->phonePrefix = $config['phone_prefix'] ?? null;
        $this->useDlr = (int)($config['use_dlr'] ?? 0) === 1;
        $this->dlrUrl = $config['dlr_url'] ?? null;
    }

    public function sendOtp(string $receiver, string $otp, string $otpTemplate): array
    {
        $message = str_replace('#OTP#', $otp, $otpTemplate);
        $smsId = (string) Str::uuid();

        if ($this->useDlr) {
            return $this->sendSmsWithDlr(
                receiver: $receiver,
                text: $message,
                smsId: $smsId,
                sender: $this->defaultSender,
                lang: $this->defaultLang,
                dlrUrl: $this->dlrUrl
            );
        }

        return $this->sendSms(
            receiver: $receiver,
            text: $message,
            smsId: $smsId,
            sender: $this->defaultSender,
            lang: $this->defaultLang
        );
    }

    public function sendSms(
        string $receiver,
        string $text,
        ?string $smsId = null,
        ?string $sender = null,
        ?string $lang = null,
        ?string $campaignId = null
    ): array {
        $endpoint = $this->baseUrl . '/VLSMSPlatformResellerAPI/NewSendingAPI/api/SMSSender/SendSMS';

        $payload = array_filter([
            'UserName' => $this->username,
            'Password' => $this->password,
            'SMSText' => $text,
            'SMSLang' => strtoupper((string)($lang ?? $this->defaultLang)),
            'SMSSender' => $sender ?? $this->defaultSender,
            'SMSReceiver' => $this->normalizePhone($receiver),
            'SMSID' => $smsId ?: (string) Str::uuid(),
            'CampaignID' => $campaignId,
        ], fn($v) => $v !== null && $v !== '');

        $statusCode = $this->postForInt($endpoint, $payload);

        return [
            'ok' => $statusCode === self::STATUS_SUCCESS,
            'status_code' => $statusCode,
            'status_text' => self::statusText($statusCode),
            'request' => $payload,
        ];
    }

    public function sendSmsWithDlr(
        string $receiver,
        string $text,
        ?string $smsId = null,
        ?string $sender = null,
        ?string $lang = null,
        ?string $campaignId = null,
        ?string $dlrUrl = null
    ): array {
        // URL is not explicitly in repo docs; per VL pattern, it is expected to be under NewSendingAPI SMSSender.
        $endpoint = $this->baseUrl . '/VLSMSPlatformResellerAPI/NewSendingAPI/api/SMSSender/SendSMSWithDLR';

        $payload = array_filter([
            'UserName' => $this->username,
            'Password' => $this->password,
            'SMSText' => $text,
            'SMSLang' => strtoupper((string)($lang ?? $this->defaultLang)),
            'SMSSender' => $sender ?? $this->defaultSender,
            'SMSReceiver' => $this->normalizePhone($receiver),
            'SMSID' => $smsId ?: (string) Str::uuid(),
            'CampaignID' => $campaignId,
            'DLRURL' => $dlrUrl,
        ], fn($v) => $v !== null && $v !== '');

        $statusCode = $this->postForInt($endpoint, $payload);

        return [
            'ok' => $statusCode === self::STATUS_SUCCESS,
            'status_code' => $statusCode,
            'status_text' => self::statusText($statusCode),
            'request' => $payload,
        ];
    }

    public function checkCredit(): array
    {
        $endpoint = $this->baseUrl . '/VLSMSPlatformResellerAPI/CheckCreditApi/api/CheckCredit';
        $payload = [
            'UserName' => $this->username,
            'Password' => $this->password,
        ];

        $creditOrCode = $this->postForInt($endpoint, $payload);

        return [
            'ok' => $creditOrCode >= 0,
            'value' => $creditOrCode,
            'status_text' => self::statusText($creditOrCode),
            'request' => $payload,
        ];
    }

    public function checkDlrStatus(string $userSmsId): array
    {
        $endpoint = $this->baseUrl . '/VLSMSPlatformResellerAPI/CheckDLRStatus/api/CheckDLRStatus';
        $payload = [
            'UserName' => $this->username,
            'Password' => $this->password,
            'UserSMSId' => $userSmsId,
        ];

        $code = $this->postForInt($endpoint, $payload);

        return [
            'ok' => $code > 0,
            'value' => $code,
            'status_text' => self::statusText($code),
            'request' => $payload,
        ];
    }

    public static function statusText(?int $code): string
    {
        return match ($code) {
            0 => 'Success',
            -1 => 'Invalid Credentials',
            -2 => 'Invalid Account IP',
            -3 => 'Invalid ANI Black List',
            -5 => 'Out Of Credit',
            -6 => 'Database Down',
            -7 => 'Inactive Account',
            -11 => 'Account Is Expired',
            -12 => 'SMS Is Empty',
            -13 => 'Invalid Sender With Connection',
            -14 => 'SMS Sending Failed Try Again',
            -16 => 'User Can Not Send With DLR',
            -18 => 'Invalid ANI',
            -19 => 'SMS Id Is Exist',
            -21 => 'Invalid Account',
            -22 => 'SMS Not Validate',
            -23 => 'Invalid Account Operator Connection',
            -26 => 'Invalid User SMS Id',
            -29 => 'Empty User Name Or Password',
            -30 => 'Invalid Sender',
            default => $code === null ? 'Unknown (no response)' : "Unknown ($code)",
        };
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (!empty($this->phonePrefix) && str_starts_with($normalized, '0')) {
            $normalized = ltrim($normalized, '0');
            $normalized = $this->phonePrefix . $normalized;
        }

        return $normalized;
    }

    private function postForInt(string $url, array $payload): ?int
    {
        try {
            $response = Http::asJson()
                ->timeout(15)
                ->retry(2, 200)
                ->post($url, $payload);

            if (!$response->successful()) {
                return null;
            }

            $body = trim((string) $response->body());

            // Sometimes API returns a bare integer, sometimes JSON.
            if (is_numeric($body)) {
                return (int) $body;
            }

            $json = $response->json();
            $candidate = Arr::first(Arr::flatten((array) $json));
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}


