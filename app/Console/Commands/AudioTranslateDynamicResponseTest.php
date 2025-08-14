<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class AudioTranslateDynamicResponseTest extends Command
{
    protected $signature = 'app:audio-translate-dynamic-response-test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $audioFile = storage_path('samples/mynameis_es.mp3');

        $json = OpenAI::audio()->translate([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'json',
        ]);

        $text = OpenAI::audio()->translate([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'text',
        ]);

        $srt = OpenAI::audio()->translate([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'srt',
        ]);

        $verboseJson = OpenAI::audio()->translate([
            'model' => 'whisper-1',
            'file' => fopen($audioFile, 'r'),
            'response_format' => 'verbose_json',
        ]);

        $vtt = OpenAI::audio()->translate([
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
