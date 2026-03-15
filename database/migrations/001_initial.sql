PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL UNIQUE,
    value TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    invited_by INTEGER,
    expires_at TEXT NOT NULL,
    accepted_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    is_system INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invitation_roles (
    invitation_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (invitation_id, role_id),
    FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS series (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    original_title TEXT,
    sort_title TEXT NOT NULL,
    year_start INTEGER,
    year_end INTEGER,
    overview TEXT,
    poster_path TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS catalog_titles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL CHECK(kind IN ('movie', 'season')),
    title TEXT NOT NULL,
    original_title TEXT,
    sort_title TEXT NOT NULL,
    year INTEGER,
    series_id INTEGER,
    season_number INTEGER,
    overview TEXT,
    runtime_minutes INTEGER,
    poster_path TEXT,
    metadata_status TEXT NOT NULL DEFAULT 'manual',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS catalog_titles_unique_season
    ON catalog_titles(series_id, season_number)
    WHERE kind = 'season' AND series_id IS NOT NULL AND season_number IS NOT NULL;

CREATE TABLE IF NOT EXISTS copies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_title_id INTEGER NOT NULL,
    media_format TEXT NOT NULL CHECK(media_format IN ('dvd', 'bluray')),
    edition TEXT,
    barcode TEXT,
    item_condition TEXT,
    storage_location TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (catalog_title_id) REFERENCES catalog_titles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS genres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS title_genres (
    catalog_title_id INTEGER NOT NULL,
    genre_id INTEGER NOT NULL,
    PRIMARY KEY (catalog_title_id, genre_id),
    FOREIGN KEY (catalog_title_id) REFERENCES catalog_titles(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS external_refs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_title_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    external_id TEXT NOT NULL,
    source_url TEXT,
    payload_json TEXT,
    last_synced_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(catalog_title_id, provider),
    FOREIGN KEY (catalog_title_id) REFERENCES catalog_titles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poster_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_title_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    remote_url TEXT NOT NULL,
    local_path TEXT NOT NULL,
    checksum TEXT,
    downloaded_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(catalog_title_id, provider),
    FOREIGN KEY (catalog_title_id) REFERENCES catalog_titles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS watch_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    catalog_title_id INTEGER NOT NULL,
    watched_at TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (catalog_title_id) REFERENCES catalog_titles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS watch_events_user_title_idx
    ON watch_events(user_id, catalog_title_id, watched_at DESC);
