<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

abstract class Controller
{
    public function __construct(protected readonly Application $app)
    {
    }

    protected function config(): Config
    {
        return $this->app->config();
    }

    protected function view(): View
    {
        return $this->app->view();
    }

    protected function auth(): AuthService
    {
        return $this->app->auth();
    }

    protected function session(): Session
    {
        return $this->app->session();
    }

    protected function csrf(): Csrf
    {
        return $this->app->csrf();
    }

    protected function db(): Database
    {
        return $this->app->db();
    }

    protected function validateCsrf(Request $request): void
    {
        if (!$this->csrf()->validate((string) $request->input('csrf_token', ''))) {
            throw new HttpException(419, 'Die Sitzung ist abgelaufen. Bitte Formular erneut absenden.');
        }
    }

    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view()->render($template, $data), $status);
    }

    protected function redirect(string $routeName, array $params = [], array $query = []): Response
    {
        return Response::redirect($this->app->router()->url($routeName, $params, $query));
    }

    protected function json(array $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }
}
