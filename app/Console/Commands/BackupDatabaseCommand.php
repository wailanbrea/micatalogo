<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup-safe {--label=manual : Label included in the backup filename}';

    protected $description = 'Exporta la base de datos actual a un archivo privado verificable';

    public function handle(): int
    {
        $directory = storage_path('app/private/database-backups');
        File::ensureDirectoryExists($directory, 0750, true);
        $label = Str::slug((string) $this->option('label')) ?: 'manual';
        $path = $directory.'/'.now()->format('Ymd_His').'_'.$label.'.sql';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('No se pudo crear el archivo privado de respaldo.');

            return self::FAILURE;
        }

        fwrite($handle, "-- MiCatalogo database backup\n-- Generated: ".now()->toIso8601String()."\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $totalRows = 0;

        foreach ($tables as $tableRow) {
            $table = array_values((array) $tableRow)[0] ?? null;
            if (! is_string($table) || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }

            $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $create = array_values((array) $createRow)[1] ?? null;
            if (is_string($create)) {
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
            }

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $values = collect((array) $row)->map(function (mixed $value) use ($handle): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return DB::connection()->getPdo()->quote((string) $value);
                })->implode(', ');
                fwrite($handle, "INSERT INTO `{$table}` VALUES ({$values});\n");
                $totalRows++;
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $size = filesize($path) ?: 0;
        if ($size < 128) {
            File::delete($path);
            $this->error('El respaldo generado no contiene datos suficientes.');

            return self::FAILURE;
        }

        $this->info('Backup verificado: '.str_replace(base_path().'/', '', $path));
        $this->line('Tablas: '.count($tables).' · Filas: '.$totalRows.' · Bytes: '.$size.' · SHA-256: '.hash_file('sha256', $path));

        return self::SUCCESS;
    }
}
