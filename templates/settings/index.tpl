{extends file='layouts/base.tpl'}

{block name=title} - Einstellungen{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Einstellungen</p>
            <h1 class="display-6">App-Grundlagen und Defaults</h1>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <form method="post" action="{route name='settings.update'}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <div>
                        <label class="form-label">App-Name</label>
                        <input type="text" name="app_name" class="form-control" value="{$settings_map.app_name|default:$app_name}">
                    </div>
                    <div>
                        <label class="form-label">Standardfilter fuer Vorschlaege</label>
                        <select name="default_recommendation_filter" class="form-select">
                            <option value="unwatched" {if ($settings_map.default_recommendation_filter|default:'unwatched') === 'unwatched'}selected{/if}>Nur ungesehene Titel</option>
                            <option value="all" {if ($settings_map.default_recommendation_filter|default:'') === 'all'}selected{/if}>Gesamter Bestand</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Einladung gueltig fuer Tage</label>
                        <input type="number" min="1" max="90" name="invite_ttl_days" class="form-control" value="{$settings_map.invite_ttl_days|default:'14'}">
                    </div>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <h2 class="h4 mb-3">Metadaten-Status</h2>
                <p class="mb-2">TMDb API-Key: {if $metadata_configured}<span class="badge text-bg-success">vorhanden</span>{else}<span class="badge text-bg-warning">fehlt</span>{/if}</p>
                <p class="text-secondary mb-0">Wikidata ist ohne API-Key verfuegbar. Fuer Poster-Downloads und TMDb-Abfragen sollte die Laufzeit `openssl` oder `curl` bereitstellen.</p>
            </div>
        </div>
    </div>
{/block}
