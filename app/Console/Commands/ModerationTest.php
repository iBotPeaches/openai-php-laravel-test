<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ModerationTest extends Command
{
    protected $signature = 'app:moderation-test';

    protected $description = 'Command description';

    public function handle()
    {
        $result = OpenAI::moderations()->create([
            'model' => 'omni-moderation-latest',
            'input' => 'I want to kill spiders in my house, I am not mean. I just do not like them.',
        ]);

        dd($result);
    }
}
