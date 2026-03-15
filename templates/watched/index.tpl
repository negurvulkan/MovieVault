{extends file='layouts/base.tpl'}

{block name=title} - Watched-List{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Watched-List</p>
            <h1 class="display-6">Gesehene Titel und Rewatches</h1>
        </div>
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='watched.index'}" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">Suche</label>
                <input type="text" name="q" class="form-control" value="{$filters.q}" placeholder="Titel, Serie oder Notiz">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <h2 class="h4 mb-3">Neues Watch-Event</h2>
                <form method="post" action="{route name='watched.store'}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <select name="catalog_title_id" class="form-select" required>
                        <option value="">Titel waehlen</option>
                        {foreach $title_options as $option}
                            <option value="{$option.id}">{$option.label}</option>
                        {/foreach}
                    </select>
                    <input type="datetime-local" name="watched_at" class="form-control">
                    <textarea name="notes" class="form-control" rows="4" placeholder="Notiz oder Stimmung"></textarea>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <form id="watched-bulk-form" method="post" action="{route name='watched.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="filters[q]" value="{$filters.q}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Bulk-Aktion</label>
                        <select name="action" class="form-select" required>
                            <option value="">Bitte waehlen</option>
                            <option value="delete_events">Watch-Events loeschen</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Anwenden auf</label>
                        <select name="selection_mode" class="form-select">
                            <option value="ids">Ausgewaehlte Events</option>
                            <option value="filtered">Alle aktuellen Treffer</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="watched" id="watched-bulk-master">
                            <label class="form-check-label" for="watched-bulk-master">Sichtbare Events markieren</label>
                        </div>
                    </div>
                </div>
            </form>

            <div class="panel-card">
                <h2 class="h4 mb-3">Historie</h2>
                <div class="vstack gap-3">
                    {foreach $events as $event}
                        <div class="list-card list-card--stack">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <strong>{$event.title}</strong>
                                    <div class="text-secondary small">
                                        {if $event.series_title|default:''}{$event.series_title|default:$event.title} - Staffel {$event.season_number|default:'?'}{else}{$event.kind}{/if}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div>{$event.watched_at|fmt_date}</div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$event.id}" data-bulk-group="watched" form="watched-bulk-form">
                                    </div>
                                </div>
                            </div>
                            {if $event.notes}<p class="mb-0 text-secondary">{$event.notes}</p>{/if}
                            <form method="post" action="{route name='watched.delete' id=$event.id}">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <button type="submit" class="btn btn-sm btn-outline-danger mt-2">Loeschen</button>
                            </form>
                        </div>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Watch-Events vorhanden.</p>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>
{/block}
