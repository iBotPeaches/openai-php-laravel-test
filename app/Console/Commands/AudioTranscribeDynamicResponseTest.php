<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class AudioTranscribeDynamicResponseTest extends Command
{
    protected $signature = 'app:audio-transcribe-dynamic-response-test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $audioFile = storage_path('samples/speech-quickbrownfox.mp3');

        $json = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'json',
        ]);

        $text = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'text',
        ]);

        $srt = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'srt',
        ]);

        $verboseJson = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'verbose_json',
        ]);

        $vtt = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'vtt',
        ]);

        $this->info('JSON');
        dump($json);

        $this->info('Text');
        dump($text);

        $this->info('SRT');
        dump($srt);

        $this->info('Verbose JSON');
        dump($verboseJson);

        $this->info('VTT');
        dump($vtt);
    }
}
