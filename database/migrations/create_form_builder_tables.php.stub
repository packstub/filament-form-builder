<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $forms = config('packstub-form-builder.tables.forms', 'form_builder_forms');
        $submissions = config('packstub-form-builder.tables.submissions', 'form_builder_submissions');
        $deliveries = config('packstub-form-builder.tables.webhook_deliveries', 'form_builder_webhook_deliveries');
        $links = config('packstub-form-builder.tables.share_links', 'form_builder_share_links');

        Schema::create($forms, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // The field definitions, as saved by the builder: [{type, data: {key, label, ...}}].
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('submit_label')->nullable();
            $table->text('success_message')->nullable();
            $table->string('redirect_url')->nullable();
            // Addresses that receive an email per submission.
            $table->json('notification_emails')->nullable();
            $table->boolean('store_submissions')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            // Extra per-form settings (honeypot, min_seconds, require_login...).
            $table->json('settings')->nullable();
            // The tenant the form belongs to, when the plugin is tenant-aware (config "tenancy").
            $table->string(config('packstub-form-builder.tenancy.column', 'tenant_id'), 64)->nullable()->index();
            // The user who created the form (config "ownership").
            $table->foreignId('user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($links, function (Blueprint $table) use ($forms): void {
            $table->id();
            $table->foreignId('form_id')->constrained($forms)->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_submissions')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create($submissions, function (Blueprint $table) use ($forms): void {
            $table->id();
            $table->foreignId('form_id')->constrained($forms)->cascadeOnDelete();
            // The submitted values, keyed by field key.
            $table->json('data')->nullable();
            // The field labels and types at submission time, keyed by field key.
            $table->json('fields')->nullable();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Where the form was submitted from (the page URL).
            $table->string('source_url', 2048)->nullable();
            $table->string('channel', 16)->default('web');
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            // The sequential number of the submission within its form (#42).
            $table->unsignedInteger('number')->nullable();
            // The visitor's anonymous id (user id, or a hash of IP + user agent), for "one per person".
            $table->string('fingerprint', 64)->nullable();
            // The share link the submission came through, for a private form.
            $table->foreignId('share_link_id')->nullable()->index();
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
            $table->index(['form_id', 'fingerprint']);
        });

        Schema::create($deliveries, function (Blueprint $table) use ($forms, $submissions): void {
            $table->id();
            $table->foreignId('form_id')->constrained($forms)->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained($submissions)->nullOnDelete();
            $table->string('url', 2048);
            $table->string('event', 64)->default('submission.received');
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('packstub-form-builder.tables.webhook_deliveries', 'form_builder_webhook_deliveries'));
        Schema::dropIfExists(config('packstub-form-builder.tables.submissions', 'form_builder_submissions'));
        Schema::dropIfExists(config('packstub-form-builder.tables.share_links', 'form_builder_share_links'));
        Schema::dropIfExists(config('packstub-form-builder.tables.forms', 'form_builder_forms'));
    }
};
