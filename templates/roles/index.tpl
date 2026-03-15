{extends file='layouts/base.tpl'}

{block name=title} - Rollen{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Rollen</p>
            <h1 class="display-6">RBAC fuer die Sammlung</h1>
        </div>
    </section>

    <div class="panel-card mb-4">
        <form method="get" action="{route name='roles.index'}" class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label">Suche</label>
                <input type="text" name="q" class="form-control" value="{$filters.q}" placeholder="Rollenname oder Beschreibung">
            </div>
            <div class="col-md-3">
                <label class="form-label">Typ</label>
                <select name="type" class="form-select">
                    <option value="">Alle</option>
                    <option value="system" {if $filters.type === 'system'}selected{/if}>Systemrollen</option>
                    <option value="custom" {if $filters.type === 'custom'}selected{/if}>Benutzerrollen</option>
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
                <h2 class="h4 mb-3">Neue Rolle</h2>
                <form method="post" action="{route name='roles.store'}" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="text" name="name" class="form-control" placeholder="Rollenname" required>
                    <textarea name="description" class="form-control" rows="3" placeholder="Kurzbeschreibung"></textarea>
                    <select name="permissions[]" class="form-select" multiple size="10">
                        {foreach $permissions_list as $permission}
                            <option value="{$permission.name}">{$permission.name} - {$permission.description}</option>
                        {/foreach}
                    </select>
                    <button type="submit" class="btn btn-primary">Rolle anlegen</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <form id="roles-bulk-form" method="post" action="{route name='roles.bulk.preview'}" class="panel-card bulk-toolbar mb-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="filters[q]" value="{$filters.q}">
                <input type="hidden" name="filters[type]" value="{$filters.type}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Bulk-Aktion</label>
                        <select name="action" class="form-select js-bulk-action" data-bulk-group="roles" required>
                            <option value="">Bitte waehlen</option>
                            <option value="add_permissions">Berechtigungen hinzufuegen</option>
                            <option value="remove_permissions">Berechtigungen entfernen</option>
                            <option value="delete_roles">Rollen loeschen</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Anwenden auf</label>
                        <select name="selection_mode" class="form-select">
                            <option value="ids">Ausgewaehlte Rollen</option>
                            <option value="filtered">Alle aktuellen Treffer</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Vorschau anzeigen</button>
                    </div>
                    <div class="col-12 js-bulk-fields" data-bulk-group="roles" data-actions="add_permissions,remove_permissions" hidden>
                        <label class="form-label">Berechtigungen</label>
                        <select name="payload[permission_names][]" class="form-select" multiple size="6">
                            {foreach $permissions_list as $permission}
                                <option value="{$permission.name}">{$permission.name} - {$permission.description}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input js-bulk-master" type="checkbox" value="1" data-bulk-group="roles" id="roles-bulk-master">
                            <label class="form-check-label" for="roles-bulk-master">Sichtbare Rollen markieren</label>
                        </div>
                    </div>
                </div>
            </form>

            <div class="vstack gap-4">
                {foreach $roles_list as $role}
                    <div class="panel-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div class="small text-secondary">{if $role.is_system}Systemrolle{else}Benutzerrolle{/if}</div>
                            <div class="form-check">
                                <input class="form-check-input js-bulk-item" type="checkbox" name="selected_ids[]" value="{$role.id}" data-bulk-group="roles" form="roles-bulk-form">
                            </div>
                        </div>
                        <form method="post" action="{route name='roles.update' id=$role.id}" class="vstack gap-3">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="row g-2">
                                <div class="col-md-6"><input type="text" name="name" class="form-control" value="{$role.name}"></div>
                                <div class="col-md-6"><input type="text" name="description" class="form-control" value="{$role.description|default:''}"></div>
                            </div>
                            <select name="permissions[]" class="form-select" multiple size="10">
                                {foreach $permissions_list as $permission}
                                    <option value="{$permission.name}" {if $permission.name|contains:$role.permission_names}selected{/if}>{$permission.name} - {$permission.description}</option>
                                {/foreach}
                            </select>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-secondary">Aktualisieren</button>
                            </div>
                        </form>
                    </div>
                {foreachelse}
                    <div class="panel-card"><p class="text-secondary mb-0">Noch keine Rollen vorhanden.</p></div>
                {/foreach}
            </div>
        </div>
    </div>
{/block}
