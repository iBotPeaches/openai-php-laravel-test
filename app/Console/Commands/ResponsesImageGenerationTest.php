<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesImageGenerationTest extends Command
{
    protected $signature = 'app:responses-image-generation-test';
    protected $description = 'Command description';

    public function handle(): int
    {
        $stream = OpenAI::responses()->createStreamed([
            'model' => 'gpt-4.1-mini',
            'input' => 'Just a black dot.',
            'tools' => [
                [
                    'type' => 'image_generation',
                    'size' => '1024x1024',
                    'quality' => 'low',
                    'partial_images' => 1,
                ],
            ],
        ]);

        foreach ($stream as $response) {
            dump(json_encode($response->toArray()));
        }

        return self::SUCCESS;
    }
}
