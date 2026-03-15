<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;

final class SeriesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);

        return $this->render('series/index.tpl', [
            'series_list' => $catalog->listSeries(),
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
}
