<?php

use App\Enums\EmailCheckResult;
use App\Services\EmailDeliverabilityChecker;
use Tests\TestCase;

uses(TestCase::class);

test('invalid and disposable addresses fail without touching DNS', function () {
    $checker = app(EmailDeliverabilityChecker::class);

    expect($checker->check('not-an-email')[0])->toBe(EmailCheckResult::Invalid)
        ->and($checker->check('someone@mailinator.com')[0])->toBe(EmailCheckResult::Disposable)
        ->and($checker->check('someone@sub.yopmail.com')[0])->toBe(EmailCheckResult::Disposable);
});

test('a domain without mail records needs human review', function () {
    // .invalid is reserved (RFC 2606) and never resolves.
    expect(app(EmailDeliverabilityChecker::class)->check('a@nowhere.invalid')[0])->toBe(EmailCheckResult::NoMailServer);
});
