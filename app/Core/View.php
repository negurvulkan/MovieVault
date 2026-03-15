<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\SettingRepository;
use App\Services\AuthService;
use App\Services\PermissionGate;
use Smarty\Smarty;

final class View
{
    private Smarty $smarty;

    public function __construct(
        private readonly Config $config,
        private readonly Router $router,
        private readonly AuthService $auth,
        private readonly PermissionGate $gate,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly SettingRepository $settings
    ) {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($this->config->get('paths.templates'));
        $this->smarty->setCompileDir($this->config->get('paths.smarty_compile'));
        $this->smarty->setCacheDir($this->config->get('paths.smarty_cache'));
        $this->smarty->escape_html = true;

        $this->smarty->registerPlugin('function', 'route', function (array $params): string {
            $name = (string) ($params['name'] ?? '');
            unset($params['name']);

            return $this->router->url($name, $params);
        });

        $this->smarty->registerPlugin('function', 'asset', function (array $params): string {
            $path = '/' . ltrim((string) ($params['path'] ?? ''), '/');
            return ($this->config->get('app.base_path') ?: '') . $path;
        });

        $this->smarty->registerPlugin('modifier', 'join_list', static function (array $value, string $separator = ', '): string {
            return implode($separator, $value);
        });

        $this->smarty->registerPlugin('function', 'can', function (array $params): bool {
            return $this->gate->allows((string) ($params['permission'] ?? ''));
        });

        $this->smarty->registerPlugin('modifier', 'fmt_date', static function (?string $value, string $format = 'd.m.Y H:i'): string {
            if (!$value) {
                return '';
            }

            $timestamp = strtotime($value);
            return $timestamp ? date($format, $timestamp) : $value;
        });

        $this->smarty->registerPlugin('modifier', 'minutes_to_hours', static function (int|string|null $value): string {
            $minutes = (int) $value;
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;

            return sprintf('%dh %02dm', $hours, $rest);
        });

        $this->smarty->registerPlugin('modifier', 'has_permission', static function (string $permission, array $permissions): bool {
            return in_array($permission, $permissions, true);
        });

        $this->smarty->registerPlugin('modifier', 'contains', static function (mixed $needle, array $haystack): bool {
            return in_array($needle, $haystack, true);
        });
    }

    public function render(string $template, array $data = []): string
    {
        $this->smarty->clearAllAssign();
        $this->smarty->assign(array_merge($this->sharedData(), $data));

        return $this->smarty->fetch($template);
    }

    private function sharedData(): array
    {
        $settings = $this->settings->allKeyed();
        $user = $this->auth->user();

        return [
            'app_name' => $settings['app_name'] ?? $this->config->get('app.name', 'MovieVault'),
            'base_path' => $this->config->get('app.base_path', ''),
            'current_user' => $user,
            'permissions' => $user['permissions'] ?? [],
            'flashes' => $this->session->pullFlashes(),
            'csrf_token' => $this->csrf->token(),
            'settings' => $settings,
        ];
    }
}
