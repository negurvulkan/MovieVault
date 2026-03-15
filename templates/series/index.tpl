{extends file='layouts/base.tpl'}

{block name=title} - Serien{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Serien</p>
            <h1 class="display-6">Serienstamm pflegen</h1>
        </div>
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='series.index'}" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">Suche</label>
                <input type="text" name="q" class="form-control" value="{$filters.q}" placeholder="Serienname oder Originaltitel">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card">
                <h2 class="h4 mb-3">Neue Serie</h2>
                <form method="post" action="{route name='series.store'}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="text" name="title" class="form-control" placeholder="Titel" required>
                    <input type="text" name="original_title" class="form-control" placeholder="Originaltitel">
                    <div class="row g-2">
                        <div class="col"><input type="number" name="year_start" class="form-control" placeholder="Startjahr"></div>
                        <div class="col"><input type="number" name="year_end" class="form-control" placeholder="Endjahr"></div>
                    </div>
                    <textarea name="overview" class="form-control" rows="4" placeholder="Kurzbeschreibung"></textarea>
                    <button type="submit" class="btn btn-primary">Serie anlegen</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            {if 'catalog.edit'|has_permission:$permissions}
                <form id="series-bulk-form" method="post" action="{route name='series.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="filters[q]" value="{$filters.q}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Bulk-Aktion</label>
                            <select name="action" class="form-select" required>
                                <option value="">Bitte waehlen</option>
                                <option value="delete_series">Serien loeschen</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Anwenden auf</label>
                            <select name="selection_mode" class="form-select">
                                <option value="ids">Ausgewaehlte Serien</option>
                                <option value="filtered">Alle aktuellen Treffer</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="series" id="series-bulk-master">
                                <label class="form-check-label" for="series-bulk-master">Sichtbare Serien markieren</label>
                            </div>
                        </div>
                    </div>
                </form>
            {/if}

            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                    <h2 class="h4 mb-0">Vorhandene Serien</h2>
                    {if 'catalog.edit'|has_permission:$permissions}
                        <span class="small text-secondary">Bulk-Loeschen laesst verknuepfte Titel bestehen.</span>
                    {/if}
                </div>
                <div class="vstack gap-3">
                    {foreach $series_list as $series}
                        <form method="post" action="{route name='series.update' id=$series.id}" class="panel-subcard">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="d-flex justify-content-between gap-3 mb-3">
                                <div class="small text-secondary">{$series.season_count} Staffeln/Titel verknuepft</div>
                                {if 'catalog.edit'|has_permission:$permissions}
                                    <div class="form-check">
                                        <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$series.id}" data-bulk-group="series" form="series-bulk-form">
                                    </div>
                                {/if}
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6"><input type="text" name="title" class="form-control" value="{$series.title}"></div>
                                <div class="col-md-6"><input type="text" name="original_title" class="form-control" value="{$series.original_title|default:''}"></div>
                                <div class="col-md-3"><input type="number" name="year_start" class="form-control" value="{$series.year_start|default:''}"></div>
                                <div class="col-md-3"><input type="number" name="year_end" class="form-control" value="{$series.year_end|default:''}"></div>
                                <div class="col-md-6"><input type="text" class="form-control" value="{$series.season_count} Staffeln/Titel" disabled></div>
                                <div class="col-12"><textarea name="overview" class="form-control" rows="3">{$series.overview|default:''}</textarea></div>
                                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-secondary">Aktualisieren</button></div>
                            </div>
                        </form>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Serien vorhanden.</p>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>
{/block}
