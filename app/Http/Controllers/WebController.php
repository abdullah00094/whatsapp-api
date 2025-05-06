<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AiChatHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\ComplaintEmailService;

class WebController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index()
    {
        // Ensure a unique user identifier in the session
        if (!session()->has('web_user_id')) {
            session(['web_user_id' => 'web_' . Str::random(12)]);
        }

        return view('chat');
    }

    // public function send(Request $request)
    // {
    //     $request->validate([
    //         'message' => 'required|string',
    //     ]);

    //     $userId = session('web_user_id');
    //     $userMessage = $request->input('message');

    //     // Generate AI response
    //     $aiResponse = $this->aiService->callAi($userMessage, $userId);

    //     // 📎 Handle sending presentation PDF
    //     if (preg_match('/\[send_presentation_pdf\]/', $aiResponse)) {
    //         Log::info('📎 Presentation request detected, sending PDF to user', ['number' => $userId]);

    //         // You will need to create this method later
    //         $this->sendPresentationPdf($userId);

    //         // Clean up the tag from response
    //         $aiResponse = preg_replace('/\[send_presentation_pdf\]/', '', $aiResponse);
    //     }

    //     // 📧 Handle complaint email sending
    //     if (str_contains($aiResponse, '[complaint_ready]')) {
    //         Log::info('[AI Agent] Complaint response ready for email.');

    //         $finalComplaintMessage = $aiResponse;

    //         app(ComplaintEmailService::class)->sendComplaintEmail($finalComplaintMessage, $userId);
    //     }

    //     // Store chat history
    //     AiChatHistory::create([
    //         'sender_number' => $userId,
    //         'user_message' => $userMessage,
    //         'ai_response' => $aiResponse,
    //     ]);

    //     return response()->json([
    //         'response' => trim($aiResponse),
    //     ]);
    // }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userId = session('web_user_id');
        $userMessage = $request->input('message');

        // 🔁 Get structured response from AI service
        $aiResponse = $this->aiService->callAi($userMessage, $userId, 'web');

        // Prepare the response payload
        $responsePayload = [
            'response' => trim($aiResponse['response']),
        ];

        // 📎 If file URL is returned, add it to payload
        if (!empty($aiResponse['file_url'])) {
            $responsePayload['file_url'] = $aiResponse['file_url'];
        }

        // 📧 If complaint tag is present, send complaint email
        if (str_contains($aiResponse['response'], '[complaint_ready]')) {
            Log::info('[AI] Complaint response triggered email.', ['user_id' => $userId]);
            app(ComplaintEmailService::class)->sendComplaintEmail($aiResponse['response'], $userId);
        }

        // 💾 Save to chat history
        AiChatHistory::create([
            'sender_number' => $userId,
            'user_message' => $userMessage,
            'ai_response' => $aiResponse['response'],
        ]);

        return response()->json($responsePayload);
    }



    private function sendPresentationPdf(string $userId)
    {
        if (str_starts_with($userId, 'web_')) {
            // For web users, return a file download link
            $webLink = asset('pdfs/عرض_خدمات_JanPro.pdf'); // Or use Storage::url() if in storage/app/public
            Log::info('📎 Web user detected. Returning file link instead.', ['link' => $webLink]);

            // Optionally you can send a system message or flag to client
            AiChatHistory::create([
                'sender_number' => $userId,
                'user_message' => '[system] Request for PDF',
                'ai_response' => "📎 يمكنك تحميل عرض الأسعار من هنا: {$webLink}"
            ]);

            return;
        }

        // WhatsApp logic as before
        $mediaId = $this->uploadPresentationPdf();

        if (!$mediaId) {
            Log::error('❌ Failed to upload PDF, cannot send document.');
            return;
        }

        try {
            $token = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_ID');
            $url = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";

            $response = Http::withToken($token)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $userId,
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => 'عرض_خدمات_JanPro.pdf',
                    'caption' => '📎 تفضل، هذا ملف تعريفي عن شركة JanPro.',
                ]
            ]);

            if ($response->successful()) {
                Log::info('✅ Presentation PDF sent successfully.', $response->json());
            } else {
                Log::warning('⚠️ Failed to send presentation PDF.', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Exception while sending presentation PDF.', [
                'error' => $e->getMessage()
            ]);
        }
    }


    private function uploadPresentationPdf(): ?string
    {
        try {
            $pdfPath = storage_path('app/pdf/presentation.pdf');

            if (!file_exists($pdfPath)) {
                Log::error('❌ Presentation PDF not found.', ['path' => $pdfPath]);
                return null;
            }

            $token = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_ID');
            $url = "https://graph.facebook.com/v22.0/{$phoneNumberId}/media";

            $response = Http::withToken($token)->attach(
                'file',
                file_get_contents($pdfPath),
                'presentation.pdf'
            )->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => 'document',
            ]);

            if ($response->successful()) {
                $mediaId = $response->json()['id'] ?? null;
                Log::info('✅ PDF uploaded to WhatsApp successfully.', ['media_id' => $mediaId]);
                return $mediaId;
            }

            Log::warning('⚠️ Failed to upload presentation PDF.', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Exception while uploading PDF.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // public function send(Request $request)
    // {
    //     $request->validate([
    //         'message' => 'required|string',
    //     ]);

    //     $userId = session('web_user_id');
    //     $userMessage = $request->input('message');

    //     // Generate AI response
    //     $aiResponse = $this->aiService->callAi($userMessage, $userId);

    //     // Store chat history
    //     AiChatHistory::create([
    //         'sender_number' => $userId,
    //         'user_message' => $userMessage,
    //         'ai_response' => $aiResponse,
    //     ]);

    //     return response()->json([
    //         'response' => $aiResponse,
    //     ]);
    // }
}
