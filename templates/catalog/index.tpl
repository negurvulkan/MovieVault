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

    <div class="table-panel">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
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
                        <td>
                            <strong>{$title.title}</strong>
                            <div class="text-secondary small">
                                {if $title.kind === 'season'}
                                    {$title.series_title|default:$title.title} · Staffel {$title.season_number|default:'?'}
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
                        <td colspan="6" class="text-center text-secondary py-4">Noch keine passenden Eintraege vorhanden.</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/block}
