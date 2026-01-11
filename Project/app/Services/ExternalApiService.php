<?php

namespace App\Services;

class ExternalApiService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://api.quotable.io/random';
    }

    public function getDailyQuote()
    {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev environments, might be needed
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'External API Error: ' . $error];
        }

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            if (isset($data['content']) && isset($data['author'])) {
                return [
                    'content' => $data['content'],
                    'author' => $data['author']
                ];
            }
        }

        return ['content' => 'The show must go on!', 'author' => 'Unknown'];
    }
}
