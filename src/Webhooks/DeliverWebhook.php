<?php

namespace Packstub\FormBuilder\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Models\WebhookDelivery;

/**
 * Post one delivery, record the outcome, and retry with a growing delay
 * (1, 5, 25 minutes...) until config "webhooks.attempts" is reached.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(): void
    {
        $delivery = $this->delivery->fresh();

        if ($delivery === null || $delivery->isDelivered()) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'Packstub-Form-Builder/1.0'];

        if ($delivery->isChannelMessage()) {
            // A chat channel's incoming webhook: a plain POST to the URL it was sent to.
            $method = 'POST';
            $url = $delivery->url;
        } else {
            $form = $delivery->form;
            $webhook = $form === null ? null : Webhook::for($form);

            if ($webhook === null) {
                $delivery->forceFill(['status' => WebhookDelivery::FAILED, 'error' => 'The form has no webhook URL any more.'])->save();

                return;
            }

            $id = 'msg_'.Str::ulid();
            $headers = [...$webhook->headers, ...$webhook->signatureHeaders($id, time(), $body), ...$headers];
            $method = $webhook->method;
            $url = $webhook->url;
        }

        $delivery->attempts++;

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('packstub-form-builder.webhooks.timeout', 15))
                ->withOptions(['verify' => (bool) config('packstub-form-builder.webhooks.verify_ssl', true)])
                ->withBody($body, 'application/json')
                ->send($method, $url);

            $delivery->response_status = $response->status();
            $delivery->response_body = Str::limit($response->body(), 2000, '…');
            $delivery->error = $response->successful() ? null : 'HTTP '.$response->status();
            $ok = $response->successful();
        } catch (\Throwable $e) {
            $delivery->response_status = null;
            $delivery->error = Str::limit($e->getMessage(), 500);
            $ok = false;
        }

        if ($ok) {
            $delivery->forceFill(['status' => WebhookDelivery::DELIVERED, 'delivered_at' => now(), 'next_attempt_at' => null])->save();

            return;
        }

        $max = max(1, (int) config('packstub-form-builder.webhooks.attempts', 3));

        if ($delivery->attempts >= $max) {
            $delivery->forceFill(['status' => WebhookDelivery::FAILED, 'next_attempt_at' => null])->save();

            return;
        }

        $delay = now()->addMinutes(5 ** ($delivery->attempts - 1));
        $delivery->forceFill(['status' => WebhookDelivery::PENDING, 'next_attempt_at' => $delay])->save();

        if (config('packstub-form-builder.webhooks.queue', true)) {
            static::dispatch($delivery)->delay($delay);
        }
    }

    /**
     * Start (or restart) a delivery: queued when a queue is configured,
     * else sent right away.
     */
    public static function start(WebhookDelivery $delivery): void
    {
        config('packstub-form-builder.webhooks.queue', true) && $delivery->exists
            ? static::dispatch($delivery)
            : (new static($delivery))->handle();
    }
}
