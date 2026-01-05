<?php

namespace App\Http\Controllers\Api\V1\Sms;

use App\Http\Controllers\Controller;
use App\Models\VictoryLinkDlrReport;
use Illuminate\Http\Request;

class VictoryLinkDlrController extends Controller
{
    /**
     * VictoryLink ReceiveDLR callback (called by VL with GET):
     *   ?userSMSId={GUID}&dlrResponseStatus={status}
     */
    public function receive(Request $request)
    {
        $request->validate([
            'userSMSId' => 'required|uuid',
            'dlrResponseStatus' => 'required',
        ]);

        VictoryLinkDlrReport::create([
            'user_sms_id' => $request->input('userSMSId'),
            'dlr_response_status' => (string) $request->input('dlrResponseStatus'),
            'payload' => $request->query(),
            'received_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}



