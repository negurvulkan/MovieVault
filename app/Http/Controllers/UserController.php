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
use App\Services\InvitationBulkService;
use App\Services\PermissionGate;
use App\Services\UserBulkService;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $userFilters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
        ];
        $invitationFilters = [
            'state' => trim((string) $request->query('state', '')),
        ];

        return $this->render('users/index.tpl', [
            'users_list' => $users->listUsers($userFilters),
            'roles_list' => $users->listRoles(),
            'invitations' => $users->listInvitations($invitationFilters),
            'invite_base_url' => rtrim((string) $this->config()->get('app.base_url', 'http://localhost'), '/')
                . ($this->config()->get('app.base_path') ?: ''),
            'user_filters' => $userFilters,
            'invitation_filters' => $invitationFilters,
        ]);
    }

    public function createInvitation(Request $request): Response
    {
        $this->validateCsrf($request);
        $email = trim((string) $request->input('email', ''));
        $roleIds = array_map('intval', (array) $request->input('role_ids', []));

        if ($email === '' || $roleIds === []) {
            throw new ValidationException(['E-Mail und mindestens eine Rolle sind erforderlich.']);
        }

        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $invitation = $users->createInvitation(
            $email,
            $this->auth()->id(),
            $roleIds,
            (int) ($request->input('invite_ttl_days') ?: $this->config()->get('security.invite_ttl_days', 14))
        );

        $inviteUrl = rtrim((string) $this->config()->get('app.base_url', 'http://localhost'), '/')
            . $this->app->router()->url('invite.accept', ['token' => $invitation['token']]);

        $this->session()->flash('success', 'Einladung erstellt: ' . $inviteUrl);
        return $this->redirect('users.index');
    }

    public function updateUser(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $users->updateUser(
            (int) $id,
            trim((string) $request->input('display_name', '')),
            (bool) $request->input('is_active', false),
            array_map('intval', (array) $request->input('role_ids', []))
        );

        $this->session()->flash('success', 'Benutzer aktualisiert.');
        return $this->redirect('users.index');
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->assertUsersBulkPermission((string) $request->input('action', ''));

        /** @var UserBulkService $bulk */
        $bulk = $this->app->make(UserBulkService::class);
        $filterInput = (array) $request->input('filters', []);
        $filters = [
            'q' => trim((string) ($filterInput['q'] ?? '')),
            'status' => trim((string) ($filterInput['status'] ?? '')),
        ];
        $preview = $bulk->preview(
            (string) $request->input('action', ''),
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters,
            (array) $request->input('payload', []),
            (int) $this->auth()->id()
        );

        $backUrl = $this->app->router()->url('users.index', [], array_filter(array_merge($filters, [
            'state' => trim((string) $request->input('state_filter', '')),
        ]), static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Benutzer',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('users.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'users') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('users.index');
        }

        $this->assertUsersBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var UserBulkService $bulk */
        $bulk = $this->app->make(UserBulkService::class);
        $result = $bulk->execute($snapshot, (int) $this->auth()->id());
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->flashBulkResult((string) ($snapshot['action_label'] ?? 'Bulk-Aktion'), $result);
        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('users.index')));
    }

    public function previewInvitationBulk(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->assertInvitationBulkPermission((string) $request->input('action', ''));

        /** @var InvitationBulkService $bulk */
        $bulk = $this->app->make(InvitationBulkService::class);
        $filterInput = (array) $request->input('filters', []);
        $filters = [
            'state' => trim((string) ($filterInput['state'] ?? '')),
        ];
        $preview = $bulk->preview(
            (string) $request->input('action', ''),
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters
        );

        $backUrl = $this->app->router()->url('users.index', [], array_filter([
            'q' => trim((string) $request->input('user_q_filter', '')),
            'status' => trim((string) $request->input('user_status_filter', '')),
            'state' => $filters['state'],
        ], static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Einladungen',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('users.invitations.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeInvitationBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'invitations') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('users.index');
        }

        $this->assertInvitationBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var InvitationBulkService $bulk */
        $bulk = $this->app->make(InvitationBulkService::class);
        $result = $bulk->execute($snapshot);
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->flashBulkResult((string) ($snapshot['action_label'] ?? 'Bulk-Aktion'), $result);
        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('users.index')));
    }

    private function assertUsersBulkPermission(string $action): void
    {
        if (!in_array($action, ['activate_users', 'deactivate_users', 'delete_users', 'add_role', 'remove_role'], true)) {
            throw new HttpException(404, 'Bulk-Aktion nicht gefunden.');
        }

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows('users.manage')) {
            throw new HttpException(403, 'Fuer diese Bulk-Aktion fehlt die Berechtigung.');
        }
    }

    private function assertInvitationBulkPermission(string $action): void
    {
        if ($action !== 'revoke_invitations') {
            throw new HttpException(404, 'Bulk-Aktion nicht gefunden.');
        }

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows('users.manage')) {
            throw new HttpException(403, 'Fuer diese Bulk-Aktion fehlt die Berechtigung.');
        }
    }

    private function flashBulkResult(string $label, array $result): void
    {
        $this->session()->flash(
            'success',
            sprintf(
                '%s: %d verarbeitet, %d uebersprungen, %d fehlgeschlagen.',
                $label,
                (int) ($result['processed'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['failed'] ?? 0)
            )
        );

        foreach ((array) ($result['warnings'] ?? []) as $warning) {
            $this->session()->flash('warning', (string) $warning);
        }
    }
}
