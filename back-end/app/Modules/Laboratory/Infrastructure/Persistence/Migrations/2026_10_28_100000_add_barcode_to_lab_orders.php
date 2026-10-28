<?php

declare(strict_types=1);

use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Application\Support\LabOrderSource;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table): void {
            $table->string('barcode', 13)->nullable()->unique()->after('order_id');
            $table->string('clinic_name', 150)->nullable()->after('barcode');
            $table->string('doctor_name', 150)->nullable()->after('clinic_name');
        });

        LabOrderModel::query()->orderBy('id')->each(function (LabOrderModel $labOrder): void {
            $order = OrderModel::query()->find($labOrder->order_id);
            $names = $order === null
                ? ['clinic' => null, 'doctor' => null]
                : LabOrderSource::names($order);

            $labOrder->forceFill([
                'barcode' => $labOrder->barcode ?: LabBarcode::next(),
                'clinic_name' => $names['clinic'],
                'doctor_name' => $names['doctor'],
            ])->save();
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['barcode', 'clinic_name', 'doctor_name']);
        });
    }
};
