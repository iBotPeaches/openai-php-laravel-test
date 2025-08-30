<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\VectorStores\VectorStoreResponse;

class ResponsesVectorSearchTest extends Command
{
    protected $signature = 'app:responses-vector-search-test';

    protected $description = 'Command description';

    public ?VectorStoreResponse $vectorStoreResponse = null;

    public function handle(): int
    {
        try {
            $this->createVectorStoreAndSearch();

            $response = OpenAI::responses()->create([
                'model' => 'gpt-4.1-2025-04-14',
                'input' => 'Find me information on the vector store I just created.',
                'instructions' => 'Use as little words as possible.',
                'include' => [
                    'file_search_call.results',
                ],
                'temperature' => 0,
                'tools' => [
                    [
                        'type' => 'file_search',
                        'vector_store_ids' => [
                            $this->vectorStoreResponse->id,
                        ],
                        'filters' => [
                            'type' => 'or',
                            'filters' => [[
                                'type' => 'or',
                                'filters' => [
                                    ['type' => 'eq', 'key' => 'state', 'value' => 'ks'],
                                    ['type' => 'ne', 'key' => 'state', 'value' => 'mo'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ]);

            dump($response);
        } finally {
            if ($this->vectorStoreResponse?->id) {
                OpenAI::vectorStores()->delete($this->vectorStoreResponse->id);
                $this->info("Deleted Vector Store with ID: {$this->vectorStoreResponse->id}");
            }
        }

        return self::SUCCESS;
    }

    public function createVectorStoreAndSearch(): void
    {
        $this->vectorStoreResponse = OpenAI::vectorStores()->create([
            'name' => 'test_vector_store_'.uniqid(),
            'description' => 'A test vector store',
            'metadata' => [
                'project' => 'test_project',
            ],
        ]);

        $this->info("Created Vector Store with ID: {$this->vectorStoreResponse->id}");
    }
}
