<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\DTO\RecommendationQuery;
use App\Services\RecommendationService;
use App\Services\StatsService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var StatsService $stats */
        $stats = $this->app->make(StatsService::class);
        /** @var RecommendationService $recommendations */
        $recommendations = $this->app->make(RecommendationService::class);

        $userId = (int) $this->auth()->id();
        $dashboard = $stats->dashboard($userId);
        $quickSuggestion = $recommendations->recommend(
            $userId,
            new RecommendationQuery('random', null, 'unwatched')
        );

        return $this->render('dashboard/index.tpl', [
            'dashboard' => $dashboard,
            'quick_suggestion' => $quickSuggestion,
        ]);
    }
}
