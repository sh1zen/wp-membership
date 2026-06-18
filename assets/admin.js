(function ($) {
    "use strict";

    function statusText(key, fallback) {
        return window.wps?.locale?.get(key, fallback) || fallback;
    }

    function setStatus($form, state, text) {
        const $status = $form.find(".wpmc-autosave-status");

        if (!$status.length) {
            return;
        }

        $status
            .removeClass("is-saving is-saved is-error")
            .addClass("is-" + state)
            .text(text);
    }

    function showToast(state, text) {
        if (window.wps?.showToast) {
            window.wps.showToast(state, text);
        }
    }

    function initLevelAutosave(form) {
        const $form = $(form);

        if ($form.data("wpmc-level-autosave-init")) {
            return;
        }

        const nonce = window.wps?.locale?.get("wpmc_ajax_nonce", "");

        if (!nonce || !window.wps?.ajaxHandler) {
            return;
        }

        $form.data("wpmc-level-autosave-init", true);

        let lastSaved = $form.serialize();
        let timer = null;
        let inFlight = false;
        let queued = false;

        function save() {
            const snapshot = $form.serialize();

            if (snapshot === lastSaved) {
                return;
            }

            if (inFlight) {
                queued = true;
                return;
            }

            inFlight = true;
            setStatus($form, "saving", statusText("autosaving", "Autosaving..."));

            window.wps.ajaxHandler({
                mod: "levels",
                mod_action: "autosave_level",
                mod_nonce: nonce,
                mod_form: snapshot,
                callback(data, state) {
                    inFlight = false;

                    if (state === "success") {
                        lastSaved = snapshot;
                        const text = data?.text || statusText("autosaved", "All changes saved");
                        setStatus($form, "saved", text);
                        showToast("success", text);
                    } else {
                        const text = data?.text || statusText("autosave_failed", "Autosave failed");
                        setStatus($form, "error", text);
                        showToast("error", text);
                    }

                    if (queued) {
                        queued = false;
                        save();
                    }
                }
            });
        }

        function debounceSave() {
            clearTimeout(timer);
            timer = window.setTimeout(save, 700);
        }

        $form.on("input change", ":input:not([type='submit']):not([type='button']):not([type='hidden'])", debounceSave);

        $form.on("click", ".wps-dropdown li", function () {
            window.setTimeout(debounceSave, 0);
        });

        $form.on("submit", function (event) {
            event.preventDefault();
            save();
        });
    }

    $(function () {
        $(".wpmc-level-autosave-form[data-wpmc-autosave='level']").each(function () {
            initLevelAutosave(this);
        });
    });
})(jQuery);
