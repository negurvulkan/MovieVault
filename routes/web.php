<?php

declare(strict_types=1);

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
use App\Http\Controllers\WishlistController;

$router->get('/login', [AuthController::class, 'showLogin'], 'login', ['guest' => true]);
$router->post('/login', [AuthController::class, 'login'], 'login.submit', ['guest' => true]);
$router->post('/logout', [AuthController::class, 'logout'], 'logout', ['auth' => true]);
$router->get('/invite/{token}', [AuthController::class, 'showInvitation'], 'invite.accept', ['guest' => true]);
$router->post('/invite/{token}', [AuthController::class, 'acceptInvitation'], 'invite.submit', ['guest' => true]);

$router->get('/', [DashboardController::class, 'index'], 'dashboard', ['auth' => true, 'permission' => 'stats.view']);
$router->get('/catalog', [CatalogController::class, 'index'], 'catalog.index', ['auth' => true, 'permission' => 'catalog.view']);
$router->get('/catalog/create', [CatalogController::class, 'create'], 'catalog.create', ['auth' => true, 'permission' => 'catalog.create']);
$router->post('/catalog/create', [CatalogController::class, 'store'], 'catalog.store', ['auth' => true, 'permission' => 'catalog.create']);
$router->get('/catalog/{id}/edit', [CatalogController::class, 'edit'], 'catalog.edit', ['auth' => true, 'permission' => 'catalog.edit']);
$router->post('/catalog/{id}/edit', [CatalogController::class, 'update'], 'catalog.update', ['auth' => true, 'permission' => 'catalog.edit']);
$router->post('/catalog/{id}/create-series', [CatalogController::class, 'createSeriesMaster'], 'catalog.series.create', ['auth' => true, 'permission' => 'catalog.edit']);
$router->post('/catalog/{id}/copies', [CatalogController::class, 'storeCopy'], 'catalog.copy.store', ['auth' => true, 'permission' => 'copies.manage']);
$router->post('/copies/{id}/edit', [CatalogController::class, 'updateCopy'], 'catalog.copy.update', ['auth' => true, 'permission' => 'copies.manage']);
$router->post('/catalog/bulk/preview', [CatalogController::class, 'previewBulk'], 'catalog.bulk.preview', ['auth' => true, 'permission' => 'catalog.view']);
$router->post('/catalog/bulk/execute', [CatalogController::class, 'executeBulk'], 'catalog.bulk.execute', ['auth' => true, 'permission' => 'catalog.view']);

$router->get('/series', [SeriesController::class, 'index'], 'series.index', ['auth' => true, 'permission' => 'catalog.view']);
$router->post('/series/create', [SeriesController::class, 'store'], 'series.store', ['auth' => true, 'permission' => 'catalog.create']);
$router->post('/series/{id}/edit', [SeriesController::class, 'update'], 'series.update', ['auth' => true, 'permission' => 'catalog.edit']);
$router->post('/series/bulk/preview', [SeriesController::class, 'previewBulk'], 'series.bulk.preview', ['auth' => true, 'permission' => 'catalog.view']);
$router->post('/series/bulk/execute', [SeriesController::class, 'executeBulk'], 'series.bulk.execute', ['auth' => true, 'permission' => 'catalog.view']);

$router->get('/watched', [WatchedController::class, 'index'], 'watched.index', ['auth' => true, 'permission' => 'watched.manage']);
$router->post('/watched/create', [WatchedController::class, 'store'], 'watched.store', ['auth' => true, 'permission' => 'watched.manage']);
$router->post('/watched/{id}/delete', [WatchedController::class, 'delete'], 'watched.delete', ['auth' => true, 'permission' => 'watched.manage']);
$router->post('/watched/bulk/preview', [WatchedController::class, 'previewBulk'], 'watched.bulk.preview', ['auth' => true, 'permission' => 'watched.manage']);
$router->post('/watched/bulk/execute', [WatchedController::class, 'executeBulk'], 'watched.bulk.execute', ['auth' => true, 'permission' => 'watched.manage']);

$router->get('/wishlist', [WishlistController::class, 'index'], 'wishlist.index', ['auth' => true, 'permission' => 'wishlist.view']);
$router->get('/wishlist/create', [WishlistController::class, 'create'], 'wishlist.create', ['auth' => true, 'permission' => 'wishlist.create']);
$router->post('/wishlist/create', [WishlistController::class, 'store'], 'wishlist.store', ['auth' => true, 'permission' => 'wishlist.create']);
$router->get('/wishlist/{id}/edit', [WishlistController::class, 'edit'], 'wishlist.edit', ['auth' => true, 'permission' => 'wishlist.view']);
$router->post('/wishlist/{id}/edit', [WishlistController::class, 'update'], 'wishlist.update', ['auth' => true, 'permission' => 'wishlist.edit']);
$router->post('/wishlist/{id}/delete', [WishlistController::class, 'delete'], 'wishlist.delete', ['auth' => true, 'permission' => 'wishlist.delete']);
$router->post('/wishlist/lists/create', [WishlistController::class, 'storeList'], 'wishlist.lists.store', ['auth' => true, 'permission' => 'wishlist.create']);
$router->post('/wishlist/lists/{id}/edit', [WishlistController::class, 'updateList'], 'wishlist.lists.update', ['auth' => true, 'permission' => 'wishlist.edit']);
$router->post('/wishlist/{id}/reserve', [WishlistController::class, 'reserve'], 'wishlist.reserve', ['auth' => true, 'permission' => 'wishlist.edit']);
$router->post('/wishlist/{id}/buy', [WishlistController::class, 'buy'], 'wishlist.buy', ['auth' => true, 'permission' => 'wishlist.edit']);
$router->post('/wishlist/{id}/drop', [WishlistController::class, 'drop'], 'wishlist.drop', ['auth' => true, 'permission' => 'wishlist.edit']);
$router->post('/wishlist/{id}/convert', [WishlistController::class, 'convert'], 'wishlist.convert', ['auth' => true, 'permission' => 'wishlist.convert']);
$router->post('/wishlist/bulk/preview', [WishlistController::class, 'previewBulk'], 'wishlist.bulk.preview', ['auth' => true, 'permission' => 'wishlist.view']);
$router->post('/wishlist/bulk/execute', [WishlistController::class, 'executeBulk'], 'wishlist.bulk.execute', ['auth' => true, 'permission' => 'wishlist.view']);

$router->get('/suggestions', [SuggestionController::class, 'index'], 'suggestions.index', ['auth' => true, 'permission' => 'suggestions.use']);
$router->get('/api/suggestions', [SuggestionController::class, 'api'], 'api.suggestions', ['auth' => true, 'permission' => 'suggestions.use']);

$router->get('/import', [ImportController::class, 'index'], 'import.index', ['auth' => true, 'permission' => 'import.run']);
$router->post('/import/upload', [ImportController::class, 'upload'], 'import.upload', ['auth' => true, 'permission' => 'import.run']);
$router->post('/import/preview', [ImportController::class, 'preview'], 'import.preview', ['auth' => true, 'permission' => 'import.run']);
$router->post('/import/commit', [ImportController::class, 'commit'], 'import.commit', ['auth' => true, 'permission' => 'import.run']);

$router->get('/users', [UserController::class, 'index'], 'users.index', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/invitations', [UserController::class, 'createInvitation'], 'users.invitation.store', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/{id}/update', [UserController::class, 'updateUser'], 'users.update', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/bulk/preview', [UserController::class, 'previewBulk'], 'users.bulk.preview', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/bulk/execute', [UserController::class, 'executeBulk'], 'users.bulk.execute', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/invitations/bulk/preview', [UserController::class, 'previewInvitationBulk'], 'users.invitations.bulk.preview', ['auth' => true, 'permission' => 'users.manage']);
$router->post('/users/invitations/bulk/execute', [UserController::class, 'executeInvitationBulk'], 'users.invitations.bulk.execute', ['auth' => true, 'permission' => 'users.manage']);

$router->get('/roles', [RoleController::class, 'index'], 'roles.index', ['auth' => true, 'permission' => 'roles.manage']);
$router->post('/roles/create', [RoleController::class, 'store'], 'roles.store', ['auth' => true, 'permission' => 'roles.manage']);
$router->post('/roles/{id}/edit', [RoleController::class, 'update'], 'roles.update', ['auth' => true, 'permission' => 'roles.manage']);
$router->post('/roles/bulk/preview', [RoleController::class, 'previewBulk'], 'roles.bulk.preview', ['auth' => true, 'permission' => 'roles.manage']);
$router->post('/roles/bulk/execute', [RoleController::class, 'executeBulk'], 'roles.bulk.execute', ['auth' => true, 'permission' => 'roles.manage']);

$router->get('/settings', [SettingsController::class, 'index'], 'settings.index', ['auth' => true, 'permission' => 'settings.manage']);
$router->post('/settings', [SettingsController::class, 'update'], 'settings.update', ['auth' => true, 'permission' => 'settings.manage']);

$router->get('/api/metadata/search', [MetadataController::class, 'search'], 'api.metadata.search', ['auth' => true, 'permission' => 'metadata.enrich']);
$router->post('/api/metadata/apply', [MetadataController::class, 'apply'], 'api.metadata.apply', ['auth' => true, 'permission' => 'metadata.enrich']);
$router->get('/api/wishlist/metadata/search', [WishlistController::class, 'metadataSearch'], 'api.wishlist.metadata.search', ['auth' => true, 'permission' => 'metadata.enrich']);
$router->post('/api/wishlist/metadata/apply', [WishlistController::class, 'metadataApply'], 'api.wishlist.metadata.apply', ['auth' => true, 'permission' => 'metadata.enrich']);
