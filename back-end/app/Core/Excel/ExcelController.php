<?php

declare(strict_types=1);

namespace App\Core\Excel;

use App\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExcelController
{
    public function __construct(private readonly ExcelExchange $excel) {}

    public function template(Request $request, string $book): BinaryFileResponse|JsonResponse
    {
        return $this->download($request, $book, true);
    }

    public function export(Request $request, string $book): BinaryFileResponse|JsonResponse
    {
        return $this->download($request, $book, false);
    }

    public function import(Request $request, string $book): JsonResponse
    {
        $definition = $this->definition($book);

        if ($definition instanceof JsonResponse) {
            return $definition;
        }

        if (! $this->allows($request, $definition->managePermission)) {
            return ApiResponse::message('ليست لديك صلاحية لاستيراد هذه البيانات.', 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:8192'],
        ], [
            'file.required' => 'اختر ملف إكسل.',
            'file.max' => 'حجم الملف أكبر من 8 ميغابايت.',
        ]);

        $extension = strtolower((string) $request->file('file')?->getClientOriginalExtension());

        if ($extension !== 'xlsx') {
            return ApiResponse::message('احفظ الملف بصيغة xlsx ثم أعد الاستيراد.', 422);
        }

        try {
            $result = $this->excel->import($definition, (string) $request->file('file')?->getRealPath());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::message($exception->getMessage(), 422);
        }

        return ApiResponse::data($result);
    }

    private function download(Request $request, string $book, bool $template): BinaryFileResponse|JsonResponse
    {
        $definition = $this->definition($book);

        if ($definition instanceof JsonResponse) {
            return $definition;
        }

        if (! $this->allows($request, $definition->viewPermission) && ! $this->allows($request, $definition->managePermission)) {
            return ApiResponse::message('ليست لديك صلاحية لتنزيل هذه البيانات.', 403);
        }

        try {
            $path = $this->excel->spreadsheet($definition, $template);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::message($exception->getMessage(), 422);
        }

        return response()->download(
            $path,
            $definition->key.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    private function definition(string $book): ExcelBook|JsonResponse
    {
        $definition = $this->excel->find($book);

        if ($definition === null) {
            return ApiResponse::message('نوع البيانات غير معروف.', 404);
        }

        return $definition;
    }

    private function allows(Request $request, string $permission): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($permission === '') {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        return $user->can($permission) === true;
    }
}
