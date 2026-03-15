{extends file='layouts/base.tpl'}

{block name=title} - Dashboard{/block}

{block name=content}
    <section class="hero-card mb-4">
        <p class="eyebrow">Dashboard</p>
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-end">
            <div>
                <h1 class="display-6 mb-2">Bestand, Watch-Historie und der naechste Filmabend.</h1>
                <p class="lead text-secondary mb-0">Die wichtigsten Zahlen und ein spontaner Vorschlag aus deiner Sammlung.</p>
            </div>
            {if $quick_suggestion}
                <div class="suggestion-callout suggestion-callout--poster">
                    <div class="suggestion-callout__poster">
                        {if $quick_suggestion.poster_path|default:''}
                            <img src="{asset path=$quick_suggestion.poster_path}" alt="{$quick_suggestion.title}">
                        {else}
                            <div class="suggestion-callout__placeholder">Kein Cover</div>
                        {/if}
                    </div>
                    <div>
                        <span class="badge text-bg-warning mb-2">Schnellvorschlag</span>
                        <strong>{$quick_suggestion.title}</strong>
                        <span>{$quick_suggestion.genres|join_list}</span>
                    </div>
                </div>
            {/if}
        </div>
    </section>

    <section class="stats-grid mb-4">
        <article class="stat-card"><span>Titel</span><strong>{$dashboard.title_count}</strong></article>
        <article class="stat-card"><span>Exemplare</span><strong>{$dashboard.copy_count}</strong></article>
        <article class="stat-card"><span>Filme</span><strong>{$dashboard.movie_count}</strong></article>
        <article class="stat-card"><span>Staffeln</span><strong>{$dashboard.season_count}</strong></article>
        <article class="stat-card"><span>Watch-Events</span><strong>{$dashboard.watched_count}</strong></article>
        <article class="stat-card"><span>Ungelesen fuer dich</span><strong>{$dashboard.unwatched_count}</strong></article>
        <article class="stat-card"><span>Watchtime</span><strong>{$dashboard.watchtime_minutes|minutes_to_hours}</strong></article>
        <article class="stat-card"><span>Metadaten gepflegt</span><strong>{$dashboard.metadata_coverage}</strong></article>
    </section>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="panel-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Zuletzt hinzugefuegt</h2>
                    <a href="{route name='catalog.index'}" class="btn btn-sm btn-outline-secondary">Zum Katalog</a>
                </div>
                <div class="vstack gap-3">
                    {foreach $dashboard.recent_titles as $title}
                        <div class="list-card">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="cover-thumb">
                                    {if $title.poster_path|default:''}
                                        <img src="{asset path=$title.poster_path}" alt="{$title.title}">
                                    {else}
                                        <div class="cover-thumb__placeholder">Kein Cover</div>
                                    {/if}
                                </div>
                                <div>
                                    <strong>{$title.title}</strong>
                                    <div class="text-secondary small">{if $title.kind === 'season'}{$title.series_title|default:$title.title} - Staffel {$title.season_number|default:'?'}{else}{$title.year|default:'Film'}{/if}</div>
                                </div>
                            </div>
                            <div class="text-secondary small">{$title.genres|join_list}</div>
                        </div>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Titel vorhanden.</p>
                    {/foreach}
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="panel-card mb-4">
                <h2 class="h4 mb-3">Top-Genres</h2>
                <div class="vstack gap-2">
                    {foreach $dashboard.top_genres as $genre}
                        <div class="list-card">
                            <strong>{$genre.name}</strong>
                            <span class="badge text-bg-dark">{$genre.count}</span>
                        </div>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Genres vorhanden.</p>
                    {/foreach}
                </div>
            </div>
            <div class="panel-card">
                <h2 class="h4 mb-3">Zuletzt gesehen</h2>
                    {if $dashboard.last_watch}
                    <strong>{$dashboard.last_watch.title}</strong>
                    <p class="text-secondary mb-1">{$dashboard.last_watch.watched_at|fmt_date}</p>
                    {if $dashboard.last_watch.series_title|default:''}
                        <p class="mb-0">{$dashboard.last_watch.series_title|default:$dashboard.last_watch.title} - Staffel {$dashboard.last_watch.season_number|default:'?'}</p>
                    {/if}
                {else}
                    <p class="text-secondary mb-0">Noch kein Watch-Event vorhanden.</p>
                {/if}
            </div>
        </div>
    </div>
{/block}
