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
use App\Services\PermissionGate;
use App\Services\SeriesBulkService;

final class SeriesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
        ];

        return $this->render('series/index.tpl', [
            'series_list' => $catalog->listSeries($filters),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->validateCsrf($request);

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            throw new ValidationException(['Der Serienname ist erforderlich.']);
        }

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $catalog->saveSeries([
            'title' => $title,
            'original_title' => $request->input('original_title'),
            'year_start' => $request->input('year_start'),
            'year_end' => $request->input('year_end'),
            'overview' => $request->input('overview'),
        ]);

        $this->session()->flash('success', 'Serie angelegt.');
        return $this->redirect('series.index');
    }

    public function update(Request $request, string $id): Response
    {
        $this->validateCsrf($request);

        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);
        $catalog->saveSeries([
            'title' => $request->input('title'),
            'original_title' => $request->input('original_title'),
            'year_start' => $request->input('year_start'),
            'year_end' => $request->input('year_end'),
            'overview' => $request->input('overview'),
            'poster_path' => $request->input('poster_path'),
        ], (int) $id);

        $this->session()->flash('success', 'Serie aktualisiert.');
        return $this->redirect('series.index');
    }

    public function previewBulk(Request $request): Response
    {
        $this->validateCsrf($request);
        $this->assertBulkPermission((string) $request->input('action', ''));

        /** @var SeriesBulkService $bulk */
        $bulk = $this->app->make(SeriesBulkService::class);
        $filterInput = (array) $request->input('filters', []);
        $filters = ['q' => trim((string) ($filterInput['q'] ?? ''))];
        $preview = $bulk->preview(
            (string) $request->input('action', ''),
            (string) $request->input('selection_mode', 'ids'),
            (array) $request->input('selected_ids', []),
            $filters
        );

        $backUrl = $this->app->router()->url('series.index', [], array_filter($filters, static fn (string $value): bool => $value !== ''));
        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $token = $snapshots->create(array_merge($preview, ['return_url' => $backUrl]));

        return $this->render('bulk/preview.tpl', [
            'module_label' => 'Serien',
            'preview' => $preview,
            'preview_token' => $token,
            'execute_url' => $this->app->router()->url('series.bulk.execute'),
            'back_url' => $backUrl,
        ]);
    }

    public function executeBulk(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var BulkSnapshotService $snapshots */
        $snapshots = $this->app->make(BulkSnapshotService::class);
        $snapshot = $snapshots->get((string) $request->input('preview_token', ''));
        if (!$snapshot || ($snapshot['module'] ?? '') !== 'series') {
            $this->session()->flash('warning', 'Die Bulk-Vorschau ist abgelaufen. Bitte Aktion erneut vorbereiten.');
            return $this->redirect('series.index');
        }

        $this->assertBulkPermission((string) ($snapshot['action'] ?? ''));

        /** @var SeriesBulkService $bulk */
        $bulk = $this->app->make(SeriesBulkService::class);
        $result = $bulk->execute($snapshot);
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

        return Response::redirect((string) ($snapshot['return_url'] ?? $this->app->router()->url('series.index')));
    }

    private function assertBulkPermission(string $action): void
    {
        if ($action !== 'delete_series') {
            throw new HttpException(404, 'Bulk-Aktion nicht gefunden.');
        }

        /** @var PermissionGate $gate */
        $gate = $this->app->make(PermissionGate::class);
        if (!$gate->allows('catalog.edit')) {
            throw new HttpException(403, 'Fuer diese Bulk-Aktion fehlt die Berechtigung.');
        }
    }
}
