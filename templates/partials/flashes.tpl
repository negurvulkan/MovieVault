{if $flashes}
    <div class="flash-stack">
        {foreach $flashes as $flash}
            <div class="alert alert-{$flash.type} shadow-sm">{$flash.message}</div>
        {/foreach}
    </div>
{/if}
