<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MetadataService;

final class MetadataController extends Controller
{
    public function search(Request $request): Response
    {
        /** @var MetadataService $metadata */
        $metadata = $this->app->make(MetadataService::class);

        try {
            $results = $metadata->searchForTitle((int) $request->query('title_id', 0));
            return $this->json(['results' => $results]);
        } catch (\Throwable $throwable) {
            return $this->json(['error' => $throwable->getMessage()], 422);
        }
    }

    public function apply(Request $request): Response
    {
        $this->validateCsrf($request);
        /** @var MetadataService $metadata */
        $metadata = $this->app->make(MetadataService::class);

        try {
            $title = $metadata->apply(
                (int) $request->input('title_id', 0),
                (string) $request->input('provider', ''),
                (string) $request->input('external_id', ''),
                (bool) $request->input('overwrite', false)
            );
            return $this->json(['title' => $title]);
        } catch (\Throwable $throwable) {
            return $this->json(['error' => $throwable->getMessage()], 422);
        }
    }
}
