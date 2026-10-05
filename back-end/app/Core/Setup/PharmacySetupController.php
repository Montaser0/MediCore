<?php

declare(strict_types=1);

namespace App\Core\Setup;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final class PharmacySetupController extends ApiController
{
    public function status(CompletePharmacySetup $setup): JsonResponse
    {
        return ApiResponse::data($setup->status());
    }

    public function store(Request $request, CompletePharmacySetup $setup): JsonResponse
    {
        $data = $request->validate([
            'pharmacy_name' => ['required', 'string', 'max:150'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'pharmacy_name.required' => 'اسم الصيدلية مطلوب.',
            'admin_name.required' => 'اسم المدير مطلوب.',
            'admin_email.required' => 'البريد الإلكتروني مطلوب.',
            'admin_email.email' => 'البريد الإلكتروني غير صالح.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ]);

        try {
            $setup->complete($data);
        } catch (SetupException $exception) {
            return ApiResponse::message($exception->getMessage(), 409);
        }

        return ApiResponse::message('تم إنشاء حساب المدير.');
    }
}
