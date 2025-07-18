<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesCodeInterpreterBackgroundTest extends Command
{
    protected $signature = 'app:responses-code-interpreter-background-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'o3-deep-research',
            'instructions' => 'You are a personal math tutor. When asked a math question, write and run code to answer the question.',
            'input' => 'I need to solve the equation 3x + 11 = 14. Can you help me?',
            'stream' => false,
            'store' => true,
            'tools' => [
                ['type' => 'web_search_preview', 'search_context_size' => 'medium'],
                ['type' => 'code_interpreter', 'container' => ['type' => 'auto']],
            ],
            'include' => [],
            'background' => true,
            'max_tool_calls' => 20,
        ]);

        sleep(15);

        $status = OpenAI::responses()->retrieve($response->id);

        dd($status);
    }
}
