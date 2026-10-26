<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Commands;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

final class ImportDrugCatalogueCommand extends Command
{
    protected $signature = 'pharmacy:import-catalogue {path?}';

    protected $description = 'Import the Syrian medicine list. Quantities are not imported.';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $path = $this->resolvePath();
        if (! is_file($path)) {
            $this->error('ملف دليل الأدوية غير موجود: '.$path);

            return self::FAILURE;
        }

        $this->info('قراءة الدليل...');

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('تعذر قراءة ملف الدليل: '.$exception->getMessage());

            return self::FAILURE;
        }

        $records = is_array($decoded) ? ($decoded['records'] ?? null) : null;
        unset($decoded);

        if (! is_array($records)) {
            $this->error('الملف لا يحتوي قائمة أدوية.');

            return self::FAILURE;
        }

        $now = now()->toDateTimeString();
        $rows = [];
        $skipped = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                $skipped++;
                continue;
            }

            $mapped = $this->map($record, $now);
            if ($mapped === null) {
                $skipped++;
                continue;
            }

            $rows[] = $mapped;
        }

        unset($records);

        $bar = $this->output->createProgressBar((int) ceil(count($rows) / 400));
        $bar->start();

        DB::disableQueryLog();

        foreach (array_chunk($rows, 400) as $chunk) {
            DrugCatalogueItemModel::query()->upsert($chunk, ['source_record_id'], [
                'name',
                'generic_name',
                'strength',
                'dosage_form',
                'pack',
                'company',
                'company_ar',
                'product_type',
                'product_family',
                'registration_status',
                'dataset',
                'published_category',
                'barcode',
                'updated_at',
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->syncLinkedMedications();

        $this->info('تم إدخال '.count($rows).' صنفاً. تُركت الكمية للصيدلي. المتجاهل: '.$skipped);

        return self::SUCCESS;
    }

    private function resolvePath(): string
    {
        $given = $this->argument('path');
        if (is_string($given) && $given !== '') {
            return $given;
        }

        return dirname(base_path()).DIRECTORY_SEPARATOR.'DOCTOR_AIC_Syria_2026_Sources.json';
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function map(array $record, string $now): ?array
    {
        $recordId = $this->clip($record['record_id'] ?? null, 40);
        $name = $this->clip($record['name'] ?? null, 180);
        if ($recordId === null || $name === null) {
            return null;
        }

        return [
            'source_record_id' => $recordId,
            'barcode' => $this->clip($record['barcode'] ?? null, 100),
            'name' => $name,
            'generic_name' => $this->clip($record['ingredient_as_published'] ?? null, 8000),
            'strength' => $this->clip($record['strength_as_published'] ?? null, 80),
            'dosage_form' => $this->clip($record['form_as_published'] ?? null, 80),
            'pack' => $this->clip($record['pack_as_published'] ?? null, 100),
            'company' => $this->clip($record['company'] ?? null, 80),
            'company_ar' => $this->clip($record['company_ar'] ?? null, 80),
            'product_type' => $this->clip($record['product_type'] ?? null, 40),
            'product_family' => $this->clip($record['product_family'] ?? null, 40),
            'registration_status' => $this->clip($record['registration_status'] ?? null, 120),
            'dataset' => $this->clip($record['dataset'] ?? null, 64),
            'published_category' => $this->clip($record['published_category'] ?? null, 150),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function clip(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }

    /**
     * Refresh names already on the shelf. Quantity and price stay as the pharmacist left them.
     */
    private function syncLinkedMedications(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('
            UPDATE medications AS m
            INNER JOIN drug_catalogue_items AS c ON c.id = m.catalogue_item_id
            SET
                m.name = c.name,
                m.generic_name = LEFT(c.generic_name, 255),
                m.strength = c.strength,
                m.dosage_form = c.dosage_form,
                m.pack = c.pack,
                m.manufacturer = COALESCE(NULLIF(c.company_ar, \'\'), c.company),
                m.category = c.product_type,
                m.barcode = COALESCE(NULLIF(c.barcode, \'\'), m.barcode)
        ');
    }
}
