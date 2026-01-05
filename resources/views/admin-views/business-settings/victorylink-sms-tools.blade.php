@extends('layouts.admin.app')

@section('title', 'VictoryLink SMS')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/sms.png')}}" class="w--26" alt="">
                </span>
                <span>VictoryLink SMS</span>
            </h1>
            @include('admin-views.business-settings.partials.third-party-links')
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Send Test SMS</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.send') }}">
                            @csrf

                            <div class="form-group">
                                <label>Receiver (phone)</label>
                                <input type="text" name="receiver" class="form-control" value="{{ old('receiver') }}" required>
                            </div>

                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="message" class="form-control" rows="3" required>{{ old('message', 'Test message from dashboard') }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Sender (optional)</label>
                                    <input type="text" name="sender" class="form-control" value="{{ old('sender', $config['sender'] ?? '') }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Lang</label>
                                    <select name="lang" class="form-control">
                                        @php($langOld = old('lang', $config['lang'] ?? 'E'))
                                        <option value="E" {{ strtoupper($langOld) == 'E' ? 'selected' : '' }}>E (English)</option>
                                        <option value="A" {{ strtoupper($langOld) == 'A' ? 'selected' : '' }}>A (Arabic)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>SMSID (optional)</label>
                                    <input type="text" name="sms_id" class="form-control" value="{{ old('sms_id') }}" placeholder="Leave empty to auto-generate">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>CampaignID (optional)</label>
                                    <input type="text" name="campaign_id" class="form-control" value="{{ old('campaign_id') }}">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="use_dlr" value="1" {{ old('use_dlr', (int)($config['use_dlr'] ?? 0)) ? 'checked' : '' }}>
                                    Send with DLR
                                </label>
                            </div>

                            <div class="form-group">
                                <label>DLR URL (optional)</label>
                                <input type="text" name="dlr_url" class="form-control" value="{{ old('dlr_url', $config['dlr_url'] ?? $dlr_callback_url) }}">
                                <small class="text-muted">Your callback endpoint: <span class="text-monospace">{{ $dlr_callback_url }}</span></small>
                            </div>

                            <button class="btn btn--primary" type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="mb-0">Check Credit</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.check-credit') }}">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">Check Credit</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Check DLR Status</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.check-dlr') }}">
                            @csrf
                            <div class="form-group">
                                <label>UserSMSId</label>
                                <input type="text" name="user_sms_id" class="form-control" value="{{ old('user_sms_id', old('sms_id')) }}" required>
                            </div>
                            <button class="btn btn-outline-primary" type="submit">Check DLR</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


