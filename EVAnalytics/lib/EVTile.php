<?php

/**
 * Setzt die Kachel zusammen: klassische Ansicht (module.html), neue Ansicht (view2.html) oder beide mit
 * einem Schalter. Im Übergang merkt sich jedes Gerät seine Wahl (localStorage), sonst gilt die klassische Ansicht.
 * Beide Ansichten haben eigene Klassen, IDs und Kennungen für Antworten des Moduls und stören sich nicht.
 */
final class EVTile
{
    public const CLASSIC = 0;
    public const NEU = 1;
    public const SWITCHABLE = 2;

    public static function Compose(string $dir, int $layout, string $initJson, int $instanceId): string
    {
        $part = function (string $file) use ($dir, $initJson): ?string {
            $html = @file_get_contents($dir . '/' . $file);
            return $html === false ? null : str_replace('%%INITIAL%%', $initJson, $html);
        };
        $classic = $layout === self::NEU ? '' : $part('module.html');
        $neu = $layout === self::CLASSIC ? '' : $part('view2.html');
        if ($classic === null || $neu === null) {
            return '<div style="padding:8px">Dateien der Kachel fehlen (module.html oder view2.html).</div>';
        }
        if ($layout === self::CLASSIC) {
            return $classic;
        }
        if ($layout === self::NEU) {
            return $neu;
        }
        $key = 'evaw-layout-' . $instanceId;
        return <<<HTML
<style>
    .evsw { display: flex; justify-content: flex-end; align-items: center; gap: 6px; padding: 2px 4px 0; font: 12px Arial, Helvetica, sans-serif; color: var(--content-color, #1f2933); }
    .evsw span { opacity: .65; }
    .evsw .g { display: inline-flex; border-radius: 8px; padding: 2px; gap: 2px; background: rgba(127, 127, 127, .14); }
    .evsw button { font: inherit; color: inherit; border: 0; background: transparent; padding: 3px 9px; border-radius: 6px; cursor: pointer; opacity: .75; }
    .evsw button[aria-pressed="true"] { background: var(--card-color, #fff); opacity: 1; font-weight: 700; box-shadow: 0 1px 2px rgba(0, 0, 0, .15); }
    .evhide { display: none !important; }
</style>
<div class="evsw" role="group" aria-label="Ansicht der Auswertung"><span>Ansicht</span><div class="g"><button type="button" data-l="0">Klassisch</button><button type="button" data-l="1">Neu (Vorschau)</button></div></div>
{$classic}
{$neu}
<script>
    (function () {
        var KEY = '{$key}', sw = document.querySelector('.evsw'), cur = 0;
        try { cur = localStorage.getItem(KEY) === '1' ? 1 : 0; } catch (e) { }
        function show(l) {
            cur = l;
            var a = document.getElementById('ev'), b = document.getElementById('v2');
            if (a) a.classList.toggle('evhide', l !== 0);
            if (b) b.classList.toggle('evhide', l !== 1);
            Array.prototype.forEach.call(sw.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', String(+x.getAttribute('data-l') === l)); });
            if (l === 1 && window.EV2) window.EV2.shown();
        }
        sw.addEventListener('click', function (e) {
            var x = e.target.closest('button'); if (!x) return;
            var l = +x.getAttribute('data-l');
            try { localStorage.setItem(KEY, String(l)); } catch (er) { }
            show(l);
        });
        show(cur);
    })();
</script>
HTML;
    }
}
