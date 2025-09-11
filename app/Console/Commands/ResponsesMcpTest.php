<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesMcpTest extends Command
{
    protected $signature = 'app:responses-mcp-test';

    protected $description = 'Test for Model Context Protocol (MCP)';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-4.1',
            'tools' => [
                [
                    'type' => 'mcp',
                    'server_label' => 'deepwiki',
                    'server_url' => 'https://mcp.deepwiki.com/mcp',
                    'require_approval' => 'never',
                ],
                [
                    'type' => 'mcp',
                    'server_label' => 'deploy-html',
                    'server_url' => 'https://remote.mcpservers.org/edgeone-pages/mcp',
                    'require_approval' => 'never',
                ],
            ],
            'input' => 'Use your method deploy-html to deploy this text "test""',
        ]);

        dd($response);

        return self::SUCCESS;
    }
}
