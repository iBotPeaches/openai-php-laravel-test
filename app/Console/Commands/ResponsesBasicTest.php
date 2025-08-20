<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesBasicTest extends Command
{
    protected $signature = 'app:responses-basic-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => 'Hello!',
        ]);

        echo $response->outputText;

        return self::SUCCESS;
    }
}
