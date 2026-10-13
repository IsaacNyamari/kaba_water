<?php

use App\Services\Accounting\FiscalPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->periods = app(FiscalPeriodService::class);
});

test('current period is the one containing today', function () {
    expect($this->periods->currentPeriod($this->vendor->id))->toBeNull();

    $this->periods->createQuarters($this->vendor->id, (int) now()->year);

    expect($this->periods->currentPeriod($this->vendor->id)->name)->toBe(now()->year.'-Q'.now()->quarter);
});

test('a period must end after it starts and cannot be created twice', function () {
    expect(fn () => $this->periods->create($this->vendor->id, 'Bad', CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-04-01')))
        ->toThrow(ValidationException::class);

    $this->periods->create($this->vendor->id, 'May', CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'));
    expect(fn () => $this->periods->create($this->vendor->id, 'May', CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31')))
        ->toThrow(ValidationException::class);
});

test('closing twice or reopening an open period is refused', function () {
    $user = accountingUser($this->vendor);
    $p = $this->periods->create($this->vendor->id, 'June', CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

    expect(fn () => $this->periods->reopen($p, $user, 'why'))->toThrow(ValidationException::class);
    $this->periods->close($p, $user);
    expect(fn () => $this->periods->close($p->fresh(), $user))->toThrow(ValidationException::class);
    expect($this->periods->isClosed($this->vendor->id, '2026-06-15'))->toBeTrue()
        ->and($this->periods->isClosed($this->vendor->id, '2026-07-01'))->toBeFalse();
});
