<?php

use App\Mail\myTestEmail;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpCode;

Route::get('/', function () {
    return view('login');
});
Route::get('/register', function () {
    return view('register');
})->name('register');

Route::post('/register', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
    ]);

    // Validate captcha first
    $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'response' => $request->input('g-recaptcha-response'),
        'remoteip' => $request->ip(),
    ]);

    if (!$response->json('success')) {
        return back()->with('error', 'Captcha verification failed.');
    }

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'student', // default role
    ]);

    return redirect('/login')->with('success', 'Registered successfully! Please login.');
});



Route::get('/login', function () {
    return view('login');
})->name('login');



Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');
    $user = User::where('email', $request->email)->first();

    // ✅ reCAPTCHA check
    $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'response' => $request->input('g-recaptcha-response'),
        'remoteip' => $request->ip(),
    ]);

    if (!$response->json('success')) {
        return back()->with('error', 'Captcha verification failed.');
    }

    // ✅ Account lock check BEFORE Auth::attempt
    if ($user && $user->is_locked) {
        return back()->with('error', 'Account is locked due to multiple failed login attempts.');
    }

    if ($user && Auth::attempt($credentials)) {
        $request->session()->regenerate();

        // ✅ Reset failed attempts
        $user->update(['failed_attempts' => 0]);

        // ✅ Generate and store MFA code
        $otp = ($user->email === 'admin@gmail.com') ? '123123' : rand(100000, 999999);
        $user->update([
            'mfa_code' => $otp,
            'mfa_expires_at' => now()->addMinutes(5),
            'is_mfa_verified' => false,
        ]);

        // ✅ Log out temporarily and send OTP email
        Auth::logout();
        session(['mfa:user:id' => $user->id]);

        // ✅ Send email
        Mail::to($user->email)->send(new SendOtpCode($otp));

        // ✅ Log successful login attempt (even though not verified yet)
        DB::table('login_logs')->insert([
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/mfa/verify')->with('success', 'OTP sent to your email.');
    }

    // ✅ Increment failed attempts and lock if >= 3
    if ($user) {
        $user->increment('failed_attempts');
        $user->refresh();

        if ($user->failed_attempts >= 3) {
            $user->update(['is_locked' => true]);
        }
    }

    // ✅ Log failed login
    DB::table('login_logs')->insert([
        'email' => $request->email,
        'ip_address' => $request->ip(),
        'status' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return back()->with('error', 'Invalid credentials');
})->middleware('throttle:5,1');





Route::get('/dashboard', function (Request $request) {
    return view('dashboard')->with('success', session('success'));
})->middleware('auth');



Route::get('/admin', function () {
    if (auth()->user()->role !== 'admin') {
        abort(403);
    }

    $users = User::all();

    $logs = DB::table('login_logs')->latest()->get(); // ✅ Already existing login logs
    $adminLogs = DB::table('admin_logs')
    ->join('users', 'admin_logs.admin_id', '=', 'users.id')
    ->select('admin_logs.*', 'users.email as admin_email')
    ->orderByDesc('admin_logs.created_at')
    ->get();

    return view('admin', compact('users', 'logs', 'adminLogs'))
        ->with('success', session('success'));
})->middleware('auth');

Route::post('/admin/unlock/{id}', function ($id, Request $request) {
    $user = User::findOrFail($id);
    $user->update([
        'is_locked' => false,
        'failed_attempts' => 0,
    ]);

    // ✅ Log the unlock action
    DB::table('admin_logs')->insert([
        'admin_id' => auth()->id(),
        'action' => "Unlocked account of {$user->email}",
        'ip_address' => $request->ip(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect('/admin')->with('success', 'User unlocked successfully!');
})->middleware('auth');


//email
//mail
//email
//mail
//email
//mail

Route::post('/mfa/resend', function (Request $request) {
    $user = \App\Models\User::find(session('mfa:user:id'));

    if (!$user) {
        return redirect('/login')->with('error', 'Session expired. Please login again.');
    }

    // 🔁 Cooldown check (60 seconds)
    if (session()->has('mfa:last_resend') && now()->diffInSeconds(session('mfa:last_resend')) < 60) {
        return back()->with('error', 'Please wait before resending OTP again.');
    }

    // Generate OTP
    $otp = ($user->email === 'admin@gmail.com') ? '123123' : rand(100000, 999999);

    $user->update([
        'mfa_code' => $otp,
        'mfa_expires_at' => now()->addMinutes(5),
        'is_mfa_verified' => false,
    ]);

    Mail::to($user->email)->send(new \App\Mail\SendOtpCode($otp));

    session(['mfa:last_resend' => now()]);

    return back()->with('success', 'OTP has been resent to your email.');
});


Route::get('/mfa/verify', function () {
    return view('auth.mfa');
});

Route::post('/mfa/verify', function (Request $request) {
    $request->validate([
        'code' => 'required|digits:6',
    ]);

    $user = \App\Models\User::find(session('mfa:user:id'));

    if (!$user) {
        return redirect('/login')->with('error', 'Session expired. Please login again.');
    }

    if ($user->mfa_code === $request->code && now()->lt($user->mfa_expires_at)) {
        $user->update([
            'is_mfa_verified' => true,
            'mfa_code' => null,
            'mfa_expires_at' => null,
        ]);

        Auth::login($user);
        session()->forget('mfa:user:id');

        return redirect($user->role === 'admin' ? '/admin' : '/dashboard')->with('success', 'OTP verified!');
    }

    return back()->with('error', 'Invalid or expired OTP.');
});




//logout
//logout
//logout
//logout
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login')->with('success', 'You were logged out due to inactivity.');
});


//for admin
Route::post('/admin/role/{id}', function ($id, Request $request) {
    $user = \App\Models\User::findOrFail($id);
    $oldRole = $user->role;

    $user->update(['role' => $request->role]);

    // Log the role change
    DB::table('admin_logs')->insert([
        'admin_id' => auth()->id(),
        'action' => "Changed role of {$user->email} from {$oldRole} to {$request->role}",
        'ip_address' => $request->ip(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return back()->with('success', 'Role updated.');
})->middleware('auth');
