<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesTest extends Command
{
    protected $signature = 'app:responses-test';
    protected $description = 'Command description';

    public function handle()
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-4o-mini',
            'tools' => [
                [
                    'type' => 'web_search_preview'
                ]
            ],
            'metadata' => [
                'foo' => 'bar',
                'openai' => 'php'
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'blog_titles',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'The title of the blog post'
                            ],
                            'url' => [
                                'type' => 'string',
                                'description' => 'The URL of the blog post'
                            ]
                        ],
                        'required' => ['title', 'url'],
                        'additionalProperties' => false,
                    ],
                ]
            ],
            'input' => 'What is last blog from Connor Tumbleson?'
        ]);

        dd($response);
    }
}
