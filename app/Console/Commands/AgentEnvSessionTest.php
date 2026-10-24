<?php

namespace App\Console\Commands;

use App\Console\Concerns\RetriesUntilReady;
use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Agents\Environments\AgentEnvironmentResponse;
use OpenAI\Responses\Agents\Environments\Files\AgentEnvironmentFileResponse;
use OpenAI\Responses\Agents\Environments\Templates\AgentEnvironmentTemplateResponse;
use OpenAI\Responses\Agents\Sessions\AgentSessionResponse;
use RuntimeException;

class AgentEnvSessionTest extends Command
{
    use RetriesUntilReady;

    protected $signature = 'app:agent-env-session-test {--model=gpt-5.4}';

    protected $description = 'Test Agent Environments and Sessions (beta) together: template, prewarmed environment, session, files and events.';

    public function handle(ClientContract $client): int
    {
        $environments = $client->agents()->environments();
        $templates = $environments->templates();
        $files = $environments->files();
        $sessions = $client->agents()->sessions();

        // Create a template. Environment variables and setup commands are write-only.
        $template = $templates->create([
            'name' => 'Test Template '.uniqid(),
            'packages' => ['python' => ['requests']],
            'network' => [
                'access' => 'restricted',
                'allowed_domains' => ['pypi.org', 'files.pythonhosted.org'],
            ],
            'setup_commands' => [
                ['command' => 'pip list'],
            ],
        ]);

        $session = null;

        try {
            $this->info('--- Create template ---');
            $this->printTemplate($template);
            $this->info('Request ID: '.$template->meta()->requestId);

            // Update the template.
            $this->newLine();
            $this->info('--- Update template ---');
            $this->printTemplate($templates->update($template->id, [
                'name' => $template->name.' (renamed)',
            ]));

            // Prewarm an environment from the template.
            $this->newLine();
            $this->info('--- Create environment ---');
            $environment = $environments->create([
                'environment' => [
                    'type' => 'openai_hosted',
                    'environment_template_id' => $template->id,
                ],
            ]);
            $this->printEnvironment($environment);
            $this->info('Request ID: '.$environment->meta()->requestId);

            // Wait for the environment to finish provisioning.
            $this->newLine();
            $this->info('--- Wait for environment ---');
            $environment = $this->eventually(
                fn () => $environments->retrieve($environment->id),
                function (AgentEnvironmentResponse $retrieved) {
                    if ($retrieved->status === 'failed') {
                        throw new RuntimeException("The environment {$retrieved->id} failed to provision.");
                    }

                    return $retrieved->status === 'ready';
                },
                attempts: 60,
                seconds: 5,
            );
            $this->printEnvironment($environment);

            // Start a session on the prewarmed environment. Without input, no turn runs yet.
            $this->newLine();
            $this->info('--- Create session ---');
            $session = $sessions->create([
                'agent' => [
                    'model' => $this->option('model'),
                    'instructions' => 'Be brief.',
                ],
                'environment' => [
                    'type' => 'openai_hosted',
                    'environment_id' => $environment->id,
                ],
                'metadata' => ['source' => 'openai-php-laravel-test'],
            ]);
            $this->printSession($session);
            $this->info('Request ID: '.$session->meta()->requestId);

            // List sessions.
            $this->newLine();
            $this->info('--- List sessions ---');
            $sessionList = $this->eventually(
                fn () => $sessions->list(['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->id === $session->id),
            );

            $this->info("Listed {$sessionList->object} of ".count($sessionList->data).' session(s) (has more: '.var_export($sessionList->hasMore, true).')');
            $this->info("First ID: {$sessionList->firstId}, Last ID: {$sessionList->lastId}");
            foreach ($sessionList->data as $listedSession) {
                $this->line("Session ID: {$listedSession->id}, Status: {$listedSession->status}, Model: {$listedSession->agent->model}");
            }

            // Retrieve the session.
            $this->newLine();
            $this->info('--- Retrieve session ---');
            $this->printSession($this->eventually(fn () => $sessions->retrieve($session->id)));

            // Update the session.
            $this->newLine();
            $this->info('--- Update session ---');
            $this->printSession($sessions->update($session->id, [
                'metadata' => ['source' => 'openai-php-laravel-test', 'updated' => 'true'],
                'agent' => ['reasoning' => ['effort' => 'low']],
            ]));

            // Write a file into the connected environment.
            $this->newLine();
            $this->info('--- Create file ---');
            $secret = 'openai-php-'.uniqid();
            $path = "/workspace/{$secret}.txt";
            $file = $files->create($environment->id, [
                'type' => 'inline',
                'path' => $path,
                'data' => base64_encode("The secret word is {$secret}.\n"),
            ]);
            $this->printFile($file);
            $this->info('Request ID: '.$file->meta()->requestId);

            // List the files, following the cursor through every page.
            $this->newLine();
            $this->info('--- List files ---');
            $page = null;
            $found = false;

            do {
                $fileList = $files->list($environment->id, array_filter([
                    'path' => '/workspace',
                    'page' => $page,
                ]));

                $this->info("Listed {$fileList->object} of ".count($fileList->data).' file(s) (has more: '.var_export($fileList->hasMore, true).', next: '.var_export($fileList->next, true).')');
                foreach ($fileList->data as $listedFile) {
                    $this->printFile($listedFile);
                    $found = $found || $listedFile->path === $path;
                }

                $page = $fileList->next;
            } while ($fileList->hasMore && $page !== null);

            $found
                ? $this->info("Found the created file at {$path}.")
                : $this->warn("The created file at {$path} was not listed.");

            // Ask the agent to read the file, then wait for the turn to finish.
            $this->newLine();
            $this->info('--- Send message event ---');
            $before = $sessions->retrieve($session->id);
            $sessions->events()->create($session->id, [
                'events' => [
                    [
                        'type' => 'agent.session.input.message',
                        'input' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    ['type' => 'input_text', 'text' => "Read {$path} and reply with the secret word only."],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
            $this->info('Event sent.');

            // The status can read idle before the turn starts, and last_active_at doesn't move, so wait for usage to grow.
            $this->newLine();
            $this->info('--- Wait for turn ---');
            $finished = $this->eventually(
                fn () => $sessions->retrieve($session->id),
                fn (AgentSessionResponse $retrieved) => $retrieved->status !== 'in_progress'
                    && ($retrieved->usage?->totalTokens ?? 0) > ($before->usage?->totalTokens ?? 0),
                attempts: 60,
                seconds: 3,
            );
            $this->printSession($finished);
            $this->line(json_encode($finished->toArray(), JSON_PRETTY_PRINT));
        } finally {
            // Delete the session.
            if ($session !== null) {
                $this->newLine();
                $this->info('--- Delete session ---');
                $deleted = $sessions->delete($session->id);
                $this->info("Deleted session ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
            }

            // Delete the template.
            $this->newLine();
            $this->info('--- Delete template ---');
            $deleted = $templates->delete($template->id);
            $this->info("Deleted template ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
        }

        return self::SUCCESS;
    }

    private function printTemplate(AgentEnvironmentTemplateResponse $template): void
    {
        $this->info("Template ID: {$template->id}, Object: {$template->object}, Name: {$template->name}");
        $this->info("Created at: {$template->createdAt}, Updated at: {$template->updatedAt}, Desktop enabled: ".var_export($template->desktop->enabled, true));
        $this->info("Network access: {$template->network->access}, allowed domains: ".json_encode($template->network->allowedDomains));
        $this->info('Packages: npm '.json_encode($template->packages->npm).', python '.json_encode($template->packages->python).', system '.json_encode($template->packages->system));
    }

    private function printEnvironment(AgentEnvironmentResponse $environment): void
    {
        $this->info("Environment ID: {$environment->id}, Object: {$environment->object}, Type: {$environment->type}, Status: {$environment->status}");
    }

    private function printSession(AgentSessionResponse $session): void
    {
        $this->info("Session ID: {$session->id}, Object: {$session->object}, Status: {$session->status}");
        $this->info("Created at: {$session->createdAt}, Last active at: {$session->lastActiveAt}, Error: ".json_encode($session->error));
        $this->info("Agent ID: {$session->agent->id}, Model: {$session->agent->model}, Reasoning effort: ".var_export($session->agent->reasoning->effort, true));
        $this->info('Environment: '.($session->environment['type'] ?? '?').' '.($session->environment['id'] ?? '').', Vault IDs: '.json_encode($session->vaultIds));
        $this->info('Metadata: '.json_encode($session->metadata).', Required actions: '.count($session->requiredActions));

        if ($session->usage !== null) {
            $this->info("Usage: {$session->usage->inputTokens} input, {$session->usage->outputTokens} output, {$session->usage->totalTokens} total tokens");
        }
    }

    private function printFile(AgentEnvironmentFileResponse $file): void
    {
        $this->line("File: {$file->path}, Object: {$file->object}, Environment ID: {$file->environmentId}, Size: {$file->sizeBytes} bytes");
    }
}
