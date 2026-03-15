{extends file='layouts/base.tpl'}

{block name=title} - Bulk-Vorschau{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Bulk-Vorschau</p>
            <h1 class="display-6">{$preview.action_label}</h1>
            <p class="text-secondary mb-0">{$module_label}: {$preview.summary.affected} betroffen, {$preview.summary.executable} ausfuehrbar, {$preview.summary.skipped} uebersprungen.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{$back_url}">Zurueck</a>
    </section>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <h2 class="h4 mb-3">Bestaetigung</h2>
                {if $preview.payload_lines}
                    <div class="vstack gap-2 mb-3">
                        {foreach $preview.payload_lines as $line}
                            <div class="small text-secondary">{$line}</div>
                        {/foreach}
                    </div>
                {/if}

                {if $preview.warnings}
                    <div class="vstack gap-2 mb-3">
                        {foreach $preview.warnings as $warning}
                            <div class="alert alert-warning mb-0">{$warning}</div>
                        {/foreach}
                    </div>
                {/if}

                <form method="post" action="{$execute_url}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="preview_token" value="{$preview_token}">
                    <button type="submit" class="btn btn-primary" {if $preview.summary.executable <= 0}disabled{/if}>Jetzt ausfuehren</button>
                    <a class="btn btn-outline-secondary" href="{$back_url}">Abbrechen</a>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="table-panel">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Eintrag</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Hinweis</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $preview.items as $item}
                            <tr>
                                <td><strong>{$item.label}</strong></td>
                                <td class="text-secondary small">{$item.detail|default:'-'}</td>
                                <td>
                                    {if $item.status === 'ready'}
                                        <span class="badge text-bg-success">bereit</span>
                                    {else}
                                        <span class="badge text-bg-secondary">skip</span>
                                    {/if}
                                </td>
                                <td class="small">{$item.message|default:'-'}</td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-4">Keine Datensaetze in der Vorschau.</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
{/block}
