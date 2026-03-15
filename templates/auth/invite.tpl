{extends file='layouts/base.tpl'}

{block name=title} - Einladung annehmen{/block}

{block name=content}
    <div class="auth-shell auth-shell--single">
        <section class="panel-card">
            <p class="eyebrow">Einladung</p>
            <h1 class="h3 mb-3">Konto fuer {$invitation.email} aktivieren</h1>
            <p class="text-secondary">Rollen: {foreach $invitation.roles as $role}{$role.name}{if !$role@last}, {/if}{/foreach}</p>
            <form method="post" action="{route name='invite.submit' token=$token}" class="vstack gap-3 mt-4">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <div>
                    <label class="form-label">Anzeigename</label>
                    <input type="text" name="display_name" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Passwort</label>
                    <input type="password" name="password" class="form-control" minlength="{$minimum_password_length}" required>
                </div>
                <div>
                    <label class="form-label">Passwort bestaetigen</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="{$minimum_password_length}" required>
                </div>
                <button type="submit" class="btn btn-primary">Konto aktivieren</button>
            </form>
        </section>
    </div>
{/block}
