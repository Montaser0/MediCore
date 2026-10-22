<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class UploadStudyImagesRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::RADIOLOGY_IMAGES_UPLOAD;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('hms.documents.imaging.max_package_kb', 512000);
        $maxBatch = (int) config('hms.documents.imaging.max_batch', 30);

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$maxBatch],
            'images.*' => ['required', 'file', 'max:'.$maxKb],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'اختر صورة واحدة على الأقل للرفع.',
            'images.max' => 'عدد الملفات في الدفعة أكبر من المسموح. ارفع مجلد الفحص أو ملف ZIP.',
            'images.*.file' => 'تعذر رفع الملف. إذا كان الفحص كبيراً أرسله ملفاً مضغوطاً واحداً.',
            'images.*.max' => 'حجم الملف أكبر من الحد المسموح.',
        ];
    }
}
