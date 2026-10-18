<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A user who belongs to a new team with the given role and has it as their current team.
 *
 * @return array{0: User, 1: Team}
 */
function widgetSettingsUser(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

it('shows the settings page to an owner of a team without a widget', function () {
    [$user] = widgetSettingsUser();

    $this->actingAs($user)
        ->get(route('widget.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Widget')
            ->where('widget', null)
            ->where('maxOrigins', 10)
            ->where('permissions.canManage', true));
});

it('shows the existing widget with its embed code', function () {
    [$user, $team] = widgetSettingsUser();
    $widget = Widget::factory()->for($team)->allowingOrigins(['https://acme.com'])->create();

    $this->actingAs($user)
        ->get(route('widget.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('widget.publicKey', $widget->public_key)
            ->where('widget.enabled', true)
            ->where('widget.allowedOrigins', ['https://acme.com'])
            ->where('widget.embedCode', fn (string $code) => str_contains($code, 'data-key="'.$widget->public_key.'"')
                && str_contains($code, url('widget.js'))));
});

it('shows the page read-only to members', function () {
    [$user] = widgetSettingsUser(TeamRole::Member);

    $this->actingAs($user)
        ->get(route('widget.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canManage', false));
});

it('sends guests to the login page', function () {
    $this->get(route('widget.edit'))->assertRedirect(route('login'));
});

it('creates the widget on the first save with cleaned-up origins', function () {
    [$user, $team] = widgetSettingsUser();

    $this->actingAs($user)
        ->from(route('widget.edit'))
        ->put(route('widget.update', $team), [
            'enabled' => '1',
            'greeting' => 'Hi! Ask me about Acme.',
            'allowed_origins' => "HTTPS://Acme.com/\r\n\r\n  https://help.acme.com  \nhttps://acme.com",
        ])
        ->assertRedirect(route('widget.edit'))
        ->assertSessionHasNoErrors();

    $widget = $team->widget()->first();

    expect($widget->enabled)->toBeTrue()
        ->and($widget->greeting)->toBe('Hi! Ask me about Acme.')
        ->and($widget->allowed_origins)->toBe(['https://acme.com', 'https://help.acme.com'])
        ->and($widget->public_key)->toStartWith(Widget::KEY_PREFIX);
});

it('updates an existing widget without changing its key', function () {
    [$user, $team] = widgetSettingsUser();
    $widget = Widget::factory()->for($team)->create();

    $this->actingAs($user)
        ->put(route('widget.update', $team), [
            'enabled' => '0',
            'greeting' => '',
            'allowed_origins' => 'https://new.example.com',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $widget->fresh();

    expect($fresh->public_key)->toBe($widget->public_key)
        ->and($fresh->enabled)->toBeFalse()
        ->and($fresh->greeting)->toBeNull()
        ->and($fresh->allowed_origins)->toBe(['https://new.example.com'])
        ->and(Widget::query()->count())->toBe(1);
});

it('rejects an invalid website address', function () {
    [$user, $team] = widgetSettingsUser();

    $this->actingAs($user)
        ->put(route('widget.update', $team), [
            'enabled' => '1',
            'allowed_origins' => "https://acme.com\nacme.com/pricing",
        ])
        ->assertSessionHasErrors(['allowed_origins' => '"acme.com/pricing" isn\'t a website address we can use. Write the full address starting with https://, without a page path, e.g. https://example.com.']);

    expect($team->widget()->exists())->toBeFalse();
});

it('needs at least one website to switch the widget on', function () {
    [$user, $team] = widgetSettingsUser();

    $this->actingAs($user)
        ->put(route('widget.update', $team), ['enabled' => '1', 'allowed_origins' => "\n  \n"])
        ->assertSessionHasErrors(['allowed_origins' => 'Add at least one website before switching the widget on.']);
});

it('can be saved switched off without any websites', function () {
    [$user, $team] = widgetSettingsUser();

    $this->actingAs($user)
        ->put(route('widget.update', $team), ['enabled' => '0'])
        ->assertSessionHasNoErrors();

    expect($team->widget()->first()->allowed_origins)->toBe([]);
});

it('limits the number of websites', function () {
    [$user, $team] = widgetSettingsUser();
    $origins = collect(range(1, 11))->map(fn (int $i) => "https://site{$i}.example.com")->implode("\n");

    $this->actingAs($user)
        ->put(route('widget.update', $team), ['enabled' => '1', 'allowed_origins' => $origins])
        ->assertSessionHasErrors(['allowed_origins' => 'You can add up to 10 websites.']);
});

it('does not let members change the widget', function () {
    [$user, $team] = widgetSettingsUser(TeamRole::Member);

    $this->actingAs($user)
        ->put(route('widget.update', $team), ['enabled' => '0'])
        ->assertForbidden();

    expect($team->widget()->exists())->toBeFalse();
});

it('does not let outsiders change another team\'s widget', function () {
    [$user] = widgetSettingsUser();
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->put(route('widget.update', $otherTeam), ['enabled' => '0'])
        ->assertForbidden();
});

it('regenerates the key', function () {
    [$user, $team] = widgetSettingsUser(TeamRole::Admin);
    $widget = Widget::factory()->for($team)->create();

    $this->actingAs($user)
        ->from(route('widget.edit'))
        ->post(route('widget.regenerate-key', $team))
        ->assertRedirect(route('widget.edit'));

    expect($widget->fresh()->public_key)->not->toBe($widget->public_key);
});

it('returns 404 when regenerating the key of a team without a widget', function () {
    [$user, $team] = widgetSettingsUser();

    $this->actingAs($user)
        ->post(route('widget.regenerate-key', $team))
        ->assertNotFound();
});

it('does not let members regenerate the key', function () {
    [$user, $team] = widgetSettingsUser(TeamRole::Member);
    $widget = Widget::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('widget.regenerate-key', $team))
        ->assertForbidden();

    expect($widget->fresh()->public_key)->toBe($widget->public_key);
});
