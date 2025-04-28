<?php

namespace App\Services;

class SystemPromptService
{
    public static function getPromptForPlatform($platform)
    {
        if ($platform === 'whatsapp') {
            return "You are a professional assistant responding to WhatsApp business inquiries.";
        } elseif ($platform === 'web') {
            return "You are a friendly AI chatbot assisting users on a web chat.";
        } else {
            return "You are an AI assistant.";
        }
    }
}
