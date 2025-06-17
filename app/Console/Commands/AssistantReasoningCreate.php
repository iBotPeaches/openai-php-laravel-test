<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class AssistantReasoningCreate extends Command
{
    protected $signature = 'app:assistant-reasoning-create';

    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $response = OpenAI::assistants()->create([
            'name' => 'Reasoning Assistant',
            'description' => 'An assistant that can reason through complex problems.',
            'model' => 'o1',
            'reasoning_effort' => 'low',
            'instructions' => 'Use reasoning to solve problems and provide explanations.',
        ]);

        dump($response);

        $this->info('Assistant created successfully.');
        OpenAI::assistants()->delete($response->id);
        $this->info('Assistant deleted successfully.');

        return self::SUCCESS;
    }
}
