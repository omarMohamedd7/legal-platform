<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate a new OTP for the user
     *
     * @param User $user
     * @return string
     */
    public function generateOtp(User $user): string
    {
        // Generate a 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in the user record with expiration time (10 minutes)
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);
        
        return $otp;
    }
    
    /**
     * Verify if the provided OTP is valid for the user
     *
     * @param User $user
     * @param string $otp
     * @return bool
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        // Check if OTP matches and has not expired
        if ($user->otp === $otp && $user->otp_expires_at > Carbon::now()) {
            // Clear the OTP after successful verification
            $user->update([
                'otp' => null,
                'otp_expires_at' => null,
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Send OTP email to the user
     *
     * @param User $user
     * @param string $otp
     * @param string $purpose
     * @return void
     */
    public function sendOtpEmail(User $user, string $otp, string $purpose = 'authentication'): void
    {
        $subject = $purpose === 'password_reset' 
            ? 'Password Reset OTP Code' 
            : 'Authentication OTP Code';
            
        $template = $purpose === 'password_reset'
            ? 'emails.otp-password-reset'
            : 'emails.otp-authentication';
        
        // Send email using Laravel's Mail facade with a template
        Mail::send($template, ['user' => $user, 'otp' => $otp], function ($mail) use ($user, $subject) {
            $mail->to($user->email)
                ->subject($subject);
        });
    }
} 