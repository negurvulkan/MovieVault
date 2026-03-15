{extends file='layouts/base.tpl'}

{block name=title} - Login{/block}

{block name=content}
    <div class="auth-shell">
        <section class="hero-card hero-card--auth">
            <p class="eyebrow">MovieVault</p>
            <h1 class="display-5 mb-3">Deine Medien, sauber organisiert.</h1>
            <p class="lead text-secondary">Verwalte Filme, Staffeln, Editionen, Watch-Historie und spontane Filmabende in einer kompakten Bibliothek.</p>
            <div class="feature-grid mt-4">
                <div class="feature-chip">DVD und Blu-ray</div>
                <div class="feature-chip">CSV-Import</div>
                <div class="feature-chip">Watch-Tracking</div>
                <div class="feature-chip">Vorschlaege nach Genre</div>
            </div>
        </section>
        <section class="panel-card">
            <h2 class="h4 mb-3">Anmelden</h2>
            <form method="post" action="{route name='login.submit'}" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <div>
                    <label class="form-label">E-Mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Passwort</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Einloggen</button>
            </form>
        </section>
    </div>
{/block}
