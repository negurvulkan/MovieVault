<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\DTO\RecommendationQuery;
use App\Repositories\CatalogRepository;
use App\Services\RecommendationService;

final class SuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var RecommendationService $recommendations */
        $recommendations = $this->app->make(RecommendationService::class);
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);

        $query = $this->buildQuery($request);
        $suggestion = $recommendations->recommend((int) $this->auth()->id(), $query);

        return $this->render('suggestions/index.tpl', [
            'suggestion' => $suggestion,
            'genre_options' => $catalog->allGenres(),
            'query' => [
                'mode' => $query->mode,
                'genre' => $query->genre,
                'filter' => $query->filter,
            ],
        ]);
    }

    public function api(Request $request): Response
    {
        /** @var RecommendationService $recommendations */
        $recommendations = $this->app->make(RecommendationService::class);
        $query = $this->buildQuery($request);
        $suggestion = $recommendations->recommend((int) $this->auth()->id(), $query);

        return $this->json(['suggestion' => $suggestion]);
    }

    private function buildQuery(Request $request): RecommendationQuery
    {
        $mode = (string) $request->query('mode', 'random');
        $genre = trim((string) $request->query('genre', '')) ?: null;
        $filter = (string) $request->query('filter', 'unwatched');

        return new RecommendationQuery($mode, $genre, $filter);
    }
}
