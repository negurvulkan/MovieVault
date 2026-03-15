{extends file='layouts/base.tpl'}

{block name=title} - Rollen{/block}

{block name=content}
    <section class="section-head">
        <div>
            <p class="eyebrow">Rollen</p>
            <h1 class="display-6">RBAC fuer die Sammlung</h1>
        </div>
    </section>

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
            <div class="vstack gap-4">
                {foreach $roles_list as $role}
                    <div class="panel-card">
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
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-secondary">{if $role.is_system}Systemrolle{else}Benutzerrolle{/if}</span>
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
