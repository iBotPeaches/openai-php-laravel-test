<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatAudioTest extends Command
{
    protected $signature = 'app:chat-audio-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-audio-preview-2025-06-03',
            'modalities' => ['text', 'audio'],
            'audio' => ['format' => 'mp3', 'voice' => 'nova'],
            'temperature' => 0.7,
            'messages' => [
                ['role' => 'user', 'content' => 'What is the capital of France?'],
            ],
        ]);

        dd($response->toArray());

        return self::SUCCESS;
    }
}
