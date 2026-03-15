<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);

        return $this->render('roles/index.tpl', [
            'roles_list' => $users->listRoles(),
            'permissions_list' => $users->listPermissions(),
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
}
