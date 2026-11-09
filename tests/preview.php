<?php

/**
 * Erzeugt tests/out/tile-preview.html: die Auswertungs-Kachel mit Testdaten, ohne Symcon.
 * Alle Kombinationen aus Zeitraum und Auswahl werden vorab berechnet; ein Ersatz fuer
 * requestAction beantwortet die Klicks daraus.
 * Aufruf: php tests/preview.php
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
require __DIR__ . '/../EVAnalytics/lib/EVPeriod.php';
require __DIR__ . '/../EVAnalytics/lib/EVStats.php';
require __DIR__ . '/../EVAnalytics/lib/EVReport.php';
require __DIR__ . '/fixtures.php';

$biz = ['from' => 7, 'to' => 18];
$views = [];
$scopes = ["all", "site:ADF", "site:RGST", "saeule:ADF:ADF-LS01", "saeule:RGST:RGST-LS01"];
foreach (EVPeriod::TYPES as $type) {
    for ($off = 0; $off >= -1; $off--) {
        foreach ($scopes as $sc) {
            $d = EVReport::Build($meta, $r, $type, $off, $sc, $now, $biz);
            $views[$type . '|' . $off . '|' . $sc] = $d;
            if (!$d['canPrev']) {
                continue 2;
            }
        }
    }
}
// Nur für die Tagesansicht: rechter Ladepunkt am 2.10. steckt 9–16 Uhr, lädt 9.14–10.05 und 15.00–15.10 Uhr
$r->logs[202] = $steps([[$hm(9, 0), true], [$hm(16, 0), false]]);
$r->logs[252] = $steps([[$hm(9, 14), true], [$hm(10, 5), false], [$hm(15, 0), true], [$hm(15, 10), false]]);
$days = [];
foreach (['all', 'site:ADF', 'saeule:ADF:ADF-LS01', 'site:RGST', 'saeule:RGST:RGST-LS01'] as $sc) {
    for ($d = mktime(0, 0, 0, 10, 1, 2026); $d <= EVPeriod::Day0($now); $d = EVPeriod::AddDays($d, 1)) {
        $days[$sc . '|' . $d] = EVReport::Day($meta, $r, $sc, $d, $now);
    }
}
$init = ['ev' => 'report', 'data' => $views['monat|0|all']];
require_once __DIR__ . '/../EVAnalytics/lib/EVTile.php';
$tile = EVTile::Compose(__DIR__ . '/../EVAnalytics', EVTile::SWITCHABLE, json_encode($init), 12345);

$stub = '<script>
var EV_VIEWS = ' . json_encode($views) . ';
var EV_DAYS = ' . json_encode($days) . ';
// #v2=1&tab=power&scope2=site:ADF&bar2=1 öffnet die neue Ansicht (für Bildschirmfotos)
(function () { var h = decodeURIComponent(location.hash.slice(1)), o = {}; h.split("&").forEach(function (kv) { var i = kv.indexOf("="); if (i > 0) o[kv.slice(0, i)] = kv.slice(i + 1); });
  try { localStorage.setItem("evaw-layout-12345", o.v2 === "1" ? "1" : "0"); } catch (e) { }
  if (o.v2 !== "1") return;
  window.addEventListener("load", function () {
    function step2() {
      if (o.tab) { var t = document.querySelector("#v2_tabs button[data-t=\"" + o.tab + "\"]"); if (t) t.click(); }
      if (o.bar2 != null) setTimeout(function () { var hs = document.querySelectorAll("#v2_body .v2-card .hit:not(.nc)"), el = hs[+o.bar2]; if (el) el.dispatchEvent(new MouseEvent("click", { bubbles: true })); }, 300);
    }
    if (o.scope2) { var s = document.getElementById("v2_scope"); s.value = o.scope2; s.dispatchEvent(new Event("change")); setTimeout(step2, 400); } else setTimeout(step2, 100);
  });
})();
window.addEventListener("load", function () {
  var h = decodeURIComponent(location.hash.slice(1)); if (!h) return;
  var o = {}; h.split("&").forEach(function (kv) { var i = kv.indexOf("="); o[kv.slice(0, i)] = kv.slice(i + 1); });
  setTimeout(function () {
    if (o.scope) { var sel = document.getElementById("ev_scope"); sel.value = o.scope; sel.dispatchEvent(new Event("change")); }
    setTimeout(function () {
      var hits = document.querySelectorAll((o.scope && o.scope !== "all" ? "#ev_peak" : "#ev_pdays") + " .hit"), el = hits[+o.bar || 0];
      if (el) { var r = el.getBoundingClientRect(); el.dispatchEvent(new MouseEvent("click", { bubbles: true, clientX: r.x + 2, clientY: r.y + 2 })); }
      setTimeout(function () { var c = document.getElementById("ev_curvecard"); if (c) window.scrollTo(0, c.getBoundingClientRect().top + window.scrollY - 10); document.getElementById("ev_tip").hidden = true; }, 400);
    }, 400);
  }, 200);
});
function requestAction(ident, value) {
  var v = JSON.parse(value);
  setTimeout(function () {
    if (ident === "View") {
      var d = EV_VIEWS[v.type + "|" + v.off + "|" + v.scope];
      handleMessage(JSON.stringify(d ? { ev: "report", cid: v.cid, data: d } : { ev: "csv", cid: v.cid, error: "keine Vorschau für diese Ansicht" }));
      handleMessage(JSON.stringify({ ev: "report", cid: "fremde-kachel", data: EV_VIEWS["jahr|0|all"] }));   // muss ignoriert werden
    } else if (ident === "Day") {
      handleMessage(JSON.stringify({ ev: "day", cid: v.cid, data: EV_DAYS[v.scope + "|" + v.day] || null }));
    } else if (ident === "Csv") {
      handleMessage({ ev: "csv", cid: v.cid, url: "#csv-" + v.mode, lines: 42 });
    }
  }, 150);
}
</script>';

$page = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vorschau EV Auswertung</title>
<style>:root{--card-color:#ffffff;--content-color:#1f2933;--accent-color:#2F80ED}
@media (prefers-color-scheme: dark){:root{--card-color:#2b2d31;--content-color:#f1f3f5}}
body{margin:0;padding:16px;background:var(--card-color)}</style>' . $stub . '</head><body>' . $tile . '</body></html>';

@mkdir(__DIR__ . '/out');
file_put_contents(__DIR__ . '/out/tile-preview.html', $page);
echo 'Vorschau mit ' . count($views) . ' Ansichten: tests/out/tile-preview.html (' . round(strlen($page) / 1024) . " KB)\n";
