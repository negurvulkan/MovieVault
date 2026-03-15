<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Text;

final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findUserByEmail(string $email): ?array
    {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => strtolower($email)]);
        return $user ? $this->hydrateUser($user) : null;
    }

    public function findUserById(int $id): ?array
    {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
        return $user ? $this->hydrateUser($user) : null;
    }

    public function listUsers(): array
    {
        $users = $this->db->fetchAll('SELECT * FROM users ORDER BY lower(display_name), lower(email)');
        return array_map(fn (array $user): array => $this->hydrateUser($user), $users);
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id',
            ['id' => $userId, 'last_login_at' => $this->now(), 'updated_at' => $this->now()]
        );
    }

    public function listInvitations(): array
    {
        return $this->db->fetchAll(
            'SELECT invitations.*, users.display_name AS invited_by_name
             FROM invitations
             LEFT JOIN users ON users.id = invitations.invited_by
             ORDER BY invitations.created_at DESC'
        );
    }

    public function listRoles(): array
    {
        $roles = $this->db->fetchAll('SELECT * FROM roles ORDER BY is_system DESC, lower(name)');
        foreach ($roles as &$role) {
            $role['permissions'] = $this->db->fetchAll(
                'SELECT permissions.name, permissions.description
                 FROM permissions
                 INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
                 WHERE role_permissions.role_id = :role_id
                 ORDER BY permissions.name',
                ['role_id' => $role['id']]
            );
            $role['permission_names'] = array_map(
                static fn (array $permission): string => $permission['name'],
                $role['permissions']
            );
        }

        return $roles;
    }

    public function listPermissions(): array
    {
        return $this->db->fetchAll('SELECT * FROM permissions ORDER BY name');
    }

    public function createInvitation(string $email, ?int $invitedBy, array $roleIds, int $ttlDays = 14): array
    {
        $token = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $token);
        $now = $this->now();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $ttlDays . ' days'));

        $this->db->transaction(function () use ($email, $tokenHash, $invitedBy, $roleIds, $now, $expiresAt): void {
            $this->db->execute(
                'INSERT INTO invitations (email, token_hash, invited_by, expires_at, created_at)
                 VALUES (:email, :token_hash, :invited_by, :expires_at, :created_at)',
                [
                    'email' => strtolower($email),
                    'token_hash' => $tokenHash,
                    'invited_by' => $invitedBy,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                ]
            );

            $invitationId = $this->db->lastInsertId();
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO invitation_roles (invitation_id, role_id) VALUES (:invitation_id, :role_id)',
                    ['invitation_id' => $invitationId, 'role_id' => (int) $roleId]
                );
            }
        });

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function findInvitationByToken(string $token): ?array
    {
        $invitation = $this->db->fetchOne(
            'SELECT * FROM invitations WHERE token_hash = :token_hash LIMIT 1',
            ['token_hash' => hash('sha256', $token)]
        );

        if (!$invitation) {
            return null;
        }

        $invitation['roles'] = $this->db->fetchAll(
            'SELECT roles.* FROM roles
             INNER JOIN invitation_roles ON invitation_roles.role_id = roles.id
             WHERE invitation_roles.invitation_id = :invitation_id
             ORDER BY roles.name',
            ['invitation_id' => $invitation['id']]
        );

        return $invitation;
    }

    public function acceptInvitation(string $token, string $displayName, string $passwordHash): int
    {
        $invitation = $this->findInvitationByToken($token);
        if (!$invitation) {
            throw new \RuntimeException('Einladung nicht gefunden.');
        }

        if ($invitation['accepted_at'] !== null) {
            throw new \RuntimeException('Die Einladung wurde bereits verwendet.');
        }

        if (strtotime((string) $invitation['expires_at']) < time()) {
            throw new \RuntimeException('Die Einladung ist abgelaufen.');
        }

        return $this->db->transaction(function () use ($invitation, $displayName, $passwordHash): int {
            $this->db->execute(
                'INSERT INTO users (email, display_name, password_hash, created_at, updated_at)
                 VALUES (:email, :display_name, :password_hash, :created_at, :updated_at)',
                [
                    'email' => strtolower($invitation['email']),
                    'display_name' => $displayName,
                    'password_hash' => $passwordHash,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]
            );

            $userId = $this->db->lastInsertId();
            $roles = $this->db->fetchAll(
                'SELECT role_id FROM invitation_roles WHERE invitation_id = :invitation_id',
                ['invitation_id' => $invitation['id']]
            );
            foreach ($roles as $role) {
                $this->db->execute(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => $role['role_id']]
                );
            }

            $this->db->execute(
                'UPDATE invitations SET accepted_at = :accepted_at WHERE id = :id',
                ['accepted_at' => $this->now(), 'id' => $invitation['id']]
            );

            return $userId;
        });
    }

    public function updateUser(int $userId, string $displayName, bool $isActive, array $roleIds): void
    {
        $this->db->transaction(function () use ($userId, $displayName, $isActive, $roleIds): void {
            $this->db->execute(
                'UPDATE users SET display_name = :display_name, is_active = :is_active, updated_at = :updated_at WHERE id = :id',
                [
                    'id' => $userId,
                    'display_name' => $displayName,
                    'is_active' => $isActive ? 1 : 0,
                    'updated_at' => $this->now(),
                ]
            );

            $this->db->execute('DELETE FROM user_roles WHERE user_id = :user_id', ['user_id' => $userId]);
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => (int) $roleId]
                );
            }
        });
    }

    public function saveRole(?int $roleId, string $name, string $description, array $permissionNames, bool $isSystem = false): int
    {
        $slug = Text::slug($name);

        return $this->db->transaction(function () use ($roleId, $name, $slug, $description, $permissionNames, $isSystem): int {
            if ($roleId === null) {
                $this->db->execute(
                    'INSERT INTO roles (name, slug, description, is_system, created_at, updated_at)
                     VALUES (:name, :slug, :description, :is_system, :created_at, :updated_at)',
                    [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'is_system' => $isSystem ? 1 : 0,
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    ]
                );
                $roleId = $this->db->lastInsertId();
            } else {
                $this->db->execute(
                    'UPDATE roles SET name = :name, slug = :slug, description = :description, updated_at = :updated_at WHERE id = :id',
                    [
                        'id' => $roleId,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'updated_at' => $this->now(),
                    ]
                );
                $this->db->execute('DELETE FROM role_permissions WHERE role_id = :role_id', ['role_id' => $roleId]);
            }

            foreach ($permissionNames as $permissionName) {
                $permission = $this->db->fetchOne(
                    'SELECT id FROM permissions WHERE name = :name LIMIT 1',
                    ['name' => $permissionName]
                );
                if ($permission) {
                    $this->db->execute(
                        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)',
                        ['role_id' => $roleId, 'permission_id' => $permission['id']]
                    );
                }
            }

            return $roleId;
        });
    }

    public function seedPermissions(array $permissions): void
    {
        $createdAt = $this->now();
        foreach ($permissions as $name => $description) {
            $this->db->execute(
                'INSERT INTO permissions (name, description, created_at)
                 VALUES (:name, :description, :created_at)
                 ON CONFLICT(name) DO UPDATE SET description = excluded.description',
                ['name' => $name, 'description' => $description, 'created_at' => $createdAt]
            );
        }
    }

    public function ensureSystemRoles(): array
    {
        $definitions = [
            'administrator' => [
                'name' => 'Administrator',
                'description' => 'Voller Zugriff auf alle Bereiche.',
                'permissions' => array_map(fn (array $permission): string => $permission['name'], $this->listPermissions()),
            ],
            'redaktion' => [
                'name' => 'Redaktion',
                'description' => 'Pflegt Titel, Exemplare und Metadaten.',
                'permissions' => ['catalog.view', 'catalog.create', 'catalog.edit', 'copies.manage', 'metadata.enrich', 'watched.manage', 'suggestions.use', 'stats.view'],
            ],
            'import' => [
                'name' => 'Import',
                'description' => 'Darf CSV-Importe ausfuehren.',
                'permissions' => ['catalog.view', 'import.run', 'stats.view'],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Darf Vorschlaege, Watch-Liste und Dashboard nutzen.',
                'permissions' => ['catalog.view', 'watched.manage', 'suggestions.use', 'stats.view'],
            ],
        ];

        $roleIds = [];
        foreach ($definitions as $slug => $definition) {
            $existing = $this->db->fetchOne('SELECT id FROM roles WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            $roleIds[$slug] = $this->saveRole(
                $existing['id'] ?? null,
                $definition['name'],
                $definition['description'],
                $definition['permissions'],
                true
            );
        }

        return $roleIds;
    }

    private function hydrateUser(array $user): array
    {
        $roles = $this->db->fetchAll(
            'SELECT roles.id, roles.name, roles.slug
             FROM roles
             INNER JOIN user_roles ON user_roles.role_id = roles.id
             WHERE user_roles.user_id = :user_id
             ORDER BY roles.name',
            ['user_id' => $user['id']]
        );
        $permissions = $this->db->fetchAll(
            'SELECT DISTINCT permissions.name
             FROM permissions
             INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
             INNER JOIN user_roles ON user_roles.role_id = role_permissions.role_id
             WHERE user_roles.user_id = :user_id
             ORDER BY permissions.name',
            ['user_id' => $user['id']]
        );

        $user['roles'] = $roles;
        $user['role_ids'] = array_map(static fn (array $role): int => (int) $role['id'], $roles);
        $user['permissions'] = array_map(static fn (array $permission): string => $permission['name'], $permissions);

        return $user;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
