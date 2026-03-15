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

    public function listUsers(array $filters = []): array
    {
        $sql = 'SELECT * FROM users';
        $conditions = [];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = '(display_name LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $conditions[] = 'is_active = 1';
            } elseif ($filters['status'] === 'inactive') {
                $conditions[] = 'is_active = 0';
            }
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY lower(display_name), lower(email)';

        $users = $this->db->fetchAll($sql, $params);
        return array_map(fn (array $user): array => $this->hydrateUser($user), $users);
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id',
            ['id' => $userId, 'last_login_at' => $this->now(), 'updated_at' => $this->now()]
        );
    }

    public function findUsersByIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT * FROM users WHERE id IN (' . $placeholders . ') ORDER BY lower(display_name), lower(email)'
        );
        $statement->execute($ids);
        $users = $statement->fetchAll() ?: [];

        return array_map(fn (array $user): array => $this->hydrateUser($user), $users);
    }

    public function listInvitations(array $filters = []): array
    {
        $sql = 'SELECT invitations.*, users.display_name AS invited_by_name
                FROM invitations
                LEFT JOIN users ON users.id = invitations.invited_by';
        $conditions = [];
        $params = [];

        if (!empty($filters['state'])) {
            if ($filters['state'] === 'open') {
                $conditions[] = 'invitations.accepted_at IS NULL AND invitations.expires_at >= :now_open';
                $params['now_open'] = $this->now();
            } elseif ($filters['state'] === 'accepted') {
                $conditions[] = 'invitations.accepted_at IS NOT NULL';
            } elseif ($filters['state'] === 'expired') {
                $conditions[] = 'invitations.accepted_at IS NULL AND invitations.expires_at < :now_expired';
                $params['now_expired'] = $this->now();
            }
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY invitations.created_at DESC';

        $invitations = $this->db->fetchAll($sql, $params);
        return array_map(fn (array $invitation): array => $this->hydrateInvitation($invitation), $invitations);
    }

    public function findInvitationsByIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT invitations.*, users.display_name AS invited_by_name
             FROM invitations
             LEFT JOIN users ON users.id = invitations.invited_by
             WHERE invitations.id IN (' . $placeholders . ')
             ORDER BY invitations.created_at DESC'
        );
        $statement->execute($ids);
        $invitations = $statement->fetchAll() ?: [];

        return array_map(fn (array $invitation): array => $this->hydrateInvitation($invitation), $invitations);
    }

    public function listRoles(array $filters = []): array
    {
        $sql = 'SELECT * FROM roles';
        $conditions = [];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = '(name LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'system') {
                $conditions[] = 'is_system = 1';
            } elseif ($filters['type'] === 'custom') {
                $conditions[] = 'is_system = 0';
            }
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY is_system DESC, lower(name)';
        $roles = $this->db->fetchAll($sql, $params);
        foreach ($roles as &$role) {
            $role = $this->hydrateRole($role);
        }

        return $roles;
    }

    public function findRolesByIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT * FROM roles WHERE id IN (' . $placeholders . ') ORDER BY is_system DESC, lower(name)'
        );
        $statement->execute($ids);
        $roles = $statement->fetchAll() ?: [];
        foreach ($roles as &$role) {
            $role = $this->hydrateRole($role);
        }

        return $roles;
    }

    public function listPermissions(): array
    {
        return $this->db->fetchAll('SELECT * FROM permissions ORDER BY name');
    }

    public function setUsersActive(array $userIds, bool $isActive): int
    {
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge([$isActive ? 1 : 0, $this->now()], $userIds);
        $statement = $this->db->pdo()->prepare(
            'UPDATE users SET is_active = ?, updated_at = ? WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($params);

        return $statement->rowCount();
    }

    public function deleteUsers(array $userIds): int
    {
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM users WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($userIds);

        return $statement->rowCount();
    }

    public function addRoleToUsers(array $userIds, int $roleId): int
    {
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            return 0;
        }

        return $this->db->transaction(function () use ($userIds, $roleId): int {
            $changes = 0;
            foreach ($userIds as $userId) {
                $exists = $this->db->fetchOne(
                    'SELECT 1 FROM user_roles WHERE user_id = :user_id AND role_id = :role_id LIMIT 1',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
                if ($exists) {
                    continue;
                }

                $this->db->execute(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
                $changes++;
            }

            if ($changes > 0) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $statement = $this->db->pdo()->prepare(
                    'UPDATE users SET updated_at = ? WHERE id IN (' . $placeholders . ')'
                );
                $statement->execute(array_merge([$this->now()], $userIds));
            }

            return $changes;
        });
    }

    public function removeRoleFromUsers(array $userIds, int $roleId): int
    {
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            return 0;
        }

        return $this->db->transaction(function () use ($userIds, $roleId): int {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $statement = $this->db->pdo()->prepare(
                'DELETE FROM user_roles WHERE role_id = ? AND user_id IN (' . $placeholders . ')'
            );
            $statement->execute(array_merge([$roleId], $userIds));
            $changes = $statement->rowCount();

            if ($changes > 0) {
                $update = $this->db->pdo()->prepare(
                    'UPDATE users SET updated_at = ? WHERE id IN (' . $placeholders . ')'
                );
                $update->execute(array_merge([$this->now()], $userIds));
            }

            return $changes;
        });
    }

    public function revokeInvitations(array $invitationIds): int
    {
        $invitationIds = $this->normalizeIds($invitationIds);
        if ($invitationIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($invitationIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM invitations WHERE id IN (' . $placeholders . ') AND accepted_at IS NULL AND expires_at >= ?'
        );
        $statement->execute(array_merge($invitationIds, [$this->now()]));

        return $statement->rowCount();
    }

    public function addPermissionsToRoles(array $roleIds, array $permissionNames): int
    {
        $roleIds = $this->normalizeIds($roleIds);
        $permissionIds = $this->permissionIdsByName($permissionNames);
        if ($roleIds === [] || $permissionIds === []) {
            return 0;
        }

        return $this->db->transaction(function () use ($roleIds, $permissionIds): int {
            $changes = 0;
            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    $exists = $this->db->fetchOne(
                        'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id LIMIT 1',
                        ['role_id' => $roleId, 'permission_id' => $permissionId]
                    );
                    if ($exists) {
                        continue;
                    }

                    $this->db->execute(
                        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)',
                        ['role_id' => $roleId, 'permission_id' => $permissionId]
                    );
                    $changes++;
                }
            }

            if ($changes > 0) {
                $this->touchRoles($roleIds);
            }

            return $changes;
        });
    }

    public function removePermissionsFromRoles(array $roleIds, array $permissionNames): int
    {
        $roleIds = $this->normalizeIds($roleIds);
        $permissionIds = $this->permissionIdsByName($permissionNames);
        if ($roleIds === [] || $permissionIds === []) {
            return 0;
        }

        return $this->db->transaction(function () use ($roleIds, $permissionIds): int {
            $rolePlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
            $permissionPlaceholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $statement = $this->db->pdo()->prepare(
                'DELETE FROM role_permissions
                 WHERE role_id IN (' . $rolePlaceholders . ')
                   AND permission_id IN (' . $permissionPlaceholders . ')'
            );
            $statement->execute(array_merge($roleIds, $permissionIds));
            $changes = $statement->rowCount();

            if ($changes > 0) {
                $this->touchRoles($roleIds);
            }

            return $changes;
        });
    }

    public function deleteRoles(array $roleIds): int
    {
        $roleIds = $this->normalizeIds($roleIds);
        if ($roleIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM roles WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($roleIds);

        return $statement->rowCount();
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

    private function hydrateInvitation(array $invitation): array
    {
        $invitation['is_open'] = $invitation['accepted_at'] === null
            && strtotime((string) $invitation['expires_at']) >= time();

        return $invitation;
    }

    private function hydrateRole(array $role): array
    {
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

        return $role;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private function permissionIdsByName(array $permissionNames): array
    {
        $permissionNames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $name): string => trim((string) $name),
            $permissionNames
        ))));
        if ($permissionNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($permissionNames), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT id FROM permissions WHERE name IN (' . $placeholders . ')'
        );
        $statement->execute($permissionNames);
        $rows = $statement->fetchAll() ?: [];

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function touchRoles(array $roleIds): void
    {
        $roleIds = $this->normalizeIds($roleIds);
        if ($roleIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'UPDATE roles SET updated_at = ? WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute(array_merge([$this->now()], $roleIds));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
