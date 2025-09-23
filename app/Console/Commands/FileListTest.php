<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class FileListTest extends Command
{
    protected $signature = 'app:file-list-test';

    protected $description = 'Test listing files to confirm pagination fields are present.';

    public function handle(): int
    {
        $files = OpenAI::files()->list();

        $this->info('File List:');
        foreach ($files->data as $file) {
            $this->info("File ID: {$file->id}, Filename: {$file->filename}, Purpose: {$file->purpose}");
        }

        $this->info('First ID: '.($files->firstId ?? 'null'));
        $this->info('Last ID: '.($files->lastId ?? 'null'));
        $this->info('Has More: '.($files->hasMore ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
