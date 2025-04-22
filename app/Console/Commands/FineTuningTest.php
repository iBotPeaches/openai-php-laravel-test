<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class FineTuningTest extends Command
{
    protected $signature = 'app:fine-tuning-test';

    protected $description = 'Command description';

    public function handle()
    {
        $response = OpenAI::fineTuning()->createJob([
            'training_file' => 'file-abc123',
            'validation_file' => null,
            'model' => 'gpt-4o-mini-2024-07-18',
            'hyperparameters' => [
                'n_epochs' => 4,
            ],
            'suffix' => null,
        ]);

        dd($response);
        $response = OpenAI::fineTuning()->listJobs();
        dd($response);
    }
}
