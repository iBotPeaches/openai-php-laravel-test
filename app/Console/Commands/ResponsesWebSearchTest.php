<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesWebSearchTest extends Command
{
    protected $signature = 'app:responses-web-search-test';

    protected $description = 'Testing include of web search result sources';

    public function handle(): void
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-5',
            'tools' => [
                [
                    'type' => 'web_search_preview',
                ],
            ],
            'include' => [
                'web_search_call.action.sources',
            ],
            'input' => 'What is last blog from Connor Tumbleson?',
        ]);

        dump($response);
    }
}
