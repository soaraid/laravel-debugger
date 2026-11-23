<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Soara\Debugger\Debugger;

beforeEach(function () {
    Debugger::auth(fn () => true);
    Storage::fake('local');
    config()->set('debugger.storage.disk', 'local');
});

it('uploads a file into the private folder', function () {
    $this->postJson('/debugger/api/storage', ['file' => UploadedFile::fake()->create('Invoice Sample.pdf', 20)])
        ->assertJsonPath('notice.tone', 'success');

    Storage::disk('local')->assertExists('debugger/invoice-sample.pdf');

    $this->getJson('/debugger/api/storage')->assertJsonPath('files.0.name', 'invoice-sample.pdf');
});

it('never serves an uploaded file as html, whatever it contains', function () {
    Storage::disk('local')->put('debugger/notes.txt', '<html><script>alert(1)</script></html>');
    Storage::disk('local')->put('debugger/page.html', '<html><script>alert(1)</script></html>');

    $text = $this->get('/debugger/storage/file?name=notes.txt')->assertOk();
    expect($text->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($text->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($text->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $html = $this->get('/debugger/storage/file?name=page.html')->assertOk();
    expect($html->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($html->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('refuses paths outside the folder', function () {
    Storage::disk('local')->put('secret.txt', 'secret');

    $this->get('/debugger/storage/file?name=../secret.txt')->assertNotFound();
    $this->deleteJson('/debugger/api/storage', ['name' => '../secret.txt'])->assertJsonPath('notice.tone', 'danger');

    Storage::disk('local')->assertExists('secret.txt');
});

it('deletes a test upload', function () {
    Storage::disk('local')->put('debugger/old.csv', 'a,b');

    $this->deleteJson('/debugger/api/storage', ['name' => 'old.csv'])->assertJsonPath('notice.tone', 'success');

    Storage::disk('local')->assertMissing('debugger/old.csv');
});

it('checks the disks only when asked', function () {
    $this->getJson('/debugger/api/storage')->assertOk()->assertJsonMissingPath('probes');

    $this->postJson('/debugger/api/storage/check')->assertOk()->assertJsonPath('probes.0.ok', true);
});
