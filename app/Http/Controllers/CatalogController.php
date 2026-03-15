<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;
use App\Services\BulkSnapshotService;
use App\Services\CatalogBulkService;
use App\Services\PermissionGate;

final class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'kind' => (string) $request->query('kind', ''),
            'genre' => (string) $request->query('genre', ''),
            'watch_filter' => (string) $request->query('watch_filter', ''),
        ];

        return $this->render('catalog/index.tpl', [
            'titles' => $catalog->allTitles($filters, (int) $this->auth()->id()),
            'series_list' => $catalog->listSeries(),
            'genre_options' => $catalog->allGenres(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);

        return $this->render('catalog/form.tpl', [
            'title_item' => null,
            'series_list' => $catalog->listSeries(),
            'genre_options' => $catalog->allGenres(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $titleId = $catalog->saveTitle($this->extractTitlePayload($request));

        if ($request->input('media_format')) {
            $catalog->storeCopy($titleId, $this->extractCopyPayload($request));
        }

        $this->session()->flash('success', 'Titel angelegt.');
        return $this->redirect('catalog.edit', ['id' => $titleId]);
    }

    public function edit(Request $request, string $id): Response
    {
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $title = $catalog->findTitle((int) $id);
        if (!$title) {
            throw new HttpException(404, 'Titel nicht gefunden.');
        }

        return $this->render('catalog/form.tpl', [
            'title_item' => $title,
            'series_list' => $catalog->listSeries(),
            'genre_options' => $catalog->allGenres(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $catalog->saveTitle($this->extractTitlePayload($request), (int) $id);

        $this->session()->flash('success', 'Titel gespeichert.');
        return $this->redirect('catalog.edit', ['id' => (int) $id]);
    }

    public function storeCopy(Request $request, string $id): Response
    {
        $this->validateCsrf($request);
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $catalog->storeCopy((int) $id, $this->extractCopyPayload($request));

        $this->session()->flash('success', 'Exemplar hinzugefuegt.');
        return $this->redirect('catalog.edit', ['id' => (int) $id]);
    }

    public function updateCopy(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $copy = $catalog->findCopy((int) $id);
        if (!$copy) {
            throw new HttpException(404, 'Exemplar nicht gefunden.');
        }

        $catalog->updateCopy((int) $id, $this->extractCopyPayload($request));
        $this->session()->flash('success', 'Exemplar aktualisiert.');

        return $this->redirect('catalog.edit', ['id' => (int) $copy['catalog_title_id']]);
    }

    public function createSeriesMaster(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $title = $catalog->findTitle((int) $id);
        if (!$title) {
            throw new HttpException(404, 'Titel nicht gefunden.');
        }

        $seriesId = $catalog->createSeriesFromTitle((int) $id);
        $series = $catalog->findSeries($seriesId);

        $this->session()->flash(
            'success',
            sprintf('Serienstamm "%s" wurde erstellt bzw. verknuepft.', $series['title'] ?? $title['title'])
        );

        return $this->redirect('catalog.edit', ['id' => (int) $id]);
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        $action = (string) $request->input('action', '');
        $this->assertBulkPermission($action);

        /** @var CatalogBulkService $bulk */
        $bulk = $this->app->make(CatalogBulkService::class);
        $filters = $this->extractFilters((array) $request->input('filters', []));
        $preview = $bulk->preview(
            $action,
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters,
            (array) $request->input('payload', []),
            (int) $this->auth()->id()
        );

        $backUrl = $this->app->router()->url('catalog.index', [], array_filter($filters, static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Katalog',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('catalog.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'catalog') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('catalog.index');
        }

        $this->assertBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var CatalogBulkService $bulk */
        $bulk = $this->app->make(CatalogBulkService::class);
        $result = $bulk->execute($snapshot);
        $snapshots->forget((string) $request->input('preview_token', ''));

        $this->flashBulkResult((string) ($snapshot['action_label'] ?? 'Bulk-Aktion'), $result);

        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('catalog.index')));
    }

    private function extractTitlePayload(Request $request): array
    {
        $kind = (string) $request->input('kind', 'movie');
        $title = trim((string) $request->input('title', ''));

        $errors = [];
        if ($title === '') {
            $errors[] = 'Der Titel ist erforderlich.';
        }
        if (!in_array($kind, ['movie', 'season'], true)) {
            $errors[] = 'Der Typ ist ungueltig.';
        }
        if ($kind === 'season' && !$request->input('series_id')) {
            $errors[] = 'Fuer Staffeln ist eine Serie erforderlich.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'kind' => $kind,
            'title' => $title,
            'original_title' => trim((string) $request->input('original_title', '')) ?: null,
            'year' => trim((string) $request->input('year', '')) ?: null,
            'series_id' => trim((string) $request->input('series_id', '')) ?: null,
            'season_number' => trim((string) $request->input('season_number', '')) ?: null,
            'overview' => trim((string) $request->input('overview', '')) ?: null,
            'runtime_minutes' => trim((string) $request->input('runtime_minutes', '')) ?: null,
            'poster_path' => trim((string) $request->input('poster_path', '')) ?: null,
            'metadata_status' => (string) $request->input('metadata_status', 'manual'),
            'genres' => $this->splitGenres((string) $request->input('genres_text', '')),
        ];
    }

    private function extractCopyPayload(Request $request): array
    {
        return [
            'media_format' => (string) $request->input('media_format', 'dvd'),
            'edition' => trim((string) $request->input('edition', '')) ?: null,
            'barcode' => trim((string) $request->input('barcode', '')) ?: null,
            'item_condition' => trim((string) $request->input('item_condition', '')) ?: null,
            'storage_location' => trim((string) $request->input('storage_location', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];
    }

    private function splitGenres(string $value): array
    {
        $parts = preg_split('/[,;|]/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function extractFilters(array $input): array
    {
        return [
            'q' => trim((string) ($input['q'] ?? '')),
            'kind' => trim((string) ($input['kind'] ?? '')),
            'genre' => trim((string) ($input['genre'] ?? '')),
            'watch_filter' => trim((string) ($input['watch_filter'] ?? '')),
        ];
    }

    private function assertBulkPermission(string $action): void
    {
        $permission = match ($action) {
            'create_copies' => 'copies.manage',
            'create_series_master', 'delete_titles' => 'catalog.edit',
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
