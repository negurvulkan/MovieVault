{extends file='layouts/base.tpl'}

{block name=title} - Vorschlaege{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Vorschlaege</p>
            <h1 class="display-6">Was schauen wir als Naechstes?</h1>
        </div>
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='suggestions.index'}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Modus</label>
                <select name="mode" class="form-select">
                    <option value="random" {if $query.mode === 'random'}selected{/if}>Zufall</option>
                    <option value="genre" {if $query.mode === 'genre'}selected{/if}>Nach Genre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Genre</label>
                <select name="genre" class="form-select">
                    <option value="">Bitte waehlen</option>
                    {foreach $genre_options as $genre}
                        <option value="{$genre.slug}" {if $query.genre === $genre.slug}selected{/if}>{$genre.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter</label>
                <select name="filter" class="form-select">
                    <option value="unwatched" {if $query.filter === 'unwatched'}selected{/if}>Nur ungesehen</option>
                    <option value="all" {if $query.filter === 'all'}selected{/if}>Gesamter Bestand</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Vorschlagen</button>
            </div>
        </form>
    </div>

    <div class="hero-card suggestion-hero js-suggestion-card" data-mode="{$query.mode}" data-genre="{$query.genre|default:''}" data-filter="{$query.filter}">
        {if $suggestion}
            <div class="suggestion-hero__media">
                {if $suggestion.poster_path|default:''}
                    <img class="suggestion-hero__poster" src="{asset path=$suggestion.poster_path}" alt="{$suggestion.title}">
                {else}
                    <div class="suggestion-hero__placeholder">Kein Cover</div>
                {/if}
            </div>
            <div class="suggestion-hero__body">
                <p class="eyebrow">Heute passt vielleicht</p>
                <h2 class="display-6 mb-3">{$suggestion.title}</h2>
                <p class="lead text-secondary">
                    {if $suggestion.kind === 'season'}{$suggestion.series_title|default:$suggestion.title} - Staffel {$suggestion.season_number|default:'?'}{else}{$suggestion.year|default:'Film'}{/if}
                </p>
                <p class="mb-4">{$suggestion.overview|default:'Keine Beschreibung vorhanden.'}</p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    {foreach $suggestion.genres as $genre}<span class="badge text-bg-dark">{$genre}</span>{/foreach}
                </div>
                <button type="button" class="btn btn-outline-secondary js-refresh-suggestion">Noch einen Vorschlag</button>
            </div>
        {else}
            <div class="suggestion-hero__body">
                <p class="eyebrow">Keine Treffer</p>
                <h2 class="h3">Fuer diese Kombination ist aktuell nichts verfuegbar.</h2>
            </div>
        {/if}
    </div>
{/block}
