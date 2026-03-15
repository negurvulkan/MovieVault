{extends file='layouts/base.tpl'}

{block name=title} - {if $wish_item.id|default:false}Wunsch bearbeiten{else}Wunsch anlegen{/if}{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Wunschliste</p>
            <h1 class="display-6">{if $wish_item.id|default:false}Wunsch bearbeiten{else}Neuen Wunsch anlegen{/if}</h1>
            <p class="text-secondary mb-0">Ideal fuer gemeinsame Einkaufslisten, Flohmarktbesuche und spontane Ladenfunde.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{$back_url}">Zurueck</a>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="panel-card">
                <form method="post" action="{if $wish_item.id|default:false}{route name='wishlist.update' id=$wish_item.id}{else}{route name='wishlist.store'}{/if}" class="row g-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="metadata_status" value="{$wish_item.metadata_status|default:'manual'}">
                    <input type="hidden" name="poster_path" value="{$wish_item.poster_path|default:''}">

                    <div class="col-md-4">
                        <label class="form-label">Liste</label>
                        <select name="wish_list_id" class="form-select" required>
                            <option value="">Bitte waehlen</option>
                            {foreach $wish_lists as $list}
                                <option value="{$list.id}" {if $wish_item.wish_list_id == $list.id}selected{/if}>{$list.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Typ</label>
                        <select name="kind" class="form-select" required>
                            <option value="movie" {if $wish_item.kind|default:'movie' === 'movie'}selected{/if}>Film</option>
                            <option value="season" {if $wish_item.kind|default:'' === 'season'}selected{/if}>Staffel</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Wunschformat</label>
                        <select name="target_format" class="form-select">
                            <option value="dvd" {if $wish_item.target_format|default:'dvd' === 'dvd'}selected{/if}>DVD</option>
                            <option value="bluray" {if $wish_item.target_format|default:'' === 'bluray'}selected{/if}>Blu-ray</option>
                            <option value="any" {if $wish_item.target_format|default:'' === 'any'}selected{/if}>Egal</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prioritaet</label>
                        <select name="priority" class="form-select">
                            <option value="high" {if $wish_item.priority|default:'' === 'high'}selected{/if}>Hoch</option>
                            <option value="medium" {if $wish_item.priority|default:'medium' === 'medium'}selected{/if}>Mittel</option>
                            <option value="low" {if $wish_item.priority|default:'' === 'low'}selected{/if}>Niedrig</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Titel</label>
                        <input type="text" name="title" class="form-control" value="{$wish_item.title|default:''}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Originaltitel</label>
                        <input type="text" name="original_title" class="form-control" value="{$wish_item.original_title|default:''}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Jahr</label>
                        <input type="number" name="year" class="form-control" value="{$wish_item.year|default:''}" min="1900" max="2100">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Serie</label>
                        <input type="text" name="series_title" class="form-control" value="{$wish_item.series_title|default:''}" placeholder="Bei Staffeln erforderlich">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Staffel</label>
                        <input type="number" name="season_number" class="form-control" value="{$wish_item.season_number|default:''}" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="open" {if $wish_item.status|default:'open' === 'open'}selected{/if}>Offen</option>
                            <option value="reserved" {if $wish_item.status|default:'' === 'reserved'}selected{/if}>Reserviert</option>
                            <option value="bought" {if $wish_item.status|default:'' === 'bought'}selected{/if}>Gekauft</option>
                            <option value="dropped" {if $wish_item.status|default:'' === 'dropped'}selected{/if}>Verworfen</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Zielpreis</label>
                        <input type="text" name="target_price" class="form-control" value="{$wish_item.target_price|default:''}" placeholder="9,99">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gesehen fuer</label>
                        <input type="text" name="seen_price" class="form-control" value="{$wish_item.seen_price|default:''}" placeholder="7,50">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gekauft fuer</label>
                        <input type="text" name="bought_price" class="form-control" value="{$wish_item.bought_price|default:''}" placeholder="5,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Laufzeit (Min.)</label>
                        <input type="number" name="runtime_minutes" class="form-control" value="{$wish_item.runtime_minutes|default:''}" min="1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Laden / Haendler</label>
                        <input type="text" name="store_name" class="form-control" value="{$wish_item.store_name|default:''}" placeholder="z. B. DVD-Laden Musterstadt">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ort / Regal / Flohmarkt</label>
                        <input type="text" name="location" class="form-control" value="{$wish_item.location|default:''}" placeholder="z. B. Halle B, Stand 12">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Funddatum</label>
                        <input type="datetime-local" name="found_at" class="form-control" value="{$wish_item.found_at|default:''|replace:' ':'T'|truncate:16:''}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reserviert seit</label>
                        <input type="datetime-local" name="reserved_at" class="form-control" value="{$wish_item.reserved_at|default:''|replace:' ':'T'|truncate:16:''}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kaufdatum</label>
                        <input type="datetime-local" name="bought_at" class="form-control" value="{$wish_item.bought_at|default:''|replace:' ':'T'|truncate:16:''}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Genres</label>
                        <input type="text" name="genres_text" class="form-control" value="{$wish_item.genres|default:[]|join_list}" placeholder="Action, Sci-Fi, Drama">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="overview" class="form-control" rows="4">{$wish_item.overview|default:''}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notizen</label>
                        <textarea name="notes" class="form-control" rows="5" placeholder="Warum suchst du diesen Titel, Wunsch-Edition, Hinweise fuer Mitkaeufer ...">{$wish_item.notes|default:''}</textarea>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                        <a class="btn btn-outline-secondary" href="{$back_url}">Zurueck zur Liste</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="panel-card mb-4">
                <h2 class="h4 mb-3">Cover</h2>
                <div class="poster-preview">
                    {if $wish_item.poster_path|default:''}
                        <img src="{asset path=$wish_item.poster_path}" alt="{$wish_item.title|default:'Cover'}">
                    {else}
                        <div class="poster-preview__empty">Noch kein Cover vorhanden</div>
                    {/if}
                </div>
            </div>

            <div class="panel-card mb-4">
                <h2 class="h4 mb-3">Metadaten</h2>
                {if !('metadata.enrich'|has_permission:$permissions)}
                    <p class="text-secondary mb-0">Fuer das Anreichern aus oeffentlichen Quellen fehlt die Berechtigung.</p>
                {elseif $wish_item.id|default:false}
                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-outline-primary js-wishlist-metadata-search" data-wish-item-id="{$wish_item.id}">Oeffentliche Quellen durchsuchen</button>
                        <div class="js-wishlist-metadata-results"></div>
                    </div>
                {else}
                    <p class="text-secondary mb-0">Metadaten koennen nach dem ersten Speichern geladen werden.</p>
                {/if}
            </div>

            {if $wish_item.id|default:false}
                <div class="panel-card">
                    <h2 class="h4 mb-3">Schnellaktionen</h2>
                    <div class="d-grid gap-2">
                        {if 'wishlist.convert'|has_permission:$permissions}
                            <form method="post" action="{route name='wishlist.convert' id=$wish_item.id}">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <input type="hidden" name="return_url" value="{$back_url}">
                                <button type="submit" class="btn btn-outline-dark w-100" {if $wish_item.is_converted|default:false}disabled{/if}>In Sammlung uebernehmen</button>
                            </form>
                        {/if}
                        {if 'wishlist.delete'|has_permission:$permissions}
                            <form method="post" action="{route name='wishlist.delete' id=$wish_item.id}">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <button type="submit" class="btn btn-outline-danger w-100">Wunsch-Eintrag loeschen</button>
                            </form>
                        {/if}
                    </div>
                    {if $wish_item.converted_title|default:''}
                        <p class="small text-secondary mt-3 mb-0">Bereits uebernommen als: {$wish_item.converted_title}</p>
                    {/if}
                </div>
            {/if}
        </div>
    </div>
{/block}
