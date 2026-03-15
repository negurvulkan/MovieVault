{extends file='layouts/base.tpl'}

{block name=title} - CSV-Import{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">CSV-Import</p>
            <h1 class="display-6">Vorlage, Mapping und Vorschau</h1>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <h2 class="h4 mb-3">1. Datei hochladen</h2>
                <form method="post" action="{route name='import.upload'}" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-primary">CSV laden</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel-card">
                <h2 class="h4 mb-3">2. Spalten zuordnen</h2>
                {if $upload_state}
                    <form method="post" action="{route name='import.preview'}" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>CSV-Spalte</th>
                                        <th>Ziel-Feld</th>
                                        <th>Beispielwerte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach $upload_state.headers as $headerIndex => $header}
                                        <tr>
                                            <td><strong>{$header}</strong></td>
                                            <td>
                                                <select name="mapping[{$header}]" class="form-select form-select-sm">
                                                    <option value="">Ignorieren</option>
                                                    {foreach $canonical_fields as $field}
                                                        <option value="{$field}" {if ($upload_state.auto_mapping[$header]|default:'') === $field}selected{/if}>{$field}</option>
                                                    {/foreach}
                                                </select>
                                            </td>
                                            <td class="small text-secondary">
                                                {foreach $upload_state.sample_rows as $sample}
                                                    {$sample[$headerIndex]|default:''}{if !$sample@last} · {/if}
                                                {/foreach}
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-secondary">Vorschau erzeugen</button>
                        </div>
                    </form>
                {else}
                    <p class="text-secondary mb-0">Noch keine Datei geladen.</p>
                {/if}
            </div>
        </div>
    </div>

    {if $preview_state}
        <div class="panel-card mt-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">3. Vorschau</h2>
                    <p class="text-secondary mb-0">{$preview_state.summary.row_count} Zeilen, {$preview_state.summary.error_count} Fehler, {$preview_state.summary.warning_count} Hinweise</p>
                </div>
                <form method="post" action="{route name='import.commit'}">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <button type="submit" class="btn btn-primary" {if $preview_state.summary.error_count > 0}disabled{/if}>Import uebernehmen</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Zeile</th>
                            <th>Titel</th>
                            <th>Typ</th>
                            <th>Hinweise</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $preview_state.rows as $row}
                            <tr>
                                <td>{$row.line}</td>
                                <td>{$row.data.title|default:'-'}</td>
                                <td>{$row.data.kind|default:'movie'}</td>
                                <td>
                                    {foreach $row.errors as $message}<span class="badge text-bg-danger me-1">{$message}</span>{/foreach}
                                    {foreach $row.warnings as $message}<span class="badge text-bg-warning me-1">{$message}</span>{/foreach}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}
{/block}
