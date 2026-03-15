<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;
use App\Repositories\WatchRepository;

final class WatchedController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var WatchRepository $watch */
        $watch = $this->app->make(WatchRepository::class);
        /** @var CatalogRepository $catalog */
        $catalog = $this->app->make(CatalogRepository::class);

        return $this->render('watched/index.tpl', [
            'events' => $watch->listEvents((int) $this->auth()->id()),
            'title_options' => $catalog->titlesForSelect(),
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
}
