<?php

namespace App\Console\Commands;

use App\Console\Concerns\RetriesUntilReady;
use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\AgentEnvironmentsContract;
use OpenAI\Responses\Agents\Environments\AgentEnvironmentResponse;
use OpenAI\Responses\Agents\Environments\Files\AgentEnvironmentFileResponse;
use OpenAI\Responses\Agents\Environments\Templates\AgentEnvironmentTemplateResponse;

class AgentEnvironmentsTest extends Command
{
    use RetriesUntilReady;

    protected $signature = 'app:agent-environments-test
        {--create-environment : Also create an environment from the template (needs the prewarming beta)}
        {--environment= : ID of an environment connected to a session, to test the files endpoints}';

    protected $description = 'Test the Agent Environments API (beta): templates, environments and files.';

    public function handle(ClientContract $client): int
    {
        $environments = $client->agents()->environments();
        $templates = $environments->templates();

        // Create a template. Environment variables and setup commands are write-only.
        $template = $templates->create([
            'name' => 'Test Template '.uniqid(),
            'packages' => [
                'python' => ['requests'],
                'npm' => ['left-pad'],
            ],
            'network' => [
                'access' => 'restricted',
                'allowed_domains' => ['pypi.org', 'files.pythonhosted.org'],
            ],
            'setup_commands' => [
                ['command' => 'pip list'],
            ],
        ]);

        try {
            $this->info('--- Create template ---');
            $this->printTemplate($template);
            $this->info('Request ID: '.$template->meta()->requestId);

            // List templates.
            $this->newLine();
            $this->info('--- List templates ---');
            $templateList = $this->eventually(
                fn () => $templates->list(['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->id === $template->id),
            );

            $this->info("Listed {$templateList->object} of ".count($templateList->data).' template(s) (has more: '.var_export($templateList->hasMore, true).')');
            $this->info("First ID: {$templateList->firstId}, Last ID: {$templateList->lastId}");
            foreach ($templateList->data as $listedTemplate) {
                $this->line("Template ID: {$listedTemplate->id}, Name: {$listedTemplate->name}");
            }

            // Retrieve the template.
            $this->newLine();
            $this->info('--- Retrieve template ---');
            $this->printTemplate($this->eventually(fn () => $templates->retrieve($template->id)));

            // Update the template.
            $this->newLine();
            $this->info('--- Update template ---');
            $updated = $templates->update($template->id, [
                'name' => $template->name.' (renamed)',
                'desktop' => ['enabled' => true],
            ]);
            $this->printTemplate($updated);
            $this->line(json_encode($updated->toArray(), JSON_PRETTY_PRINT));

            // List environments.
            $this->newLine();
            $this->info('--- List environments ---');
            $environmentList = $environments->list(['limit' => 100]);

            $this->info("Listed {$environmentList->object} of ".count($environmentList->data).' environment(s) (has more: '.var_export($environmentList->hasMore, true).')');
            $this->info("First ID: {$environmentList->firstId}, Last ID: {$environmentList->lastId}");
            foreach ($environmentList->data as $listedEnvironment) {
                $this->line("Environment ID: {$listedEnvironment->id}, Type: {$listedEnvironment->type}, Status: {$listedEnvironment->status}");
            }

            if ($this->option('create-environment')) {
                $this->createEnvironment($environments, $template->id);
            }

            if ($environmentId = $this->option('environment')) {
                $this->testFiles($environments, $environmentId);
            }
        } finally {
            // Delete the template.
            $this->newLine();
            $this->info('--- Delete template ---');
            $deleted = $templates->delete($template->id);
            $this->info("Deleted template ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
        }

        return self::SUCCESS;
    }

    private function createEnvironment(AgentEnvironmentsContract $environments, string $templateId): void
    {
        // Create an environment from the template.
        $this->newLine();
        $this->info('--- Create environment ---');
        $environment = $environments->create([
            'environment' => [
                'type' => 'openai_hosted',
                'environment_template_id' => $templateId,
            ],
        ]);
        $this->printEnvironment($environment);
        $this->info('Request ID: '.$environment->meta()->requestId);

        // Retrieve the environment.
        $this->newLine();
        $this->info('--- Retrieve environment ---');
        $this->printEnvironment($this->eventually(fn () => $environments->retrieve($environment->id)));
    }

    private function test_files(AgentEnvironmentsContract $environments, string $environmentId): void
    {
        $files = $environments->files();

        // Retrieve the connected environment.
        $this->newLine();
        $this->info('--- Retrieve environment ---');
        $this->printEnvironment($environments->retrieve($environmentId));

        // Write a file into the environment.
        $this->newLine();
        $this->info('--- Create file ---');
        $path = '/workspace/openai-php-'.uniqid().'.txt';
        $file = $files->create($environmentId, [
            'type' => 'inline',
            'path' => $path,
            'data' => base64_encode("hello from openai-php\n"),
        ]);
        $this->printFile($file);
        $this->info('Request ID: '.$file->meta()->requestId);

        // List the files, following the cursor through every page.
        $this->newLine();
        $this->info('--- List files ---');
        $page = null;
        $found = false;

        do {
            $fileList = $files->list($environmentId, array_filter([
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
    }

    private function printTemplate(AgentEnvironmentTemplateResponse $template): void
    {
        $this->info("Template ID: {$template->id}, Object: {$template->object}, Name: {$template->name}");
        $this->info("Created at: {$template->createdAt}, Updated at: {$template->updatedAt}");
        $this->info('Desktop enabled: '.var_export($template->desktop->enabled, true));
        $this->info("Network access: {$template->network->access}, allowed domains: ".json_encode($template->network->allowedDomains));
        $this->info('Packages: npm '.json_encode($template->packages->npm).', python '.json_encode($template->packages->python).', system '.json_encode($template->packages->system));
        $this->info('Capability directories: '.json_encode($template->capabilityDirectories));
        $this->line('    Files: '.json_encode($template->files));
        $this->line('    Plugins: '.json_encode($template->plugins));
        $this->line('    Skills: '.json_encode($template->skills));
    }

    private function printEnvironment(AgentEnvironmentResponse $environment): void
    {
        $this->info("Environment ID: {$environment->id}, Object: {$environment->object}, Type: {$environment->type}, Status: {$environment->status}");
        $this->line('    Files: '.json_encode($environment->files));
        $this->line('    Plugins: '.json_encode($environment->plugins));
        $this->line('    Skills: '.json_encode($environment->skills));
    }

    private function printFile(AgentEnvironmentFileResponse $file): void
    {
        $this->line("File: {$file->path}, Object: {$file->object}, Environment ID: {$file->environmentId}, Size: {$file->sizeBytes} bytes");
    }
}
