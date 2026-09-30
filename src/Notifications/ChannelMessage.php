<?php

namespace Packstub\FormBuilder\Notifications;

use Illuminate\Support\Str;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * A message about a new submission for a chat channel's incoming webhook:
 * Slack (Block Kit), Discord (an embed) or Microsoft Teams (an Adaptive
 * Card, as Teams workflows expect). The form's channels live in its
 * Notifications settings.
 */
final class ChannelMessage
{
    public const PROVIDERS = ['slack', 'discord', 'teams'];

    /** How many values a message shows, and how long each may be. */
    public const MAX_FIELDS = 10;

    public const MAX_VALUE = 300;

    /**
     * The form's channels: [['provider' => 'slack', 'url' => '...'], ...],
     * invalid rows left out.
     *
     * @return array<int, array{provider: string, url: string}>
     */
    public static function channelsFor(Form $form): array
    {
        $channels = [];

        foreach ((array) $form->setting('channels', []) as $row) {
            $provider = is_array($row) ? ($row['provider'] ?? null) : null;
            $url = is_array($row) ? ($row['url'] ?? null) : null;

            if (in_array($provider, self::PROVIDERS, true) && is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
                $channels[] = ['provider' => $provider, 'url' => $url];
            }
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $provider, FormSubmission $submission): array
    {
        $form = $submission->form;
        $title = __('packstub-form-builder::form-builder.channels.title', ['form' => $form->name, 'number' => $submission->reference()]);
        $rows = self::rows($submission);
        $url = self::url($submission);

        return match ($provider) {
            'discord' => self::discord($title, $rows, $url),
            'teams' => self::teams($title, $rows, $url),
            default => self::slack($title, $rows, $url),
        };
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     * @return array<string, mixed>
     */
    private static function slack(string $title, array $rows, ?string $url): array
    {
        $blocks = [
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*'.self::slackEscape($title).'*']],
        ];

        if ($rows !== []) {
            $blocks[] = ['type' => 'section', 'fields' => array_map(fn (array $row): array => [
                'type' => 'mrkdwn',
                'text' => '*'.self::slackEscape($row['label'])."*\n".self::slackEscape($row['value']),
            ], $rows)];
        }

        if ($url !== null) {
            $blocks[] = ['type' => 'actions', 'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => __('packstub-form-builder::form-builder.mail.view')],
                'url' => $url,
            ]]];
        }

        return ['text' => $title, 'blocks' => $blocks];
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     * @return array<string, mixed>
     */
    private static function discord(string $title, array $rows, ?string $url): array
    {
        $embed = array_filter([
            'title' => Str::limit($title, 250, '…'),
            'url' => $url,
            'fields' => array_map(fn (array $row): array => [
                'name' => Str::limit($row['label'], 250, '…'),
                'value' => $row['value'],
                'inline' => false,
            ], $rows),
            'timestamp' => now()->toIso8601String(),
        ], fn ($value): bool => $value !== null && $value !== []);

        return ['content' => null, 'embeds' => [$embed], 'allowed_mentions' => ['parse' => []]];
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     * @return array<string, mixed>
     */
    private static function teams(string $title, array $rows, ?string $url): array
    {
        $card = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => array_values(array_filter([
                ['type' => 'TextBlock', 'text' => $title, 'weight' => 'Bolder', 'size' => 'Medium', 'wrap' => true],
                $rows === [] ? null : ['type' => 'FactSet', 'facts' => array_map(fn (array $row): array => ['title' => $row['label'], 'value' => $row['value']], $rows)],
            ])),
        ];

        if ($url !== null) {
            $card['actions'] = [['type' => 'Action.OpenUrl', 'title' => __('packstub-form-builder::form-builder.mail.view'), 'url' => $url]];
        }

        return ['type' => 'message', 'attachments' => [['contentType' => 'application/vnd.microsoft.card.adaptive', 'content' => $card]]];
    }

    /**
     * The first values of the submission, empty ones left out, shortened.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private static function rows(FormSubmission $submission): array
    {
        return collect($submission->formatted())
            ->filter(fn (array $row): bool => trim($row['value']) !== '')
            ->take(self::MAX_FIELDS)
            ->map(fn (array $row): array => ['label' => $row['label'], 'value' => Str::limit($row['value'], self::MAX_VALUE, '…')])
            ->values()
            ->all();
    }

    private static function url(FormSubmission $submission): ?string
    {
        if (! $submission->exists) {
            return null;
        }

        try {
            return FormBuilderPlugin::submissionsUrl($submission->form);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function slackEscape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
