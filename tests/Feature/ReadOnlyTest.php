<?php

use Soara\Debugger\Debugger;

it('is read-only in production by default', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(Debugger::readOnly())->toBeTrue();
});

it('is writable outside production by default', function () {
    expect(Debugger::readOnly())->toBeFalse();
});

it('follows the read_only setting when it is set', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('debugger.read_only', 'false');
    expect(Debugger::readOnly())->toBeFalse();

    app()->detectEnvironment(fn () => 'local');
    config()->set('debugger.read_only', true);
    expect(Debugger::readOnly())->toBeTrue();
});

it('tells the app it is read-only', function () {
    Debugger::auth(fn () => true);
    config()->set('debugger.read_only', true);

    $config = $this->shellConfig('/debugger/logs');

    expect($config['readOnly'])->toBeTrue();
});
