<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingRepository;

final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var SettingRepository $settings */
        $settings = $this->app->make(SettingRepository::class);

        return $this->render('settings/index.tpl', [
            'settings_map' => $settings->allKeyed(),
            'metadata_configured' => (bool) $this->config()->get('metadata.tmdb_api_key'),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->validateCsrf($request);

        /** @var SettingRepository $settings */
        $settings = $this->app->make(SettingRepository::class);
        $settings->upsertMany([
            'app_name' => trim((string) $request->input('app_name', 'MovieVault')) ?: 'MovieVault',
            'default_recommendation_filter' => (string) $request->input('default_recommendation_filter', 'unwatched'),
            'invite_ttl_days' => (string) $request->input('invite_ttl_days', '14'),
        ]);

        $this->session()->flash('success', 'Einstellungen gespeichert.');
        return $this->redirect('settings.index');
    }
}
