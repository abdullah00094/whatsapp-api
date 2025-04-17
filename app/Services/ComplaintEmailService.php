<?php
namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ComplaintEmailService
{
    public function sendComplaintEmail(string $aiGeneratedEmailText, string $userPhone)
    {
        Log::info('[ComplaintEmailService] Preparing to send complaint email.', [
            'phone' => $userPhone,
            'content' => $aiGeneratedEmailText,
        ]);

        try {
            Mail::raw($aiGeneratedEmailText, function ($mail) use ($userPhone) {
                $mail->to('amrfoks+1kbw3egiwrnv4mluk22o@boards.trello.com') // static email for now
                     ->subject('🚨 New Complaint from ' . $userPhone);
            });

            Log::info('[ComplaintEmailService] Complaint email sent successfully.');
        } catch (\Throwable $e) {
            Log::error('[ComplaintEmailService] Failed to send complaint email.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

