<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\UserRepository;
use App\Services\BulkSnapshotService;
use App\Services\PermissionGate;
use App\Services\RoleBulkService;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => trim((string) $request->query('type', '')),
        ];

        return $this->render('roles/index.tpl', [
            'roles_list' => $users->listRoles($filters),
            'permissions_list' => $users->listPermissions(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->validateCsrf($request);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['Der Rollenname ist erforderlich.']);
        }

        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $users->saveRole(
            null,
            $name,
            trim((string) $request->input('description', '')),
            (array) $request->input('permissions', [])
        );

        $this->session()->flash('success', 'Rolle angelegt.');
        return $this->redirect('roles.index');
    }

    public function update(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $users->saveRole(
            (int) $id,
            trim((string) $request->input('name', '')),
            trim((string) $request->input('description', '')),
            (array) $request->input('permissions', [])
        );

        $this->session()->flash('success', 'Rolle aktualisiert.');
        return $this->redirect('roles.index');
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->assertBulkPermission((string) $request->input('action', ''));

        /** @var RoleBulkService $bulk */
        $bulk = $this->app->make(RoleBulkService::class);
        $filterInput = (array) $request->input('filters', []);
        $filters = [
            'q' => trim((string) ($filterInput['q'] ?? '')),
            'type' => trim((string) ($filterInput['type'] ?? '')),
        ];
        $preview = $bulk->preview(
            (string) $request->input('action', ''),
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters,
            (array) $request->input('payload', [])
        );

        $backUrl = $this->app->router()->url('roles.index', [], array_filter($filters, static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Rollen',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('roles.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'roles') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('roles.index');
        }

        $this->assertBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var RoleBulkService $bulk */
        $bulk = $this->app->make(RoleBulkService::class);
        $result = $bulk->execute($snapshot);
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->session()->flash(
            'success',
            sprintf(
                '%s: %d verarbeitet, %d uebersprungen, %d fehlgeschlagen.',
                (string) ($snapshot['action_label'] ?? 'Bulk-Aktion'),
                (int) ($result['processed'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['failed'] ?? 0)
            )
        );
        foreach ((array) ($result['warnings'] ?? []) as $warning) {
            $this->session()->flash('warning', (string) $warning);
        }

        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('roles.index')));
    }

    private function assertBulkPermission(string $action): void
    {
        if (!in_array($action, ['add_permissions', 'remove_permissions', 'delete_roles'], true)) {
            throw new HttpException(404, 'Bulk-Aktion nicht gefunden.');
        }

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows('roles.manage')) {
            throw new HttpException(403, 'Fuer diese Bulk-Aktion fehlt die Berechtigung.');
        }
    }
}
