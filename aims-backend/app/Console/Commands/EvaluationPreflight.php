<?php

namespace App\Console\Commands;

use App\Services\EvaluationCsvImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class EvaluationPreflight extends Command
{
    protected $signature = 'evaluations:preflight {--backup : Back up the configured MariaDB/MySQL database} {--csv= : Inspect a report CSV without importing it}';

    protected $description = 'Audit evaluation history and optionally back up the database before the additive module migration';

    public function handle(): int
    {
        $this->info('Connection: '.DB::getDriverName());
        $this->line('Database: '.DB::connection()->getDatabaseName());
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->line('Server: '.DB::selectOne('SELECT VERSION() AS version')->version);
        }
        $this->table(['Type', 'Status', 'Count'], DB::table('evaluations')->selectRaw('evaluation_type, status, COUNT(*) AS total')->groupBy('evaluation_type', 'status')->get()->map(fn ($r) => (array) $r));
        $nulls = DB::table('evaluations')->whereNull('student_internship_id')->count();
        $mismatches = DB::table('evaluations as e')->join('student_internships as i', 'i.id', '=', 'e.student_internship_id')->whereColumn('e.student_id', '!=', 'i.student_id')->count();
        $duplicates = DB::table('evaluations')->whereNotNull('student_internship_id')->selectRaw('student_internship_id, evaluation_type, COUNT(*) AS total')->groupBy('student_internship_id', 'evaluation_type')->havingRaw('COUNT(*) > 1')->get()->count();
        $this->line("Unresolved legacy internship references: $nulls; mismatched students: $mismatches; duplicate internship/type groups: $duplicates");
        $this->table(['Index', 'Columns', 'Unique'], collect(DB::getSchemaBuilder()->getIndexes('evaluations'))->map(fn ($i) => [$i['name'], implode(', ', $i['columns']), $i['unique'] ? 'yes' : 'no']));
        $this->table(['Foreign key', 'Columns', 'Target', 'On delete'], collect(DB::getSchemaBuilder()->getForeignKeys('evaluations'))->map(fn ($fk) => [$fk['name'], implode(', ', $fk['columns']), $fk['foreign_table'], $fk['on_delete']]));
        if ($mismatches || $duplicates) {
            $this->error('Resolve integrity errors before migrating. No data was changed.');

            return self::FAILURE;
        }
        if ($this->option('backup')) {
            if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $this->error('This backup option requires MariaDB/MySQL.');

                return self::FAILURE;
            }
            $config = DB::connection()->getConfig();
            $directory = storage_path('app/private/evaluation-backups');
            File::ensureDirectoryExists($directory);
            $path = $directory.'/before-whole-semester-'.now()->format('Ymd-His').'.sql';
            $binary = is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump';
            $process = new Process([$binary, '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], '--single-transaction', '--routines', '--triggers', '--result-file='.$path, $config['database']], null, ['MYSQL_PWD' => $config['password'] ?? '']);
            $process->setTimeout(120)->mustRun();
            $this->info('Backup: '.$path.' ('.filesize($path).' bytes)');
            $this->line('SHA256: '.hash_file('sha256', $path));
        }
        if ($path = $this->option('csv')) {
            $preview = app(EvaluationCsvImport::class)->parse($path, ['layout' => 'report', 'category_column' => 0, 'criterion_column' => 1]);
            $this->line("CSV: {$preview['category_count']} categories; {$preview['subgroup_count']} subgroups; {$preview['criteria_count']} criteria; ".count($preview['errors']).' errors');
            foreach ($preview['errors'] as $error) {
                $this->error('Row '.($error['row'] ?? '?').': '.$error['message']);
            }
            if ($preview['errors']) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
