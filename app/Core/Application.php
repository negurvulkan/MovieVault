<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WatchedController;
use App\Repositories\CatalogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Repositories\WatchRepository;
use App\Services\AuthService;
use App\Services\BulkSnapshotService;
use App\Services\CatalogBulkService;
use App\Services\CsvImportService;
use App\Services\HttpClient;
use App\Services\InvitationBulkService;
use App\Services\InstallerService;
use App\Services\MetadataService;
use App\Services\PermissionGate;
use App\Services\RecommendationService;
use App\Services\RoleBulkService;
use App\Services\SeriesBulkService;
use App\Services\StatsService;
use App\Services\UserBulkService;
use App\Services\WatchedBulkService;
use Throwable;

final class Application
{
    private readonly Config $config;
    private readonly Container $container;

    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->container = new Container();

        $this->bootstrapSession();
        $this->ensureDirectories();
        $this->registerBindings();
        $this->registerRoutes();
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            $match = $this->router()->match($request);
            if ($match === null) {
                throw new HttpException(404, 'Die angeforderte Seite wurde nicht gefunden.');
            }

            /** @var Route $route */
            $route = $match['route'];
            $params = $match['params'];

            if (($route->options['guest'] ?? false) && $this->auth()->check()) {
                Response::redirect($this->router()->url('dashboard'))->send();
                return;
            }

            if (($route->options['auth'] ?? false) && !$this->auth()->check()) {
                $this->session()->flash('warning', 'Bitte zuerst anmelden.');
                Response::redirect($this->router()->url('login'))->send();
                return;
            }

            $permission = $route->options['permission'] ?? null;
            if (is_string($permission) && !$this->make(PermissionGate::class)->allows($permission)) {
                throw new HttpException(403, 'Fuer diese Aktion fehlt die Berechtigung.');
            }

            [$controllerClass, $method] = $route->handler;
            $controller = new $controllerClass($this);
            $response = $controller->{$method}($request, ...array_values($params));

            if (!$response instanceof Response) {
                $response = Response::html((string) $response);
            }
        } catch (ValidationException $exception) {
            $this->session()->flash('danger', $exception->getMessage());
            foreach ($exception->errors() as $error) {
                $this->session()->flash('danger', $error);
            }
            $response = Response::redirect($_SERVER['HTTP_REFERER'] ?? $this->router()->url('dashboard'));
        } catch (HttpException $exception) {
            $response = Response::html(
                $this->view()->render('error.tpl', [
                    'status' => $exception->status,
                    'message' => $exception->getMessage(),
                ]),
                $exception->status
            );
        } catch (Throwable $throwable) {
            $response = Response::html(
                $this->view()->render('error.tpl', [
                    'status' => 500,
                    'message' => $this->config->get('app.debug') ? $throwable->getMessage() : 'Es ist ein unerwarteter Fehler aufgetreten.',
                ]),
                500
            );
        }

        $response->send();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function router(): Router
    {
        /** @var Router $router */
        $router = $this->container->get(Router::class);
        return $router;
    }

    public function view(): View
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        return $view;
    }

    public function auth(): AuthService
    {
        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);
        return $auth;
    }

    public function session(): Session
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        return $session;
    }

    public function csrf(): Csrf
    {
        /** @var Csrf $csrf */
        $csrf = $this->container->get(Csrf::class);
        return $csrf;
    }

    public function db(): Database
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        return $db;
    }

    public function make(string $id): mixed
    {
        return $this->container->get($id);
    }

    private function bootstrapSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) $this->config->get('session.name', 'movievault_session'));
        session_set_cookie_params([
            'lifetime' => (int) $this->config->get('session.lifetime', 28800),
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    private function ensureDirectories(): void
    {
        foreach ((array) $this->config->get('paths', []) as $path) {
            if (is_string($path) && !is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }

    private function registerBindings(): void
    {
        $this->container->singleton(Config::class, fn () => $this->config);
        $this->container->singleton(Session::class, fn () => new Session());
        $this->container->singleton(Csrf::class, fn (Container $c) => new Csrf(
            $c->get(Session::class),
            (string) $this->config->get('security.csrf_key', '_csrf_token')
        ));
        $this->container->singleton(Router::class, fn () => new Router((string) $this->config->get('app.base_path', '')));
        $this->container->singleton(Database::class, fn () => new Database((string) $this->config->get('database.path')));
        $this->container->singleton(SettingRepository::class, fn (Container $c) => new SettingRepository($c->get(Database::class)));
        $this->container->singleton(UserRepository::class, fn (Container $c) => new UserRepository($c->get(Database::class)));
        $this->container->singleton(CatalogRepository::class, fn (Container $c) => new CatalogRepository($c->get(Database::class)));
        $this->container->singleton(WatchRepository::class, fn (Container $c) => new WatchRepository($c->get(Database::class)));
        $this->container->singleton(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(Session::class),
            $c->get(UserRepository::class),
            $this->config
        ));
        $this->container->singleton(PermissionGate::class, fn (Container $c) => new PermissionGate($c->get(AuthService::class)));
        $this->container->singleton(BulkSnapshotService::class, fn (Container $c) => new BulkSnapshotService(
            $c->get(Session::class)
        ));
        $this->container->singleton(View::class, fn (Container $c) => new View(
            $this->config,
            $c->get(Router::class),
            $c->get(AuthService::class),
            $c->get(PermissionGate::class),
            $c->get(Session::class),
            $c->get(Csrf::class),
            $c->get(SettingRepository::class)
        ));
        $this->container->singleton(HttpClient::class, fn () => new HttpClient($this->config));
        $this->container->singleton(MetadataService::class, fn (Container $c) => new MetadataService(
            $this->config,
            $c->get(CatalogRepository::class),
            $c->get(HttpClient::class)
        ));
        $this->container->singleton(CsvImportService::class, fn (Container $c) => new CsvImportService(
            $this->config,
            $c->get(CatalogRepository::class),
            $c->get(WatchRepository::class)
        ));
        $this->container->singleton(CatalogBulkService::class, fn (Container $c) => new CatalogBulkService(
            $c->get(CatalogRepository::class)
        ));
        $this->container->singleton(SeriesBulkService::class, fn (Container $c) => new SeriesBulkService(
            $c->get(CatalogRepository::class)
        ));
        $this->container->singleton(WatchedBulkService::class, fn (Container $c) => new WatchedBulkService(
            $c->get(WatchRepository::class)
        ));
        $this->container->singleton(UserBulkService::class, fn (Container $c) => new UserBulkService(
            $c->get(UserRepository::class)
        ));
        $this->container->singleton(InvitationBulkService::class, fn (Container $c) => new InvitationBulkService(
            $c->get(UserRepository::class)
        ));
        $this->container->singleton(RoleBulkService::class, fn (Container $c) => new RoleBulkService(
            $c->get(UserRepository::class)
        ));
        $this->container->singleton(RecommendationService::class, fn (Container $c) => new RecommendationService(
            $c->get(CatalogRepository::class),
            $c->get(WatchRepository::class)
        ));
        $this->container->singleton(StatsService::class, fn (Container $c) => new StatsService(
            $c->get(CatalogRepository::class),
            $c->get(WatchRepository::class)
        ));
        $this->container->singleton(InstallerService::class, fn (Container $c) => new InstallerService(
            $this->config,
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(SettingRepository::class)
        ));
    }

    private function registerRoutes(): void
    {
        $router = $this->router();
        require __DIR__ . '/../../routes/web.php';
    }
}
