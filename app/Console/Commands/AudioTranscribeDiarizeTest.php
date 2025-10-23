<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class AudioTranscribeDiarizeTest extends Command
{
    protected $signature = 'app:audio-transcribe-diarize-test';

    protected $description = 'Transcribe audio with speaker diarization.';

    public function handle()
    {
        $voiceSampleFile = storage_path('samples/mynameis_es.mp3');
        $audioFile = storage_path('samples/speech-quickbrownfox.mp3');
        $dataVoiceUrl = 'data:audio/mpeg;base64,'.base64_encode(file_get_contents($voiceSampleFile));

        $json = OpenAI::audio()->transcribe([
            'model' => 'gpt-4o-transcribe-diarize',
            'file' => fopen($audioFile, 'r'),
            'known_speaker_names' => ['alice'],
            'known_speaker_references' => [
                'alice' => $dataVoiceUrl,
            ],
            'response_format' => 'diarized_json',
        ]);

        dd($json->toArray());
    }
}
