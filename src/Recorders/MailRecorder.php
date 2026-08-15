<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Konekt\Spoke\Support\JsonlWriter;
use Throwable;

class MailRecorder
{
    public function __construct(private JsonlWriter $writer)
    {
    }

    public function record(MessageSending $event): void
    {
        try {
            $message = $event->message;

            $bodyFile = $this->storeBody(
                (string) ($message->getHtmlBody() ?? $message->getTextBody() ?? '')
            );

            $this->writer->write('mails', [
                't' => now()->format('Y-m-d H:i:s.v'),
                'to' => $this->addresses($message->getTo()),
                'cc' => $this->addresses($message->getCc()),
                'bcc' => $this->addresses($message->getBcc()),
                'subject' => $message->getSubject(),
                'body_file' => $bodyFile,
            ]);
        } catch (Throwable $e) {
        }
    }

    private function storeBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $dir = config('spoke.mail_body_dir');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }

        $name = date('Y-m-d') . '-' . Str::random(12) . '.html';

        file_put_contents($dir . '/' . $name, $body, LOCK_EX);

        return $name;
    }

    private function addresses(array $addresses): array
    {
        return array_map(function ($address) {
            $name = $address->getName();

            return $name !== '' ? $name . ' <' . $address->getAddress() . '>' : $address->getAddress();
        }, $addresses);
    }
}
