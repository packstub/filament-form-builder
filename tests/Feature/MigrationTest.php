<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('adds the 1.3 tables and columns to a 1.2 install and is a no-op on a fresh one', function (): void {
    $migration = include __DIR__.'/../../database/migrations/add_share_links_and_owner_to_form_builder_tables.php.stub';

    // Fresh install: everything is already there.
    $migration->up();

    // A 1.2 schema: no owner, no share links.
    Schema::drop('form_builder_share_links');
    Schema::table('form_builder_forms', function (Blueprint $table): void {
        $table->dropIndex(['user_id']);
        $table->dropColumn('user_id');
    });
    Schema::table('form_builder_submissions', function (Blueprint $table): void {
        $table->dropIndex(['share_link_id']);
        $table->dropColumn('share_link_id');
    });

    expect(Schema::hasTable('form_builder_share_links'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('form_builder_share_links'))->toBeTrue()
        ->and(Schema::hasColumn('form_builder_forms', 'user_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_builder_submissions', 'share_link_id'))->toBeTrue();
});
