<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ContainerTest extends Command
{
    protected $signature = 'app:container-test';

    protected $description = 'Command description';

    public function handle(): int
    {
        // Make a container.
        $container = OpenAI::containers()->create([
            'name' => 'Test Container',
        ]);
        echo "Container created with ID: {$container->id}\n";

        // List containers
        $containers = OpenAI::containers()->list();
        echo "List of containers:\n";
        foreach ($containers->data as $c) {
            echo "Container ID: {$c->id}, Name: {$c->name}, Status: {$c->status}\n";
        }

        // Retrieve the container
        $retrievedContainer = OpenAI::containers()->retrieve($container->id);
        echo "Retrieved Container ID: {$retrievedContainer->id}, Name: {$retrievedContainer->name}, Status: {$retrievedContainer->status}\n";

        // Delete the container
        $deleteResponse = OpenAI::containers()->delete($container->id);
        echo "Container deleted with ID: {$deleteResponse->id}\n";

        return self::SUCCESS;
    }
}
