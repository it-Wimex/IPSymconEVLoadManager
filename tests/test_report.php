<?php

require_once __DIR__ . '/../EVAnalytics/lib/EVReport.php';

require_once __DIR__ . '/fixtures.php';

$biz = ['from' => 7, 'to' => 18];

$v = EVReport::Build($meta, $r, 'monat', 0, 'all', $now, $biz);
check('Bericht: Energie gesamt Oktober', $v['kpi']['kwh'], 410.0);
check('Bericht: Vergleich 1.–14. September', $v['kpi']['prevKwh'], 210.0);
check('Bericht: Ladevorgänge', $v['kpi']['ses'], 24);
check('Bericht: Ø Dauer 2,75 h', $v['kpi']['avgDur'], 2.75);
check('Bericht: Ø kWh je Vorgang', $v['kpi']['avgKwh'], round(410 / 24, 3));
check('Bericht: Auslastung', $v['kpi']['occ'], round(66 / 876, 4));
check('Bericht: Auslastung Geschäftszeit', $v['kpi']['bizOcc'], round(48 / 290, 4));
check('Bericht: davon aktiv geladen', $v['kpi']['load'], round(33 / 876, 4));
check('Bericht: aktiv geladen Geschäftszeit', $v['kpi']['bizLoad'], round(24 / 290, 4));
check('Bericht: aktiv geladen je Standort', [$v['rows'][0]['load'], $v['rows'][1]['load']], [round(28 / 648, 4), round(5 / 228, 4)]);
check('Bericht: Heatmap aktiv geladen Mo 8–9 Uhr', $v['heat']['gridL'][0][8], round(7200 / 21600, 4));
check('Bericht: Heatmap aktiv geladen Mo 9–10 Uhr', $v['heat']['gridL'][0][9], 0.5);
check('Bericht: höchste Leistung kommt von Arensdorf', $v['kpi']['peakSite'], 'Arensdorf');
check('Bericht: ein Standort-Tag am Budget', $v['kpi']['limitDays'], 1);
check('Bericht: Zeilen sind Standorte', array_column($v['rows'], 'label'), ['Arensdorf', 'Regenstauf']);
check('Bericht: Regenstauf 200 kWh', $v['rows'][1]['kwh'], 200.0);
check('Bericht: Drill-down auf Standort', $v['rows'][0]['drill'], 'site:ADF');
check('Bericht: Balken 2.10. hat 15 kWh', $v['energy'][1]['kwh'], 15.0);
check('Bericht: Balken 5.10. aufgeteilt nach Standort', $v['energy'][4]['by'], ['Arensdorf' => 15.0, 'Regenstauf' => 20.0]);
check('Bericht: Balken 20.10. liegt in der Zukunft', $v['energy'][19]['future'], true);
check('Bericht: Heatmap Montag 8–9 Uhr', $v['heat']['grid'][0][8], round(3600 * 2 / (3 * 2 * 3600), 4));
check('Bericht: Vorher-Knopf aktiv', $v['canPrev'], true);
check('Bericht: Weiter-Knopf aus', $v['canNext'], false);
check('Bericht: lässt sich als JSON ausgeben', json_encode($v) !== false, true);

$s = EVReport::Build($meta, $r, 'monat', 0, 'site:ADF', $now, $biz);
check('Standort: Budget als Grenze', $s['scope']['limitKw'], 13.8);
check('Standort: 2.10. am Budget', $s['peaks'][1]['limitDays'], 1);
check('Standort: Zeilen sind Ladepunkte mit Säule', array_column($s['rows'], 'label'), ['Säule 1 · links', 'Säule 1 · rechts']);
check('Standort: Ladepunkt ohne Belegung hat 0 Vorgänge', $s['rows'][1]['ses'], 0);
check('Standort: Ladepunkt ohne Laden hat 0 %', $s['rows'][1]['load'], 0.0);
check('Standort: Brotkrumen', array_column($s['scope']['crumbs'], 'l'), ['Alle Standorte', 'Arensdorf']);

$g = EVReport::Build($meta, $r, 'monat', 0, 'saeule:RGST:RGST-LS01', $now, $biz);
check('Säule: ohne Limit keine Grenze', $g['scope']['limitKw'], null);
check('Säule: Hinweis zum Standort', $g['scope']['note'], 'Regenstauf: ChargePilot per Modbus über VPN.');
check('Säule: Bezeichnung des Limits', $g['scope']['limitLabel'], 'Säulen-Limit');

$old = EVReport::Build($meta, $r, 'jahr', -1, 'all', $now, $biz);
check('2025: Energie aus Historie', $old['kpi']['kwh'], 365 * 15.0);
check('2025: keine Ladevorgänge', $old['kpi']['ses'], null);
check('2025: keine Spitzen', $old['kpi']['peak'], null);
check('2025: kein aktives Laden', [$old['kpi']['load'], $old['heat']['gridL']], [null, null]);
$noL = $meta;
unset($noL['RGST']['saeulen'][0]['points'][0]['varL']);
$nl = EVReport::Build($noL, $r, 'monat', 0, 'site:RGST', $now, $biz);
check('Altbestand ohne Lädt-Variable: Auslastung bleibt, Laden unbekannt', [$nl['kpi']['occ'] > 0, $nl['kpi']['load'], $nl['heat']['gridL']], [true, null, null]);
check('Unbekannte Auswahl wird zu Alle', EVReport::Build($meta, $r, 'monat', 0, 'saeule:XX:YY', $now, $biz)['scope']['value'], 'all');

$csv = EVReport::Csv($meta, $r, 'monat', 0, 'site:ADF', 'summen', $now);
$lines = explode("\r\n", trim($csv['text']));
check('CSV Summen: Kopfzeile', substr($lines[0], 3), 'Standort;Säule;Ladepunkt;Von;Bis;Energie kWh;Ladevorgänge;Ø kWh je Vorgang;Ø Dauer h;Belegt h;Auslastung %;Lädt aktiv h;Aktiv geladen %');
check('CSV Summen: Zeile links', $lines[1], 'Arensdorf;Säule 1;links;01.10.2026;14.10.2026;140,00;14;10,00;4,00;56,00;17,28;28,00;8,64');
check('CSV Summen: Dateiname', $csv['filename'], 'ev-auswertung_ADF_Oktober-2026_summen.csv');
$cv = EVReport::Csv($meta, $r, 'monat', 0, 'saeule:RGST:RGST-LS01', 'verlauf', $now);
$cl = explode("\r\n", trim($cv['text']));
check('CSV Verlauf: 10 Tage Regenstauf', $cv['count'], 10);
check('CSV Verlauf: erste Zeile', $cl[1], '05.10.2026;Regenstauf;Säule 1;links;20,00;1;1,00;0,50');

// ---- Leistungsspitzen: Uhrzeit, Dauer am Budget, Lastverlauf ----
$d2 = mktime(0, 0, 0, 10, 2, 2026);
check('Spitzen: Zeitpunkt der höchsten Leistung', $s['kpi']['peakAt'], $d2 + 9 * 3600 + 14 * 60);
check('Spitzen: am Budget im Monat (Lücke 10.01 überbrückt)', $s['kpi']['limitSec'], (51 + 10) * 60);
check('Spitzen: Balken 2.10. mit Uhrzeit, Tag und Dauer', [$s['peaks'][1]['at'], $s['peaks'][1]['day'], $s['peaks'][1]['limitSec']], [$d2 + 9 * 3600 + 14 * 60, $d2, 3660]);
check('Spitzen: Tag ohne Budget hat 0 Sekunden', $s['peaks'][2]['limitSec'], 0);
check('Spitzen: Alle Standorte, Dauer je Standort', array_column($v['sitePeaks'], 'limitSec'), [3660, 0]);
check('Spitzen: Alle Standorte, Zeitpunkt Arensdorf', $v['sitePeaks'][0]['at'], $d2 + 9 * 3600 + 14 * 60);

$c = $s['curve'];
check('Lastverlauf: Spitzentag vorgewählt', [$c['scope'], $c['day']], ['site:ADF', $d2]);
check('Lastverlauf: Spitze 13,8 kW um 9.14 Uhr', $c['peak'], ['m' => 554, 'kw' => 13.8]);
check('Lastverlauf: Abschnitte am Budget in Minuten', $c['segs'], [[554, 605], [900, 910]]);
check('Lastverlauf: Dauer am Budget', $c['limitMin'], 61);
check('Lastverlauf: Ladepunkte mit fester Farbe', array_column($c['series'], 'slot'), [0, 1]);
check('Lastverlauf: Namen mit Säule', array_column($c['series'], 'name'), ['Säule 1 · links', 'Säule 1 · rechts']);
check('Lastverlauf: Gesamt als Wechselliste', array_slice($c['total'], 0, 3), [[0, 0.0], [480, 7.0], [554, 13.8]]);
check('Lastverlauf: ganzer Tag', [$c['len'], $c['upto']], [1440, 1440]);
check('Aufteilung zur Spitze: kW', array_column($c['breakdown'], 'kw'), [7.0, 6.8]);
check('Aufteilung zur Spitze: Anteil', array_column($c['breakdown'], 'share'), [round(7 / 13.8, 4), round(6.8 / 13.8, 4)]);
check('Aufteilung: Ø kW am Budget', array_column($c['breakdown'], 'avgLimit'), [round((51 * 7.0 + 10 * 6.9) / 61, 2), round((50 * 6.8 + 6.0 + 10 * 6.9) / 61, 2)]);
check('Aufteilung: Energie am Tag', array_column($c['breakdown'], 'kwh'), [10.0, 5.0]);
check('Lastverlauf: Navigation', [$c['prev'], $c['next']], [mktime(0, 0, 0, 10, 1, 2026), mktime(0, 0, 0, 10, 3, 2026)]);
check('Lastverlauf Alle Standorte: Standort mit der höchsten Auslastung des Budgets', $v['curve']['scope'], 'site:ADF');

$first = EVReport::Day($meta, $r, 'site:ADF', mktime(0, 0, 0, 9, 1, 2026), $now);
check('Lastverlauf: vor Beginn der Aufzeichnung wird der erste Tag gezeigt', [$first['day'], $first['prev']], [mktime(0, 0, 0, 10, 1, 2026), null]);
$today = EVReport::Day($meta, $r, 'site:ADF', $now + 5 * 86400, $now);
check('Lastverlauf: Zukunft wird heute, nur bis jetzt', [$today['day'], $today['upto'], $today['next']], [EVPeriod::Day0($now), 720, null]);
check('Lastverlauf: Säule ohne Limit', EVReport::Day($meta, $r, 'saeule:RGST:RGST-LS01', $now, $now)['limitMin'], null);
check('Lastverlauf: Alle Standorte direkt gibt es nicht', EVReport::Day($meta, $r, 'all', $now, $now), null);
$noX = $meta;
unset($noX['ADF']['varX']);
check('Altbestand ohne „am Budget“: Dauer unbekannt', EVReport::Build($noX, $r, 'monat', 0, 'site:ADF', $now, $biz)['kpi']['limitSec'], null);
