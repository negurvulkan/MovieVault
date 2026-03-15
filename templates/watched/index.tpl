{extends file='layouts/base.tpl'}

{block name=title} - Watched-List{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Watched-List</p>
            <h1 class="display-6">Gesehene Titel und Rewatches</h1>
        </div>
    </section>

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
            <div class="panel-card">
                <h2 class="h4 mb-3">Historie</h2>
                <div class="vstack gap-3">
                    {foreach $events as $event}
                        <div class="list-card list-card--stack">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <strong>{$event.title}</strong>
                                    <div class="text-secondary small">
                                        {if $event.series_title|default:''}{$event.series_title|default:$event.title} · Staffel {$event.season_number|default:'?'}{else}{$event.kind}{/if}
                                    </div>
                                </div>
                                <span>{$event.watched_at|fmt_date}</span>
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
