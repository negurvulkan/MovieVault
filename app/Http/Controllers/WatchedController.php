<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;
use App\Repositories\WatchRepository;
use App\Services\BulkSnapshotService;
use App\Services\PermissionGate;
use App\Services\WatchedBulkService;

final class WatchedController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var WatchRepository $watch */
        $watch = $this->app->make(WatchRepository::class);
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
        ];

        return $this->render('watched/index.tpl', [
            'events' => $watch->listEvents((int) $this->auth()->id(), $filters),
            'title_options' => $catalog->titlesForSelect(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->validateCsrf($request);

        $titleId = (int) $request->input('catalog_title_id', 0);
        if ($titleId <= 0) {
            throw new ValidationException(['Bitte einen Titel waehlen.']);
        }

        /** @var WatchRepository $watch */
        $watch = $this->app->make(WatchRepository::class);
        $watch->addEvent(
            (int) $this->auth()->id(),
            $titleId,
            (string) ($request->input('watched_at') ?: date('Y-m-d H:i:s')),
            trim((string) $request->input('notes', '')) ?: null
        );

        $this->session()->flash('success', 'Watch-Event gespeichert.');
        return $this->redirect('watched.index');
    }

    public function delete(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var WatchRepository $watch */
        $watch = $this->app->make(WatchRepository::class);
        $watch->deleteEvent((int) $id, (int) $this->auth()->id());

        $this->session()->flash('success', 'Watch-Event entfernt.');
        return $this->redirect('watched.index');
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->assertBulkPermission((string) $request->input('action', ''));

        /** @var WatchedBulkService $bulk */
        $bulk = $this->app->make(WatchedBulkService::class);
        $filterInput = (array) $request->input('filters', []);
        $filters = [
            'q' => trim((string) ($filterInput['q'] ?? '')),
        ];
        $preview = $bulk->preview(
            (string) $request->input('action', ''),
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters,
            (int) $this->auth()->id()
        );

        $backUrl = $this->app->router()->url('watched.index', [], array_filter($filters, static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Watched-List',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('watched.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'watched') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('watched.index');
        }

        $this->assertBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var WatchedBulkService $bulk */
        $bulk = $this->app->make(WatchedBulkService::class);
        $result = $bulk->execute($snapshot, (int) $this->auth()->id());
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->session()->flash(
            'success',
            sprintf(
                '%s: %d verarbeitet, %d uebersprungen, %d fehlgeschlagen.',
                (string) ($snapshot['action_label'] ?? 'Bulk-Aktion'),
                (int) ($result['processed'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['failed'] ?? 0)
            )
        );
        foreach ((array) ($result['warnings'] ?? []) as $warning) {
            $this->session()->flash('warning', (string) $warning);
        }

        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('watched.index')));
    }

    private function assertBulkPermission(string $action): void
    {
        if ($action !== 'delete_events') {
            throw new HttpException(404, 'Bulk-Aktion nicht gefunden.');
        }

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows('watched.manage')) {
            throw new HttpException(403, 'Fuer diese Bulk-Aktion fehlt die Berechtigung.');
        }
    }
}
