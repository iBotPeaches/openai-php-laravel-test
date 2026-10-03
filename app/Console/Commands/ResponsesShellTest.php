<?php

namespace App\Console\Commands;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\ListInputItems;
use OpenAI\Responses\Responses\Output\OutputShellCall;
use OpenAI\Responses\Responses\Output\OutputShellCallAction;
use OpenAI\Responses\Responses\Output\OutputShellCallOutput;
use OpenAI\Responses\Responses\Output\ShellCallOutcome\OutputShellCallOutcomeExit;
use OpenAI\Responses\Responses\RetrieveResponse;
use OpenAI\Responses\Responses\Streaming\OutputItem;
use OpenAI\Responses\Responses\Streaming\Response as StreamingResponse;
use OpenAI\Responses\Responses\Streaming\ShellCallCommand;
use OpenAI\Responses\Responses\Streaming\ShellCallCommandDelta;
use OpenAI\Responses\Responses\Streaming\ShellCallOutputContentDelta;
use OpenAI\Responses\Responses\Streaming\ShellCallOutputContentDone;
use OpenAI\Responses\Responses\Tool\ShellTool;
use OpenAI\Responses\StreamResponse;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ResponsesShellTest extends Command
{
    protected $signature = 'app:responses-shell-test
        {--environment=container_auto : Where the shell runs: container_auto (hosted by OpenAI) or local (this machine)}
        {--skill= : Attach the word count skill as reference or inline (container_auto), or as local (local)}
        {--stream : Stream the response to watch the shell_call events (container_auto only)}
        {--model=gpt-5.4 : The model to use}
        {--input= : Override the prompt}
        {--timeout-after= : Cap local commands at this many seconds, to force a timeout outcome}
        {--force : Run the commands the model asks for without confirming (local only)}';

    protected $description = 'Test the Responses API shell tool: hosted containers, local execution, attached skills and streaming.';

    private const SKILL = 'word-count';

    private const DESCRIPTION = 'Count the words in a text file.';

    private const TURNS = 5;

    private ?string $skillId = null;

    public function handle(ClientContract $client): int
    {
        $environment = (string) $this->option('environment');
        $skill = $this->option('skill') === null ? null : (string) $this->option('skill');

        if (! in_array($environment, ['container_auto', 'local'], true)) {
            $this->error('The --environment option takes container_auto or local.');

            return self::INVALID;
        }

        $allowed = $environment === 'local' ? ['local'] : ['reference', 'inline'];

        if ($skill !== null && ! in_array($skill, $allowed, true)) {
            $this->error("The {$environment} environment only accepts --skill=".implode(' or --skill=', $allowed).'.');

            return self::INVALID;
        }

        if ($this->option('stream') && $environment === 'local') {
            $this->error('Local execution needs a complete shell_call before it can run anything, so --stream only works with container_auto.');

            return self::INVALID;
        }

        $workingDirectory = storage_path('app/shell-'.uniqid());
        mkdir($workingDirectory, 0755, true);

        try {
            $tool = $this->tool($client, $environment, $skill, $workingDirectory);

            $this->info('Requesting tool:');
            $this->line($this->json($tool));

            $parameters = [
                'model' => (string) $this->option('model'),
                'tools' => [$tool],
                'input' => $this->prompt($environment, $skill, $workingDirectory),
            ];

            $response = $this->option('stream')
                ? $this->stream($parameters)
                : $this->create($client, $parameters);

            if ($environment === 'local' && $response instanceof CreateResponse) {
                $response = $this->runShellCalls($client, $response, $parameters, $workingDirectory);
            }

            if ($response instanceof CreateResponse) {
                $this->verify($client, $response->id);
            }
        } finally {
            $this->cleanup($client, $workingDirectory);
        }

        return self::SUCCESS;
    }

    /**
     * Build the shell tool definition, uploading or writing the skill it points at.
     *
     * @return array<string, mixed>
     */
    private function tool(ClientContract $client, string $environment, ?string $skill, string $workingDirectory): array
    {
        if ($environment === 'local') {
            return [
                'type' => 'shell',
                'environment' => array_filter([
                    'type' => 'local',
                    'skills' => $skill === null ? null : [
                        [
                            'name' => self::SKILL,
                            'description' => self::DESCRIPTION,
                            'path' => $this->writeSkill($workingDirectory),
                        ],
                    ],
                ], fn (mixed $value): bool => $value !== null),
            ];
        }

        return [
            'type' => 'shell',
            'environment' => array_filter([
                'type' => 'container_auto',
                'memory_limit' => '1g',
                'skills' => match ($skill) {
                    'reference' => [
                        ['type' => 'skill_reference', 'skill_id' => $this->uploadSkill($client, $workingDirectory)],
                    ],
                    'inline' => [
                        [
                            'type' => 'inline',
                            'name' => self::SKILL,
                            'description' => self::DESCRIPTION,
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'application/zip',
                                'data' => base64_encode((string) file_get_contents($this->bundleSkill($workingDirectory))),
                            ],
                        ],
                    ],
                    default => null,
                },
            ], fn (mixed $value): bool => $value !== null),
        ];
    }

    private function prompt(string $environment, ?string $skill, string $workingDirectory): string
    {
        if ($override = $this->option('input')) {
            return (string) $override;
        }

        if ($environment === 'local') {
            $this->writeSample($workingDirectory);

            return $skill === null
                ? "Your shell runs in {$workingDirectory}. Use it to run `pwd` and `ls -a`, then tell me what is in that directory."
                : "Your shell runs in {$workingDirectory}, which holds sample.txt. Use the ".self::SKILL.' skill from the local environment to count the words in sample.txt, then report the JSON it printed.';
        }

        return $skill === null
            ? 'Use the shell to run `uname -sm` and print the first line of /etc/os-release, then tell me what the sandbox is running.'
            : 'Write "one two three four five" into /mnt/data/sample.txt with the shell, then use the '.self::SKILL.' skill to count the words in that file and report the JSON it printed.';
    }

    private function create(ClientContract $client, array $parameters): CreateResponse
    {
        $response = $this->eventually(fn (): CreateResponse => $client->responses()->create($parameters));

        $this->report($response);

        return $response;
    }

    /**
     * Stream the response, printing every shell event the client now parses.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function stream(array $parameters): ?CreateResponse
    {
        $completed = null;

        $stream = $this->eventually(fn (): StreamResponse => $this->streamingClient()->responses()->createStreamed($parameters));

        foreach ($stream as $event) {
            $payload = $event->response;

            $shellEvent = $payload instanceof ShellCallCommand
                || $payload instanceof ShellCallCommandDelta
                || $payload instanceof ShellCallOutputContentDelta
                || $payload instanceof ShellCallOutputContentDone;

            if ($shellEvent) {
                $this->comment($event->event.' -> '.class_basename($payload));
                $this->line($this->json($payload->toArray()));

                continue;
            }

            if ($payload instanceof OutputItem && ($payload->item instanceof OutputShellCall || $payload->item instanceof OutputShellCallOutput)) {
                $this->comment($event->event.' -> '.class_basename($payload->item));
                $this->line($this->json($payload->item->toArray()));

                continue;
            }

            $this->line($event->event);

            if ($payload instanceof StreamingResponse && $event->event === 'response.completed') {
                $completed = $payload->response;
            }
        }

        if ($completed instanceof CreateResponse) {
            $this->report($completed);
        }

        return $completed;
    }

    /**
     * A freshly uploaded skill is not readable right away, so retry while the service says it is missing.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $call
     * @return TResult
     */
    private function eventually(callable $call): mixed
    {
        $attempts = 5;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $call();
            } catch (ErrorException $e) {
                if ($attempt === $attempts || ! str_contains($e->getMessage(), 'Skill')) {
                    throw $e;
                }

                $this->comment("The uploaded skill is not readable yet, retrying (attempt {$attempt} of {$attempts})...");
                sleep(2);
            }
        }
    }

    /**
     * The container binds a PSR-18 bridge that cannot stream, so SSE needs a Guzzle backed client.
     */
    private function streamingClient(): ClientContract
    {
        return \OpenAI::factory()
            ->withApiKey((string) config('openai.api_key'))
            ->withOrganization(config('openai.organization'))
            ->withHttpClient(new GuzzleClient(['timeout' => (int) config('openai.request_timeout', 120)]))
            ->make();
    }

    /**
     * Run the commands the model asked for and hand the results back as shell_call_output items.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function runShellCalls(ClientContract $client, CreateResponse $response, array $parameters, string $workingDirectory): CreateResponse
    {
        for ($turn = 1; $turn <= self::TURNS; $turn++) {
            $calls = array_values(array_filter(
                $response->output,
                fn (mixed $item): bool => $item instanceof OutputShellCall,
            ));

            if ($calls === []) {
                return $response;
            }

            if (! $this->option('force') && ! $this->confirm('Run the shell call(s) above in '.$workingDirectory.'?', true)) {
                $this->warn('Stopped before running anything locally.');

                return $response;
            }

            $input = [];

            foreach ($calls as $call) {
                $input[] = [
                    'type' => 'shell_call_output',
                    'call_id' => $call->callId,
                    'output' => array_map(
                        fn (string $command): array => $this->runLocally($command, $call->action, $workingDirectory),
                        $call->action->commands,
                    ),
                ];
            }

            $this->info('Sending back:');
            $this->line($this->json($input));

            $response = $this->create($client, [
                ...$parameters,
                'input' => $input,
                'previous_response_id' => $response->id,
            ]);
        }

        $this->warn('Gave up after '.self::TURNS.' shell turns.');

        return $response;
    }

    /**
     * @return array{stdout: string, stderr: string, outcome: array{type: string, exit_code?: int}}
     */
    private function runLocally(string $command, OutputShellCallAction $action, string $workingDirectory): array
    {
        $this->comment("$ {$command}");

        $timeout = $this->option('timeout-after') !== null
            ? (float) $this->option('timeout-after')
            : ($action->timeoutMs ?? 30_000) / 1000;

        $process = Process::fromShellCommandline($command, $workingDirectory, timeout: $timeout);

        try {
            $process->run();
            $outcome = ['type' => 'exit', 'exit_code' => $process->getExitCode() ?? 0];
        } catch (ProcessTimedOutException) {
            $outcome = ['type' => 'timeout'];
        }

        $limit = $action->maxOutputLength ?? 4096;

        return [
            'stdout' => substr($process->getOutput(), 0, $limit),
            'stderr' => substr($process->getErrorOutput(), 0, $limit),
            'outcome' => $outcome,
        ];
    }

    /**
     * Retrieve the stored response and its input items, so the parsers and toArray() see real payloads.
     */
    private function verify(ClientContract $client, string $id): void
    {
        $retrieved = $client->responses()->retrieve($id);

        $this->newLine();
        $this->info("Retrieved {$retrieved->id}:");

        foreach ($retrieved->tools as $tool) {
            if ($tool instanceof ShellTool) {
                $this->line('shell tool: '.$this->json($tool->toArray(), false));
            }
        }

        $this->roundTrip('retrieved response', $retrieved->toArray(), RetrieveResponse::from($retrieved->toArray(), $retrieved->meta())->toArray());

        $items = $client->responses()->list($id, ['limit' => 100]);

        $shellItems = array_filter(
            $items->data,
            fn (mixed $item): bool => $item instanceof OutputShellCall || $item instanceof OutputShellCallOutput,
        );

        $this->line('stored input items: '.count($items->data).' parsed, '.count($shellItems).' of them shell items');

        foreach ($shellItems as $item) {
            $this->line($this->json($item->toArray(), false));
        }

        $this->roundTrip('stored input items', $items->toArray(), ListInputItems::from($items->toArray(), $items->meta())->toArray());
    }

    /**
     * @param  array<mixed>  $original
     * @param  array<mixed>  $reparsed
     */
    private function roundTrip(string $subject, array $original, array $reparsed): void
    {
        if ($original === $reparsed) {
            $this->info("The {$subject} survives a toArray() round trip.");

            return;
        }

        $this->error("The {$subject} does NOT survive a toArray() round trip.");
        $this->line('before: '.$this->json($original, false));
        $this->line('after:  '.$this->json($reparsed, false));
    }

    private function report(CreateResponse $response): void
    {
        $this->newLine();
        $this->info("Response {$response->id} ({$response->status}) from {$response->model}");

        foreach ($response->output as $item) {
            if ($item instanceof OutputShellCall) {
                $this->comment("shell_call {$item->callId} ({$item->status})");

                foreach ($item->action->commands as $index => $command) {
                    $this->line("  command[{$index}]: {$command}");
                }

                $this->line('  max_output_length: '.($item->action->maxOutputLength ?? 'null').', timeout_ms: '.($item->action->timeoutMs ?? 'null'));
                $this->line('  environment: '.($item->environment === null ? 'null' : $this->json($item->environment->toArray(), false)));

                continue;
            }

            if ($item instanceof OutputShellCallOutput) {
                $this->comment("shell_call_output {$item->callId} ({$item->status})");

                foreach ($item->output as $index => $output) {
                    $outcome = $output->outcome instanceof OutputShellCallOutcomeExit
                        ? "exit {$output->outcome->exitCode}"
                        : $output->outcome->type;

                    $this->line("  output[{$index}]: outcome {$outcome}");
                    $this->line('    stdout: '.$this->oneLine($output->stdout));
                    $this->line('    stderr: '.$this->oneLine($output->stderr));
                }

                continue;
            }

            $this->line($item->type);
        }

        if (($response->outputText ?? '') !== '') {
            $this->newLine();
            $this->info('Output text: '.$response->outputText);
        }
    }

    /**
     * Upload the skill bundle so the container can mount it by id.
     */
    private function uploadSkill(ClientContract $client, string $workingDirectory): string
    {
        $skill = $client->skills()->create([
            'files' => fopen($this->bundleSkill($workingDirectory), 'r'),
        ]);

        $this->skillId = $skill->id;
        $this->info("Uploaded skill {$skill->id} ({$skill->name}), version {$skill->defaultVersion}.");

        return $skill->id;
    }

    /**
     * Zip the skill, keeping every file under one top-level directory as the API requires.
     */
    private function bundleSkill(string $workingDirectory): string
    {
        $bundle = $workingDirectory.'/'.self::SKILL.'.zip';

        $zip = new ZipArchive;
        $zip->open($bundle, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(self::SKILL.'/SKILL.md', $this->skillMarkdown());
        $zip->addFromString(self::SKILL.'/scripts/count_words.py', $this->skillScript());
        $zip->close();

        return $bundle;
    }

    /**
     * Lay the same skill out on disk for the local environment, which points at a path.
     */
    private function writeSkill(string $workingDirectory): string
    {
        $directory = $workingDirectory.'/'.self::SKILL;
        mkdir($directory.'/scripts', 0755, true);
        file_put_contents($directory.'/SKILL.md', $this->skillMarkdown());
        file_put_contents($directory.'/scripts/count_words.py', $this->skillScript());

        return $directory;
    }

    private function writeSample(string $workingDirectory): void
    {
        file_put_contents($workingDirectory.'/sample.txt', 'one two three four five'.PHP_EOL);
    }

    private function skillMarkdown(): string
    {
        $name = self::SKILL;
        $description = self::DESCRIPTION;

        return <<<MARKDOWN
        ---
        name: {$name}
        description: {$description}
        ---

        # Word count

        Run `python3 scripts/count_words.py <file>` from this skill directory to count the
        words in a text file. It prints JSON, for example `{"skill": "word-count", "word_count": 5}`.
        MARKDOWN;
    }

    private function skillScript(): string
    {
        return <<<'PYTHON'
        import json
        import sys

        with open(sys.argv[1]) as handle:
            words = handle.read().split()

        print(json.dumps({"skill": "word-count", "word_count": len(words)}))
        PYTHON;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function json(array $value, bool $pretty = true): string
    {
        return (string) json_encode($value, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES);
    }

    private function oneLine(string $value): string
    {
        $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);

        return strlen($value) > 500 ? substr($value, 0, 500).'...' : $value;
    }

    private function cleanup(ClientContract $client, string $workingDirectory): void
    {
        if ($this->skillId !== null) {
            $deleted = $client->skills()->delete($this->skillId);
            $this->info("Deleted skill {$deleted->id}: ".var_export($deleted->deleted, true));
            $this->skillId = null;
        }

        $this->remove($workingDirectory);
    }

    private function remove(string $path): void
    {
        foreach (glob($path.'/{,.}*', GLOB_BRACE) ?: [] as $child) {
            if (in_array(basename($child), ['.', '..'], true)) {
                continue;
            }

            is_dir($child) ? $this->remove($child) : unlink($child);
        }

        rmdir($path);
    }
}
