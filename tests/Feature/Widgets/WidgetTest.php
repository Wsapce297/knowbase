<?php

use App\Models\Team;
use App\Models\Widget;
use Illuminate\Database\UniqueConstraintViolationException;

it('gives a new widget a public key', function () {
    $widget = Team::factory()->create()->widget()->create();

    expect($widget->public_key)
        ->toStartWith(Widget::KEY_PREFIX)
        ->toHaveLength(strlen(Widget::KEY_PREFIX) + Widget::KEY_RANDOM_LENGTH);
});

it('gives every widget a different key', function () {
    $first = Widget::factory()->create();
    $second = Widget::factory()->create();

    expect($first->public_key)->not->toBe($second->public_key);
});

it('can regenerate its key', function () {
    $widget = Widget::factory()->create();
    $oldKey = $widget->public_key;

    $widget->regenerateKey();

    expect($widget->fresh()->public_key)
        ->not->toBe($oldKey)
        ->toStartWith(Widget::KEY_PREFIX);
});

it('starts switched off with no allowed websites', function () {
    $widget = Team::factory()->create()->widget()->create()->fresh();

    expect($widget->enabled)->toBeFalse()
        ->and($widget->allowed_origins)->toBe([])
        ->and($widget->greeting)->toBeNull();
});

it('stores allowed origins as a list', function () {
    $widget = Widget::factory()
        ->allowingOrigins(['https://acme.com', 'https://help.acme.com'])
        ->create();

    expect($widget->fresh()->allowed_origins)->toBe(['https://acme.com', 'https://help.acme.com']);
});

it('belongs to its team', function () {
    $team = Team::factory()->create();
    $widget = Widget::factory()->for($team)->create();

    expect($widget->team->is($team))->toBeTrue()
        ->and($team->widget->is($widget))->toBeTrue();
});

it('allows only one widget per team', function () {
    $team = Team::factory()->create();
    Widget::factory()->for($team)->create();

    expect(fn () => Widget::factory()->for($team)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('is deleted together with its team', function () {
    $widget = Widget::factory()->create();

    $widget->team->delete();

    expect(Widget::query()->count())->toBe(0);
});
