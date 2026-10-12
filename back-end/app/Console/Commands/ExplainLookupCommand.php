<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prints the MySQL plan for the visit list. Run it on a copy of the data
 * before relying on a new index; it does not change the schema.
 */
final class ExplainLookupCommand extends Command
{
    protected $signature = 'lookup:explain {--department= : Department id} {--status=completed : Visit status}';

    protected $description = 'EXPLAIN ANALYZE the department/status/date visit query';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('EXPLAIN ANALYZE يعمل على MySQL فقط. الاتصال الحالي: '.DB::getDriverName());

            return self::FAILURE;
        }

        $department = $this->option('department');
        $status = (string) $this->option('status');

        if ($department === null || $department === '') {
            $department = DB::table('departments')->orderBy('id')->value('id');
        }

        if ($department === null) {
            $this->error('لا يوجد قسم لتجربة الخطة.');

            return self::FAILURE;
        }

        $rows = DB::select(
            'EXPLAIN ANALYZE SELECT id, patient_id, status, created_at FROM encounters WHERE department_id = ? AND status = ? ORDER BY created_at DESC LIMIT 25',
            [(int) $department, $status],
        );

        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $this->line((string) ($values[0] ?? json_encode($row)));
        }

        return self::SUCCESS;
    }
}
