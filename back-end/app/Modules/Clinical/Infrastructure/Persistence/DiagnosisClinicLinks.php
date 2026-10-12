<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence;

use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;

/**
 * Default diagnosis catalogue, each code tied to the clinics that use it.
 * Existing links are left alone so an administrator can retarget a clinic.
 */
final class DiagnosisClinicLinks
{
    public static function ensureDefaults(): void
    {
        foreach (self::defaults() as [$code, $title, $category, $clinicCodes]) {
            $row = DiagnosisCodeModel::query()->firstOrCreate(
                ['system' => 'icd10', 'code' => $code],
                ['title' => $title, 'category' => $category, 'is_active' => true],
            );

            if ($row->clinics()->exists()) {
                continue;
            }

            $ids = ClinicModel::query()->whereIn('code', $clinicCodes)->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }

            $row->clinics()->syncWithoutDetaching($ids->all());
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    private static function defaults(): array
    {
        return [
            ['A09', 'التهاب معدة وأمعاء إنتاني', 'الأمراض الإنتانية', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN']],
            ['E11', 'داء السكري من النمط الثاني', 'أمراض الغدد الصم', ['IM-ENDO', 'IM-GEN']],
            ['E78.5', 'فرط شحوم الدم', 'أمراض الغدد الصم', ['IM-ENDO', 'IM-GEN']],
            ['I10', 'ارتفاع الضغط الشرياني الأساسي', 'أمراض الدوران', ['IM-CARD', 'IM-GEN', 'ER-TRIAGE']],
            ['I21', 'احتشاء العضلة القلبية الحاد', 'أمراض الدوران', ['IM-CARD', 'ER-TRIAGE']],
            ['J00', 'زكام حاد', 'الأمراض التنفسية', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN', 'EN-GEN']],
            ['J03', 'التهاب اللوزتين الحاد', 'الأمراض التنفسية', ['EN-GEN', 'PD-GEN', 'ER-TRIAGE']],
            ['J06.9', 'التهاب حاد في الطرق التنفسية العلوية', 'الأمراض التنفسية', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN', 'EN-GEN']],
            ['J18.9', 'ذات الرئة غير محددة العامل', 'الأمراض التنفسية', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN']],
            ['J45', 'الربو القصبي', 'الأمراض التنفسية', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN']],
            ['K02', 'نخر الأسنان', 'أمراض الفم', ['DN-GEN']],
            ['K05', 'التهاب اللثة', 'أمراض الفم', ['DN-GEN']],
            ['K29.7', 'التهاب المعدة', 'أمراض الجهاز الهضمي', ['IM-GAST', 'IM-GEN']],
            ['K35', 'التهاب الزائدة الدودية الحاد', 'أمراض الجهاز الهضمي', ['SR-GEN', 'ER-TRIAGE']],
            ['L20', 'التهاب الجلد التأتبي', 'الأمراض الجلدية', ['DR-GEN']],
            ['L30.9', 'التهاب جلد غير محدد', 'الأمراض الجلدية', ['DR-GEN']],
            ['H10.9', 'التهاب الملتحمة', 'أمراض العين', ['OP-GEN']],
            ['H52', 'أخطاء الانكسار', 'أمراض العين', ['OP-GEN']],
            ['H66', 'التهاب الأذن الوسطى', 'أمراض الأذن', ['EN-GEN', 'PD-GEN']],
            ['M54.5', 'ألم أسفل الظهر', 'الأمراض العضلية الهيكلية', ['SR-ORTHO', 'IM-GEN']],
            ['N20', 'حصيات الكلية والحالب', 'الأمراض البولية التناسلية', ['SR-URO']],
            ['N39.0', 'إنتان الطرق البولية', 'الأمراض البولية التناسلية', ['SR-URO', 'IM-GEN']],
            ['N76', 'التهاب المهبل', 'الأمراض النسائية', ['OB-GYN']],
            ['O80', 'ولادة طبيعية مفردة', 'الحمل والولادة', ['OB-ANC', 'OB-GYN']],
            ['Z34', 'متابعة حمل طبيعي', 'الحمل والولادة', ['OB-ANC']],
            ['P07', 'وليد خديج', 'حديثو الولادة', ['PD-NEO']],
            ['P59.9', 'يرقان الوليد', 'حديثو الولادة', ['PD-NEO']],
            ['R50.9', 'حرارة غير محددة السبب', 'الأعراض والعلامات', ['IM-GEN', 'ER-TRIAGE', 'PD-GEN']],
            ['R10.4', 'ألم بطني غير محدد', 'الأعراض والعلامات', ['IM-GEN', 'IM-GAST', 'ER-TRIAGE', 'SR-GEN']],
            ['S52', 'كسر في الساعد', 'الرضوض', ['SR-ORTHO', 'ER-TRIAGE']],
            ['S82', 'كسر في الساق', 'الرضوض', ['SR-ORTHO', 'ER-TRIAGE']],
            ['Z00.0', 'فحص طبي عام', 'الحالة الصحية', ['IM-GEN', 'PD-GEN']],
        ];
    }
}
