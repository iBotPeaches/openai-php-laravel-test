<?php

namespace App\Console\Commands;

use App\Console\Concerns\RetriesUntilReady;
use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Vaults\Credentials\VaultCredentialResponse;
use OpenAI\Responses\Vaults\VaultResponse;

class VaultsTest extends Command
{
    use RetriesUntilReady;

    protected $signature = 'app:vaults-test';

    protected $description = 'Test the Vaults API (beta): vaults and credentials of every auth type.';

    public function handle(ClientContract $client): int
    {
        $vaults = $client->vaults();
        $credentials = $vaults->credentials();

        // Create a vault.
        $vault = $vaults->create([
            'name' => 'Test Vault '.uniqid(),
            'metadata' => ['source' => 'openai-php-laravel-test'],
        ]);

        try {
            $this->info('--- Create vault ---');
            $this->printVault($vault);
            $this->info('Request ID: '.$vault->meta()->requestId);

            // List vaults.
            $this->newLine();
            $this->info('--- List vaults ---');
            $vaultList = $this->eventually(
                fn () => $vaults->list(['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->id === $vault->id),
            );

            $this->info("Listed {$vaultList->object} of ".count($vaultList->data).' vault(s) (has more: '.var_export($vaultList->hasMore, true).')');
            $this->info("First ID: {$vaultList->firstId}, Last ID: {$vaultList->lastId}");
            foreach ($vaultList->data as $listedVault) {
                $this->line("Vault ID: {$listedVault->id}, Name: {$listedVault->name}");
            }

            // Retrieve the vault.
            $this->newLine();
            $this->info('--- Retrieve vault ---');
            $this->printVault($this->eventually(fn () => $vaults->retrieve($vault->id)));

            // Update the vault.
            $this->newLine();
            $this->info('--- Update vault ---');
            $this->printVault($vaults->update($vault->id, [
                'name' => $vault->name.' (renamed)',
            ]));

            // Create one credential of each auth type.
            $this->newLine();
            $this->info('--- Create credentials ---');
            $created = [];

            foreach ($this->credentialPayloads() as $payload) {
                $credential = $credentials->create($vault->id, $payload);
                $created[] = $credential;
                $this->printCredential($credential);
            }

            // List credentials.
            $this->newLine();
            $this->info('--- List credentials ---');
            $credentialList = $this->eventually(
                fn () => $credentials->list($vault->id, ['limit' => 100]),
                fn ($list) => count($list->data) >= count($created),
            );

            $this->info("Listed {$credentialList->object} of ".count($credentialList->data).' credential(s) (has more: '.var_export($credentialList->hasMore, true).')');
            $this->info("First ID: {$credentialList->firstId}, Last ID: {$credentialList->lastId}");
            foreach ($credentialList->data as $listedCredential) {
                $this->line("Credential ID: {$listedCredential->id}, Name: {$listedCredential->name}, Type: {$listedCredential->auth['type']}");
            }

            // Retrieve each credential.
            $this->newLine();
            $this->info('--- Retrieve credentials ---');
            foreach ($created as $credential) {
                $this->printCredential($this->eventually(fn () => $credentials->retrieve($vault->id, $credential->id)));
            }

            // Rotate the static bearer token.
            $this->newLine();
            $this->info('--- Update credential ---');
            $updated = $credentials->update($vault->id, $created[0]->id, [
                'metadata' => ['type' => 'static_bearer', 'rotated' => 'true'],
                'auth' => [
                    'type' => 'static_bearer',
                    'token' => 'rotated-token-'.uniqid(),
                ],
            ]);
            $this->printCredential($updated);
            $this->line(json_encode($updated->toArray(), JSON_PRETTY_PRINT));

            // Delete each credential.
            $this->newLine();
            $this->info('--- Delete credentials ---');
            foreach ($created as $credential) {
                $deleted = $credentials->delete($vault->id, $credential->id);
                $this->info("Deleted credential ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
            }
        } finally {
            // Delete the vault.
            $this->newLine();
            $this->info('--- Delete vault ---');
            $deleted = $vaults->delete($vault->id);
            $this->info("Deleted vault ID: {$deleted->id}, Object: {$deleted->object}, Deleted: ".var_export($deleted->deleted, true));
        }

        return self::SUCCESS;
    }

    /**
     * One create payload per credential auth type. Secret values are write-only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function credentialPayloads(): array
    {
        return [
            [
                'name' => 'Docs MCP token',
                'metadata' => ['type' => 'static_bearer'],
                'auth' => [
                    'type' => 'static_bearer',
                    'mcp_server_url' => 'https://example.com/mcp',
                    'token' => 'secret-token-'.uniqid(),
                ],
            ],
            [
                'name' => 'Docs MCP OAuth',
                'metadata' => ['type' => 'mcp_oauth'],
                'auth' => [
                    'type' => 'mcp_oauth',
                    'mcp_server_url' => 'https://example.com/oauth-mcp',
                    'access_token' => 'access-token-'.uniqid(),
                    'refresh' => [
                        'client_id' => 'client-'.uniqid(),
                        'refresh_token' => 'refresh-token-'.uniqid(),
                        'token_endpoint' => 'https://example.com/oauth/token',
                        'token_endpoint_auth' => [
                            'type' => 'client_secret_post',
                            'client_secret' => 'client-secret-'.uniqid(),
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Billing API key',
                'metadata' => ['type' => 'environment_variable'],
                'auth' => [
                    'type' => 'environment_variable',
                    'secret_name' => 'BILLING_API_KEY',
                    'secret_value' => 'billing-key-'.uniqid(),
                    'networking' => [
                        'type' => 'limited',
                        'allowed_hosts' => ['api.billing.example.com'],
                    ],
                ],
            ],
        ];
    }

    private function printVault(VaultResponse $vault): void
    {
        $this->info("Vault ID: {$vault->id}, Object: {$vault->object}, Name: {$vault->name}");
        $this->info("Created at: {$vault->createdAt}, Metadata: ".json_encode($vault->metadata));
    }

    private function printCredential(VaultCredentialResponse $credential): void
    {
        $this->info("Credential ID: {$credential->id}, Object: {$credential->object}, Vault ID: {$credential->vaultId}");
        $this->info("Name: {$credential->name}, Created at: {$credential->createdAt}, Updated at: {$credential->updatedAt}");
        $this->info('Metadata: '.json_encode($credential->metadata));
        $this->line('    Auth: '.json_encode($credential->auth));
    }
}
