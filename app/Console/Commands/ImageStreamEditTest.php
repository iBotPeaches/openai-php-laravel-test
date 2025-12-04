<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ImageStreamEditTest extends Command
{
    protected $signature = 'app:image-edit-stream-test';

    protected $description = 'Stream events for an image edit.';

    public function handle(): int
    {
        $image1 = fopen(storage_path('samples/logo_small.png'), 'r');
        $image2 = fopen(storage_path('samples/logo_small.png'), 'r');
        $prompt = 'Blend these two images';

        $stream = OpenAI::images()->editStreamed([
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'image' => [
                $image1,
                $image2,
            ],
            'partial_images' => 2,
        ]);

        foreach ($stream as $event) {
            $this->line(json_encode($event->toArray(), JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
