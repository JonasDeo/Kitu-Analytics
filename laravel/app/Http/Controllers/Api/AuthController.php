<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'business_owner',
        ]);

        // Generate OTP (in production send via Africa's Talking SMS)
        $otp = rand(100000, 999999);
        Cache::put("otp:{$user->phone}", $otp, now()->addMinutes(10));

        // TODO: Send OTP via Africa's Talking SMS gateway
        // For now return OTP in response (development only)
        return response()->json([
            'message' => 'Registration successful. Verify your phone.',
            'user_id' => $user->id,
            'otp' => app()->environment('local') ? $otp : null,
        ], 201);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        $cached = Cache::get("otp:{$request->phone}");

        if (!$cached || $cached != $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        $user = User::where('phone', $request->phone)->firstOrFail();
        $user->update([
            'is_verified' => true,
            'phone_verified_at' => now(),
        ]);

        Cache::forget("otp:{$request->phone}");

        $token = $user->createToken('kitu-app')->plainTextToken;

        return response()->json([
            'message' => 'Phone verified successfully.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('kitu-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('businesses'));
    }
}