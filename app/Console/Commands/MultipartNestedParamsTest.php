<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use Throwable;

class MultipartNestedParamsTest extends Command
{
    protected $signature = 'app:multipart-nested-params-test';

    protected $description = 'Test nested parameters of multipart requests: keyed objects, numeric values and lists.';

    public function handle(ClientContract $client): int
    {
        $results = [
            'File upload with expires_after (int seconds)' => fn () => $this->uploadWithExpiry($client, 86400),
            'File upload with expires_after (string seconds)' => fn () => $this->uploadWithExpiry($client, '86400'),
            'Transcription with chunking_strategy (float threshold)' => fn () => $this->transcribeWithChunking($client),
            'Transcription with timestamp_granularities list' => fn () => $this->transcribeWithGranularities($client),
            'Diarize with known_speaker_* lists' => fn () => $this->diarize($client),
        ];

        $failed = false;

        foreach ($results as $name => $run) {
            $this->newLine();
            $this->info("--- {$name} ---");

            try {
                $run();
                $this->info('PASS');
            } catch (Throwable $e) {
                $failed = true;
                $this->error('FAIL: '.$e::class.': '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function uploadWithExpiry(ClientContract $client, int|string $seconds): void
    {
        $file = $client->files()->upload([
            'purpose' => 'user_data',
            'file' => fopen(storage_path('samples/test.txt'), 'r'),
            'expires_after' => [
                'anchor' => 'created_at',
                'seconds' => $seconds,
            ],
        ]);

        try {
            $this->info("File ID: {$file->id}, Created at: {$file->createdAt}, Expires at: ".($file->expiresAt ?? 'null'));

            $expected = $file->createdAt + (int) $seconds;
            if ($file->expiresAt !== $expected) {
                throw new \RuntimeException("Expected expires_at {$expected}, got ".($file->expiresAt ?? 'null').'.');
            }
        } finally {
            $client->files()->delete($file->id);
            $this->line("Deleted file ID: {$file->id}");
        }
    }

    private function transcribeWithChunking(ClientContract $client): void
    {
        $transcription = $client->audio()->transcribe([
            'model' => 'gpt-4o-transcribe',
            'file' => fopen(storage_path('samples/speech-quickbrownfox.mp3'), 'r'),
            'chunking_strategy' => [
                'type' => 'server_vad',
                'threshold' => 0.5,
                'prefix_padding_ms' => 300,
                'silence_duration_ms' => 500,
            ],
        ]);

        $this->info("Text: {$transcription->text}");
    }

    private function transcribeWithGranularities(ClientContract $client): void
    {
        $transcription = $client->audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen(storage_path('samples/speech-quickbrownfox.mp3'), 'r'),
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['word', 'segment'],
        ]);

        $this->info("Text: {$transcription->text}");
        $this->info('Words: '.count($transcription->words).', Segments: '.count($transcription->segments));

        if (count($transcription->words) === 0 || count($transcription->segments) === 0) {
            throw new \RuntimeException('Expected both word and segment timestamps.');
        }
    }

    private function diarize(ClientContract $client): void
    {
        $reference = 'data:audio/mpeg;base64,'.base64_encode(file_get_contents(storage_path('samples/mynameis_es.mp3')));

        $transcription = $client->audio()->transcribe([
            'model' => 'gpt-4o-transcribe-diarize',
            'file' => fopen(storage_path('samples/speech-quickbrownfox.mp3'), 'r'),
            'known_speaker_names' => ['alice'],
            'known_speaker_references' => [$reference],
            'response_format' => 'diarized_json',
        ]);

        $this->info("Text: {$transcription->text}");
        foreach ($transcription->segments as $segment) {
            $this->line("    [{$segment->speaker}] {$segment->text}");
        }
    }
}
