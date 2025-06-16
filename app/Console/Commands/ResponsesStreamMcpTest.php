<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesStreamMcpTest extends Command
{
    protected $signature = 'app:responses-stream-mcp-test';
    protected $description = 'Test for Model Context Protocol (MCP)';

    public function handle(): int
    {
        $stream = OpenAI::responses()->createStreamed([
            'model' => 'gpt-4.1',
            'tools' => [
                [
                    'type' => 'mcp',
                    'server_label' => 'deepwiki',
                    'server_url' => 'https://mcp.deepwiki.com/mcp',
                    'require_approval' => 'never'
                ]
            ],
            'input' => 'What transport protocols are supported in the 2025-03-26 version of the MCP spec?'
        ]);

        foreach ($stream as $event) {
            $this->line(json_encode($event->toArray(), JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
