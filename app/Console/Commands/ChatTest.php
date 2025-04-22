<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatTest extends Command
{
    protected $signature = 'app:chat-test';
    protected $description = 'Command description';

    public function handle()
    {
        $result = OpenAI::completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => "This is a test",
            'max_tokens' => 600,
            'temperature' => 0
        ]);

        dd($result);
    }
}
