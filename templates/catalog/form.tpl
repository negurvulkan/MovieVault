{extends file='layouts/base.tpl'}

{block name=title} - {if $title_item}Titel bearbeiten{else}Titel anlegen{/if}{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Katalogpflege</p>
            <h1 class="display-6">{if $title_item}{$title_item.title}{else}Neuen Titel anlegen{/if}</h1>
        </div>
        <a class="btn btn-outline-secondary" href="{route name='catalog.index'}">Zurueck</a>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="panel-card">
                <form method="post" action="{if $title_item}{route name='catalog.update' id=$title_item.id}{else}{route name='catalog.store'}{/if}" class="row g-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="poster_path" value="{$title_item.poster_path|default:''}">
                    <div class="col-md-3">
                        <label class="form-label">Typ</label>
                        <select name="kind" class="form-select">
                            <option value="movie" {if ($title_item.kind|default:'movie') === 'movie'}selected{/if}>Film</option>
                            <option value="season" {if ($title_item.kind|default:'') === 'season'}selected{/if}>Staffel</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Titel</label>
                        <input type="text" name="title" class="form-control" value="{$title_item.title|default:''}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jahr</label>
                        <input type="number" name="year" class="form-control" value="{$title_item.year|default:''}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Originaltitel</label>
                        <input type="text" name="original_title" class="form-control" value="{$title_item.original_title|default:''}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Serie</label>
                        <select name="series_id" class="form-select">
                            <option value="">Keine</option>
                            {foreach $series_list as $series}
                                <option value="{$series.id}" {if ($title_item.series_id|default:0) == $series.id}selected{/if}>{$series.title}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Staffel</label>
                        <input type="number" name="season_number" class="form-control" value="{$title_item.season_number|default:''}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Laufzeit (Minuten)</label>
                        <input type="number" name="runtime_minutes" class="form-control" value="{$title_item.runtime_minutes|default:''}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Genres</label>
                        <input type="text" name="genres_text" class="form-control" value="{$title_item.genres|default:[]|join_list}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="overview" class="form-control" rows="5">{$title_item.overview|default:''}</textarea>
                    </div>
                    {if !$title_item}
                        <div class="col-md-3">
                            <label class="form-label">Erstes Format</label>
                            <select name="media_format" class="form-select">
                                <option value="">Keins</option>
                                <option value="dvd">DVD</option>
                                <option value="bluray">Blu-ray</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Edition</label><input type="text" name="edition" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Barcode</label><input type="text" name="barcode" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Zustand</label><input type="text" name="item_condition" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Lagerort</label><input type="text" name="storage_location" class="form-control"></div>
                    {/if}
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{if $title_item}Speichern{else}Titel anlegen{/if}</button>
                        {if $title_item && 'metadata.enrich'|has_permission:$permissions}
                            <button type="button" class="btn btn-outline-secondary js-metadata-search" data-title-id="{$title_item.id}">Metadaten suchen</button>
                        {/if}
                    </div>
                </form>
            </div>

            {if $title_item && !$title_item.series_id && 'catalog.edit'|has_permission:$permissions}
                <div class="panel-card mt-4">
                    <h2 class="h4 mb-3">Serienstamm nachtraeglich anlegen</h2>
                    <p class="text-secondary">Nuetzlich fuer importierte Serienboxen: Der aktuelle Titel wird mit einem neuen oder bereits vorhandenen Serienstamm gleichen Namens verknuepft.</p>
                    <form method="post" action="{route name='catalog.series.create' id=$title_item.id}">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <button type="submit" class="btn btn-outline-dark">Als Serienstamm verknuepfen</button>
                    </form>
                </div>
            {/if}

            {if $title_item && 'copies.manage'|has_permission:$permissions}
                <div class="panel-card mt-4">
                    <h2 class="h4 mb-3">Exemplare</h2>
                    <form method="post" action="{route name='catalog.copy.store' id=$title_item.id}" class="row g-3 mb-4">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <div class="col-md-2">
                            <select name="media_format" class="form-select">
                                <option value="dvd">DVD</option>
                                <option value="bluray">Blu-ray</option>
                            </select>
                        </div>
                        <div class="col-md-2"><input type="text" name="edition" class="form-control" placeholder="Edition"></div>
                        <div class="col-md-2"><input type="text" name="barcode" class="form-control" placeholder="Barcode"></div>
                        <div class="col-md-2"><input type="text" name="item_condition" class="form-control" placeholder="Zustand"></div>
                        <div class="col-md-2"><input type="text" name="storage_location" class="form-control" placeholder="Lagerort"></div>
                        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Hinzufuegen</button></div>
                    </form>

                    <div class="vstack gap-3">
                        {foreach $title_item.copies as $copy}
                            <form method="post" action="{route name='catalog.copy.update' id=$copy.id}" class="list-card row g-2 align-items-center">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <div class="col-md-2">
                                    <select name="media_format" class="form-select form-select-sm">
                                        <option value="dvd" {if $copy.media_format === 'dvd'}selected{/if}>DVD</option>
                                        <option value="bluray" {if $copy.media_format === 'bluray'}selected{/if}>Blu-ray</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="text" name="edition" class="form-control form-control-sm" value="{$copy.edition|default:''}"></div>
                                <div class="col-md-2"><input type="text" name="barcode" class="form-control form-control-sm" value="{$copy.barcode|default:''}"></div>
                                <div class="col-md-2"><input type="text" name="item_condition" class="form-control form-control-sm" value="{$copy.item_condition|default:''}"></div>
                                <div class="col-md-2"><input type="text" name="storage_location" class="form-control form-control-sm" value="{$copy.storage_location|default:''}"></div>
                                <div class="col-md-2 d-grid"><button type="submit" class="btn btn-sm btn-outline-secondary">Aktualisieren</button></div>
                            </form>
                        {foreachelse}
                            <p class="text-secondary mb-0">Noch keine Exemplare erfasst.</p>
                        {/foreach}
                    </div>
                </div>
            {/if}
        </div>

        <div class="col-xl-4">
            <div class="panel-card">
                <h2 class="h4 mb-3">Poster</h2>
                {if $title_item.poster_path|default:''}
                    <img class="poster-preview" src="{asset path=$title_item.poster_path}" alt="Poster">
                {else}
                    <div class="poster-placeholder">Noch kein Poster vorhanden</div>
                {/if}
            </div>
            {if $title_item && 'metadata.enrich'|has_permission:$permissions}
                <div class="panel-card mt-4">
                    <h2 class="h4 mb-3">Metadaten-Treffer</h2>
                    <div class="metadata-results js-metadata-results">
                        <p class="text-secondary mb-0">Mit dem Button oben lassen sich TMDb- und Wikidata-Treffer laden.</p>
                    </div>
                </div>
            {/if}
        </div>
    </div>
{/block}
