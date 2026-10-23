<?php

declare(strict_types=1);

namespace App\Http;

/** Тело запроса — не корректный JSON (ожидался application/json с валидным телом). */
final class InvalidJsonException extends \RuntimeException
{
}
