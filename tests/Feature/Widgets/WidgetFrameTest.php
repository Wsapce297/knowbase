<?php

use App\Models\Team;
use App\Models\Widget;

it('shows the widget page with the team name and greeting', function () {
    $team = Team::factory()->create(['name' => 'Acme Support']);
    $widget = Widget::factory()->for($team)
        ->allowingOrigins(['https://acme.com', 'https://help.acme.com'])
        ->create(['greeting' => 'Hi there! How can we help?']);

    $this->get(route('widget.frame', $widget->public_key))
        ->assertOk()
        ->assertSee('Acme Support')
        ->assertSee('Hi there! How can we help?')
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://acme.com https://help.acme.com');
});

it('uses the default greeting when the team has not set one', function () {
    $widget = Widget::factory()->create(['greeting' => null]);

    $this->get(route('widget.frame', $widget->public_key))
        ->assertOk()
        ->assertSee(Widget::DEFAULT_GREETING);
});

it('returns 404 for an unknown key', function () {
    $this->get(route('widget.frame', Widget::generateKey()))->assertNotFound();
});

it('returns 404 for a malformed key without looking it up', function () {
    $this->get('/widget/not-a-key')->assertNotFound();
});

it('returns 404 when the widget is switched off', function () {
    $widget = Widget::factory()->disabled()->create();

    $this->get(route('widget.frame', $widget->public_key))->assertNotFound();
});

it('stops working with the old key after the key is regenerated', function () {
    $widget = Widget::factory()->create();
    $oldKey = $widget->public_key;

    $widget->regenerateKey();

    $this->get(route('widget.frame', $oldKey))->assertNotFound();
    $this->get(route('widget.frame', $widget->public_key))->assertOk();
});

it('cannot be framed anywhere when no websites are allowed', function () {
    $widget = Widget::factory()->allowingOrigins([])->create();

    $this->get(route('widget.frame', $widget->public_key))
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

it('leaves unsafe stored origins out of the security header', function () {
    $widget = Widget::factory()
        ->allowingOrigins(['https://acme.com', 'https://evil.com; script-src *', 'acme.com/pricing'])
        ->create();

    $this->get(route('widget.frame', $widget->public_key))
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://acme.com');
});

it('does not start a session or set cookies', function () {
    $widget = Widget::factory()->create();

    $response = $this->get(route('widget.frame', $widget->public_key));

    expect($response->headers->getCookies())->toBe([]);
});

it('is never cached or indexed', function () {
    $widget = Widget::factory()->create();

    $response = $this->get(route('widget.frame', $widget->public_key))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('escapes the greeting so it cannot inject HTML', function () {
    $widget = Widget::factory()->create(['greeting' => '<script>alert(1)</script>']);

    $this->get(route('widget.frame', $widget->public_key))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});
