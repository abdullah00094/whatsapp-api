<?php

// app/Http/Controllers/EmailController.php
namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Mail\ComplaintMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function sendComplaintFromAgent(Request $request)
    {
        $complaint = Complaint::create([
            'sender_number' => $request->input('sender_number', 'Unknown'), // fallback just in case
            'details' => $request->input('chat_content'),
        ]);

        Mail::to('abdullah.morsi94@gmail.com')->send(new ComplaintMail($complaint));

        return response()->json(['status' => 'Complaint saved and email sent successfully']);
    }
}
