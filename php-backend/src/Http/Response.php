<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Простейший HTTP-ответ: статус + заголовки + тело.
 *
 * PSR-7 не тащим — каркасу достаточно единого способа JSON-ответов
 * (контракт §4.2: статусы 201/400/500, body с русскими текстами).
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(int $status, array $payload): self
    {
        // JSON_UNESCAPED_UNICODE: русские тексты ошибок читаемо и без \u-эскейпов
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            $body === false ? '{}' : $body,
        );
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
