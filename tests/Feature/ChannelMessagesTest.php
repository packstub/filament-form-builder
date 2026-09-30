<?php

use Illuminate\Support\Facades\Http;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\WebhookDelivery;
use Packstub\FormBuilder\Notifications\ChannelMessage;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Webhooks\DeliverWebhook;

function channelForm(): Form
{
    return contactForm(['settings' => ['channels' => [
        ['provider' => 'slack', 'url' => 'https://hooks.slack.com/services/T/B/x'],
        ['provider' => 'discord', 'url' => 'https://discord.com/api/webhooks/1/abc'],
        ['provider' => 'teams', 'url' => 'https://example.webhook.office.com/workflows/1'],
        ['provider' => 'slack', 'url' => 'http://insecure.example.com/hook'],
        ['provider' => 'irc', 'url' => 'https://irc.example.com/hook'],
    ]]]);
}

it('posts a message to every valid channel and logs each delivery', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);
    $form = channelForm();

    app(Submitter::class)->submit($form, contactInput($form));

    $deliveries = WebhookDelivery::query()->orderBy('id')->get();

    expect($deliveries->pluck('event')->all())->toBe(['channel.slack', 'channel.discord', 'channel.teams'])
        ->and($deliveries->pluck('status')->unique()->all())->toBe(['delivered'])
        ->and($deliveries->every->isChannelMessage())->toBeTrue();

    Http::assertSentCount(3);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.slack.com/services/T/B/x'
        && $request['text'] === 'New submission #1 to Contact'
        && $request['blocks'][1]['fields'][0]['text'] === "*Name*\nAda Lovelace"
        && ! $request->hasHeader('webhook-signature'));

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://discord.com')
        && $request['embeds'][0]['title'] === 'New submission #1 to Contact'
        && $request['embeds'][0]['fields'][1] === ['name' => 'Email', 'value' => 'ada@example.com', 'inline' => false]
        && $request['allowed_mentions'] === ['parse' => []]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'office.com')
        && $request['attachments'][0]['contentType'] === 'application/vnd.microsoft.card.adaptive'
        && $request['attachments'][0]['content']['body'][1]['facts'][0] === ['title' => 'Name', 'value' => 'Ada Lovelace']);
});

it('keeps channel messages separate from the webhook', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);
    $form = contactForm(['settings' => [
        'webhook_url' => 'https://hooks.example.com/forms',
        'channels' => [['provider' => 'slack', 'url' => 'https://hooks.slack.com/services/T/B/x']],
    ]]);

    app(Submitter::class)->submit($form, contactInput($form));

    expect(WebhookDelivery::query()->pluck('event')->sort()->values()->all())->toBe(['channel.slack', 'submission.received']);

    // Removing the webhook URL does not break a channel retry.
    $form->update(['settings' => ['channels' => $form->settings['channels']]]);
    $channel = WebhookDelivery::query()->where('event', 'channel.slack')->first();
    $channel->forceFill(['status' => 'pending'])->save();

    (new DeliverWebhook($channel))->handle();

    expect($channel->fresh()->status)->toBe('delivered');
});

it('escapes Slack control characters and skips empty values', function (): void {
    $form = contactForm();
    $submission = app(Submitter::class)->submit($form, contactInput($form, ['name' => '<!channel> & co', 'topic' => null]))->submission;

    $payload = ChannelMessage::payload('slack', $submission);
    $texts = array_column($payload['blocks'][1]['fields'], 'text');

    expect($texts[0])->toBe("*Name*\n&lt;!channel&gt; &amp; co")
        ->and(implode(' ', $texts))->not->toContain('Topic');
});
