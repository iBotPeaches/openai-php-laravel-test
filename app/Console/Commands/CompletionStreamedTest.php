<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class CompletionStreamedTest extends Command
{
    protected $signature = 'app:completion-streamed-test';

    protected $description = 'Stream test with usage inclusion.';

    public function handle()
    {
        $stream = OpenAI::completions()->createStreamed([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => 'This is a test',
        ]);

        foreach ($stream as $response) {
            dump(json_encode($response->toArray()));
        }
    }
}
