<?php

use App\Support\Modules;
use App\Support\TeamRoles;

beforeEach(function () {
    $this->vendor = accountingVendor();
});

test('when Project Management is off its pages answer 404 and it leaves the module list', function () {
    config(['modules.projects' => false]);
    $owner = accountingUser($this->vendor, 'vendor');

    $this->actingAs($owner)->get(route('projects.index'))->assertNotFound();
    $this->actingAs($owner)->get(route('projects.suppliers.index'))->assertNotFound();

    expect(Modules::for($owner))->not->toHaveKey('projects')
        ->and(Modules::for($owner))->toHaveKey('water')
        ->and(TeamRoles::assignable())->not->toHaveKey('project_manager');
});

test('a project-only user sees the module page instead of an error while the module is off', function () {
    config(['modules.projects' => false]);
    $manager = accountingUser($this->vendor, 'project_manager');

    expect($manager->homeRoute())->toBe(route('modules'));
    $this->actingAs($manager)->get(route('modules'))->assertOk()->assertSee('No modules available');
});

test('the daily project alerts do nothing while the module is off', function () {
    config(['modules.projects' => false]);

    $this->artisan('projects:alerts')->expectsOutputToContain('switched off')->assertSuccessful();
});

test('switching the module on brings it back', function () {
    config(['modules.projects' => true]);
    $owner = accountingUser($this->vendor, 'vendor');

    $this->actingAs($owner)->get(route('projects.index'))->assertOk();
    expect(Modules::for($owner))->toHaveKey('projects');
});
