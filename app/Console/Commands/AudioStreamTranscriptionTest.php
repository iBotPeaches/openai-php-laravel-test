<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class AudioStreamTranscriptionTest extends Command
{
    protected $signature = 'app:audio-stream-transcription-test';
    protected $description = 'Command description';

    public function handle(): int
    {
        $audioFile = storage_path('samples/speech-quickbrownfox.mp3');

        $stream = OpenAI::audio()->transcribeStreamed([
            'model' => 'gpt-4o-transcribe',
            'file' => fopen($audioFile, 'r'),
        ]);

        foreach ($stream as $response) {
            dump(json_encode($response->toArray()));
        }

        return self::SUCCESS;
    }
}
