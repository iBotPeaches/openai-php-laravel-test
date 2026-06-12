<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class ResponsesCompactionTest extends Command
{
    protected $signature = 'app:responses-compaction-test';

    protected $description = 'Test compaction.';

    public function handle(): int
    {
        $firstConversation = OpenAI::conversations()->create([
            'metadata' => ['topic' => 'demo'],
            'items' => [
                [
                    'role' => 'developer',
                    'content' => 'You MUST speak in pirate.',
                ],
                [
                    'role' => 'user',
                    'content' => 'We’re no strangers to love,
You know the rules and so do I.
A full commitment’s what I’m thinking of,
You wouldnt get this from any other guy.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

Annnnnd if you ask me how I’m feeling,
Don’t tell me you’re too blind to see…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Give you up. give you up.
Give you up, give you up.
Never gonna give
Never gonna give, give you up.
Never gonna give
Never gonna give, give you up.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.We’re no strangers to love,
You know the rules and so do I.
A full commitment’s what I’m thinking of,
You wouldnt get this from any other guy.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

Annnnnd if you ask me how I’m feeling,
Don’t tell me you’re too blind to see…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Give you up. give you up.
Give you up, give you up.
Never gonna give
Never gonna give, give you up.
Never gonna give
Never gonna give, give you up.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.We’re no strangers to love,
You know the rules and so do I.
A full commitment’s what I’m thinking of,
You wouldnt get this from any other guy.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

Annnnnd if you ask me how I’m feeling,
Don’t tell me you’re too blind to see…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Give you up. give you up.
Give you up, give you up.
Never gonna give
Never gonna give, give you up.
Never gonna give
Never gonna give, give you up.

We’ve known each other for so long
Your heart’s been aching
But you’re too shy to say it.
Inside we both know what’s been going on,
We know the game and we’re gonna play it.

I just wanna tell you how I’m feeling,
Gotta make you understand…

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.

Never gonna give you up,
Never gonna let you down,
Never gonna run around and desert you.
Never gonna make you cry,
Never gonna say goodbye,
Never gonna tell a lie and hurt you.',
                ],
            ],
        ]);

        $response = OpenAI::responses()->create([
            'model' => 'gpt-5.4',
            'input' => 'Hello who am I?',
            'conversation' => $firstConversation->id,
            'context_management' => [
                ['type' => 'compaction', 'compact_threshold' => 1001],
            ],
        ]);

        dd($response);
    }
}
