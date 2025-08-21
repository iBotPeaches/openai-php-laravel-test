<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatImageTest extends Command
{
    protected $signature = 'app:chat-image-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "What's in this image?",
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens' => 2000,
            'temperature' => 0.5,
            'stream' => false,
        ]);

        dd($response);

        return self::SUCCESS;
    }
}
