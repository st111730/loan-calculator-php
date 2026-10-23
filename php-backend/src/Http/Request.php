<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Тонкая обёртка над суперглобальными массивами (аналог объекта req в Express).
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? rawurldecode($path) : '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
        );
    }

    /**
     * Разбор JSON-тела запроса (§4.2). Эквивалент app.use(express.json()).
     *
     * Битое или пустое тело выбрасывает InvalidJsonException; точка входа
     * отвечает на него 400 {"error":"invalid_json"} — Node-версия для того же
     * случая отдавала 500, что в спецификации отмечено как дефект (§7, п. 6).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            throw new InvalidJsonException('Тело запроса не является корректным JSON');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
