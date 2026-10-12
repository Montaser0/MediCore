<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'أمراض الدم' => 'hematology',
            'التخثر' => 'hematology',
            'بنك الدم' => 'hematology',
            'الكيمياء الحيوية' => 'chemistry',
            'المناعة' => 'chemistry',
            'الأحياء الدقيقة' => 'chemistry',
            'الغدد الصم' => 'hormones',
        ];

        foreach ($map as $from => $to) {
            DB::table('lab_tests')->where('category', $from)->update(['category' => $to]);
        }
    }

    public function down(): void
    {
        $map = [
            'hematology' => 'أمراض الدم',
            'chemistry' => 'الكيمياء الحيوية',
            'hormones' => 'الغدد الصم',
        ];

        foreach ($map as $from => $to) {
            DB::table('lab_tests')->where('category', $from)->update(['category' => $to]);
        }
    }
};
