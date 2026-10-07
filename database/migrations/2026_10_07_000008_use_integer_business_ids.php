<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COLUMNS = [
        'kecamatan' => ['kecamatan_id'],
        'puskesmas' => ['puskesmas_id', 'kecamatan_id'],
        'akun' => ['akun_id', 'puskesmas_id'],
        'layanan' => ['layanan_id'],
        'jadwal' => ['jadwal_id', 'puskesmas_id', 'layanan_id'],
        'antrean' => ['antrean_id', 'jadwal_id', 'akun_id'],
    ];

    public function up(): void
    {
        $this->convert('INT', true);
    }

    public function down(): void
    {
        $this->convert('BIGINT', false);
    }

    private function convert(string $type, bool $checkRange): void
    {
        // SQLite uses integer affinity for both widths; MySQL is the live database.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Migrasi ukuran ID ini mendukung MySQL dan SQLite saja.');
        }

        $database = DB::getDatabaseName();
        $metadata = [];
        foreach (self::COLUMNS as $table => $columns) {
            $metadata[$table] = DB::select(
                'SELECT COLUMN_NAME, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database, $table]
            );
            if ($checkRange) {
                foreach ($columns as $column) {
                    if (DB::table($table)->where($column, '>', 4294967295)->orWhere($column, '<', 0)->exists()) {
                        throw new RuntimeException("Nilai {$table}.{$column} di luar rentang INT UNSIGNED. Tidak ada struktur diubah.");
                    }
                }
                $next = DB::selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table]);
                if ($next && (float) $next->next_id > 4294967295) {
                    throw new RuntimeException("AUTO_INCREMENT {$table} di luar rentang INT UNSIGNED.");
                }
            }
        }

        $foreignKeys = DB::select(
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL',
            [$database]
        );
        $foreignKeys = array_filter($foreignKeys, fn ($key) => isset(self::COLUMNS[$key->TABLE_NAME]) && isset(self::COLUMNS[$key->REFERENCED_TABLE_NAME]));
        $quote = fn (string $name): string => '`'.str_replace('`', '``', $name).'`';

        // MySQL DDL implicitly commits. Back up before running; do not use fresh.
        foreach ($foreignKeys as $key) {
            DB::statement('ALTER TABLE '.$quote($key->TABLE_NAME).' DROP FOREIGN KEY '.$quote($key->CONSTRAINT_NAME));
        }
        foreach (self::COLUMNS as $table => $columns) {
            $changes = [];
            foreach ($metadata[$table] as $column) {
                if (! in_array($column->COLUMN_NAME, $columns, true)) {
                    continue;
                }
                $changes[] = 'MODIFY COLUMN '.$quote($column->COLUMN_NAME).' '.$type.' UNSIGNED '
                    .($column->IS_NULLABLE === 'YES' ? 'NULL DEFAULT NULL' : 'NOT NULL')
                    .(str_contains($column->EXTRA, 'auto_increment') ? ' AUTO_INCREMENT' : '');
            }
            DB::statement('ALTER TABLE '.$quote($table).' '.implode(', ', $changes));
        }
        foreach ($foreignKeys as $key) {
            DB::statement('ALTER TABLE '.$quote($key->TABLE_NAME).' ADD CONSTRAINT '.$quote($key->CONSTRAINT_NAME)
                .' FOREIGN KEY ('.$quote($key->COLUMN_NAME).') REFERENCES '.$quote($key->REFERENCED_TABLE_NAME)
                .' ('.$quote($key->REFERENCED_COLUMN_NAME).') ON UPDATE '.$key->UPDATE_RULE.' ON DELETE '.$key->DELETE_RULE);
        }
    }
};
