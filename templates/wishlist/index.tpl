{extends file='layouts/base.tpl'}

{block name=title} - Wunschliste{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Wunschliste</p>
            <h1 class="display-6">Gemeinsame Einkaufslisten fuer Flohmarkt, Laden und spontane Funde</h1>
            <p class="text-secondary mb-0">Wuensche sammeln, teilen, priorisieren und bei Bedarf direkt in die Sammlung uebernehmen.</p>
        </div>
        {if 'wishlist.create'|has_permission:$permissions}
            <a class="btn btn-primary" href="{route name='wishlist.create' list_id=$filters.list_id}">Neuen Wunsch anlegen</a>
        {/if}
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='wishlist.index'}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Suche</label>
                <input type="text" name="q" class="form-control" value="{$filters.q}" placeholder="Titel, Serie, Liste oder Notiz">
            </div>
            <div class="col-md-2">
                <label class="form-label">Liste</label>
                <select name="list_id" class="form-select">
                    <option value="">Alle</option>
                    {foreach $wish_lists as $list}
                        <option value="{$list.id}" {if $filters.list_id == $list.id}selected{/if}>{$list.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Alle</option>
                    <option value="open" {if $filters.status === 'open'}selected{/if}>Offen</option>
                    <option value="reserved" {if $filters.status === 'reserved'}selected{/if}>Reserviert</option>
                    <option value="bought" {if $filters.status === 'bought'}selected{/if}>Gekauft</option>
                    <option value="dropped" {if $filters.status === 'dropped'}selected{/if}>Verworfen</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Prioritaet</label>
                <select name="priority" class="form-select">
                    <option value="">Alle</option>
                    <option value="high" {if $filters.priority === 'high'}selected{/if}>Hoch</option>
                    <option value="medium" {if $filters.priority === 'medium'}selected{/if}>Mittel</option>
                    <option value="low" {if $filters.priority === 'low'}selected{/if}>Niedrig</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Format</label>
                <select name="target_format" class="form-select">
                    <option value="">Alle</option>
                    <option value="dvd" {if $filters.target_format === 'dvd'}selected{/if}>DVD</option>
                    <option value="bluray" {if $filters.target_format === 'bluray'}selected{/if}>Blu-ray</option>
                    <option value="any" {if $filters.target_format === 'any'}selected{/if}>Egal</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Typ</label>
                <select name="kind" class="form-select">
                    <option value="">Alle</option>
                    <option value="movie" {if $filters.kind === 'movie'}selected{/if}>Film</option>
                    <option value="season" {if $filters.kind === 'season'}selected{/if}>Staffel</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Ansicht</label>
                <select name="view" class="form-select">
                    <option value="list" {if $filters.view === 'list'}selected{/if}>Liste</option>
                    <option value="cards" {if $filters.view === 'cards'}selected{/if}>Karten</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            {if 'wishlist.create'|has_permission:$permissions}
                <div class="panel-card mb-4">
                    <h2 class="h4 mb-3">Neue gemeinsame Liste</h2>
                    <form method="post" action="{route name='wishlist.lists.store'}" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <input type="text" name="name" class="form-control" placeholder="z. B. Flohmarkt Samstag" required>
                        <textarea name="description" class="form-control" rows="3" placeholder="Wofuer ist diese Liste gedacht?"></textarea>
                        <select name="member_ids[]" class="form-select" multiple size="6">
                            {foreach $active_users as $user}
                                <option value="{$user.id}" {if $user.id == $current_user.id}selected{/if}>{$user.display_name} ({$user.email})</option>
                            {/foreach}
                        </select>
                        <button type="submit" class="btn btn-primary">Liste anlegen</button>
                    </form>
                </div>
            {/if}

            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Deine Einkaufslisten</h2>
                    {if $selected_list}
                        <span class="badge text-bg-dark">Aktiv: {$selected_list.name}</span>
                    {/if}
                </div>
                <div class="vstack gap-3">
                    {foreach $wish_lists as $list}
                        <form method="post" action="{route name='wishlist.lists.update' id=$list.id}" class="panel-subcard">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="d-flex justify-content-between gap-3 mb-2">
                                <a class="text-decoration-none fw-semibold" href="{route name='wishlist.index' list_id=$list.id view=$filters.view}">{$list.name}</a>
                                <span class="badge text-bg-warning">{$list.active_items_count} offen</span>
                            </div>
                            <p class="small text-secondary mb-2">{if $list.description}{$list.description}{else}Keine Beschreibung{/if}</p>
                            <div class="small text-secondary mb-3">Mitglieder: {$list.member_names|default:'-'}</div>
                            {if 'wishlist.edit'|has_permission:$permissions}
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text" name="name" class="form-control form-control-sm" value="{$list.name}">
                                    </div>
                                    <div class="col-12">
                                        <textarea name="description" class="form-control form-control-sm" rows="2">{$list.description}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <select name="member_ids[]" class="form-select form-select-sm" multiple size="5">
                                            {foreach $active_users as $user}
                                                <option value="{$user.id}" {if $user.id|contains:$list.member_ids}selected{/if}>{$user.display_name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Liste speichern</button>
                                    </div>
                                </div>
                            {/if}
                        </form>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Einkaufslisten vorhanden.</p>
                    {/foreach}
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <form id="wishlist-bulk-form" method="post" action="{route name='wishlist.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="filters[q]" value="{$filters.q}">
                <input type="hidden" name="filters[list_id]" value="{$filters.list_id}">
                <input type="hidden" name="filters[status]" value="{$filters.status}">
                <input type="hidden" name="filters[priority]" value="{$filters.priority}">
                <input type="hidden" name="filters[target_format]" value="{$filters.target_format}">
                <input type="hidden" name="filters[kind]" value="{$filters.kind}">
                <input type="hidden" name="filters[view]" value="{$filters.view}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Bulk-Aktion</label>
                        <select name="action" class="form-select js-bulk-action" data-bulk-group="wishlist" required>
                            <option value="">Bitte waehlen</option>
                            {if 'wishlist.edit'|has_permission:$permissions}
                                <option value="mark_reserved">Als reserviert markieren</option>
                                <option value="mark_bought">Als gekauft markieren</option>
                                <option value="mark_dropped">Verwerfen</option>
                                <option value="change_priority">Prioritaet aendern</option>
                                <option value="change_target_format">Wunschformat aendern</option>
                            {/if}
                            {if 'wishlist.convert'|has_permission:$permissions}
                                <option value="convert_to_catalog">In Sammlung uebernehmen</option>
                            {/if}
                            {if 'wishlist.delete'|has_permission:$permissions}
                                <option value="delete_wishes">Eintraege loeschen</option>
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
                    <div class="col-md-3 js-bulk-fields" data-bulk-group="wishlist" data-actions="change_priority" hidden>
                        <label class="form-label">Neue Prioritaet</label>
                        <select name="payload[priority]" class="form-select">
                            <option value="">Bitte waehlen</option>
                            <option value="high">Hoch</option>
                            <option value="medium">Mittel</option>
                            <option value="low">Niedrig</option>
                        </select>
                    </div>
                    <div class="col-md-3 js-bulk-fields" data-bulk-group="wishlist" data-actions="change_target_format" hidden>
                        <label class="form-label">Neues Format</label>
                        <select name="payload[target_format]" class="form-select">
                            <option value="">Bitte waehlen</option>
                            <option value="dvd">DVD</option>
                            <option value="bluray">Blu-ray</option>
                            <option value="any">Egal</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau</button>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="wishlist" id="wishlist-bulk-master">
                            <label class="form-check-label" for="wishlist-bulk-master">Sichtbare Wunsch-Eintraege markieren</label>
                        </div>
                    </div>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Wunsch-Eintraege</h2>
                <span class="text-secondary small">{$wish_items|@count} Treffer</span>
            </div>

            {if $filters.view === 'cards'}
                <div class="catalog-grid">
                    {foreach $wish_items as $item}
                        <article class="catalog-card">
                            <div class="catalog-card__poster">
                                {if $item.poster_path|default:''}
                                    <img class="catalog-card__image" src="{asset path=$item.poster_path}" alt="{$item.title}">
                                {else}
                                    <div class="catalog-card__placeholder">Kein Cover</div>
                                {/if}
                                <div class="catalog-card__bulk">
                                    <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$item.id}" data-bulk-group="wishlist" form="wishlist-bulk-form">
                                </div>
                            </div>
                            <div class="catalog-card__body">
                                <div class="d-flex justify-content-between gap-3 align-items-start mb-2">
                                    <div>
                                        <h2 class="h5 mb-1">{$item.title}</h2>
                                        <div class="text-secondary small">
                                            {if $item.kind === 'season'}
                                                {$item.series_title|default:$item.title} - Staffel {$item.season_number|default:'?'}
                                            {else}
                                                {$item.year|default:'Film'}
                                            {/if}
                                        </div>
                                    </div>
                                    <span class="badge text-bg-dark">{$item.list_name}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge text-bg-light border">{$item.status}</span>
                                    <span class="badge text-bg-light border">{$item.priority}</span>
                                    <span class="badge text-bg-light border">{if $item.target_format === 'bluray'}Blu-ray{elseif $item.target_format === 'any'}Egal{else}DVD{/if}</span>
                                    {foreach $item.genres as $genre}
                                        <span class="badge text-bg-light border">{$genre}</span>
                                    {/foreach}
                                </div>
                                {if $item.reserved_by_name|default:''}
                                    <p class="small text-secondary mb-2">Reserviert von {$item.reserved_by_name}</p>
                                {/if}
                                {if $item.notes}<p class="small text-secondary mb-3">{$item.notes}</p>{/if}
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="{route name='wishlist.edit' id=$item.id list_id=$filters.list_id status=$filters.status priority=$filters.priority target_format=$filters.target_format kind=$filters.kind q=$filters.q view=$filters.view}">Bearbeiten</a>
                                    {if 'wishlist.edit'|has_permission:$permissions}
                                        <form method="post" action="{route name='wishlist.reserve' id=$item.id}">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="return_url" value="{$current_url}">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Reservieren</button>
                                        </form>
                                        <form method="post" action="{route name='wishlist.buy' id=$item.id}">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="return_url" value="{$current_url}">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Gekauft</button>
                                        </form>
                                        <form method="post" action="{route name='wishlist.drop' id=$item.id}">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="return_url" value="{$current_url}">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Verwerfen</button>
                                        </form>
                                    {/if}
                                    {if 'wishlist.convert'|has_permission:$permissions}
                                        <form method="post" action="{route name='wishlist.convert' id=$item.id}">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="return_url" value="{$current_url}">
                                            <button type="submit" class="btn btn-sm btn-outline-dark" {if $item.is_converted}disabled{/if}>In Sammlung</button>
                                        </form>
                                    {/if}
                                </div>
                            </div>
                        </article>
                    {foreachelse}
                        <div class="panel-card text-center text-secondary py-4">Noch keine passenden Wunsch-Eintraege vorhanden.</div>
                    {/foreach}
                </div>
            {else}
                <div class="table-panel">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="bulk-check-col"></th>
                                <th>Titel</th>
                                <th>Liste</th>
                                <th>Status</th>
                                <th>Prioritaet</th>
                                <th>Format</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $wish_items as $item}
                                <tr>
                                    <td class="bulk-check-col">
                                        <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$item.id}" data-bulk-group="wishlist" form="wishlist-bulk-form">
                                    </td>
                                    <td>
                                        <div class="catalog-row-title">
                                            <div class="cover-thumb cover-thumb--sm">
                                                {if $item.poster_path|default:''}
                                                    <img src="{asset path=$item.poster_path}" alt="{$item.title}">
                                                {else}
                                                    <div class="cover-thumb__placeholder">Kein Cover</div>
                                                {/if}
                                            </div>
                                            <div>
                                                <strong>{$item.title}</strong>
                                                <div class="text-secondary small">
                                                    {if $item.kind === 'season'}
                                                        {$item.series_title|default:$item.title} - Staffel {$item.season_number|default:'?'}
                                                    {else}
                                                        {$item.year|default:'Film'}
                                                    {/if}
                                                </div>
                                                {if $item.reserved_by_name|default:''}
                                                    <div class="small text-secondary">Reserviert von {$item.reserved_by_name}</div>
                                                {/if}
                                            </div>
                                        </div>
                                    </td>
                                    <td>{$item.list_name}</td>
                                    <td><span class="badge text-bg-light border">{$item.status}</span></td>
                                    <td><span class="badge text-bg-light border">{$item.priority}</span></td>
                                    <td>{if $item.target_format === 'bluray'}Blu-ray{elseif $item.target_format === 'any'}Egal{else}DVD{/if}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{route name='wishlist.edit' id=$item.id list_id=$filters.list_id status=$filters.status priority=$filters.priority target_format=$filters.target_format kind=$filters.kind q=$filters.q view=$filters.view}">Bearbeiten</a>
                                            {if 'wishlist.edit'|has_permission:$permissions}
                                                <form method="post" action="{route name='wishlist.reserve' id=$item.id}">
                                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                                    <input type="hidden" name="return_url" value="{$current_url}">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Reservieren</button>
                                                </form>
                                                <form method="post" action="{route name='wishlist.buy' id=$item.id}">
                                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                                    <input type="hidden" name="return_url" value="{$current_url}">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Gekauft</button>
                                                </form>
                                            {/if}
                                            {if 'wishlist.convert'|has_permission:$permissions}
                                                <form method="post" action="{route name='wishlist.convert' id=$item.id}">
                                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                                    <input type="hidden" name="return_url" value="{$current_url}">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark" {if $item.is_converted}disabled{/if}>In Sammlung</button>
                                                </form>
                                            {/if}
                                        </div>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">Noch keine passenden Wunsch-Eintraege vorhanden.</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>
{/block}
