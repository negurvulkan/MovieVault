<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login.tpl');
    }

    public function login(Request $request): Response
    {
        $this->validateCsrf($request);

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            throw new ValidationException(['E-Mail und Passwort sind erforderlich.']);
        }

        if (!$this->auth()->attempt($email, $password)) {
            $this->session()->flash('danger', 'Anmeldung fehlgeschlagen.');
            return $this->redirect('login');
        }

        $this->session()->flash('success', 'Willkommen zurueck.');
        return $this->redirect('dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->auth()->logout();
        $this->session()->flash('success', 'Erfolgreich abgemeldet.');

        return $this->redirect('login');
    }

    public function showInvitation(Request $request, string $token): Response
    {
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $invitation = $users->findInvitationByToken($token);
        if (!$invitation) {
            throw new HttpException(404, 'Die Einladung ist ungueltig oder nicht mehr vorhanden.');
        }

        return $this->render('auth/invite.tpl', [
            'invitation' => $invitation,
            'minimum_password_length' => $this->auth()->minimumPasswordLength(),
            'token' => $token,
        ]);
    }

    public function acceptInvitation(Request $request, string $token): Response
    {
        $this->validateCsrf($request);

        $displayName = trim((string) $request->input('display_name', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        $errors = [];
        if ($displayName === '') {
            $errors[] = 'Ein Anzeigename ist erforderlich.';
        }
        if (strlen($password) < $this->auth()->minimumPasswordLength()) {
            $errors[] = 'Das Passwort ist zu kurz.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Die Passwortbestaetigung stimmt nicht ueberein.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);
        $userId = $users->acceptInvitation($token, $displayName, password_hash($password, PASSWORD_DEFAULT));
        $this->auth()->loginById($userId);
        $this->session()->flash('success', 'Konto erfolgreich aktiviert.');

        return $this->redirect('dashboard');
    }
}
