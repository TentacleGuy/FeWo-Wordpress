<?php
defined('ABSPATH') || exit;

final class FWB_Domain {
    public static function date(string $value): DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) { throw new InvalidArgumentException('Ungültiges Datum.'); }
        return $date;
    }
    public static function integer($value, int $min, int $max): int {
        if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value) || (int)$value < $min || (int)$value > $max) {
            throw new InvalidArgumentException('Ungültige Anzahl.');
        }
        return (int)$value;
    }
    public static function cents($value): int {
        if (!is_scalar($value) || !preg_match('/^\d{1,7}([.,]\d{1,2})?$/D', (string)$value)) { throw new InvalidArgumentException('Betrag bitte als Zahl mit maximal zwei Nachkommastellen eingeben.'); }
        return (int)round((float)str_replace(',', '.', (string)$value) * 100);
    }
    public static function money(int $cents): string { if(class_exists('FWB_I18n')&&FWB_I18n::foreign()&&class_exists('NumberFormatter'))return (string)(new NumberFormatter(FWB_I18n::locale(),NumberFormatter::CURRENCY))->formatCurrency($cents/100,'EUR');return number_format($cents / 100, 2, ',', '.') . ' €'; }
    public static function overlap(string $a, string $b, string $c, string $d): bool { return $a < $d && $b > $c; }
    public static function quote(array $input, array $s, string $today): array {
        $start = self::date((string)($input['arrival'] ?? ''));
        $end = self::date((string)($input['departure'] ?? ''));
        if ($start->format('Y-m-d') < $today || $end <= $start) { throw new InvalidArgumentException('Bitte einen zukünftigen Aufenthalt mit Abreise nach der Anreise wählen.'); }
        if ($start > self::date($today)->modify('+3 years')) { throw new InvalidArgumentException('Anfragen sind bis drei Jahre im Voraus möglich.'); }
        $nights = (int)$start->diff($end)->days;
        if ($nights < $s['min_nights'] || $nights > 90) { throw new InvalidArgumentException('Mindestaufenthalt: ' . $s['min_nights'] . ' Nächte; maximal 90 Nächte.'); }
        $guests = self::integer($input['guests'] ?? '', 1, $s['max_guests']);
        $taxable = self::integer($input['taxable_guests'] ?? '', 0, $guests);
        if ($s['night_price'] <= 0) { throw new InvalidArgumentException('Der Gastgeber hat die Preise noch nicht freigeschaltet.'); }
        $extra = max(0, $guests - $s['included_guests']);
        $supplement = $extra ? $s['extra_price'] * ($s['extra_unit'] === 'person' ? $extra : 1) * ($s['extra_period'] === 'night' ? $nights : 1) : 0;
        $base = $nights * $s['night_price'];
        $total = $base + $supplement + $s['cleaning_price'];
        $deposit = (int)round($total * $s['deposit_percent'] / 100);
        return [
            'arrival' => $start->format('Y-m-d'), 'departure' => $end->format('Y-m-d'),
            'nights' => $nights, 'guests' => $guests, 'taxable_guests' => $taxable,
            'base' => $base, 'supplement' => $supplement, 'cleaning' => $s['cleaning_price'],
            'total' => $total, 'deposit' => $deposit, 'balance' => $total - $deposit,
            'local_tax' => $taxable * $nights * $s['local_tax'],
            'due_date' => $start->modify('-' . $s['balance_days'] . ' days')->format('Y-m-d'),
            'cancel_date' => $start->modify('-' . $s['cancel_days'] . ' days')->format('Y-m-d'),
            'vat_percent' => $s['vat_percent'], 'vat' => (int)round($total * $s['vat_percent'] / (100 + $s['vat_percent'])),
            'checkin' => $s['checkin'], 'checkout' => $s['checkout'], 'rules' => $s['rules'],
            'tax_note' => $s['tax_note'], 'deposit_percent' => $s['deposit_percent'],
        ];
    }
    public static function proof(string $challenge, string $nonce, int $difficulty): bool {
        return preg_match('/^\d{1,12}$/D', $nonce) && str_starts_with(hash('sha256', $challenge . ':' . $nonce), str_repeat('0', $difficulty));
    }
}
