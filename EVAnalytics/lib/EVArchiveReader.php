<?php

/**
 * Archivzugriff für EVReport über die Symcon-Funktionen AC_*.
 * Liefert die Daten in der Form, die EVReport erwartet (siehe dort).
 */
final class EVArchiveReader
{
    private $ac;

    public function __construct(int $archiveId)
    {
        $this->ac = $archiveId;
    }

    /** Energie je Tag; bei Zählervariablen steht die Summe der Zuwächse in "Avg" */
    public function DailyEnergy(int $varE, int $from, int $to): array
    {
        return $this->daily($varE, $from, $to, 'Avg');
    }

    /** Höchste Leistung je Tag mit Zeitpunkt: [Tagesbeginn => ['kw' => kW, 'at' => Zeitpunkt]] */
    public function DailyPeak(int $varP, int $from, int $to): array
    {
        $out = [];
        foreach ($this->dailyRows($varP, $from, $to) as $d => $r) {
            if (isset($r['Max'])) {
                $out[$d] = ['kw' => (float) $r['Max'], 'at' => (int) ($r['MaxTime'] ?? $d)];
            }
        }
        return $out;
    }

    private function daily(int $var, int $from, int $to, string $field): array
    {
        $out = [];
        foreach ($this->dailyRows($var, $from, $to) as $d => $r) {
            if (isset($r[$field])) {
                $out[$d] = (float) $r[$field];
            }
        }
        return $out;
    }

    /** Tagesaggregation [Tagesbeginn => Zeile mit Avg, Min, Max, MinTime, MaxTime, …], aufsteigend */
    private function dailyRows(int $var, int $from, int $to): array
    {
        if (!$this->ac || $var <= 0 || !IPS_VariableExists($var) || $to <= $from) {
            return [];
        }
        $rows = @AC_GetAggregatedValues($this->ac, $var, 1 /* Tag */, $from, $to - 1, 0);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $d = EVPeriod::Day0((int) $r['TimeStamp']);
            if ($d >= EVPeriod::Day0($from) && $d < $to) {
                $out[$d] = $r;
            }
        }
        ksort($out);
        return $out;
    }

    /** Geloggte Ja/Nein-Werte aufsteigend */
    public function Logged(int $varB, int $from, int $to): array
    {
        return array_map(function ($r) {
            return ['TimeStamp' => $r['TimeStamp'], 'Value' => (bool) $r['Value']];
        }, $this->Values($varB, $from, $to));
    }

    /** Geloggte Werte aufsteigend, unverändert; AC_GetLoggedValues liefert höchstens 10.000 Werte je Abfrage */
    public function Values(int $var, int $from, int $to): array
    {
        if (!$this->ac || $var <= 0 || !IPS_VariableExists($var) || $to <= $from) {
            return [];
        }
        $out = [];
        $end = $to - 1;
        for ($guard = 0; $guard < 50; $guard++) {
            $rows = @AC_GetLoggedValues($this->ac, $var, $from, $end, 10000);
            if (!is_array($rows) || count($rows) == 0) {
                break;
            }
            $min = PHP_INT_MAX;
            foreach ($rows as $r) {
                $out[] = ['TimeStamp' => (int) $r['TimeStamp'], 'Value' => $r['Value']];
                $min = min($min, (int) $r['TimeStamp']);
            }
            if (count($rows) < 10000 || $min <= $from) {
                break;
            }
            $end = $min - 1;
        }
        usort($out, function ($a, $b) {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        return $out;
    }

    /** Zustand unmittelbar vor $ts (letzter Wert der 30 Tage davor) */
    public function StateBefore(int $varB, int $ts): bool
    {
        return (bool) $this->ValueBefore($varB, $ts);
    }

    /** Letzter geloggter Wert der 30 Tage vor $ts, null wenn keiner (AC_GetLoggedValues liefert den neuesten zuerst) */
    public function ValueBefore(int $var, int $ts)
    {
        if (!$this->ac || $var <= 0 || !IPS_VariableExists($var)) {
            return null;
        }
        $rows = @AC_GetLoggedValues($this->ac, $var, $ts - 30 * 86400, $ts - 1, 1);
        $best = null;
        foreach (is_array($rows) ? $rows : [] as $r) {
            if ($best === null || (int) $r['TimeStamp'] > (int) $best['TimeStamp']) {
                $best = $r;
            }
        }
        return $best === null ? null : $best['Value'];
    }
}
