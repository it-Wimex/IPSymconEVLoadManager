<?php

/** Archiv-Ersatz mit fest vorgegebenen Daten */
final class StubReader
{
    public $energy = [];   // varE => [Tag => kWh]
    public $max = [];      // varP => [Tag => kW]
    public $at = [];       // varP => [Tag => Zeitpunkt des Höchstwerts], sonst 12 Uhr
    public $logs = [];     // Ja/Nein-Variable => aufsteigend [['TimeStamp','Value']]
    public $vals = [];     // Leistung => aufsteigend [['TimeStamp','Value']]

    public function DailyEnergy(int $v, int $from, int $to): array
    {
        return array_filter($this->energy[$v] ?? [], function ($d) use ($from, $to) {
            return $d >= $from && $d < $to;
        }, ARRAY_FILTER_USE_KEY);
    }

    public function DailyPeak(int $v, int $from, int $to): array
    {
        $out = [];
        foreach ($this->max[$v] ?? [] as $d => $kw) {
            if ($d >= $from && $d < $to) {
                $out[$d] = ['kw' => $kw, 'at' => $this->at[$v][$d] ?? $d + 12 * 3600];
            }
        }
        return $out;
    }

    public function Values(int $v, int $from, int $to): array
    {
        return array_values(array_filter($this->vals[$v] ?? [], function ($r) use ($from, $to) {
            return $r['TimeStamp'] >= $from && $r['TimeStamp'] < $to;
        }));
    }

    public function ValueBefore(int $v, int $ts)
    {
        $x = null;
        foreach ($this->vals[$v] ?? [] as $r) {
            if ($r['TimeStamp'] < $ts) {
                $x = $r['Value'];
            }
        }
        return $x;
    }

    public function Logged(int $v, int $from, int $to): array
    {
        return array_values(array_filter($this->logs[$v] ?? [], function ($r) use ($from, $to) {
            return $r['TimeStamp'] >= $from && $r['TimeStamp'] < $to;
        }));
    }

    public function StateBefore(int $v, int $ts): bool
    {
        $s = false;
        foreach ($this->logs[$v] ?? [] as $r) {
            if ($r['TimeStamp'] < $ts) {
                $s = (bool) $r['Value'];
            }
        }
        return $s;
    }
}

$now = mktime(12, 0, 0, 10, 14, 2026);
$meta = [
    'ADF' => ['name' => 'Arensdorf', 'sourceLabel' => 'EVLM', 'budgetKw' => 13.8, 'varP' => 501, 'varX' => 551,
        'histSince' => mktime(0, 0, 0, 1, 1, 2025), 'detailSince' => mktime(0, 0, 0, 10, 1, 2026),
        'saeulen' => [['id' => 'ADF-LS01', 'name' => 'Säule 1', 'limitKw' => 13.8, 'varP' => 401, 'varX' => 451, 'points' => [
            ['id' => 'ADF-LS01-1', 'name' => 'links', 'varE' => 101, 'varB' => 201, 'varP' => 301, 'varL' => 251],
            ['id' => 'ADF-LS01-2', 'name' => 'rechts', 'varE' => 102, 'varB' => 202, 'varP' => 302, 'varL' => 252]]]]],
    'RGST' => ['name' => 'Regenstauf', 'sourceLabel' => 'ChargePilot', 'budgetKw' => 22.1, 'varP' => 511, 'varX' => 561,
        'note' => 'Regenstauf: ChargePilot per Modbus über VPN.',
        'histSince' => mktime(0, 0, 0, 10, 5, 2026), 'detailSince' => mktime(0, 0, 0, 10, 5, 2026),
        'saeulen' => [['id' => 'RGST-LS01', 'name' => 'Säule 1', 'limitKw' => null, 'varP' => 411, 'points' => [
            ['id' => 'RGST-LS01-1', 'name' => 'links', 'varE' => 111, 'varB' => 211, 'varP' => 311, 'varL' => 261]]]]],
];
$r = new StubReader();
for ($d = mktime(0, 0, 0, 1, 1, 2025); $d <= EVPeriod::Day0($now); $d = EVPeriod::AddDays($d, 1)) {
    $r->energy[101][$d] = 10.0;
    $r->energy[102][$d] = 5.0;
    if ($d >= mktime(0, 0, 0, 10, 5, 2026)) {
        $r->energy[111][$d] = 20.0;
        $r->max[511][$d] = 10.0;
    }
    if ($d >= mktime(0, 0, 0, 10, 1, 2026)) {
        $r->max[501][$d] = $d === mktime(0, 0, 0, 10, 2, 2026) ? 13.8 : 7.0;
        $r->max[401][$d] = $d === mktime(0, 0, 0, 10, 2, 2026) ? 13.8 : 7.0;
        $r->logs[201][] = ['TimeStamp' => $d + 8 * 3600, 'Value' => true];
        $r->logs[201][] = ['TimeStamp' => $d + 12 * 3600, 'Value' => false];
        $r->logs[251][] = ['TimeStamp' => $d + 8 * 3600, 'Value' => true];     // lädt die ersten 2 der 4 belegten Stunden
        $r->logs[251][] = ['TimeStamp' => $d + 10 * 3600, 'Value' => false];
    }
    if ($d >= mktime(0, 0, 0, 10, 5, 2026)) {
        $r->logs[211][] = ['TimeStamp' => $d + 9 * 3600, 'Value' => true];
        $r->logs[211][] = ['TimeStamp' => $d + 10 * 3600, 'Value' => false];
        $r->logs[261][] = ['TimeStamp' => $d + 9 * 3600, 'Value' => true];     // lädt eine halbe Stunde
        $r->logs[261][] = ['TimeStamp' => $d + 9 * 3600 + 1800, 'Value' => false];
    }
}

// Freitag 2.10.: Arensdorf am Budget 9.14–10.05 Uhr (eine Minute Lücke um 10.01, wird überbrückt) und 15.00–15.10 Uhr
$d2 = mktime(0, 0, 0, 10, 2, 2026);
$hm = function (int $h, int $m) use ($d2) {
    return $d2 + $h * 3600 + $m * 60;
};
$r->at[501][$d2] = $hm(9, 14);
$r->at[401][$d2] = $hm(9, 14);
$steps = function (array $list) {
    return array_map(function ($x) {
        return ['TimeStamp' => $x[0], 'Value' => $x[1]];
    }, $list);
};
$r->vals[501] = $steps([[$hm(8, 0), 7.0], [$hm(9, 14), 13.8], [$hm(10, 1), 13.0], [$hm(10, 2), 13.8], [$hm(10, 5), 7.0], [$hm(12, 0), 0.0],
    [$hm(15, 0), 13.8], [$hm(15, 10), 0.0]]);
$r->vals[401] = $r->vals[501];
$r->vals[301] = $steps([[$hm(8, 0), 7.0], [$hm(12, 0), 0.0], [$hm(15, 0), 6.9], [$hm(15, 10), 0.0]]);
$r->vals[302] = $steps([[$hm(9, 14), 6.8], [$hm(10, 1), 6.0], [$hm(10, 2), 6.8], [$hm(10, 5), 0.0], [$hm(15, 0), 6.9], [$hm(15, 10), 0.0]]);
foreach ([551, 451] as $x) {
    $r->logs[$x] = $steps([[$hm(9, 14), true], [$hm(10, 1), false], [$hm(10, 2), true], [$hm(10, 5), false], [$hm(15, 0), true], [$hm(15, 10), false]]);
}
