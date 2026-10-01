<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.3.0: the share links table, the link on submissions and the owner of a
 * form. Guarded so a fresh install (whose create migration already has
 * them) is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = config('packstub-form-builder.tables.forms', 'form_builder_forms');
        $submissions = config('packstub-form-builder.tables.submissions', 'form_builder_submissions');
        $links = config('packstub-form-builder.tables.share_links', 'form_builder_share_links');

        Schema::table($forms, function (Blueprint $table) use ($forms): void {
            if (! Schema::hasColumn($forms, 'user_id')) {
                $table->foreignId('user_id')->nullable()->index();
            }
        });

        if (! Schema::hasTable($links)) {
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
        }

        Schema::table($submissions, function (Blueprint $table) use ($submissions): void {
            if (! Schema::hasColumn($submissions, 'share_link_id')) {
                $table->foreignId('share_link_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Additive: nothing to undo inside the major.
    }
};
