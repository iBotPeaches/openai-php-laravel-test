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
            'instructions' => 'Extremely precise brief answers intended for someone with OSHA training.',
            'input' => 'What is the purpose of a culvert?',
            'previous_response_id' => null,
        ]);

        dd($response->toArray());
    }
}
