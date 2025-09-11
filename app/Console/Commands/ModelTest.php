<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ModelTest extends Command
{
    protected $signature = 'app:model-test';

    protected $description = 'List available models from OpenAI';

    public function handle(): int
    {
        $response = OpenAI::models()->list();
        dd($response);

        return self::SUCCESS;
    }
}
