<?php 
namespace App\Services;

use App\Models\Complaint;
use App\Mail\ComplaintMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComplaintService
{
    public function startComplaint(string $senderNumber): Complaint
    {
        Log::info('🚀 Starting complaint process', ['sender_number' => $senderNumber]);

        $complaint = Complaint::create([
            'sender_number' => $senderNumber,
            'details' => null,
            'status' => 'pending',
        ]);

        Log::info('📝 Complaint record created', ['complaint_id' => $complaint->id]);

        return $complaint;
    }

    public function finalizeComplaint(string $senderNumber, string $details): void
    {
        Log::info('🧩 Finalizing complaint with details', [
            'sender_number' => $senderNumber,
            'details' => $details,
        ]);

        $complaint = Complaint::where('sender_number', $senderNumber)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$complaint) {
            Log::warning('❌ No pending complaint found for number', ['number' => $senderNumber]);
            return;
        }

        $complaint->details = $details;
        $complaint->status = 'submitted';
        $complaint->save();

        Log::info('✅ Complaint saved and marked as submitted', ['id' => $complaint->id]);

        Mail::to('support@yourcompany.com')->send(new ComplaintMail($complaint));

        Log::info('📨 Complaint email sent', ['complaint_id' => $complaint->id]);
    }
}
