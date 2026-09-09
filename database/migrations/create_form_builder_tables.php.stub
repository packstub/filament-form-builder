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
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('packstub-form-builder.tables.submissions', 'form_builder_submissions'));
        Schema::dropIfExists(config('packstub-form-builder.tables.forms', 'form_builder_forms'));
    }
};
