<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatStreamIncludeUsageTest extends Command
{
    protected $signature = 'app:chat-stream-include-usage-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $stream = OpenAI::chat()->createStreamed([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'user', 'content' => 'What is the capital of France? (please reform it JSON)'],
            ],
            'stream_options' => ['include_usage' => true],
        ]);

        foreach ($stream as $response) {
            dump(json_encode($response->toArray()));
        }

        return self::SUCCESS;
    }
}
