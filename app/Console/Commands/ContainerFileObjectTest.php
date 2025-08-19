<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ContainerFileObjectTest extends Command
{
    protected $signature = 'app:container-file-object-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $this->makeContainerWithFallbackToDeletion();

        return self::SUCCESS;
    }

    private function makeContainerWithFallbackToDeletion(): void
    {
        $container = null;

        try {
            $container = OpenAI::containers()->create([
                'name' => 'Test Container',
            ]);

            $this->info('Created container with ID: '.$container->id);

            // Manually upload a different file
            $file = OpenAI::files()->upload([
                'file' => fopen(storage_path('samples/sample.jsonl'), 'r'),
                'purpose' => 'evals',
            ]);

            $manualFile = OpenAI::containers()->files()->create($container->id, [
                'file_id' => $file->id,
            ]);

            // Create a file in the container
            $createdFile = OpenAI::containers()->files()->create($container->id, [
                'file' => fopen(storage_path('samples/test.txt'), 'r'),
            ]);

            $this->info('Created file with ID: '.$createdFile->id);

            // List files in the container
            $files = OpenAI::containers()->files()->list($container->id);
            $this->info('Files in container:');
            foreach ($files->data as $file) {
                $this->info("- File ID: {$file->id}, Size: {$file->bytes} bytes");
            }

            // Retrieve the file
            $retrievedFile = OpenAI::containers()->files()->retrieve($container->id, $createdFile->id);
            $this->info('Retrieved file with ID: '.$retrievedFile->id);
            $this->info('File size: '.$retrievedFile->bytes.' bytes');

            // Read the file content
            $fileContent = OpenAI::containers()->files()->content($container->id, $retrievedFile->id);
            $this->info('File content: '.$fileContent);

            // Read the manually uploaded file content
            $manualFileContent = OpenAI::containers()->files()->content($container->id, $manualFile->id);
            $this->info('Manually uploaded file content: '.$manualFileContent);

            // Delete the file
            OpenAI::containers()->files()->delete($container->id, $createdFile->id);
            $this->info('Deleted file with ID: '.$createdFile->id);

            // Delete the manually uploaded file
            OpenAI::containers()->files()->delete($container->id, $manualFile->id);
            $this->info('Deleted manually uploaded file with ID: '.$manualFile->id);
        } finally {
            if ($container !== null) {
                $this->info('Cleaning (i.e deleting) container: '.$container->id);
                OpenAI::containers()->delete($container->id);
            }
        }
    }
}
