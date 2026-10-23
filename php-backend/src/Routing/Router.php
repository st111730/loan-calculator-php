<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;

/**
 * Роутер без внешних зависимостей (FastRoute убран). MATCH-шаблоны маршрутов
 * компилируются в PCRE нативными средствами PHP; 404 и 405 отвечаются JSON-ом,
 * как и весь API (Node-версия отдавала штатную HTML-страницу Express без
 * спецификации — §4.5).
 *
 * Поддержка шаблонов: статические сегменты + плейсхолдеры {var} и {var:regex}
 * (последние — только в конце пути, что совпадает с текущей таблицей §4).
 */
final class Router
{
    /** @var list<array{method: string, path: string, regex: string, handler: array{0: class-string, 1: string}}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Обработка входящего запроса: 404 и 405 отвечаются JSON-ом, как и весь API.
     */
    public function dispatch(Request $request): void
    {
        $found = null;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            if (!in_array($route['method'], $allowedMethods, true)) {
                $allowedMethods[] = $route['method'];
            }

            if ($route['method'] === $request->method) {
                $found = [$route['handler'], $this->extractVars($route['path'], $matches)];
                break;
            }
        }

        if ($found === null) {
            Response::json(
                $allowedMethods !== [] ? 405 : 404,
                $allowedMethods !== [] ? ['error' => 'method_not_allowed'] : ['error' => 'not_found'],
            )->send();
            return;
        }

        [$handler, $vars] = $found;
        /** @var array{0: class-string, 1: string} $handler */
        [$class, $method] = $handler;
        (new $class)->$method($request, $vars);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private function add(string $method, string $path, array $handler): void
    {
        $regex = $this->compile($path);
        $this->routes[] = ['method' => $method, 'path' => $path, 'regex' => $regex, 'handler' => $handler];
    }

    /**
     * Компиляция шаблона пути в PCRE: {id:\d+} — кастомный паттерн,
     * {id} — без кастомного попадает один сегмент (любой, кроме слэша).
     */
    private function compile(string $path): string
    {
        $placeholder = '~\{\s*([^}:]+?)\s*(?::\s*([^}]+?)\s*)?\}~u';
        $callback = static function (array $m): string {
            [$name, $pattern] = [$m[1], $m[2] !== '' ? $m[2] : '[^/]+'];
            return '(?<' . $name . '>' . $pattern . ')';
        };
        $regex = preg_replace_callback($placeholder, $callback, $path);

        return '~^' . $regex . '$~u';
    }

    /**
     * Имена переменных берутся из исходного шаблона; FastRoute возвращал бы их
     * ассоциативно — здесь то же самое из именованных групп PCRE.
     *
     * @return array<string, string>
     */
    private function extractVars(string $path, array $matches): array
    {
        if (preg_match_all('~\{\s*([^}:]+?)\s*(?::[^}]+)?\}~u', $path, $names) === 0) {
            return [];
        }

        $vars = [];
        foreach ($names[1] as $name) {
            $vars[$name] = $matches[$name] ?? '';
        }

        return $vars;
    }
}
