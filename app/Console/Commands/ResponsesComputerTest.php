<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\Input\ComputerToolCallOutput;
use OpenAI\Responses\Responses\ListInputItems;
use OpenAI\Responses\Responses\Output\OutputComputerToolCall;
use OpenAI\Responses\Responses\RetrieveResponse;
use OpenAI\Responses\Responses\Tool\ComputerTool;
use OpenAI\Responses\Responses\Tool\ComputerUseTool;

class ResponsesComputerTest extends Command
{
    protected $signature = 'app:responses-computer-test
        {--tool=computer : The tool to request: computer (batched actions) or computer_use_preview (one action per call)}
        {--model= : The model to use (defaults to gpt-5.4 for computer, computer-use-preview for computer_use_preview)}
        {--input= : Override the prompt}
        {--turns=3 : How many computer_call turns to answer with a screenshot}';

    protected $description = 'Test the Responses API computer tool: batched actions, the preview tool, screenshot replies and round trips.';

    private const WIDTH = 1024;

    private const HEIGHT = 768;

    public function handle(ClientContract $client): int
    {
        $tool = (string) $this->option('tool');

        if (! in_array($tool, ['computer', 'computer_use_preview'], true)) {
            $this->error('The --tool option takes computer or computer_use_preview.');

            return self::INVALID;
        }

        $definition = $tool === 'computer'
            ? ['type' => 'computer']
            : ['type' => 'computer_use_preview', 'display_width' => self::WIDTH, 'display_height' => self::HEIGHT, 'environment' => 'browser'];

        $this->info('Requesting tool:');
        $this->line($this->json($definition));

        $parameters = array_filter([
            'model' => (string) ($this->option('model') ?? ($tool === 'computer' ? 'gpt-5.4' : 'computer-use-preview')),
            'tools' => [$definition],
            'truncation' => $tool === 'computer_use_preview' ? 'auto' : null,
            'input' => (string) ($this->option('input') ?? 'Take a screenshot, then type "hello world" into the search box and press Enter.'),
        ], fn (mixed $value): bool => $value !== null);

        $response = $client->responses()->create($parameters);
        $this->report($response);

        $turns = max(0, (int) $this->option('turns'));

        for ($turn = 1; $turn <= $turns; $turn++) {
            $calls = array_values(array_filter(
                $response->output,
                fn (mixed $item): bool => $item instanceof OutputComputerToolCall,
            ));

            if ($calls === []) {
                break;
            }

            // Pretend we ran every action and hand back the same fake screen.
            $input = array_map(fn (OutputComputerToolCall $call): array => array_filter([
                'type' => 'computer_call_output',
                'call_id' => $call->callId,
                'acknowledged_safety_checks' => $call->pendingSafetyChecks === [] ? null : array_map(
                    fn ($check): array => $check->toArray(),
                    $call->pendingSafetyChecks,
                ),
                'output' => [
                    'type' => 'computer_screenshot',
                    'image_url' => 'data:image/png;base64,'.base64_encode($this->screenshot($turn)),
                ],
            ], fn (mixed $value): bool => $value !== null), $calls);

            $this->info("Sending back screenshot for turn {$turn} (".count($calls).' call(s)).');

            $response = $client->responses()->create([
                ...$parameters,
                'input' => $input,
                'previous_response_id' => $response->id,
            ]);

            $this->report($response);
        }

        $this->verify($client, $response->id);

        return self::SUCCESS;
    }

    private function report(CreateResponse $response): void
    {
        $this->newLine();
        $this->info("Response {$response->id} ({$response->status}) from {$response->model}");

        foreach ($response->tools as $tool) {
            $this->line('  tool: '.class_basename($tool).' '.$this->json($tool->toArray(), false));
        }

        foreach ($response->output as $item) {
            if ($item instanceof OutputComputerToolCall) {
                $this->comment("computer_call {$item->callId} ({$item->status}), ".count($item->actions).' action(s), '.count($item->pendingSafetyChecks).' pending safety check(s)');

                foreach ($item->actions as $index => $action) {
                    $this->line("  actions[{$index}]: ".class_basename($action).' '.$this->json($action->toArray(), false));
                }

                foreach ($item->pendingSafetyChecks as $check) {
                    $this->warn('  safety check: '.$this->json($check->toArray(), false));
                }

                $this->line('  toArray(): '.$this->json($item->toArray(), false));

                continue;
            }

            $this->line($item->type);
        }

        if (($response->outputText ?? '') !== '') {
            $this->info('Output text: '.$response->outputText);
        }
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
            if ($tool instanceof ComputerTool || $tool instanceof ComputerUseTool) {
                $this->line(class_basename($tool).': '.$this->json($tool->toArray(), false));
            }
        }

        $this->roundTrip('retrieved response', $retrieved->toArray(), RetrieveResponse::from($retrieved->toArray(), $retrieved->meta())->toArray());

        $items = $client->responses()->list($id, ['limit' => 100]);

        $computerItems = array_filter(
            $items->data,
            fn (mixed $item): bool => $item instanceof OutputComputerToolCall || $item instanceof ComputerToolCallOutput,
        );

        $this->line('stored input items: '.count($items->data).' parsed, '.count($computerItems).' of them computer items');

        foreach ($computerItems as $item) {
            $array = $item->toArray();

            if (isset($array['output']['image_url'])) {
                $array['output']['image_url'] = substr($array['output']['image_url'], 0, 40).'...';
            }

            $this->line(class_basename($item).' '.$this->json($array, false));
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

    /**
     * Draw a fake browser window with a search box, so the model has something to act on.
     */
    private function screenshot(int $turn): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $white = imagecolorallocate($image, 255, 255, 255);
        $grey = imagecolorallocate($image, 220, 220, 220);
        $dark = imagecolorallocate($image, 40, 40, 40);
        $blue = imagecolorallocate($image, 30, 100, 220);

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, self::WIDTH, 60, $grey);
        imagestring($image, 5, 20, 22, 'https://example.test/search', $dark);

        imagestring($image, 5, 412, 260, 'Example Search (turn '.$turn.')', $dark);
        imagerectangle($image, 262, 320, 762, 360, $dark);
        imagestring($image, 4, 272, 332, 'Search...', $grey);
        imagefilledrectangle($image, 452, 390, 572, 430, $blue);
        imagestring($image, 5, 487, 402, 'Search', $white);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<mixed>  $value
     */
    private function json(array $value, bool $pretty = true): string
    {
        return (string) json_encode($value, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES);
    }
}
