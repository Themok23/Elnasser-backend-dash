<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\VictoryLinkSmsService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VictoryLinkSmsController extends Controller
{
    public function index()
    {
        $config = Setting::where('settings_type', 'sms_config')
            ->where('key_name', 'victorylink')
            ->first();

        return view('admin-views.business-settings.victorylink-sms-tools', [
            'config' => $config?->live_values ?? [],
            'dlr_callback_url' => url('/api/v1/sms/victorylink/receive-dlr'),
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'receiver' => 'required|string',
            'message' => 'required|string',
        ]);

        $configRow = Setting::where('settings_type', 'sms_config')
            ->where('key_name', 'victorylink')
            ->first();

        $config = $configRow?->live_values ?? [];
        if (($config['status'] ?? 0) != 1) {
            Toastr::error('VictoryLink gateway is not active.');
            return back()->withInput();
        }

        $service = new VictoryLinkSmsService($config);

        $smsId = $request->filled('sms_id') ? $request->string('sms_id')->toString() : (string) Str::uuid();
        $sender = $request->filled('sender') ? $request->string('sender')->toString() : ($config['sender'] ?? null);
        $lang = $request->filled('lang') ? $request->string('lang')->toString() : ($config['lang'] ?? 'E');

        $useDlr = $request->boolean('use_dlr', (int)($config['use_dlr'] ?? 0) === 1);
        $dlrUrl = $request->filled('dlr_url') ? $request->string('dlr_url')->toString() : ($config['dlr_url'] ?? null);

        $result = $useDlr
            ? $service->sendSmsWithDlr(
                receiver: $request->string('receiver')->toString(),
                text: $request->string('message')->toString(),
                smsId: $smsId,
                sender: $sender,
                lang: $lang,
                campaignId: $request->input('campaign_id'),
                dlrUrl: $dlrUrl
            )
            : $service->sendSms(
                receiver: $request->string('receiver')->toString(),
                text: $request->string('message')->toString(),
                smsId: $smsId,
                sender: $sender,
                lang: $lang,
                campaignId: $request->input('campaign_id')
            );

        if ($result['ok'] ?? false) {
            Toastr::success("Sent successfully. Code: {$result['status_code']}");
        } else {
            $code = $result['status_code'] ?? null;
            $text = $result['status_text'] ?? 'Unknown';
            Toastr::error("Send failed. Code: {$code} ({$text})");
        }

        return back()->withInput([
            'sms_id' => $smsId,
        ]);
    }

    public function checkCredit()
    {
        $configRow = Setting::where('settings_type', 'sms_config')
            ->where('key_name', 'victorylink')
            ->first();

        $config = $configRow?->live_values ?? [];
        $service = new VictoryLinkSmsService($config);
        $result = $service->checkCredit();

        if ($result['ok'] ?? false) {
            $value = $result['value'];
            Toastr::success("Credit: {$value}");
        } else {
            Toastr::error('CheckCredit failed: ' . ($result['status_text'] ?? 'Unknown'));
        }

        return back();
    }

    public function checkDlrStatus(Request $request)
    {
        $request->validate([
            'user_sms_id' => 'required|string',
        ]);

        $configRow = Setting::where('settings_type', 'sms_config')
            ->where('key_name', 'victorylink')
            ->first();

        $config = $configRow?->live_values ?? [];
        $service = new VictoryLinkSmsService($config);
        $result = $service->checkDlrStatus($request->string('user_sms_id')->toString());

        if ($result['ok'] ?? false) {
            Toastr::success("DLR Status: {$result['value']} ({$result['status_text']})");
        } else {
            Toastr::error("DLR Status check failed: {$result['value']} ({$result['status_text']})");
        }

        return back();
    }
}



