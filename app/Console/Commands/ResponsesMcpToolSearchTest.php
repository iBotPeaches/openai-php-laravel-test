<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesMcpToolSearchTest extends Command
{
    protected $signature = 'app:responses-mcp-tool-search-test';

    protected $description = 'Test for Model Context Protocol (MCP) Tool Search';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-5.5',
            'tools' => [
                [
                    'type' => 'namespace',
                    'name' => 'crm',
                    'description' => 'CRM tools for customer lookup and order management.',
                    'tools' => [
                        [
                            'type' => 'function',
                            'name' => 'list_open_orders',
                            'description' => 'List open orders for a customer ID.',
                            'defer_loading' => true,
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'customer_id' => ['type' => 'string'],
                                ],
                                'required' => ['customer_id'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'tool_search',
                ],
            ],
            'input' => 'List open orders for customer ID 12345',
        ]);

        $this->line(json_encode($response->toArray(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
