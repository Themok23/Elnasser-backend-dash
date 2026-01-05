<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $gateway = 'victorylink';

        $defaults = [
            'gateway' => $gateway,
            'mode' => 'test',
            'status' => 0,

            // VictoryLink credentials
            'username' => null,
            'password' => null,

            // Defaults for sending
            'sender' => null,
            'lang' => 'E', // E (English) | A (Arabic) per VL docs

            // OTP template (used by SmsGateway::send)
            'otp_template' => 'Your OTP is: #OTP#',

            // Optional phone normalization: if set and phone starts with 0, we will replace leading 0 with this prefix (example: 20)
            'phone_prefix' => null,

            // Optional DLR support
            'use_dlr' => 0,
            'dlr_url' => null,

            // Optional base url override
            'base_url' => 'https://smsvas.vlserv.com',
        ];

        $credentials = json_encode($defaults);

        DB::table('addon_settings')->updateOrInsert(
            ['key_name' => $gateway, 'settings_type' => 'sms_config'],
            [
                'key_name' => $gateway,
                'live_values' => $credentials,
                'test_values' => $credentials,
                'settings_type' => 'sms_config',
                'mode' => 'test',
                'is_active' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('addon_settings')
            ->where('key_name', 'victorylink')
            ->where('settings_type', 'sms_config')
            ->delete();
    }
};


