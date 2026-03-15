{extends file='layouts/base.tpl'}

{block name=title} - Serien{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Serien</p>
            <h1 class="display-6">Serienstamm pflegen</h1>
        </div>
    </section>

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
            <div class="panel-card">
                <h2 class="h4 mb-3">Vorhandene Serien</h2>
                <div class="vstack gap-3">
                    {foreach $series_list as $series}
                        <form method="post" action="{route name='series.update' id=$series.id}" class="panel-subcard">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
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
