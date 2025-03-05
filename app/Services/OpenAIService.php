<?php

namespace App\Services;

use OpenAI;

class OpenAIService
{
    protected $client;

    public function __construct()
    {
        $this->client = OpenAI::client(env('OPENAI_API_KEY'));
    }

    public function chat($message)
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini', 
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là một trợ lý AI.'],
                ['role' => 'user', 'content' => $message]
            ],
            'max_tokens' => 200
        ]);

        return $response['choices'][0]['message']['content'];
    }
}