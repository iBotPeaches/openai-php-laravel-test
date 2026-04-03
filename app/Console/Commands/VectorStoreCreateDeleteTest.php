<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class VectorStoreCreateDeleteTest extends Command
{
    protected $signature = 'app:vector-store-create-delete-test';

    protected $description = 'Creates a vector store with no description and then deletes it';

    public function handle(): int
    {
        $vectorStore = OpenAI::vectorStores()->create([
            'name' => 'test_vector_store_'.uniqid(),
        ]);

        $this->info("Created Vector Store with ID: {$vectorStore->id}");
        dump($vectorStore->toArray());

        OpenAI::vectorStores()->delete($vectorStore->id);
        $this->info("Deleted Vector Store with ID: {$vectorStore->id}");

        return self::SUCCESS;
    }
}
