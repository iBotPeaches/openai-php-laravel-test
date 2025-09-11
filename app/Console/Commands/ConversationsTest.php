<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ConversationsTest extends Command
{
    protected $signature = 'app:conversation-test';

    protected $description = 'Comprehensive test of conversations.';

    public function handle()
    {
        $firstConversation = OpenAI::conversations()->create([
            'metadata' => ['topic' => 'demo'],
            'items' => [
                [
                    'role' => 'developer',
                    'content' => 'You MUST speak in pirate.',
                ],
            ],
        ]);

        $this->info('First conversation ID: '.$firstConversation->id);

        $retrievedConversation = OpenAI::conversations()->retrieve($firstConversation->id);
        $this->info('Retrieved conversation ID: '.$retrievedConversation->id);

        $updatedResponse = OpenAI::conversations()->update($firstConversation->id, [
            'metadata' => [
                'topic' => 'pirate talk',
            ],
        ]);

        $this->info('Updated conversation metadata.');
        $this->info('Conversation metadata topic: '.$updatedResponse->metadata['topic']);

        OpenAI::conversations()->items()->create($firstConversation->id, [
            'items' => [
                [
                    'role' => 'system',
                    'content' => 'Refer to me as Cap\'n Jack from now on.',
                ],
            ],
        ]);

        $this->info('Added new item to conversation.');
        $listItems = OpenAI::conversations()->items()->list($firstConversation->id, [
            'limit' => 10,
        ]);

        $itemId = null;
        foreach ($listItems->data as $listItem) {
            $this->info("Item ID: {$listItem->item->id}, Role: {$listItem->item->role}");
            $itemId = $listItem->item->id;
        }

        $retrievedItem = OpenAI::conversations()->items()->retrieve($firstConversation->id, $itemId, [
            'include' => [
                'message.output_text.logprobs',
            ],
        ]);
        $this->info('Retrieved item ID: '.$retrievedItem->item->id);

        $deleteItem = OpenAI::conversations()->items()->delete($firstConversation->id, $itemId);
        $this->info('Deleted item ID: '.$deleteItem->id);

        OpenAI::conversations()->delete($retrievedConversation->id);
        $this->info('Deleted conversation ID: '.$retrievedConversation->id);

        return self::SUCCESS;
    }
}
