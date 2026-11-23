<?php

namespace Soara\Debugger\Mail;

use Illuminate\Support\Facades\Mail;

class MailTester
{
    /**
     * @return array{recipient: string, queued: bool, mailer: string, delivers: bool}
     */
    public function send(string $to, string $subject, string $body, bool $queue): array
    {
        $recipient = (string) (config('debugger.mail.override_recipient') ?: $to);
        $mail = new TestMail($subject, $body);
        $pending = Mail::to($recipient);

        $queue ? $pending->queue($mail) : $pending->send($mail);

        $mailer = (string) config('mail.default');

        return [
            'recipient' => $recipient,
            'queued' => $queue,
            'mailer' => $mailer,
            'delivers' => ! in_array($this->transport($mailer), ['log', 'array'], true),
        ];
    }

    /**
     * Mailer settings without secrets.
     *
     * @return array<string, string>
     */
    public function settings(): array
    {
        $mailer = (string) config('mail.default');
        $config = (array) config("mail.mailers.{$mailer}", []);
        $transport = $this->transport($mailer);
        $queue = (string) config('queue.default');

        $settings = ['Mailer' => $mailer.($transport !== $mailer ? " ({$transport})" : '')];

        if ($transport === 'smtp') {
            $settings['Host'] = (string) ($config['host'] ?? '–');
            $settings['Port'] = trim(($config['port'] ?? '–').' '.(($config['scheme'] ?? $config['encryption'] ?? null) ? '('.($config['scheme'] ?? $config['encryption']).')' : ''));
        }

        $settings['From'] = (string) (config('mail.from.address') ?: '–');
        $settings['Queue'] = $queue.' / '.(string) config("queue.connections.{$queue}.queue", 'default');

        return $settings;
    }

    public function transport(string $mailer): string
    {
        return (string) config("mail.mailers.{$mailer}.transport", $mailer);
    }
}
