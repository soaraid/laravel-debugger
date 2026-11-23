<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Mail\MailTester;
use Soara\Debugger\Queue\WorkerHeartbeat;
use Soara\Debugger\Support\Redactor;
use Throwable;

class MailController extends Controller
{
    public function __construct(protected MailTester $mail) {}

    public function index(Request $request, WorkerHeartbeat $worker): JsonResponse
    {
        $queue = config('queue.default').'/'.config('queue.connections.'.config('queue.default').'.queue', 'default');

        return response()->json([
            'settings' => $this->mail->settings(),
            'worker' => $worker->last()?->diffForHumans(),
            'override' => config('debugger.mail.override_recipient'),
            'queue' => $queue,
            'defaults' => [
                'to' => (string) ($request->user()->email ?? ''),
                'subject' => 'Test email from '.config('app.name'),
                'body' => 'If you can read this, mail is working.',
                'via' => 'queue',
            ],
        ]);
    }

    public function send(Request $request, AuditLog $audit): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email:rfc,filter', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'via' => ['required', 'in:queue,sync'],
        ], [
            'to.email' => 'Enter a full email address, like name@example.com.',
            'to.required' => 'Enter the address to send the test email to.',
        ]);

        try {
            $result = $this->mail->send($data['to'], $data['subject'], $data['body'], $data['via'] === 'queue');
            $audit->record('mail.test', $result['recipient'], ['via' => $data['via']]);
        } catch (Throwable $e) {
            return $this->notice("Couldn't send: ".Redactor::clean($e->getMessage(), 400).' Check MAIL_HOST, MAIL_PORT and the credentials, then try again.', 'danger');
        }

        if (! $result['delivers']) {
            return $this->notice("The {$result['mailer']} mailer only writes emails to the log, so nothing was delivered to {$result['recipient']}.", 'info');
        }

        $queue = config('queue.default').'/'.config('queue.connections.'.config('queue.default').'.queue', 'default');

        return $this->notice($result['queued']
            ? 'Queued at '.now()->format('H:i')." on {$queue} for {$result['recipient']}. If it doesn't arrive in a minute, look for a failed TestMail job in Queue."
            : "Sent to {$result['recipient']}. Check the inbox, and the spam folder.");
    }
}
