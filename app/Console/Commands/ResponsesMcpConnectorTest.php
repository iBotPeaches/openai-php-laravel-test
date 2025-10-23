<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesMcpConnectorTest extends Command
{
    protected $signature = 'app:responses-mcp-connector-test';

    protected $description = 'Test for Model Context Protocol (MCP) via Connectors';

    public function handle(): int
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-5',
            'tools' => [
                [
                    'type' => 'mcp',
                    'server_label' => 'Dropbox',
                    'connector_id' => 'connector_dropbox',
                    'authorization' => config('services.dropbox.api_key'),
                    'require_approval' => 'never',
                ],
            ],
            'input' => 'What slide decks do I have in-progress?',
        ]);

        dd($response);

        return self::SUCCESS;
    }
}
