<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ChatRequestFileAttachmentTest extends Command
{
    protected $signature = 'app:chat-request-file-attachment-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'user', 'content' => 'What is in this file?'],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'What is in this file? Please return json.'],
                        ['type' => 'file', 'file' => [
                            'filename' => 'random.pdf',
                            // https://stackoverflow.com/a/66905260/455008
                            'file_data' => 'data:application/pdf;base64,JVBERi0xLjIgCjkgMCBvYmoKPDwKPj4Kc3RyZWFtCkJULyAzMiBUZiggIFlPVVIgVEVYVCBIRVJFICAgKScgRVQKZW5kc3RyZWFtCmVuZG9iago0IDAgb2JqCjw8Ci9UeXBlIC9QYWdlCi9QYXJlbnQgNSAwIFIKL0NvbnRlbnRzIDkgMCBSCj4+CmVuZG9iago1IDAgb2JqCjw8Ci9LaWRzIFs0IDAgUiBdCi9Db3VudCAxCi9UeXBlIC9QYWdlcwovTWVkaWFCb3ggWyAwIDAgMjUwIDUwIF0KPj4KZW5kb2JqCjMgMCBvYmoKPDwKL1BhZ2VzIDUgMCBSCi9UeXBlIC9DYXRhbG9nCj4+CmVuZG9iagp0cmFpbGVyCjw8Ci9Sb290IDMgMCBSCj4+CiUlRU9G',
                        ],
                        ],
                    ]],
            ],
        ]);

        dd($response);
    }
}
