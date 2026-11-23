<?php

use Illuminate\Support\Facades\Mail;
use Soara\Debugger\Debugger;
use Soara\Debugger\Mail\TestMail;

beforeEach(function () {
    Debugger::auth(fn () => true);
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => 'smtp.mailgun.org', 'port' => 587, 'username' => 'postmaster', 'password' => 'hunter2']);
});

it('shows mailer settings without secrets', function () {
    $this->getJson('/debugger/api/mail')
        ->assertOk()
        ->assertJsonPath('settings.Host', 'smtp.mailgun.org')
        ->assertDontSee('hunter2');
});

it('queues a test email', function () {
    Mail::fake();

    $this->postJson('/debugger/api/mail', ['to' => 'dev@example.com', 'subject' => 'Hi', 'body' => 'Test', 'via' => 'queue'])
        ->assertJsonPath('notice.tone', 'success');

    Mail::assertQueued(TestMail::class, fn ($mail) => $mail->hasTo('dev@example.com'));
});

it('sends a test email right away', function () {
    Mail::fake();

    $this->postJson('/debugger/api/mail', ['to' => 'dev@example.com', 'subject' => 'Hi', 'body' => 'Test', 'via' => 'sync']);

    Mail::assertSent(TestMail::class);
});

it('sends to the override recipient when set', function () {
    Mail::fake();
    config()->set('debugger.mail.override_recipient', 'inbox@example.com');

    $this->postJson('/debugger/api/mail', ['to' => 'client@example.com', 'subject' => 'Hi', 'body' => 'Test', 'via' => 'sync']);

    Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('inbox@example.com') && ! $mail->hasTo('client@example.com'));
});

it('says so when the mailer only logs', function () {
    config()->set('mail.default', 'log');

    $this->postJson('/debugger/api/mail', ['to' => 'dev@example.com', 'subject' => 'Hi', 'body' => 'Test', 'via' => 'sync'])
        ->assertJsonPath('notice.tone', 'info');
});

it('validates the address with a fix-it message', function () {
    Mail::fake();

    $this->postJson('/debugger/api/mail', ['to' => 'dev@', 'subject' => 'Hi', 'body' => 'Test', 'via' => 'sync'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to' => 'Enter a full email address, like name@example.com.']);
});
