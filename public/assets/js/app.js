(() => {
    const appConfig = window.movieVault || {};

    async function loadMetadata(button) {
        const titleId = button.dataset.titleId;
        const container = document.querySelector(".js-metadata-results");
        if (!titleId || !container) {
            return;
        }

        container.innerHTML = '<div class="text-secondary">Suche laeuft...</div>';

        const url = new URL(appConfig.routes.metadataSearch, window.location.origin);
        url.searchParams.set("title_id", titleId);

        const response = await fetch(url.toString(), { credentials: "same-origin" });
        const payload = await response.json();

        if (!response.ok || payload.error) {
            container.innerHTML = `<div class="alert alert-warning mb-0">${payload.error || "Keine Treffer gefunden."}</div>`;
            return;
        }

        if (!payload.results || payload.results.length === 0) {
            container.innerHTML = '<div class="text-secondary">Keine Treffer gefunden.</div>';
            return;
        }

        container.innerHTML = payload.results.map((result) => {
            return `
                <article class="metadata-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h3>${escapeHtml(result.title || "Treffer")}</h3>
                            <div class="small text-secondary">${escapeHtml(result.provider)}${result.year ? " · " + escapeHtml(String(result.year)) : ""}</div>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary js-apply-metadata"
                                data-title-id="${escapeHtml(String(titleId))}"
                                data-provider="${escapeHtml(result.provider)}"
                                data-external-id="${escapeHtml(result.external_id)}">
                            Anwenden
                        </button>
                    </div>
                    <p class="mb-0 mt-2">${escapeHtml(result.overview || "Keine Beschreibung vorhanden.")}</p>
                </article>
            `;
        }).join("");
    }

    async function applyMetadata(button) {
        const formData = new FormData();
        formData.set("csrf_token", appConfig.csrfToken || "");
        formData.set("title_id", button.dataset.titleId || "");
        formData.set("provider", button.dataset.provider || "");
        formData.set("external_id", button.dataset.externalId || "");
        formData.set("overwrite", "0");

        const response = await fetch(appConfig.routes.metadataApply, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
        });
        const payload = await response.json();

        if (!response.ok || payload.error) {
            window.alert(payload.error || "Metadaten konnten nicht uebernommen werden.");
            return;
        }

        window.location.reload();
    }

    async function refreshSuggestion(button) {
        const card = button.closest(".js-suggestion-card");
        if (!card) {
            return;
        }

        const url = new URL(appConfig.routes.suggestions, window.location.origin);
        url.searchParams.set("mode", card.dataset.mode || "random");
        url.searchParams.set("genre", card.dataset.genre || "");
        url.searchParams.set("filter", card.dataset.filter || "unwatched");

        const response = await fetch(url.toString(), { credentials: "same-origin" });
        const payload = await response.json();
        const suggestion = payload.suggestion;
        if (!suggestion) {
            return;
        }

        card.innerHTML = `
            <p class="eyebrow">Heute passt vielleicht</p>
            <h2 class="display-6 mb-3">${escapeHtml(suggestion.title)}</h2>
            <p class="lead text-secondary">${suggestion.kind === "season"
                ? `${escapeHtml(suggestion.series_title || suggestion.title || "")} · Staffel ${escapeHtml(String(suggestion.season_number || ""))}`
                : escapeHtml(String(suggestion.year || "Film"))}</p>
            <p class="mb-4">${escapeHtml(suggestion.overview || "Keine Beschreibung vorhanden.")}</p>
            <div class="d-flex flex-wrap gap-2 mb-4">${(suggestion.genres || []).map((genre) => `<span class="badge text-bg-dark">${escapeHtml(genre)}</span>`).join("")}</div>
            <button type="button" class="btn btn-outline-secondary js-refresh-suggestion">Noch einen Vorschlag</button>
        `;
    }

    document.addEventListener("click", (event) => {
        const metadataButton = event.target.closest(".js-metadata-search");
        if (metadataButton) {
            loadMetadata(metadataButton).catch((error) => window.alert(error.message));
            return;
        }

        const applyButton = event.target.closest(".js-apply-metadata");
        if (applyButton) {
            applyMetadata(applyButton).catch((error) => window.alert(error.message));
            return;
        }

        const suggestionButton = event.target.closest(".js-refresh-suggestion");
        if (suggestionButton) {
            refreshSuggestion(suggestionButton).catch((error) => window.alert(error.message));
        }
    });

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }
})();
