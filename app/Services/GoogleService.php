<?php

namespace App\Services;

use Google_Client;
use Google\Service\Gmail;

class GoogleService
{
    public function getClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/google/client_secret.json'));
        $client->addScope(Gmail::GMAIL_SEND);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri(route('google.callback'));
        return $client;
    }
}
