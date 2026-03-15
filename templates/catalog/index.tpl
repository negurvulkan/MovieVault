{extends file='layouts/base.tpl'}

{block name=title} - Katalog{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Katalog</p>
            <h1 class="display-6">Filme, Staffeln und Besitzstand</h1>
        </div>
        {if 'catalog.create'|has_permission:$permissions}
            <a class="btn btn-primary" href="{route name='catalog.create'}">Neuen Titel anlegen</a>
        {/if}
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='catalog.index'}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Suche</label>
                <input type="text" name="q" class="form-control" value="{$filters.q}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Typ</label>
                <select name="kind" class="form-select">
                    <option value="">Alle</option>
                    <option value="movie" {if $filters.kind === 'movie'}selected{/if}>Film</option>
                    <option value="season" {if $filters.kind === 'season'}selected{/if}>Staffel</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Genre</label>
                <select name="genre" class="form-select">
                    <option value="">Alle</option>
                    {foreach $genre_options as $genre}
                        <option value="{$genre.slug}" {if $filters.genre === $genre.slug}selected{/if}>{$genre.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="watch_filter" class="form-select">
                    <option value="">Alle</option>
                    <option value="unwatched" {if $filters.watch_filter === 'unwatched'}selected{/if}>Ungesehen</option>
                    <option value="watched" {if $filters.watch_filter === 'watched'}selected{/if}>Gesehen</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>

    {assign var=has_catalog_bulk value=false}
    {if 'copies.manage'|has_permission:$permissions || 'catalog.edit'|has_permission:$permissions}
        {assign var=has_catalog_bulk value=true}
    {/if}

    <form method="post" action="{route name='catalog.bulk.preview'}" class="vstack gap-3">
        <input type="hidden" name="csrf_token" value="{$csrf_token}">
        <input type="hidden" name="filters[q]" value="{$filters.q}">
        <input type="hidden" name="filters[kind]" value="{$filters.kind}">
        <input type="hidden" name="filters[genre]" value="{$filters.genre}">
        <input type="hidden" name="filters[watch_filter]" value="{$filters.watch_filter}">

        {if $has_catalog_bulk}
            <div class="panel-card bulk-toolbar">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Bulk-Aktion</label>
                        <select name="action" class="form-select js-bulk-action" data-bulk-group="catalog" required>
                            <option value="">Bitte waehlen</option>
                            {if 'copies.manage'|has_permission:$permissions}
                                <option value="create_copies">Physische Medien anlegen</option>
                            {/if}
                            {if 'catalog.edit'|has_permission:$permissions}
                                <option value="create_series_master">Serienstamm anlegen/verknuepfen</option>
                                <option value="delete_titles">Katalogeintraege loeschen</option>
                            {/if}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Anwenden auf</label>
                        <select name="selection_mode" class="form-select">
                            <option value="ids">Ausgewaehlte Eintraege</option>
                            <option value="filtered">Alle aktuellen Treffer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check pt-4">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="catalog" id="catalog-bulk-master">
                            <label class="form-check-label" for="catalog-bulk-master">Sichtbare Eintraege markieren</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                    </div>
                    <div class="col-12 js-bulk-fields" data-bulk-group="catalog" data-actions="create_copies" hidden>
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Format</label>
                                <select name="payload[media_format]" class="form-select">
                                    <option value="dvd">DVD</option>
                                    <option value="bluray">Blu-ray</option>
                                </select>
                            </div>
                            <div class="col-md-2"><label class="form-label">Edition</label><input type="text" name="payload[edition]" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label">Zustand</label><input type="text" name="payload[item_condition]" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Lagerort</label><input type="text" name="payload[storage_location]" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Notiz</label><input type="text" name="payload[notes]" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <div class="table-panel">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        {if $has_catalog_bulk}
                            <th class="bulk-check-col">
                                <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="catalog">
                            </th>
                        {/if}
                        <th>Titel</th>
                        <th>Typ</th>
                        <th>Genres</th>
                        <th>Exemplare</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $titles as $title}
                        <tr>
                            {if $has_catalog_bulk}
                                <td class="bulk-check-col">
                                    <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$title.id}" data-bulk-group="catalog">
                                </td>
                            {/if}
                            <td>
                                <strong>{$title.title}</strong>
                                <div class="text-secondary small">
                                    {if $title.kind === 'season'}
                                        {$title.series_title|default:$title.title} - Staffel {$title.season_number|default:'?'}
                                    {else}
                                        {$title.year|default:'Film'}
                                    {/if}
                                </div>
                            </td>
                            <td><span class="badge text-bg-dark">{if $title.kind === 'season'}Staffel{else}Film{/if}</span></td>
                            <td>{$title.genres|join_list}</td>
                            <td>{$title.copies_count}</td>
                            <td>
                                {if $title.watched}
                                    <span class="badge text-bg-success">gesehen</span>
                                {else}
                                    <span class="badge text-bg-secondary">offen</span>
                                {/if}
                            </td>
                            <td class="text-end">
                                {if 'catalog.edit'|has_permission:$permissions}
                                    <a class="btn btn-sm btn-outline-primary" href="{route name='catalog.edit' id=$title.id}">Bearbeiten</a>
                                {/if}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="{if $has_catalog_bulk}7{else}6{/if}" class="text-center text-secondary py-4">Noch keine passenden Eintraege vorhanden.</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </form>
{/block}
