{extends file='layouts/base.tpl'}

{block name=title} - Benutzer{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Benutzer</p>
            <h1 class="display-6">Einladungen und Rollenzuweisungen</h1>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <h2 class="h4 mb-3">Einladung erstellen</h2>
                <form method="post" action="{route name='users.invitation.store'}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="email" name="email" class="form-control" placeholder="E-Mail" required>
                    <select name="role_ids[]" class="form-select" multiple size="6" required>
                        {foreach $roles_list as $role}
                            <option value="{$role.id}">{$role.name}</option>
                        {/foreach}
                    </select>
                    <input type="number" name="invite_ttl_days" min="1" max="90" class="form-control" value="14">
                    <button type="submit" class="btn btn-primary">Einladung anlegen</button>
                </form>
                <p class="small text-secondary mt-3 mb-0">Der erzeugte Link erscheint als Flash-Nachricht und kann manuell geteilt werden.</p>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel-card mb-4">
                <h2 class="h4 mb-3">Aktive Benutzer</h2>
                <div class="vstack gap-3">
                    {foreach $users_list as $user}
                        <form method="post" action="{route name='users.update' id=$user.id}" class="panel-subcard">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="row g-2">
                                <div class="col-md-4"><input type="text" name="display_name" class="form-control" value="{$user.display_name}"></div>
                                <div class="col-md-4"><input type="email" class="form-control" value="{$user.email}" disabled></div>
                                <div class="col-md-2">
                                    <div class="form-check pt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {if $user.is_active}checked{/if}>
                                        <label class="form-check-label">Aktiv</label>
                                    </div>
                                </div>
                                <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-secondary">Speichern</button></div>
                                <div class="col-12">
                                    <select name="role_ids[]" class="form-select" multiple size="4">
                                        {foreach $roles_list as $role}
                                            <option value="{$role.id}" {if $role.id|contains:$user.role_ids}selected{/if}>{$role.name}</option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </form>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Benutzer angelegt.</p>
                    {/foreach}
                </div>
            </div>

            <div class="panel-card">
                <h2 class="h4 mb-3">Einladungen</h2>
                <div class="vstack gap-3">
                    {foreach $invitations as $invitation}
                        <div class="list-card list-card--stack">
                            <strong>{$invitation.email}</strong>
                            <div class="small text-secondary">Erstellt {$invitation.created_at|fmt_date} · Gueltig bis {$invitation.expires_at|fmt_date:'d.m.Y'}</div>
                            <div>
                                {if $invitation.accepted_at}
                                    <span class="badge text-bg-success">angenommen</span>
                                {else}
                                    <span class="badge text-bg-warning">offen</span>
                                {/if}
                            </div>
                        </div>
                    {foreachelse}
                        <p class="text-secondary mb-0">Noch keine Einladungen vorhanden.</p>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>
{/block}
