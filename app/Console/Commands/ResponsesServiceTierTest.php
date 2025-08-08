<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesServiceTierTest extends Command
{
    protected $signature = 'app:responses-service-tier-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-5',
            'input' => 'What is the purpose of a culvert?',
            'safety_identifier' => 'openai-php-test-sdk',
            'service_tier' => 'flex',
            'store' => false,
            'prompt_cache_key' => 'test-prompt-cache-key',
        ]);

        dd($response->toArray());
    }
}
