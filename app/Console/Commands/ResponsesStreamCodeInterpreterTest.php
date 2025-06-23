<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesStreamCodeInterpreterTest extends Command
{
    protected $signature = 'app:responses-stream-code-interpreter-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $stream = OpenAI::responses()->createStreamed([
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are a personal math tutor. When asked a math question, write and run code to answer the question.',
            'input' => 'I need to solve the equation 3x + 11 = 14. Can you help me?',
            'tools' => [
                [
                    'type' => 'code_interpreter',
                    'container' => [
                        'type' => 'auto',
                    ],
                ],
            ],
        ]);

        foreach ($stream as $response) {
            dump(json_encode($response->toArray()));
        }

        return self::SUCCESS;
    }
}
