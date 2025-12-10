<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\WebhookVerificationException;
use OpenAI\Webhooks\WebhookSignatureVerifier;
use Psr\Http\Message\ServerRequestInterface;

class WebhookController extends Controller
{
    public function __invoke(ServerRequestInterface $request): JsonResponse
    {
        $verifier = new WebhookSignatureVerifier('whsec_');

        try {
            $verifier->verify($request);

            Log::info('Webhook signature verified successfully.', [
                'headers' => $request->getHeaders(),
                'body' => (string) $request->getBody(),
            ]);

        } catch (WebhookVerificationException $exception) {
            Log::error($exception->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }
}
