<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use RuntimeException;
use ZipArchive;

class SkillsTest extends Command
{
    protected $signature = 'app:skills-test';

    protected $description = 'Comprehensive test of skills and skill versions.';

    public function handle(ClientContract $client): int
    {
        $skills = $client->skills();
        $versions = $skills->versions();

        $name = 'basic-math-'.uniqid();
        $workingDirectory = storage_path('app/'.$name);
        mkdir($workingDirectory, 0755, true);

        try {
            // Create a skill by uploading a zip bundle.
            $skill = $skills->create([
                'files' => $this->zipUpload($workingDirectory, $name, 'Add or multiply numbers.'),
            ]);

            $this->info("Created skill ID: {$skill->id}");
            $this->info("Name: {$skill->name}, Description: {$skill->description}");
            $this->info("Default version: {$skill->defaultVersion}, Latest version: {$skill->latestVersion}");

            // List skills.
            $skillList = $this->eventually(
                fn () => $skills->list(['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->id === $skill->id),
            );

            $this->info("Listed {$skillList->object} of skills (has more: ".var_export($skillList->hasMore, true).')');
            foreach ($skillList->data as $listedSkill) {
                $this->info("Skill ID: {$listedSkill->id}, Name: {$listedSkill->name}, Latest version: {$listedSkill->latestVersion}");
            }

            // Retrieve the skill.
            $retrievedSkill = $this->eventually(fn () => $skills->retrieve($skill->id));
            $this->info("Retrieved skill ID: {$retrievedSkill->id}, Created at: {$retrievedSkill->createdAt}");

            // Create a second, immutable version.
            $version = $versions->create($skill->id, [
                'files' => $this->zipUpload($workingDirectory, $name, 'Add, multiply or divide numbers.'),
            ]);

            $this->info("Created skill version: {$version->version} (ID: {$version->id}) for skill {$version->skillId}");
            $this->info("Version description: {$version->description}");

            // List versions of the skill.
            $versionList = $this->eventually(
                fn () => $versions->list($skill->id, ['limit' => 100]),
                fn ($list) => collect($list->data)->contains(fn ($listed) => $listed->version === $version->version),
            );

            $this->info('Listed '.count($versionList->data)." version(s), first: {$versionList->firstId}, last: {$versionList->lastId}");
            foreach ($versionList->data as $listedVersion) {
                $this->info("Version: {$listedVersion->version}, ID: {$listedVersion->id}, Name: {$listedVersion->name}");
            }

            // Retrieve a single version.
            $retrievedVersion = $this->eventually(fn () => $versions->retrieve($skill->id, $version->version));
            $this->info("Retrieved version: {$retrievedVersion->version}, Object: {$retrievedVersion->object}");

            // Download the zip bundle of that version.
            $versionContent = $this->eventually(fn () => $versions->content($skill->id, $version->version));
            $this->info('Downloaded version bundle of '.strlen($versionContent).' bytes.');

            // Point the default version at the version we just created.
            $updatedSkill = $skills->update($skill->id, ['default_version' => $version->version]);
            $this->info("Updated default version to: {$updatedSkill->defaultVersion}");

            // Download the zip bundle of the default version.
            $skillContent = $this->eventually(fn () => $skills->content($skill->id));
            $this->info('Downloaded skill bundle of '.strlen($skillContent).' bytes.');

            // Delete the version, but first move the default pointer off it.
            $skills->update($skill->id, ['default_version' => '1']);
            $deletedVersion = $this->eventually(fn () => $versions->delete($skill->id, $version->version));
            $this->info("Deleted version: {$deletedVersion->version}, Deleted: ".var_export($deletedVersion->deleted, true));

            // Delete the skill.
            $deletedSkill = $this->eventually(fn () => $skills->delete($skill->id));
            $this->info("Deleted skill ID: {$deletedSkill->id}, Deleted: ".var_export($deletedSkill->deleted, true));
        } finally {
            $this->cleanup($workingDirectory);
        }

        return self::SUCCESS;
    }

    /**
     * Retry a read until it succeeds, because reads that follow a write are eventually consistent.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $read
     * @param  (callable(TResult): bool)|null  $ready
     * @return TResult
     */
    private function eventually(callable $read, ?callable $ready = null): mixed
    {
        $attempts = 5;
        $ready ??= static fn (): bool => true;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $read();

                if ($ready($result)) {
                    return $result;
                }
            } catch (ErrorException $e) {
                if ($attempt === $attempts) {
                    throw $e;
                }
            }

            $this->comment("Waiting for the skill to become readable (attempt {$attempt} of {$attempts})...");
            sleep(1);
        }

        throw new RuntimeException('The skill never became readable.');
    }

    /**
     * Bundle a skill into a zip and return an open handle to it.
     *
     * The API requires every file to live under a single top-level directory. The client sends
     * multipart filenames as a plain basename, so that prefix only survives inside a zip - a
     * file-per-file directory upload cannot express it.
     *
     * @return resource
     */
    private function zipUpload(string $directory, string $name, string $description)
    {
        $skill = <<<MARKDOWN
        ---
        name: {$name}
        description: {$description}
        ---

        # Basic math

        Use `math.py` to add or multiply two numbers.

        ```
        python math.py add 2 3
        python math.py multiply 2 3
        ```
        MARKDOWN;

        $script = <<<'PYTHON'
        import sys

        operation, left, right = sys.argv[1], float(sys.argv[2]), float(sys.argv[3])
        print(left + right if operation == "add" else left * right)
        PYTHON;

        $bundle = $directory.'/'.uniqid('bundle-').'.zip';
        $zip = new ZipArchive;
        $zip->open($bundle, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($name.'/SKILL.md', $skill);
        $zip->addFromString($name.'/math.py', $script);
        $zip->close();

        return fopen($bundle, 'r');
    }

    private function cleanup(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}
