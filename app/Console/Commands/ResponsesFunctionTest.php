<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesFunctionTest extends Command
{
    protected $signature = 'app:responses-function-test';

    protected $description = 'Test function calling with the responses endpoint.';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-4o-mini',
            'tools' => [
                [
                    'type' => 'function',
                    'name' => 'get_temperature',
                    'description' => 'Get the current temperature in a given location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The city and state, e.g. San Francisco, CA',
                            ],
                            'unit' => [
                                'type' => 'string',
                                'enum' => ['celsius', 'fahrenheit'],
                            ],
                        ],
                        'required' => ['location'],
                    ],
                ],
            ],
            'input' => 'What is the temperature in Rio Grande do Norte, Brazil?',
        ]);

        foreach ($response->output as $item) {
            if ($item->type === 'function_call') {
                $name = $item->name ?? null;
                $args = json_decode($item->arguments ?? '{}', true) ?: [];

                if ($name === 'get_temperature') {
                    dump($args);
                }
            }
        }

        dd($response);
    }
}
