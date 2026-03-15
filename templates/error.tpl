{extends file='layouts/base.tpl'}

{block name=title} - Fehler{/block}

{block name=content}
    <div class="hero-card text-center">
        <p class="eyebrow">Status {$status}</p>
        <h1 class="display-5 mb-3">Da ist etwas schiefgelaufen</h1>
        <p class="lead text-secondary mb-4">{$message}</p>
        <a class="btn btn-primary" href="{if $current_user}{route name='dashboard'}{else}{route name='login'}{/if}">Zurueck zur App</a>
    </div>
{/block}
