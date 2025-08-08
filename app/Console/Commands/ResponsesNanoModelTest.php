<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesNanoModelTest extends Command
{
    protected $signature = 'app:responses-nano-model-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-5-nano',
            'input' => 'What is the purpose of a culvert?',
        ]);

        dd($response->toArray());
    }
}
