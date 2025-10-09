<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI;

class ChatImageNanoBananaTest extends Command
{
    protected $signature = 'app:chat-image-nano-banana-test';

    protected $description = 'Chat image using LiteLLM Nano model.';

    public function handle(): void
    {
        $apiKey = config()->string('services.lite-llm.api_key');
        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('http://127.0.0.1:4000')
            ->make();

        $response = $client->chat()->create([
            'model' => 'gemini/gemini-2.5-flash-image-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Generate a beautiful sunset over mountains and describe it',
                ],
            ],
        ]);

        dd($response);
    }
}
