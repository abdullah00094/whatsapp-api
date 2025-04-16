<?php
namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Mail\Mailable;

class ComplaintMail extends Mailable
{
    public Complaint $complaint;

    public function __construct(Complaint $complaint)
    {
        $this->complaint = $complaint;
    }

    public function build()
    {
        return $this->subject('New Complaint Received')
                    ->text('emails.complaint_plain');
    }
}
