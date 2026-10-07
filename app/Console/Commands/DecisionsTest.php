<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Decisions\ChoiceAnswer;
use OpenAI\Responses\Decisions\PredicateAnswer;
use OpenAI\Responses\Decisions\RefusalAnswer;
use OpenAI\Responses\Decisions\ScoreAnswer;

class DecisionsTest extends Command
{
    protected $signature = 'app:decisions-test {--model=gpt-6-luna}';

    protected $description = 'Test the Decisions API with predicate, choice and score questions.';

    public function handle(ClientContract $client): int
    {
        $response = $client->decisions()->create([
            'model' => $this->option('model'),
            'input' => 'Please refund my order. This is the third time I ask and nobody has answered me!',
            'questions' => [
                [
                    'type' => 'predicate',
                    'name' => 'wants_refund',
                    'instructions' => 'The customer asks for a refund.',
                ],
                [
                    'type' => 'predicate',
                    'name' => 'is_spam',
                    'instructions' => 'The text is unsolicited spam.',
                ],
                [
                    'type' => 'choice',
                    'name' => 'sentiment',
                    'instructions' => 'Pick the tone of the message.',
                    'choices' => [
                        ['value' => 'positive', 'description' => 'The tone is happy.'],
                        ['value' => 'neutral', 'description' => 'The tone is matter-of-fact.'],
                        ['value' => 'negative', 'description' => 'The tone is angry or upset.'],
                    ],
                ],
                [
                    'type' => 'score',
                    'name' => 'urgency',
                    'instructions' => 'Rate how urgent the request is.',
                    'levels' => [
                        ['label' => 'low', 'description' => 'No time pressure.'],
                        ['label' => 'medium', 'description' => 'Should be handled soon.'],
                        ['label' => 'high', 'description' => 'The customer needs help now.'],
                    ],
                ],
            ],
        ]);

        $this->info('Received '.count($response->answers).' answer(s).');

        foreach ($response->answers as $answer) {
            match (true) {
                $answer instanceof PredicateAnswer => $this->info("[predicate] {$answer->name}: probability {$answer->probability}"),
                $answer instanceof ChoiceAnswer => $this->printChoice($answer),
                $answer instanceof ScoreAnswer => $this->printScore($answer),
                $answer instanceof RefusalAnswer => $this->warn("[refusal] {$answer->name}"),
            };
        }

        // Look up answers by question name.
        $this->info('wants_refund via answer(): '.var_export($response->answer('wants_refund')?->probability, true));
        $this->info('missing via answer(): '.var_export($response->answer('missing'), true));

        $this->info('Request ID: '.$response->meta()->requestId);
        $this->line(json_encode($response->toArray(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function printChoice(ChoiceAnswer $answer): void
    {
        $this->info("[choice] {$answer->name}: {$answer->choice} (confidence {$answer->confidence})");

        foreach ($answer->probabilities as $probability) {
            $this->line("    {$probability->value}: {$probability->probability}");
        }
    }

    private function printScore(ScoreAnswer $answer): void
    {
        $this->info("[score] {$answer->name}: {$answer->score} (confidence {$answer->confidence})");

        foreach ($answer->probabilities as $probability) {
            $this->line("    {$probability->value} ({$probability->label}): {$probability->probability}");
        }
    }
}
