<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesStoredPromptTest extends Command
{
    protected $signature = 'app:responses-stored-prompt-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'prompt' => [
                'id' => 'pmpt_6859a04e9ee48194adcd8f88678be9c9007e9df1bcea2d2b',
                'version' => '1',
                'variables' => [
                    'city' => 'Tampa',
                ],
            ],
        ]);

        dd($response);
    }
}
