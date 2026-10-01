<?php

namespace Packstub\FormBuilder\Listeners;

use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Webhooks\DeliverWebhook;
use Packstub\FormBuilder\Webhooks\Webhook;

class DispatchWebhook
{
    public function handle(SubmissionReceived $event): void
    {
        if (FormBuilder::faking()) {
            return;
        }

        $webhook = Webhook::for($event->form);

        if ($webhook === null) {
            return;
        }

        $model = FormBuilder::webhookDeliveryModel();

        $delivery = new $model([
            'form_id' => $event->form->getKey(),
            'submission_id' => $event->submission->exists ? $event->submission->getKey() : null,
            'url' => $webhook->url,
            'event' => 'submission.received',
            'payload' => $webhook->payload($event->submission),
        ]);

        $delivery->setRelation('form', $event->form);
        $delivery->save();

        DeliverWebhook::start($delivery);
    }
}
