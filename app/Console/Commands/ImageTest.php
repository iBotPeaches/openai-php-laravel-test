<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ImageTest extends Command
{
    protected $signature = 'app:image-test';

    protected $description = 'Command description';

    public function handle()
    {
        $response = OpenAI::images()->create([
            'model' => 'gpt-image-1',
            'prompt' => 'A cute baby sea otter',
            'size' => '1024x1024',
        ]);

        dd($response);
    }
}
