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

        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Gateway Setup (Credentials + Status)</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.sms-module-update', ['victorylink']) }}">
                            @csrf
                            @method('post')

                            <div class="d-flex align-items-center gap-4 gap-xl-5 mb-3">
                                <div class="custom-radio">
                                    <input type="radio" id="victorylink-active" name="status" value="1"
                                        {{ (int)($config['status'] ?? 0) === 1 ? 'checked' : '' }}>
                                    <label for="victorylink-active">Active</label>
                                </div>
                                <div class="custom-radio">
                                    <input type="radio" id="victorylink-inactive" name="status" value="0"
                                        {{ (int)($config['status'] ?? 0) === 1 ? '' : 'checked' }}>
                                    <label for="victorylink-inactive">Inactive</label>
                                </div>
                            </div>

                            <input type="hidden" name="gateway" value="victorylink">
                            <input type="hidden" name="mode" value="live">

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Username</label>
                                    <input type="text" name="username" class="form-control" value="{{ old('username', $config['username'] ?? '') }}" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Password</label>
                                    <input type="text" name="password" class="form-control" value="{{ old('password', $config['password'] ?? '') }}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Sender (Fake Sender)</label>
                                    <input type="text" name="sender" class="form-control" value="{{ old('sender', $config['sender'] ?? '') }}">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Lang</label>
                                    @php($lang = old('lang', $config['lang'] ?? 'E'))
                                    <select name="lang" class="form-control">
                                        <option value="E" {{ strtoupper($lang) == 'E' ? 'selected' : '' }}>E (English)</option>
                                        <option value="A" {{ strtoupper($lang) == 'A' ? 'selected' : '' }}>A (Arabic)</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Phone Prefix (optional)</label>
                                    <input type="text" name="phone_prefix" class="form-control" value="{{ old('phone_prefix', $config['phone_prefix'] ?? '') }}" placeholder="Example: 20">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>OTP Template</label>
                                <input type="text" name="otp_template" class="form-control" value="{{ old('otp_template', $config['otp_template'] ?? 'Your OTP is: #OTP#') }}">
                                <small class="text-muted">Use <span class="text-monospace">#OTP#</span> where the code should appear.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Base URL</label>
                                    <input type="text" name="base_url" class="form-control" value="{{ old('base_url', $config['base_url'] ?? 'https://smsvas.vlserv.com') }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>
                                        <input type="checkbox" name="use_dlr" value="1" {{ old('use_dlr', (int)($config['use_dlr'] ?? 0)) ? 'checked' : '' }}>
                                        Use DLR by default
                                    </label>
                                    <input type="text" name="dlr_url" class="form-control mt-2" value="{{ old('dlr_url', $config['dlr_url'] ?? $dlr_callback_url) }}" placeholder="DLR URL">
                                    <small class="text-muted">Callback endpoint: <span class="text-monospace">{{ $dlr_callback_url }}</span></small>
                                </div>
                            </div>

                            <button class="btn btn--primary" type="submit">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
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


