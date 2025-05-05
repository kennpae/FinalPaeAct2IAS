@extends('layouts.auth') {{-- Or your layout file --}}
@section('title', 'Verify OTP')

@section('content')
    <h2>Enter Your OTP</h2>

    @if(session('error'))
        <div style="color: red; margin-bottom: 10px;">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div style="color: green; margin-bottom: 10px;">{{ session('success') }}</div>
    @endif

    {{-- 🔐 OTP Verification Form --}}
    <form method="POST" action="/mfa/verify">
        @csrf
        <input type="text" name="code" placeholder="6-digit OTP" required>
        <br><br>
        <button type="submit">Verify</button>
    </form>

    {{-- 🔁 Resend OTP with cooldown --}}
    <form method="POST" action="/mfa/resend" id="resendForm" style="margin-top: 20px;">
        @csrf
        <button type="submit" id="resendBtn">Resend OTP</button>
        <span id="countdownText" style="display:none; margin-left: 10px; color: gray;"></span>
    </form>

    {{-- 🧠 Countdown Timer Script --}}
    <script>
        const resendBtn = document.getElementById('resendBtn');
        const countdownText = document.getElementById('countdownText');

        let cooldown = sessionStorage.getItem('otpCooldown');
        if (cooldown && parseInt(cooldown) > 0) {
            startCountdown(parseInt(cooldown));
        }

        document.getElementById('resendForm').addEventListener('submit', function(e) {
            e.preventDefault();
            resendBtn.disabled = true;
            let countdown = 60;
            sessionStorage.setItem('otpCooldown', countdown);
            startCountdown(countdown);
            this.submit();
        });

        function startCountdown(seconds) {
            resendBtn.disabled = true;
            countdownText.style.display = 'inline';
            countdownText.textContent = `Please wait ${seconds}s`;
            let interval = setInterval(() => {
                seconds--;
                countdownText.textContent = `Please wait ${seconds}s`;
                sessionStorage.setItem('otpCooldown', seconds);

                if (seconds <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    countdownText.style.display = 'none';
                    sessionStorage.removeItem('otpCooldown');
                }
            }, 1000);
        }
    </script>
@endsection
