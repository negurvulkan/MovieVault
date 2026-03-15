<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Repositories\WishlistRepository;
use App\Services\BulkSnapshotService;
use App\Services\PermissionGate;
use App\Services\WishlistBulkService;
use App\Services\WishlistService;

final class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        /** @var WishlistRepository $wishlist */
        $wishlist = $this->app->make(WishlistRepository::class);
        /** @var UserRepository $users */
        $users = $this->app->make(UserRepository::class);

        $userId = (int) $this->auth()->id();
        $service->ensureDefaultList($userId, (string) ($this->auth()->user()['display_name'] ?? ''));

        $filters = $this->extractFilters([
            'q' => $request->query('q', ''),
            'status' => $request->query('status', ''),
            'priority' => $request->query('priority', ''),
            'target_format' => $request->query('target_format', ''),
            'kind' => $request->query('kind', ''),
            'list_id' => $request->query('list_id', ''),
            'view' => $request->query('view', 'list'),
        ]);

        $lists = $wishlist->listLists($userId);
        $selectedList = null;
        if (!empty($filters['list_id'])) {
            foreach ($lists as $list) {
                if ((int) $list['id'] === (int) $filters['list_id']) {
                    $selectedList = $list;
                    break;
                }
            }
        }

        return $this->render('wishlist/index.tpl', [
            'filters' => $filters,
            'wish_lists' => $lists,
            'wish_items' => $wishlist->listItems($userId, $filters),
            'selected_list' => $selectedList,
            'active_users' => $users->listUsers(['status' => 'active']),
            'current_url' => $this->app->router()->url('wishlist.index', [], array_filter($filters, static fn (string $value): bool => $value !== '')),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        /** @var WishlistRepository $wishlist */
        $wishlist = $this->app->make(WishlistRepository::class);

        $userId = (int) $this->auth()->id();
        $service->ensureDefaultList($userId, (string) ($this->auth()->user()['display_name'] ?? ''));

        $lists = $wishlist->listLists($userId);
        $selectedListId = (int) $request->query('list_id', 0);

        return $this->render('wishlist/form.tpl', [
            'wish_item' => [
                'wish_list_id' => $selectedListId > 0 ? $selectedListId : (($lists[0]['id'] ?? null) ? (int) $lists[0]['id'] : null),
                'kind' => 'movie',
                'target_format' => 'dvd',
                'priority' => 'medium',
                'status' => 'open',
                'genres' => [],
            ],
            'wish_lists' => $lists,
            'return_filters' => ['list_id' => $selectedListId > 0 ? (string) $selectedListId : ''],
            'back_url' => $this->app->router()->url('wishlist.index', [], array_filter(['list_id' => $selectedListId > 0 ? (string) $selectedListId : ''], static fn (string $value): bool => $value !== '')),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $itemId = $service->saveItem((int) $this->auth()->id(), $request->all());

        $this->session()->flash('success', 'Wunsch-Eintrag angelegt.');
        return $this->redirect('wishlist.edit', ['id' => $itemId]);
    }

    public function edit(Request $request, string $id): Response
    {
        /** @var WishlistRepository $wishlist */
        $wishlist = $this->app->make(WishlistRepository::class);

        $item = $wishlist->findItem((int) $id, (int) $this->auth()->id());
        if (!$item) {
            throw new HttpException(404, 'Wunsch-Eintrag nicht gefunden.');
        }

        return $this->render('wishlist/form.tpl', [
            'wish_item' => $item,
            'wish_lists' => $wishlist->listLists((int) $this->auth()->id()),
            'return_filters' => [
                'list_id' => trim((string) $request->query('list_id', '')),
                'status' => trim((string) $request->query('status', '')),
                'priority' => trim((string) $request->query('priority', '')),
                'target_format' => trim((string) $request->query('target_format', '')),
                'kind' => trim((string) $request->query('kind', '')),
                'q' => trim((string) $request->query('q', '')),
                'view' => trim((string) $request->query('view', '')),
            ],
            'back_url' => $this->app->router()->url('wishlist.index', [], array_filter([
                'list_id' => trim((string) $request->query('list_id', '')),
                'status' => trim((string) $request->query('status', '')),
                'priority' => trim((string) $request->query('priority', '')),
                'target_format' => trim((string) $request->query('target_format', '')),
                'kind' => trim((string) $request->query('kind', '')),
                'q' => trim((string) $request->query('q', '')),
                'view' => trim((string) $request->query('view', '')),
            ], static fn (string $value): bool => $value !== '')),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->saveItem((int) $this->auth()->id(), $request->all(), (int) $id);

        $this->session()->flash('success', 'Wunsch-Eintrag gespeichert.');
        return $this->redirect('wishlist.edit', ['id' => (int) $id]);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->deleteItem((int) $id, (int) $this->auth()->id());

        $this->session()->flash('success', 'Wunsch-Eintrag geloescht.');
        return $this->redirect('wishlist.index');
    }

    public function storeList(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->saveList((int) $this->auth()->id(), $request->all());

        $this->session()->flash('success', 'Einkaufsliste angelegt.');
        return $this->redirect('wishlist.index');
    }

    public function updateList(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->saveList((int) $this->auth()->id(), $request->all(), (int) $id);

        $this->session()->flash('success', 'Einkaufsliste gespeichert.');
        return $this->redirect('wishlist.index', [], ['list_id' => (int) $id]);
    }

    public function reserve(Request $request, string $id): Response
    {
        $this->validateCsrf($request);
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->markStatus((int) $id, (int) $this->auth()->id(), 'reserved');
        $this->session()->flash('success', 'Eintrag wurde als reserviert markiert.');

        return $this->backToWishlist($request);
    }

    public function buy(Request $request, string $id): Response
    {
        $this->validateCsrf($request);
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->markStatus((int) $id, (int) $this->auth()->id(), 'bought');
        $this->session()->flash('success', 'Eintrag wurde als gekauft markiert.');

        return $this->backToWishlist($request);
    }

    public function drop(Request $request, string $id): Response
    {
        $this->validateCsrf($request);
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $service->markStatus((int) $id, (int) $this->auth()->id(), 'dropped');
        $this->session()->flash('success', 'Eintrag wurde verworfen.');

        return $this->backToWishlist($request);
    }

    public function convert(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);
        $result = $service->convert((int) $id, (int) $this->auth()->id());

        $this->session()->flash(
            'success',
            sprintf(
                '%s wurde in die Sammlung uebernommen%s.',
                $result['title']['title'] ?? 'Der Eintrag',
                !empty($result['created_copy']) ? ' und als physisches Medium angelegt' : ''
            )
        );

        foreach ((array) ($result['warnings'] ?? []) as $warning) {
            $this->session()->flash('warning', (string) $warning);
        }

        return $this->backToWishlist($request);
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        $action = (string) $request->input('action', '');
        $this->assertBulkPermission($action);

        /** @var WishlistBulkService $bulk */
        $bulk = $this->app->make(WishlistBulkService::class);
        $filters = $this->extractFilters((array) $request->input('filters', []));
        $preview = $bulk->preview(
            $action,
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters,
            (array) $request->input('payload', []),
            (int) $this->auth()->id()
        );

        $backUrl = $this->app->router()->url('wishlist.index', [], array_filter($filters, static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Wunschliste',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('wishlist.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'wishlist') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('wishlist.index');
        }

        $this->assertBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var WishlistBulkService $bulk */
        $bulk = $this->app->make(WishlistBulkService::class);
        $result = $bulk->execute($snapshot, (int) $this->auth()->id());
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->flashBulkResult((string) ($snapshot['action_label'] ?? 'Bulk-Aktion'), $result);
        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('wishlist.index')));
    }

    public function metadataSearch(Request $request): Response
    {
        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);

        try {
            $results = $service->searchMetadata((int) $request->query('wish_item_id', 0), (int) $this->auth()->id());
            return $this->json(['results' => $results]);
        } catch (\Throwable $throwable) {
            return $this->json(['error' => $throwable->getMessage()], 422);
        }
    }

    public function metadataApply(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var WishlistService $service */
        $service = $this->app->make(WishlistService::class);

        try {
            $item = $service->applyMetadata(
                (int) $request->input('wish_item_id', 0),
                (int) $this->auth()->id(),
                (string) $request->input('provider', ''),
                (string) $request->input('external_id', ''),
                (bool) $request->input('overwrite', false)
            );
            return $this->json(['wish_item' => $item]);
        } catch (\Throwable $throwable) {
            return $this->json(['error' => $throwable->getMessage()], 422);
        }
    }

    private function backToWishlist(Request $request): Response
    {
        $returnUrl = trim((string) $request->input('return_url', ''));
        if ($returnUrl !== '') {
            return Response::redirect($returnUrl);
        }

        return $this->redirect('wishlist.index');
    }

    private function extractFilters(array $input): array
    {
        return [
            'q' => trim((string) ($input['q'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
            'priority' => trim((string) ($input['priority'] ?? '')),
            'target_format' => trim((string) ($input['target_format'] ?? '')),
            'kind' => trim((string) ($input['kind'] ?? '')),
            'list_id' => trim((string) ($input['list_id'] ?? '')),
            'view' => in_array((string) ($input['view'] ?? 'list'), ['list', 'cards'], true)
                ? (string) ($input['view'] ?? 'list')
                : 'list',
        ];
    }

    private function assertBulkPermission(string $action): void
    {
        $permission = match ($action) {
            'mark_reserved', 'mark_bought', 'mark_dropped', 'change_priority', 'change_target_format' => 'wishlist.edit',
            'convert_to_catalog' => 'wishlist.convert',
            'delete_wishes' => 'wishlist.delete',
            default => throw new HttpException(404, 'Bulk-Aktion nicht gefunden.'),
        };

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows($permission)) {
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
