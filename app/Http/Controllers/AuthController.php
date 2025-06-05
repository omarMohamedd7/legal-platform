<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $otpService;
    
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    
    // تسجيل الدخول - Step 1: Request login and receive OTP
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }
    
        // Generate and send OTP
        $otp = $this->otpService->generateOtp($user);
        $this->otpService->sendOtpEmail($user, $otp, 'authentication');
    
        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email',
            'email' => $user->email,
            'requires_otp' => true
        ]);
    }
    
    // تسجيل الدخول - Step 2: Verify OTP and complete login
    public function verifyLoginOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = User::where('email', $request->email)->firstOrFail();
        
        if (!$this->otpService->verifyOtp($user, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 401);
        }
        
        // OTP verified, create token and complete login
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return (new UserResource($user))
            ->withMessage('Login successful')
            ->additional([
                'token' => $token
            ]);
    }
    
    // Resend OTP if expired
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'purpose' => 'required|in:authentication,password_reset',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = User::where('email', $request->email)->firstOrFail();
        
        // Generate and send new OTP
        $otp = $this->otpService->generateOtp($user);
        $this->otpService->sendOtpEmail($user, $otp, $request->purpose);
        
        return response()->json([
            'success' => true,
            'message' => 'New OTP sent to your email',
            'email' => $user->email
            ]);
    }

    // تسجيل الخروج
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }
    
    /**
     * Get the authenticated user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        // Get user with appropriate relationship based on role
        $user = $request->user();
        
        if ($user->role === 'client') {
            $user->load('client');
        } elseif ($user->role === 'lawyer') {
            $user->load('lawyer');
        } elseif ($user->role === 'judge') {
            $user->load('judge');
        }
        
        return new UserResource($user);
    }
    
    /**
     * Send a reset link to the given user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        try {
        $request->validate([
            'email' => 'required|email',
        ]);

            Log::info('Password reset requested for email: ' . $request->email);

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                Log::warning('Password reset requested for non-existent email: ' . $request->email);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            // Generate and send OTP for password reset
            $otp = $this->otpService->generateOtp($user);
            Log::info('OTP generated for user ID ' . $user->id . ': ' . $otp);
            
            try {
                $this->otpService->sendOtpEmail($user, $otp, 'password_reset');
                Log::info('OTP email sent successfully to: ' . $user->email);
            } catch (\Exception $e) {
                Log::error('Failed to send OTP email: ' . $e->getMessage());
                // Continue execution to return email even if email fails
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Password reset OTP sent to your email',
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error in forgotPassword: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify OTP for password reset
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = User::where('email', $request->email)->firstOrFail();
            
            Log::info('Verifying OTP for password reset for email: ' . $user->email);
            
            if (!$this->otpService->verifyOtp($user, $request->otp)) {
                Log::warning('Invalid or expired OTP for email: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 401);
            }
            
            // Generate a password reset token that will be used in the reset step
            $token = Str::random(60);
            
            // Store the token with expiration time
            $user->update([
                'remember_token' => $token,
                'email_verified_at' => Carbon::now(), // Mark email as verified
            ]);
            
            Log::info('OTP verified successfully for email: ' . $user->email);
            
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'reset_token' => $token,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error in verifyResetOtp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset the user's password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'newPassword' => ['required', PasswordRule::defaults()],
                'confirmPassword' => 'required|same:newPassword',
            ]);

            $user = User::where('email', $request->email)->firstOrFail();
            
            Log::info('Password reset attempted for email: ' . $user->email);
            
            // Verify that the token matches
            if ($user->remember_token !== $request->token) {
                Log::warning('Invalid reset token for email: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token'
                ], 401);
            }
            
            // Update password and clear the token
            $user->update([
                'password' => Hash::make($request->newPassword),
                'remember_token' => null,
            ]);
            
            Log::info('Password reset successful for email: ' . $user->email);

            event(new PasswordReset($user));
            
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in resetPassword: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
