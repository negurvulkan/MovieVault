{extends file='layouts/base.tpl'}

{block name=title} - Benutzer{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Benutzer</p>
            <h1 class="display-6">Einladungen und Rollenzuweisungen</h1>
        </div>
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='users.index'}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Benutzersuche</label>
                <input type="text" name="q" class="form-control" value="{$user_filters.q}" placeholder="Name oder E-Mail">
            </div>
            <div class="col-md-3">
                <label class="form-label">Benutzerstatus</label>
                <select name="status" class="form-select">
                    <option value="">Alle</option>
                    <option value="active" {if $user_filters.status === 'active'}selected{/if}>Aktiv</option>
                    <option value="inactive" {if $user_filters.status === 'inactive'}selected{/if}>Inaktiv</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Einladungen</label>
                <select name="state" class="form-select">
                    <option value="">Alle</option>
                    <option value="open" {if $invitation_filters.state === 'open'}selected{/if}>Offen</option>
                    <option value="accepted" {if $invitation_filters.state === 'accepted'}selected{/if}>Angenommen</option>
                    <option value="expired" {if $invitation_filters.state === 'expired'}selected{/if}>Abgelaufen</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>

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
            <form id="users-bulk-form" method="post" action="{route name='users.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="filters[q]" value="{$user_filters.q}">
                <input type="hidden" name="filters[status]" value="{$user_filters.status}">
                <input type="hidden" name="state_filter" value="{$invitation_filters.state}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Bulk-Aktion</label>
                        <select name="action" class="form-select js-bulk-action" data-bulk-group="users" required>
                            <option value="">Bitte waehlen</option>
                            <option value="activate_users">Benutzer aktivieren</option>
                            <option value="deactivate_users">Benutzer deaktivieren</option>
                            <option value="delete_users">Benutzer loeschen</option>
                            <option value="add_role">Rolle hinzufuegen</option>
                            <option value="remove_role">Rolle entfernen</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Anwenden auf</label>
                        <select name="selection_mode" class="form-select">
                            <option value="ids">Ausgewaehlte Benutzer</option>
                            <option value="filtered">Alle aktuellen Treffer</option>
                        </select>
                    </div>
                    <div class="col-md-3 js-bulk-fields" data-bulk-group="users" data-actions="add_role,remove_role" hidden>
                        <label class="form-label">Rolle</label>
                        <select name="payload[role_id]" class="form-select">
                            <option value="">Bitte waehlen</option>
                            {foreach $roles_list as $role}
                                <option value="{$role.id}">{$role.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="users" id="users-bulk-master">
                            <label class="form-check-label" for="users-bulk-master">Sichtbare Benutzer markieren</label>
                        </div>
                    </div>
                </div>
            </form>

            <div class="panel-card mb-4">
                <h2 class="h4 mb-3">Aktive Benutzer</h2>
                <div class="vstack gap-3">
                    {foreach $users_list as $user}
                        <form method="post" action="{route name='users.update' id=$user.id}" class="panel-subcard">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="d-flex justify-content-between gap-3 mb-3">
                                <div class="small text-secondary">{if $user.is_active}Aktiv{else}Inaktiv{/if}</div>
                                <div class="form-check">
                                    <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$user.id}" data-bulk-group="users" form="users-bulk-form">
                                </div>
                            </div>
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

            <form id="invitation-bulk-form" method="post" action="{route name='users.invitations.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="filters[state]" value="{$invitation_filters.state}">
                <input type="hidden" name="user_q_filter" value="{$user_filters.q}">
                <input type="hidden" name="user_status_filter" value="{$user_filters.status}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Einladungs-Bulk</label>
                        <select name="action" class="form-select" required>
                            <option value="">Bitte waehlen</option>
                            <option value="revoke_invitations">Einladungen widerrufen</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Anwenden auf</label>
                        <select name="selection_mode" class="form-select">
                            <option value="ids">Ausgewaehlte Einladungen</option>
                            <option value="filtered">Alle aktuellen Treffer</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="invitations" id="invitations-bulk-master">
                            <label class="form-check-label" for="invitations-bulk-master">Offene Einladungen markieren</label>
                        </div>
                    </div>
                </div>
            </form>

            <div class="panel-card">
                <h2 class="h4 mb-3">Einladungen</h2>
                <div class="vstack gap-3">
                    {foreach $invitations as $invitation}
                        <div class="list-card list-card--stack">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <strong>{$invitation.email}</strong>
                                    <div class="small text-secondary">Erstellt {$invitation.created_at|fmt_date} - Gueltig bis {$invitation.expires_at|fmt_date:'d.m.Y'}</div>
                                </div>
                                <div class="text-end">
                                    <div>
                                        {if $invitation.accepted_at}
                                            <span class="badge text-bg-success">angenommen</span>
                                        {elseif $invitation.is_open}
                                            <span class="badge text-bg-warning">offen</span>
                                        {else}
                                            <span class="badge text-bg-secondary">abgelaufen</span>
                                        {/if}
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$invitation.id}" data-bulk-group="invitations" form="invitation-bulk-form" {if !$invitation.is_open}disabled{/if}>
                                    </div>
                                </div>
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
