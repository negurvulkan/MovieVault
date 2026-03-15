<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\CsvImportService;

final class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('import/index.tpl', [
            'upload_state' => $_SESSION['import_upload'] ?? null,
            'preview_state' => $_SESSION['import_preview'] ?? null,
            'canonical_fields' => CsvImportService::CANONICAL_FIELDS,
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->validateCsrf($request);
        /** @var CsvImportService $imports */
        $imports = $this->app->make(CsvImportService::class);

        $upload = $imports->storeUpload($request->file('csv_file') ?? []);
        $_SESSION['import_upload'] = $upload;
        unset($_SESSION['import_preview']);

        $this->session()->flash('success', 'CSV geladen. Jetzt Spalten zuordnen und Vorschau pruefen.');
        return $this->redirect('import.index');
    }

    public function preview(Request $request): Response
    {
        $this->validateCsrf($request);
        $upload = $_SESSION['import_upload'] ?? null;
        if (!$upload || empty($upload['path'])) {
            throw new ValidationException(['Bitte zuerst eine CSV-Datei hochladen.']);
        }

        /** @var CsvImportService $imports */
        $imports = $this->app->make(CsvImportService::class);
        $preview = $imports->preview((string) $upload['path'], (array) $request->input('mapping', []));
        $_SESSION['import_preview'] = [
            'rows' => $preview->rows,
            'errors' => $preview->errors,
            'warnings' => $preview->warnings,
            'mapping' => $preview->mapping,
            'summary' => $preview->summary,
        ];

        $this->session()->flash('success', 'Import-Vorschau aktualisiert.');
        return $this->redirect('import.index');
    }

    public function commit(Request $request): Response
    {
        $this->validateCsrf($request);
        $preview = $_SESSION['import_preview'] ?? null;
        if (!$preview || empty($preview['rows'])) {
            throw new ValidationException(['Es liegt keine Import-Vorschau zum Uebernehmen vor.']);
        }

        /** @var CsvImportService $imports */
        $imports = $this->app->make(CsvImportService::class);
        $result = $imports->commit((array) $preview['rows'], (int) $this->auth()->id());

        unset($_SESSION['import_upload'], $_SESSION['import_preview']);
        $this->session()->flash(
            'success',
            sprintf(
                'Import abgeschlossen: %d neu, %d aktualisiert, %d Exemplare, %d Watch-Events.',
                $result['created'],
                $result['updated'],
                $result['copies'],
                $result['watched']
            )
        );

        return $this->redirect('catalog.index');
    }
}
