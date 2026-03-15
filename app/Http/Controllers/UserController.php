<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);

        return $this->render('users/index.tpl', [
            'users_list' => $users->listUsers(),
            'roles_list' => $users->listRoles(),
            'invitations' => $users->listInvitations(),
            'invite_base_url' => rtrim((string) $this->config()->get('app.base_url', 'http://localhost'), '/')
                . ($this->config()->get('app.base_path') ?: ''),
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
}
