<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Services\AiMemoryService;
use Illuminate\Support\Facades\Http;



class AiService
{
    private AiMemoryService $memoryService;
    public function __construct(AiMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    public function callAI(string $message, string $sender, string $channel = 'web'): array
    {
        $apiKey = config('services.openrouter.key');

        if (!$apiKey) {
            Log::error('❌ Missing OpenRouter API key');
            return ['response' => 'عذرًا، لا أستطيع تنفيذ طلبك الآن.'];
        }

        // Clear memory if user thanks the bot
        if (preg_match('/شكراً|شكرا|شكرًا مساعد/i', $message)) {
            $this->memoryService->clearMemory($sender);
            return ['response' => 'على الرحب والسعة! إذا احتجت أي خدمة، أنا موجود دائمًا 🧽✨'];
        }

        $history = $this->memoryService->getHistory($sender);

        $systemPrompt = [
            'role' => 'system',
            'content' => <<<EOT
        You are مساعد, a smart and friendly AI assistant for JanPro, a B2B cleaning services company.

        Introduce yourself only once at the beginning of each new conversation. Then, engage in a natural, professional, and approachable tone. Prioritize understanding the customer's business needs and provide clear, concise responses.

        Instructions:

        You represent JanPro, which provides professional cleaning services to businesses and organizations.

        You are responsible for answering customer inquiries related to JanPro’s services.

        If a user requests contact details for managers, provide the following static contact numbers:
        • مدير الدعم: 0500000001  
        • مدير المبيعات: 0500000002

        Proactively suggest services from the following static list when appropriate:
        • تنظيف المكاتب والشركات  
        • خدمات النظافة اليومية أو الأسبوعية  
        • تنظيف ما بعد البناء  
        • تعقيم الأسطح والمكاتب  
        • تنظيف الأرضيات والسجاد باحترافية

        When the user asks for a price quotation, price list, or any query related to pricing for a service, you must always do the following:
        1. Respond with a polite and professional message saying that the price list PDF will be sent.  
        2. Use ONLY this exact tag on a separate line when the user asks for the price list:  
        `[send_presentation_pdf]`

        If the user expresses dissatisfaction, reports an issue, or wants to submit a complaint:

        1. Politely acknowledge the concern and start gathering the following details:
        • الاسم الكامل  
        • رقم الجوال  
        • وصف واضح للمشكلة  
        • وقت حدوث المشكلة  
        • ما الذي يتوقعه العميل لحل المشكلة (مثل: استرداد، متابعة، خصم)

        2. After collecting all the necessary info, generate a clear and professional email-style message that includes all details.

        3. At the end of that message, add the following tag on a **separate line** to indicate that the email is ready to be sent:  
        `[complaint_ready]`

        If the user shows interest in the services (such as asking what services you offer, how to request cleaning, how much it costs, or how to get started), continue the conversation naturally and provide helpful details.

        Then, if the user appears interested in getting more information or asking for a quotation, you should politely ask:

        "هل تود أن أرسل لك عرض أسعار أو تفاصيل أكثر؟ إذا سمحت، ممكن أعرف اسمك ورقم الجوال للتواصل؟"

        Ask for name and phone one at a time.

        When the user provides both, respond with a confirmation and include their data in this exact JSON format:

        {
        "name": "Ali Ahmed",
        "phone": "0500000000"
        }

        Then, on a new line, add this tag:
        [user_data_ready]

        ⚠️ Do NOT ask for this information unless the user shows interest in services.
        ⚠️ Do NOT include the tag unless both name and phone are collected.
        ⚠️ Do NOT collect this info during support, complaint, or order follow-ups.
        ⚠️ Do not ask for this information again during the same session.



        ⚠️ Important: Do **not** send or mention actual email addresses to the user. The message will be sent internally using this tag.

        When the user thanks you for your help (e.g., saying "شكرًا" or "شكرًا مساعد"), clear the memory and reset the context.

        Keep the conversation focused, relevant, and within the current business scope.

 EOT
        ];

        $messages = array_merge([$systemPrompt], $history, [['role' => 'user', 'content' => $message]]);

        $payload = [
            'model' => 'deepseek/deepseek-chat:free',
            'messages' => $messages,
            'temperature' => 0.8,
            'max_tokens' => 300,
        ];

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        try {
            Log::info('📤 Sending request to OpenRouter', ['url' => $url, 'payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://yourdomain.com',
            ])->post($url, $payload);

            Log::debug('📄 Raw response from OpenRouter: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                $aiReply = $data['choices'][0]['message']['content'] ?? null;

                if (!$aiReply) {
                    Log::warning('⚠️ AI response missing', ['response' => $data]);
                    return ['response' => 'لم أفهم تمامًا، ممكن توضح أكثر؟'];
                }

                $this->memoryService->saveMessage($sender, $message, $aiReply);

                $payload = ['response' => trim($aiReply)];

                // 📎 Handle PDF tag
                if (preg_match('/\[send_presentation_pdf\]/', $aiReply)) {
                    Log::info('📎 Presentation request detected', ['channel' => $channel]);

                    $payload['response'] = trim(preg_replace('/\[send_presentation_pdf\]/', '', $aiReply));

                    if ($channel === 'whatsapp') {
                        $this->sendPresentationPdf($sender);
                    }

                    if ($channel === 'web') {
                        $payload['file_url'] = asset('storage/app/pdf/presentation.pdf');
                    }
                }

                if (str_contains($aiReply, '[user_data_ready]')) {
                    $this->saveUserLeadFromMessage($aiReply);
                    $aiReply = str_replace('[user_data_ready]', '', $aiReply);
                }
                
                // 📧 Complaint handling
                if (str_contains($aiReply, '[complaint_ready]')) {
                    Log::info('[AI Agent] Complaint response ready for email.');
                    app(\App\Services\ComplaintEmailService::class)->sendComplaintEmail($aiReply, $sender);
                }

                return $payload;
            }

            Log::warning('⚠️ OpenRouter API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return ['response' => 'عذرًا، النظام مشغول حاليًا. حاول بعد قليل.'];
        } catch (\Exception $e) {
            Log::error('❌ Exception calling OpenRouter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['response' => 'حدث خطأ أثناء معالجة الطلب. حاول مجددًا.'];
        }
    }
    private function sendPresentationPdf(string $number)
    {
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
                'to' => $number,
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

    public function saveUserLeadFromMessage(string $message): void
    {
        if (!preg_match('/\{.*\}/s', $message, $matches)) {
            Log::warning('❌ No JSON found in user_data_ready payload.');
            return;
        }
    
        $json = $matches[0];
        $data = json_decode($json, true);
    
        if (!is_array($data)) {
            Log::warning('❌ Invalid JSON format in user_data_ready.');
            return;
        }
    
        \App\Models\UserLead::create([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'company' => $data['company'] ?? null,
            'service' => $data['service'] ?? null,
            'details' => $message,
        ]);
    }
    

    private function extractField(string $text, string $field): ?string
    {
        $pattern = '/' . preg_quote($field, '/') . ':\s*(?:\[(.*?)\]|(.*))/u';
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1] ?? $matches[2]);
        }
        return null;
    }
}
