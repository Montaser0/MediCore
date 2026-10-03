<?php

declare(strict_types=1);

namespace App\Core\Persistence\Factories;

/**
 * مصدر الأسماء والبيانات العربية المستخدمة في الـ factories.
 *
 * Faker's Arabic locales are patchy and mix dialects, so the pools below are
 * curated instead: a Levantine hospital's patient list, not a random one.
 */
final class ArabicFaker
{
    /**
     * @var array<int, string>
     */
    private const MALE_NAMES = [
        'محمد', 'أحمد', 'عمر', 'علي', 'خالد', 'يوسف', 'إبراهيم', 'مصطفى',
        'حسن', 'حسين', 'عبد الرحمن', 'عبد الله', 'زياد', 'طارق', 'باسل',
        'وائل', 'سامر', 'فادي', 'رامي', 'نبيل', 'غسان', 'مازن', 'أنس',
        'بلال', 'كريم', 'ماهر', 'أيمن', 'هيثم', 'جمال', 'منتصر',
    ];

    /**
     * @var array<int, string>
     */
    private const FEMALE_NAMES = [
        'فاطمة', 'عائشة', 'مريم', 'زينب', 'خديجة', 'سارة', 'نور', 'ريم',
        'ليلى', 'هدى', 'سلمى', 'رنا', 'ديمة', 'لمى', 'ميساء', 'أمل',
        'سمر', 'هبة', 'رغد', 'جمانة', 'لينا', 'ندى', 'وفاء', 'إيمان',
        'شذى', 'بشرى', 'غادة', 'منى', 'ياسمين', 'تالا',
    ];

    /**
     * @var array<int, string>
     */
    private const FAMILY_NAMES = [
        'الحلبي', 'الشامي', 'الخطيب', 'العلي', 'الحسن', 'السيد', 'الأحمد',
        'الحموي', 'الدمشقي', 'الحمصي', 'الكردي', 'النجار', 'الحداد',
        'العطار', 'الصباغ', 'القاضي', 'المصري', 'الطحان', 'الخوري',
        'الحاج', 'السمان', 'الزعبي', 'العمري', 'الجابري', 'المنصور',
        'الصالح', 'الياسين', 'البكري', 'الشيخ', 'الرفاعي',
    ];

    /**
     * @var array<int, string>
     */
    private const CITIES = [
        'دمشق', 'حلب', 'حمص', 'حماة', 'اللاذقية', 'طرطوس', 'درعا',
        'السويداء', 'دير الزور', 'الحسكة', 'الرقة', 'إدلب', 'القنيطرة',
    ];

    /**
     * @var array<int, string>
     */
    private const OCCUPATIONS = [
        'معلم', 'مهندس', 'موظف', 'طالب', 'ربة منزل', 'تاجر', 'سائق',
        'ممرض', 'محاسب', 'فلاح', 'عامل', 'خياط', 'حلاق', 'صيدلي',
        'محامي', 'نجار', 'كهربائي', 'متقاعد',
    ];

    /**
     * @var array<int, string>
     */
    private const JOB_TITLES = [
        'طبيب اختصاصي', 'طبيب مقيم', 'ممرض', 'ممرضة', 'فني مخبر',
        'فني أشعة', 'صيدلي', 'موظف استقبال', 'محاسب', 'أمين مستودع',
        'رئيس قسم', 'مشرف تمريض',
    ];

    /**
     * @var array<int, string>
     */
    private const SPECIALTIES = [
        'الأمراض الباطنة', 'الجراحة العامة', 'طب الأطفال', 'النسائية والتوليد',
        'جراحة العظام', 'الأمراض القلبية', 'الجلدية', 'العينية',
        'الأذن والأنف والحنجرة', 'التخدير والإنعاش', 'الطب الإسعافي',
    ];

    public static function maleName(): string
    {
        return self::MALE_NAMES[array_rand(self::MALE_NAMES)];
    }

    public static function femaleName(): string
    {
        return self::FEMALE_NAMES[array_rand(self::FEMALE_NAMES)];
    }

    public static function firstName(string $gender = 'male'): string
    {
        return $gender === 'female' ? self::femaleName() : self::maleName();
    }

    public static function familyName(): string
    {
        return self::FAMILY_NAMES[array_rand(self::FAMILY_NAMES)];
    }

    public static function city(): string
    {
        return self::CITIES[array_rand(self::CITIES)];
    }

    public static function occupation(): string
    {
        return self::OCCUPATIONS[array_rand(self::OCCUPATIONS)];
    }

    public static function jobTitle(): string
    {
        return self::JOB_TITLES[array_rand(self::JOB_TITLES)];
    }

    public static function specialty(): string
    {
        return self::SPECIALTIES[array_rand(self::SPECIALTIES)];
    }

    public static function address(): string
    {
        $areas = ['حي الميدان', 'حي المزة', 'حي الشعلان', 'حي القصاع', 'حي برزة', 'حي الزاهرة'];

        return $areas[array_rand($areas)].' - شارع '.random_int(1, 40).' - بناء رقم '.random_int(1, 60);
    }

    /**
     * أرقام الهواتف السورية تبدأ بـ 09 وتتكوّن من عشر خانات.
     */
    public static function mobile(): string
    {
        return '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * الرقم الوطني السوري من أحد عشر رقماً.
     */
    public static function nationalId(): string
    {
        return (string) random_int(10000000000, 99999999999);
    }

    /**
     * @return array<int, string>
     */
    public static function bloodTypes(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    /**
     * @return array<int, string>
     */
    public static function maritalStatuses(): array
    {
        return ['أعزب', 'متزوج', 'مطلق', 'أرمل'];
    }

    /**
     * @return array<int, string>
     */
    public static function allergens(): array
    {
        return ['البنسلين', 'الأسبرين', 'السلفا', 'اليود', 'المكسرات', 'البيض', 'غبار الطلع', 'اللاتكس'];
    }

    /**
     * @return array<int, string>
     */
    public static function chronicConditions(): array
    {
        return [
            'ارتفاع الضغط الشرياني',
            'داء السكري من النمط الثاني',
            'الربو القصبي',
            'قصور الغدة الدرقية',
            'فرط شحوم الدم',
            'التهاب المفاصل الرثياني',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function chiefComplaints(): array
    {
        return [
            'ألم في الصدر',
            'حرارة مرتفعة منذ يومين',
            'ألم بطني',
            'صداع مستمر',
            'ضيق في التنفس',
            'سعال مع قشع',
            'ألم أسفل الظهر',
            'دوار وغثيان',
            'ألم في المفاصل',
            'مراجعة للمتابعة',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function relationships(): array
    {
        return ['الأب', 'الأم', 'الزوج', 'الزوجة', 'الابن', 'الابنة', 'الأخ', 'الأخت'];
    }
}
