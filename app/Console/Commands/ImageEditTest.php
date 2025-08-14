<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ImageEditTest extends Command
{
    protected $signature = 'app:image-edit-test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $imageResource = fopen(storage_path('samples/logo_small.png'), 'r');

        $response = OpenAI::images()->edit([
            'model' => 'gpt-image-1',
            'image' => $imageResource,
            'prompt' => 'Turn everything blue (that was black)',
            'n' => 1,
            'size' => '1536x1024',
        ]);

        dd($response);
    }
}
