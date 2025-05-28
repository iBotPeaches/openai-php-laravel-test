<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesCancelTest extends Command
{
    protected $signature = 'app:responses-cancel-test';

    protected $description = 'Command description';

    public function handle()
    {
        $start = microtime(true);
        $response = OpenAI::responses()->create([
            'background' => true,
            'model' => 'gpt-4o-mini',
            'input' => 'What do you think it takes to make the best grilled cheese sandwich?',
        ]);
        $end = microtime(true);
        $this->info('Response created in '.round($end - $start, 2).' seconds');

        $this->info('Response ID: '.$response->id);
        $this->info('Status: '.$response->status);

        $start = microtime(true);
        $cancelResponse = OpenAI::responses()->cancel($response->id);
        $end = microtime(true);
        $this->info('Cancel request completed in '.round($end - $start, 2).' seconds');

        $this->info('Cancel Response Status: '.$cancelResponse->status);
    }
}
