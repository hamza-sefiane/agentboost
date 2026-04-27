<?php

namespace App\Service;

use OpenAI\Client;

class OpenAiClient implements AiClientInterface
{
    public function __construct(private Client $client) {}

    public function generate(string $prompt): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
        ]);

        return trim($response->choices[0]->message->content ?? '') ?: 'Annonce indisponible.';
    }
}