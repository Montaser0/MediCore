<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTheatreModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationCategoryModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;
use Illuminate\Database\Seeder;

/**
 * الفهارس المرجعية التي يحتاجها النظام ليكون صالحاً للعمل من اليوم الأول:
 * تحاليل يمكن طلبها، صور شعاعية، أدوية يمكن وصفها، قائمة أسعار، وأصناف
 * مستودعية. الأسماء بالعربية والرموز بالحروف اللاتينية.
 */
final class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->firstOrFail();

        $hospitalId = (int) $hospital->getKey();

        $this->labTests();
        $this->radiologyServices();
        $this->warehouses($hospitalId);
        $this->medications();
        $this->medicalSupplies();
        $this->services();
        $this->diagnosisCodes();
        $this->operations($hospitalId);
    }

    private function labTests(): void
    {
        $tests = [
            // الاسم، الرمز، التصنيف، نوع العينة، السعر، مدة الإنجاز بالساعات، هل هو بروفيل
            ['تعداد دم كامل', 'CBC', 'أمراض الدم', 'دم', 8000, 4, true],
            ['خضاب الدم', 'HGB', 'أمراض الدم', 'دم', 3000, 2, false],
            ['سكر الدم الصيامي', 'FBS', 'الكيمياء الحيوية', 'دم', 4000, 2, false],
            ['الخضاب السكري التراكمي', 'HBA1C', 'الكيمياء الحيوية', 'دم', 12000, 24, false],
            ['وظائف الكبد', 'LFT', 'الكيمياء الحيوية', 'دم', 15000, 6, true],
            ['وظائف الكلية', 'RFT', 'الكيمياء الحيوية', 'دم', 14000, 6, true],
            ['صورة شحوم الدم', 'LIPID', 'الكيمياء الحيوية', 'دم', 13000, 6, true],
            ['وظائف الغدة الدرقية', 'TFT', 'الغدد الصم', 'دم', 20000, 24, true],
            ['البروتين الارتكاسي C', 'CRP', 'المناعة', 'دم', 9000, 4, false],
            ['سرعة التثفل', 'ESR', 'أمراض الدم', 'دم', 5000, 3, false],
            ['تحليل البول العام', 'UA', 'الأحياء الدقيقة', 'بول', 5000, 3, true],
            ['زرع البول', 'UCULT', 'الأحياء الدقيقة', 'بول', 18000, 72, false],
            ['زرع الدم', 'BCULT', 'الأحياء الدقيقة', 'دم', 25000, 120, false],
            ['زمن البروثرومبين والـ INR', 'PT-INR', 'التخثر', 'دم', 10000, 4, true],
            ['الشوارد في الدم', 'LYTES', 'الكيمياء الحيوية', 'دم', 11000, 3, true],
            ['هرمون الحمل', 'BHCG', 'الغدد الصم', 'دم', 10000, 4, false],
            ['زمرة الدم والعامل الريزوسي', 'ABO-RH', 'بنك الدم', 'دم', 6000, 2, false],
            ['فيتامين D', 'VITD', 'الغدد الصم', 'دم', 22000, 48, false],
        ];

        foreach ($tests as [$name, $code, $category, $sample, $price, $turnaround, $isPanel]) {
            LabTestModel::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'sample_type' => $sample,
                'price' => $price,
                'turnaround_hours' => $turnaround,
                'is_panel' => $isPanel,
                'is_active' => true,
            ]);
        }

        $this->cbcParameters();
    }

    /**
     * مثال كامل على بروفيل بمعاييره وحدوده الحرجة، حتى تكون شاشة إدخال
     * النتائج قابلة للتجربة فوراً.
     */
    private function cbcParameters(): void
    {
        $cbc = LabTestModel::query()->where('code', 'CBC')->first();

        if ($cbc === null || $cbc->parameters()->exists()) {
            return;
        }

        $parameters = [
            // الاسم، الرمز، الواحدة، الحد الطبيعي الأدنى/الأعلى، الحد الحرج الأدنى/الأعلى
            ['خضاب الدم', 'HGB', 'g/dL', 12.0, 17.0, 7.0, 20.0],
            ['الكريات البيض', 'WBC', '10^3/uL', 4.0, 11.0, 1.5, 30.0],
            ['الصفيحات الدموية', 'PLT', '10^3/uL', 150.0, 450.0, 50.0, 1000.0],
            ['الهيماتوكريت', 'HCT', '%', 36.0, 50.0, 20.0, 60.0],
            ['الكريات الحمر', 'RBC', '10^6/uL', 4.2, 5.9, null, null],
        ];

        foreach ($parameters as $index => [$name, $code, $unit, $low, $high, $criticalLow, $criticalHigh]) {
            $cbc->parameters()->create([
                'name' => $name,
                'code' => $code,
                'unit' => $unit,
                'reference_range' => $low.' - '.$high.' '.$unit,
                'normal_low' => $low,
                'normal_high' => $high,
                'critical_low' => $criticalLow,
                'critical_high' => $criticalHigh,
                'display_order' => $index,
            ]);
        }
    }

    private function radiologyServices(): void
    {
        $services = [
            // الاسم، الرمز، الجهاز، السعر، هل يحتاج مادة ظليلة
            ['صورة صدر شعاعية', 'XR-CHEST', 'xray', 20000, false],
            ['صورة بطن شعاعية', 'XR-ABD', 'xray', 22000, false],
            ['صورة شعاعية للأطراف', 'XR-EXT', 'xray', 18000, false],
            ['طبقي محوري للدماغ', 'CT-BRAIN', 'ct', 90000, false],
            ['طبقي محوري للبطن مع حقن', 'CT-ABD-C', 'ct', 140000, true],
            ['طبقي محوري للصدر', 'CT-CHEST', 'ct', 110000, false],
            ['رنين مغناطيسي للدماغ', 'MRI-BRAIN', 'mri', 220000, false],
            ['رنين مغناطيسي للفقرات القطنية', 'MRI-LSPINE', 'mri', 230000, false],
            ['إيكو بطن', 'US-ABD', 'ultrasound', 35000, false],
            ['إيكو حمل', 'US-OB', 'ultrasound', 40000, false],
            ['إيكو قلب', 'ECHO', 'ultrasound', 60000, false],
            ['تصوير الثدي الشعاعي', 'MAMMO', 'mammography', 55000, false],
        ];

        foreach ($services as [$name, $code, $modality, $price, $contrast]) {
            RadiologyServiceModel::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'modality' => $modality,
                'price' => $price,
                'requires_contrast' => $contrast,
                'is_active' => true,
            ]);
        }
    }

    private function warehouses(int $hospitalId): void
    {
        $pharmacyDepartment = DepartmentModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('code', 'PHAR')
            ->first();

        $warehouseDepartment = DepartmentModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('code', 'WH')
            ->first();

        WarehouseModel::query()->firstOrCreate(
            ['hospital_id' => $hospitalId, 'code' => 'MAIN-STORE'],
            [
                'name' => 'المستودع الرئيسي',
                'type' => 'مركزي',
                'department_id' => $warehouseDepartment?->getKey(),
                'is_pharmacy' => false,
                'is_active' => true,
            ],
        );

        WarehouseModel::query()->firstOrCreate(
            ['hospital_id' => $hospitalId, 'code' => 'PHARMACY'],
            [
                'name' => 'صيدلية المستشفى',
                'type' => 'صيدلية',
                'department_id' => $pharmacyDepartment?->getKey(),
                'is_pharmacy' => true,
                'is_active' => true,
            ],
        );
    }

    /**
     * كل دواء يُنشأ مع صنف مستودعي يُصرف منه، لأن الدواء الذي لا تستطيع
     * الصيدلية سحبه من المستودع لا يمكن صرفه أصلاً.
     */
    private function medications(): void
    {
        $categories = [
            ['المسكنات وخافضات الحرارة', 'ANALG', false],
            ['الصادات الحيوية', 'ANTIB', false],
            ['أدوية الضغط', 'AHTN', false],
            ['أدوية السكري', 'ADM', false],
            ['أدوية الجهاز الهضمي', 'GIT', false],
            ['المواد المخدرة والمراقبة', 'CTRL', true],
        ];

        foreach ($categories as [$name, $code, $controlled]) {
            MedicationCategoryModel::query()->firstOrCreate(['name' => $name], [
                'code' => $code,
                'is_controlled' => $controlled,
            ]);
        }

        $medications = [
            // الاسم التجاري، الاسم العلمي، العيار، الشكل، طريق الإعطاء، التصنيف، السعر، رمز الصنف
            ['باراسيتامول', 'Paracetamol', '500 ملغ', 'قرص', 'فموي', 'المسكنات وخافضات الحرارة', 100, 'MED-PARA500'],
            ['ايبوبروفين', 'Ibuprofen', '400 ملغ', 'قرص', 'فموي', 'المسكنات وخافضات الحرارة', 150, 'MED-IBU400'],
            ['ديكلوفيناك', 'Diclofenac', '75 ملغ', 'أمبولة', 'عضلي', 'المسكنات وخافضات الحرارة', 400, 'MED-DICLO75'],
            ['أموكسيسيلين', 'Amoxicillin', '500 ملغ', 'كبسولة', 'فموي', 'الصادات الحيوية', 300, 'MED-AMOX500'],
            ['سيفترياكسون', 'Ceftriaxone', '1 غ', 'فيال', 'وريدي', 'الصادات الحيوية', 2500, 'MED-CEFT1G'],
            ['أزيثرومايسين', 'Azithromycin', '250 ملغ', 'قرص', 'فموي', 'الصادات الحيوية', 800, 'MED-AZI250'],
            ['سيبروفلوكساسين', 'Ciprofloxacin', '500 ملغ', 'قرص', 'فموي', 'الصادات الحيوية', 450, 'MED-CIPRO500'],
            ['أملوديبين', 'Amlodipine', '5 ملغ', 'قرص', 'فموي', 'أدوية الضغط', 120, 'MED-AMLO5'],
            ['ليزينوبريل', 'Lisinopril', '10 ملغ', 'قرص', 'فموي', 'أدوية الضغط', 140, 'MED-LISI10'],
            ['بيسوبرولول', 'Bisoprolol', '5 ملغ', 'قرص', 'فموي', 'أدوية الضغط', 160, 'MED-BISO5'],
            ['ميتفورمين', 'Metformin', '850 ملغ', 'قرص', 'فموي', 'أدوية السكري', 110, 'MED-MET850'],
            ['إنسولين غلارجين', 'Insulin Glargine', '100 وحدة/مل', 'قلم', 'تحت الجلد', 'أدوية السكري', 18000, 'MED-GLAR'],
            ['أوميبرازول', 'Omeprazole', '20 ملغ', 'كبسولة', 'فموي', 'أدوية الجهاز الهضمي', 130, 'MED-OME20'],
            ['ميتوكلوبراميد', 'Metoclopramide', '10 ملغ', 'أمبولة', 'وريدي', 'أدوية الجهاز الهضمي', 250, 'MED-METO10'],
            ['مورفين', 'Morphine Sulfate', '10 ملغ/مل', 'أمبولة', 'وريدي', 'المواد المخدرة والمراقبة', 3200, 'MED-MORPH10'],
        ];

        foreach ($medications as [$name, $generic, $strength, $form, $route, $category, $price, $sku]) {
            $categoryModel = MedicationCategoryModel::query()->where('name', $category)->first();

            $item = InventoryItemModel::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name.' '.$strength,
                    'category' => $category,
                    'unit' => $form,
                    'item_type' => 'medicine',
                    'minimum_stock' => 100,
                    'reorder_quantity' => 500,
                    'is_batch_tracked' => true,
                    'is_active' => true,
                ],
            );

            MedicationModel::query()->firstOrCreate(
                ['name' => $name, 'strength' => $strength],
                [
                    'generic_name' => $generic,
                    'dosage_form' => $form,
                    'route' => $route,
                    'category' => $category,
                    'medication_category_id' => $categoryModel?->getKey(),
                    'inventory_item_id' => $item->getKey(),
                    'price' => $price,
                    'requires_prescription' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    private function medicalSupplies(): void
    {
        $supplies = [
            // الاسم، الرمز، الواحدة، الحد الأدنى للمخزون
            ['قفازات جراحية', 'SUP-GLOVES', 'زوج', 500],
            ['محقنة 5 مل', 'SUP-SYR5', 'قطعة', 1000],
            ['محقنة 10 مل', 'SUP-SYR10', 'قطعة', 600],
            ['قثطرة وريدية قياس 20', 'SUP-IV20', 'قطعة', 300],
            ['شاش طبي', 'SUP-GAUZE', 'لفة', 200],
            ['خيوط جراحية 3-0', 'SUP-SUT30', 'علبة', 100],
            ['سيروم ملحي 500 مل', 'SUP-NS500', 'كيس', 300],
            ['سيروم سكري 5% 500 مل', 'SUP-D5500', 'كيس', 200],
            ['كمامات طبية', 'SUP-MASK', 'قطعة', 1000],
            ['لاصق طبي', 'SUP-TAPE', 'لفة', 150],
        ];

        foreach ($supplies as [$name, $sku, $unit, $minimum]) {
            InventoryItemModel::query()->firstOrCreate(['sku' => $sku], [
                'name' => $name,
                'category' => 'مستلزمات طبية',
                'unit' => $unit,
                'item_type' => 'medical_supply',
                'minimum_stock' => $minimum,
                'reorder_quantity' => $minimum * 2,
                'is_batch_tracked' => true,
                'is_active' => true,
            ]);
        }
    }

    private function services(): void
    {
        $services = [
            // الاسم، الرمز، التصنيف، السعر
            ['معاينة عامة', 'CONS-GEN', 'المعاينات', 15000],
            ['معاينة اختصاصي', 'CONS-SPEC', 'المعاينات', 30000],
            ['معاينة إسعافية', 'CONS-ER', 'المعاينات', 25000],
            ['معاينة إعادة', 'CONS-FU', 'المعاينات', 10000],
            ['تضميد جرح', 'PROC-DRESS', 'الإجراءات', 12000],
            ['خياطة جرح', 'PROC-SUT', 'الإجراءات', 25000],
            ['جلسة بخاخ', 'PROC-NEB', 'الإجراءات', 8000],
            ['تركيب قثطرة وريدية', 'PROC-IV', 'الإجراءات', 6000],
            ['تخطيط قلب', 'PROC-ECG', 'الفحوص التشخيصية', 15000],
            ['حقنة عضلية', 'PROC-IM', 'الإجراءات', 4000],
            ['سرير في غرفة عادية (لليلة)', 'BED-GEN', 'الإقامة', 40000],
            ['سرير في غرفة خاصة (لليلة)', 'BED-PRIV', 'الإقامة', 90000],
            ['سرير عناية مشددة (لليلة)', 'BED-ICU', 'الإقامة', 250000],
        ];

        foreach ($services as [$name, $code, $category, $price]) {
            ServiceModel::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'is_taxable' => false,
                'is_active' => true,
            ]);
        }
    }

    /**
     * مجموعة أولية من تصنيف ICD-10. الفهرس الكامل يُستورد لاحقاً، وهذه تكفي
     * ليعمل حقل اختيار التشخيص.
     */
    private function diagnosisCodes(): void
    {
        $codes = [
            ['A09', 'التهاب معدة وأمعاء إنتاني', 'الأمراض الإنتانية'],
            ['E11', 'داء السكري من النمط الثاني', 'أمراض الغدد الصم'],
            ['E78.5', 'فرط شحوم الدم', 'أمراض الغدد الصم'],
            ['I10', 'ارتفاع الضغط الشرياني الأساسي', 'أمراض الدوران'],
            ['I21', 'احتشاء العضلة القلبية الحاد', 'أمراض الدوران'],
            ['J06.9', 'التهاب حاد في الطرق التنفسية العلوية', 'الأمراض التنفسية'],
            ['J18.9', 'ذات الرئة غير محددة العامل', 'الأمراض التنفسية'],
            ['J45', 'الربو القصبي', 'الأمراض التنفسية'],
            ['K29.7', 'التهاب المعدة', 'أمراض الجهاز الهضمي'],
            ['K35', 'التهاب الزائدة الدودية الحاد', 'أمراض الجهاز الهضمي'],
            ['M54.5', 'ألم أسفل الظهر', 'الأمراض العضلية الهيكلية'],
            ['N20', 'حصيات الكلية والحالب', 'الأمراض البولية التناسلية'],
            ['N39.0', 'إنتان الطرق البولية', 'الأمراض البولية التناسلية'],
            ['O80', 'ولادة طبيعية مفردة', 'الحمل والولادة'],
            ['R50.9', 'حرارة غير محددة السبب', 'الأعراض والعلامات'],
            ['R10.4', 'ألم بطني غير محدد', 'الأعراض والعلامات'],
            ['S52', 'كسر في الساعد', 'الرضوض'],
            ['S82', 'كسر في الساق', 'الرضوض'],
            ['Z00.0', 'فحص طبي عام', 'الحالة الصحية'],
        ];

        foreach ($codes as [$code, $title, $category]) {
            DiagnosisCodeModel::query()->firstOrCreate(
                ['system' => 'icd10', 'code' => $code],
                ['title' => $title, 'category' => $category, 'is_active' => true],
            );
        }
    }

    private function operations(int $hospitalId): void
    {
        $theatres = [
            ['غرفة العمليات الأولى', 'OT-1'],
            ['غرفة العمليات الثانية', 'OT-2'],
            ['غرفة العمليات اليومية', 'OT-DAY'],
        ];

        foreach ($theatres as [$name, $code]) {
            OperationTheatreModel::query()->firstOrCreate(
                ['hospital_id' => $hospitalId, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        $types = [
            // الاسم، الرمز، الاختصاص، المدة المتوقعة بالدقائق، درجة الخطورة، السعر
            ['استئصال الزائدة الدودية', 'OP-APPX', 'الجراحة العامة', 60, 'moderate', 450000],
            ['استئصال المرارة بالتنظير', 'OP-LAPCHOLE', 'الجراحة العامة', 90, 'moderate', 850000],
            ['عملية قيصرية', 'OP-CS', 'النسائية والتوليد', 60, 'moderate', 700000],
            ['إصلاح فتق', 'OP-HERNIA', 'الجراحة العامة', 75, 'low', 500000],
            ['استئصال اللوزتين', 'OP-TONSIL', 'الأذن والأنف والحنجرة', 45, 'low', 380000],
            ['تثبيت كسر بالصفيحة والبراغي', 'OP-ORIF', 'جراحة العظام', 120, 'high', 1200000],
            ['استئصال الساد', 'OP-CATARACT', 'العينية', 40, 'low', 600000],
            ['مجازة الشريان التاجي', 'OP-CABG', 'جراحة القلب والصدر', 300, 'critical', 6500000],
        ];

        foreach ($types as [$name, $code, $specialty, $minutes, $risk, $price]) {
            OperationTypeModel::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'specialty' => $specialty,
                'estimated_duration_minutes' => $minutes,
                'risk_level' => $risk,
                'price' => $price,
                'is_active' => true,
            ]);
        }
    }
}
