PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS wish_lists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS wish_list_members (
    wish_list_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (wish_list_id, user_id),
    FOREIGN KEY (wish_list_id) REFERENCES wish_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS wish_list_members_user_idx
    ON wish_list_members(user_id, wish_list_id);

CREATE TABLE IF NOT EXISTS wish_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wish_list_id INTEGER NOT NULL,
    kind TEXT NOT NULL CHECK(kind IN ('movie', 'season')),
    title TEXT NOT NULL,
    original_title TEXT,
    sort_title TEXT NOT NULL,
    year INTEGER,
    series_title TEXT,
    season_number INTEGER,
    target_format TEXT NOT NULL DEFAULT 'dvd' CHECK(target_format IN ('dvd', 'bluray', 'any')),
    priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('low', 'medium', 'high')),
    status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open', 'reserved', 'bought', 'dropped')),
    target_price REAL,
    seen_price REAL,
    bought_price REAL,
    store_name TEXT,
    location TEXT,
    notes TEXT,
    overview TEXT,
    runtime_minutes INTEGER,
    poster_path TEXT,
    genres_json TEXT,
    metadata_status TEXT NOT NULL DEFAULT 'manual',
    created_by INTEGER,
    reserved_by INTEGER,
    converted_catalog_title_id INTEGER,
    found_at TEXT,
    reserved_at TEXT,
    bought_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (wish_list_id) REFERENCES wish_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reserved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_catalog_title_id) REFERENCES catalog_titles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS wish_items_list_idx
    ON wish_items(wish_list_id, status, priority);

CREATE INDEX IF NOT EXISTS wish_items_status_idx
    ON wish_items(status);

CREATE INDEX IF NOT EXISTS wish_items_priority_idx
    ON wish_items(priority);

CREATE INDEX IF NOT EXISTS wish_items_target_format_idx
    ON wish_items(target_format);

CREATE INDEX IF NOT EXISTS wish_items_created_by_idx
    ON wish_items(created_by);

CREATE TABLE IF NOT EXISTS wish_external_refs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wish_item_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    external_id TEXT NOT NULL,
    source_url TEXT,
    payload_json TEXT,
    last_synced_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(wish_item_id, provider),
    FOREIGN KEY (wish_item_id) REFERENCES wish_items(id) ON DELETE CASCADE
);
