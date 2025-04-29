<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatSearchTest extends Command
{
    protected $signature = 'app:chatsearch-test';

    protected $description = 'Command description';

    public function handle()
    {
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini-search-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Could you find 2 articles on french drains for OSHAA certification?',
                ]
            ],
            'web_search_options' => [
                'user_location' => [
                    'type' => 'approximate',
                    'approximate' => [
                        'country' => 'US',
                        'city' => 'Tampa',
                    ]
                ]
            ],
        ]);

        dd($result);
    }
}
