<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class RealtimeTokenTest extends Command
{
    protected $signature = 'app:realtime-token-test';

    protected $description = 'Command description';

    public function handle()
    {
        $response = OpenAI::realtime()->transcribeToken();
        dd($response->toArray());
    }
}
