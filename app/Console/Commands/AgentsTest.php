<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Agents\AgentResponse;
use RuntimeException;

class AgentsTest extends Command
{
    protected $signature = 'app:agents-test {--model=gpt-5.4}';

    protected $description = 'Test the Agents API (beta): create, list, retrieve, update and delete.';

    public function handle(ClientContract $client): int
    {
        $agents = $client->agents();

        // Create an agent with a function tool.
        $agent = $agents->create([
            'model' => $this->option('model'),
            'name' => 'Support Agent '.uniqid(),
            'instructions' => 'Answer billing questions.',
            'metadata' => ['source' => 'openai-php-laravel-test'],
            'tools' => [
                [
                    'type' => 'function',
                    'name' => 'get_invoice',
                    'description' => 'Look up an invoice by its number.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'number' => ['type' => 'string'],
                        ],
                        'required' => ['number'],
                    ],
                ],
            ],
        ]);

        try {
            $this->info('--- Create ---');
            $this->printAgent($agent);
            $this->info('Request ID: '.$agent->meta()->requestId);

            // List agents.
            $this->newLine();
            $this->info('--- List ---');
            $list = $this->eventually(
                fn () => $agents->list(['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->id === $agent->id),
            );

            $this->info("Listed {$list->object} of ".count($list->data).' agent(s) (has more: '.var_export($list->hasMore, true).')');
            $this->info("First ID: {$list->firstId}, Last ID: {$list->lastId}");
            foreach ($list->data as $listedAgent) {
                $this->line("Agent ID: {$listedAgent->id}, Name: {$listedAgent->name}, Model: {$listedAgent->model}");
            }

            // Retrieve the agent.
            $this->newLine();
            $this->info('--- Retrieve ---');
            $retrieved = $this->eventually(fn () => $agents->retrieve($agent->id));
            $this->printAgent($retrieved);

            // Update the reasoning effort and instructions.
            $this->newLine();
            $this->info('--- Update ---');
            $updated = $agents->update($agent->id, [
                'instructions' => 'Answer billing questions politely.',
                'reasoning' => [
                    'effort' => 'low',
                ],
            ]);
            $this->printAgent($updated);
            $this->line(json_encode($updated->toArray(), JSON_PRETTY_PRINT));
        } finally {
            // Delete the agent.
            $this->newLine();
            $this->info('--- Delete ---');
            $deleted = $agents->delete($agent->id);
            $this->info("Deleted agent ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
        }

        return self::SUCCESS;
    }

    /**
     * Retry a read until it succeeds, because reads that follow a write are eventually consistent.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $read
     * @param  (callable(TResult): bool)|null  $ready
     * @return TResult
     */
    private function eventually(callable $read, ?callable $ready = null): mixed
    {
        $attempts = 5;
        $ready ??= static fn (): bool => true;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $read();

                if ($ready($result)) {
                    return $result;
                }
            } catch (ErrorException $e) {
                if ($attempt === $attempts) {
                    throw $e;
                }
            }

            $this->comment("Waiting for the agent to become readable (attempt {$attempt} of {$attempts})...");
            sleep(1);
        }

        throw new RuntimeException('The agent never became readable.');
    }

    private function printAgent(AgentResponse $agent): void
    {
        $this->info("Agent ID: {$agent->id}, Object: {$agent->object}, Model: {$agent->model}");
        $this->info("Name: {$agent->name}, Instructions: {$agent->instructions}");
        $this->info("Created at: {$agent->createdAt}, Updated at: {$agent->updatedAt}, Service tier: {$agent->serviceTier}");
        $this->info('Reasoning effort: '.var_export($agent->reasoning->effort, true).', summary: '.var_export($agent->reasoning->summary, true));
        $this->info('Multi agent enabled: '.var_export($agent->multiAgent->enabled, true).', max concurrent subagents: '.var_export($agent->multiAgent->maxConcurrentSubagents, true));
        $this->info("Text format: {$agent->text->format['type']}, verbosity: {$agent->text->verbosity}");
        $this->info('Metadata: '.json_encode($agent->metadata));

        foreach ($agent->tools as $tool) {
            $this->line("    [{$tool['type']}] ".($tool['name'] ?? $tool['server_label'] ?? ''));
        }
    }
}
