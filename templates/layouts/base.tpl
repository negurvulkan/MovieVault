<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$app_name}{block name=title}{/block}</title>
    <link rel="stylesheet" href="{asset path='vendor/bootstrap/css/bootstrap.min.css'}">
    <link rel="stylesheet" href="{asset path='assets/css/app.css'}">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <div class="container-fluid">
                <div class="topbar__inner">
                    <a class="brand" href="{if 'stats.view'|has_permission:$permissions}{route name='dashboard'}{else}{route name='login'}{/if}">
                        <span class="brand__mark">MV</span>
                        <span>
                            <strong>{$app_name}</strong>
                            <small>Filmschrank und Serienregal</small>
                        </span>
                    </a>
                    {if $current_user}
                        <div class="topbar__actions">
                            <span class="user-pill">{$current_user.display_name}</span>
                            <form method="post" action="{route name='logout'}" class="m-0">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <button type="submit" class="btn btn-outline-light btn-sm">Abmelden</button>
                            </form>
                        </div>
                    {/if}
                </div>
            </div>
        </header>

        <div class="layout-grid">
            {if $current_user}
                <aside class="sidebar">
                    <nav class="nav flex-column gap-2">
                        {if 'stats.view'|has_permission:$permissions}<a class="nav-link" href="{route name='dashboard'}">Dashboard</a>{/if}
                        {if 'catalog.view'|has_permission:$permissions}<a class="nav-link" href="{route name='catalog.index'}">Katalog</a>{/if}
                        {if 'catalog.view'|has_permission:$permissions}<a class="nav-link" href="{route name='series.index'}">Serien</a>{/if}
                        {if 'watched.manage'|has_permission:$permissions}<a class="nav-link" href="{route name='watched.index'}">Watched-List</a>{/if}
                        {if 'suggestions.use'|has_permission:$permissions}<a class="nav-link" href="{route name='suggestions.index'}">Vorschlaege</a>{/if}
                        {if 'import.run'|has_permission:$permissions}<a class="nav-link" href="{route name='import.index'}">CSV-Import</a>{/if}
                        {if 'users.manage'|has_permission:$permissions}<a class="nav-link" href="{route name='users.index'}">Benutzer</a>{/if}
                        {if 'roles.manage'|has_permission:$permissions}<a class="nav-link" href="{route name='roles.index'}">Rollen</a>{/if}
                        {if 'settings.manage'|has_permission:$permissions}<a class="nav-link" href="{route name='settings.index'}">Einstellungen</a>{/if}
                    </nav>
                </aside>
            {/if}

            <main class="content">
                {include file='partials/flashes.tpl'}
                {block name=content}{/block}
            </main>
        </div>
    </div>

    <script>
        window.movieVault = {
            csrfToken: '{$csrf_token|escape:"javascript"}',
            basePath: '{$base_path|escape:"javascript"}',
            routes: {
                metadataSearch: '{route name="api.metadata.search"}',
                metadataApply: '{route name="api.metadata.apply"}',
                suggestions: '{route name="api.suggestions"}'
            }
        };
    </script>
    <script src="{asset path='vendor/bootstrap/js/bootstrap.bundle.min.js'}"></script>
    <script src="{asset path='assets/js/app.js'}"></script>
</body>
</html>
