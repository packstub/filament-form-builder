<?php

namespace Packstub\FormBuilder\Listeners;

use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Notifications\ChannelMessage;
use Packstub\FormBuilder\Webhooks\DeliverWebhook;

/**
 * A message to every chat channel of the form (Notifications › Chat
 * channels), posted through the same queued, retried and logged delivery
 * as the webhook.
 */
class SendChannelMessages
{
    public function handle(SubmissionReceived $event): void
    {
        if (FormBuilder::faking()) {
            return;
        }

        $model = FormBuilder::webhookDeliveryModel();

        foreach (ChannelMessage::channelsFor($event->form) as $channel) {
            $delivery = new $model([
                'form_id' => $event->form->getKey(),
                'submission_id' => $event->submission->exists ? $event->submission->getKey() : null,
                'url' => $channel['url'],
                'event' => 'channel.'.$channel['provider'],
                'payload' => ChannelMessage::payload($channel['provider'], $event->submission),
            ]);

            $delivery->setRelation('form', $event->form);
            $delivery->save();

            DeliverWebhook::start($delivery);
        }
    }
}
