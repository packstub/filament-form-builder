<?php

use Illuminate\Support\Facades\Hash;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Tests\Fixtures\RecordingSink;
use Packstub\FormBuilder\Tests\Fixtures\User;
use Packstub\FormBuilder\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->beforeEach(function (): void {
    RecordingSink::$received = [];
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createUser(array $attributes = []): User
{
    $sequence = User::query()->count() + 1;

    return User::query()->create([
        'name' => "User {$sequence}",
        'email' => "user{$sequence}@example.com",
        'password' => Hash::make('secret'),
        ...$attributes,
    ]);
}

/**
 * One builder item.
 *
 * @param  array<string, mixed>  $data
 * @return array{type: string, data: array<string, mixed>}
 */
function field(string $type, string $label, array $data = []): array
{
    return ['type' => $type, 'data' => ['label' => $label, ...$data]];
}

/**
 * A contact form: name, email (required), message, newsletter opt-in.
 *
 * @param  array<string, mixed>  $attributes
 */
function contactForm(array $attributes = []): Form
{
    return Form::query()->create([
        'name' => 'Contact',
        'slug' => 'contact',
        'fields' => [
            field('text', 'Name', ['required' => true, 'width' => 'half']),
            field('email', 'Email', ['required' => true, 'width' => 'half']),
            field('select', 'Topic', ['choices' => ['sales' => 'Sales', 'support' => 'Support']]),
            field('textarea', 'Message', ['required' => true, 'rows' => 5]),
            field('checkbox', 'Newsletter', ['key' => 'newsletter']),
            field('checkboxes', 'Interests', ['choices' => ['php' => 'PHP', 'js' => 'JavaScript']]),
            field('hidden', 'Source', ['key' => 'source', 'default' => 'website']),
            field('heading', 'Almost done'),
        ],
        ...$attributes,
    ]);
}

/**
 * Valid input for contactForm(), with the protection token.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactInput(Form $form, array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'topic' => 'sales',
        'message' => 'Hello there',
        'interests' => ['php'],
        app(ProtectionToken::class)->field() => app(ProtectionToken::class)->make($form),
        ...$overrides,
    ];
}
