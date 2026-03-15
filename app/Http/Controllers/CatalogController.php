<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;

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
}
