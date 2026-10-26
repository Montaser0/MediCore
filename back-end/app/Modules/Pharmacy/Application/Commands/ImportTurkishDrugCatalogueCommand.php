<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Commands;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * Adds the Turkish barcode catalogue beside the Syrian list.
 * Documents are not extra products: they only fill a missing ingredient or company.
 */
final class ImportTurkishDrugCatalogueCommand extends Command
{
    protected $signature = 'pharmacy:import-turkish-catalogue';

    protected $description = 'Add the Turkish medicine catalogue, including barcodes, to the drug reference.';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $directory = storage_path('app/catalogue');
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->error('تعذر إنشاء مجلد الدليل.');

            return self::FAILURE;
        }

        $drugs = $directory.DIRECTORY_SEPARATOR.'turkiye-drugs.parquet';
        $documents = $directory.DIRECTORY_SEPARATOR.'turkiye-documents.parquet';
        $json = $directory.DIRECTORY_SEPARATOR.'turkiye-drugs.json';

        try {
            $this->download(
                'https://huggingface.co/datasets/batuhanilgarr/turkiye-ilac-katalogu/resolve/main/data/drugs.parquet',
                $drugs,
            );
            $this->download(
                'https://huggingface.co/datasets/batuhanilgarr/turkiye-ilac-katalogu/resolve/main/data/documents.parquet',
                $documents,
            );
            $this->export($drugs, $documents, $json);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $rows = json_decode((string) file_get_contents($json), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('تعذر قراءة الدليل التركي: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($rows)) {
            $this->error('ملف الدليل التركي غير صالح.');

            return self::FAILURE;
        }

        $now = now()->toDateTimeString();
        $mapped = [];
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $item = $this->map($row, $now);
            if ($item === null) {
                $skipped++;
                continue;
            }

            $mapped[$item['source_record_id']] = $item;
        }

        $mapped = array_values($mapped);
        if ($mapped === []) {
            $this->error('لا توجد أدوية بباركود في الدليل التركي.');

            return self::FAILURE;
        }

        DB::disableQueryLog();
        $bar = $this->output->createProgressBar((int) ceil(count($mapped) / 400));
        $bar->start();

        foreach (array_chunk($mapped, 400) as $chunk) {
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

        $withBarcode = DrugCatalogueItemModel::query()
            ->where('dataset', 'turkiye-ilac-katalogu')
            ->whereNotNull('barcode')
            ->count();

        $this->info('أُضيف '.$withBarcode.' صنفاً تركياً مع الباركود. المتجاهل: '.$skipped);

        return self::SUCCESS;
    }

    private function download(string $url, string $path): void
    {
        $this->info('تنزيل '.$url);
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MediCore\r\n",
                'timeout' => 180,
                'follow_location' => 1,
            ],
        ]);
        $bytes = file_get_contents($url, false, $context);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('تعذر تنزيل الدليل التركي.');
        }

        file_put_contents($path, $bytes);
    }

    private function export(string $drugs, string $documents, string $json): void
    {
        $script = <<<'PY'
import json
import sys
import pyarrow.parquet as pq

drugs = pq.read_table(sys.argv[1], columns=[
    "barcode", "product_name", "manufacturer", "generic_name", "atc_code", "atc_name",
    "product_type_code", "product_type_label", "sgk_status", "kub_doc_id",
]).to_pylist()
documents = pq.read_table(sys.argv[2], columns=["doc_id", "active_ingredient", "titck_firm"]).to_pylist()
by_id = {row.get("doc_id"): row for row in documents if row.get("doc_id")}
out = []
for drug in drugs:
    barcode = str(drug.get("barcode") or "").strip()
    if barcode.endswith(".0"):
        barcode = barcode[:-2]
    doc = by_id.get(drug.get("kub_doc_id")) or {}
    out.append({
        "barcode": barcode,
        "product_name": drug.get("product_name"),
        "manufacturer": drug.get("manufacturer"),
        "generic_name": drug.get("generic_name"),
        "atc_code": drug.get("atc_code"),
        "atc_name": drug.get("atc_name"),
        "product_type_code": drug.get("product_type_code"),
        "product_type_label": drug.get("product_type_label"),
        "sgk_status": drug.get("sgk_status"),
        "active_ingredient": doc.get("active_ingredient"),
        "titck_firm": doc.get("titck_firm"),
    })
with open(sys.argv[3], "w", encoding="utf-8") as handle:
    json.dump(out, handle, ensure_ascii=False)
PY;

        $scriptPath = dirname($json).DIRECTORY_SEPARATOR.'export-turkish-catalogue.py';
        file_put_contents($scriptPath, $script);

        $python = 'python';
        $command = escapeshellarg($python).' '.escapeshellarg($scriptPath).' '.escapeshellarg($drugs).' '.escapeshellarg($documents).' '.escapeshellarg($json);
        exec($command, $output, $code);
        if ($code !== 0 || ! is_file($json)) {
            throw new RuntimeException('تعذر قراءة ملفات الدليل التركي.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function map(array $row, string $now): ?array
    {
        $barcode = $this->clip($row['barcode'] ?? null, 100);
        $name = $this->clip($row['product_name'] ?? null, 180);
        if ($barcode === null || $name === null || ! preg_match('/^\d{8,14}$/', $barcode)) {
            return null;
        }

        $generic = $this->clip($row['generic_name'] ?? null, 8000);
        if ($generic === null || str_starts_with($generic, '_')) {
            $generic = $this->clip($row['active_ingredient'] ?? null, 8000);
        }

        $company = $this->clip($row['manufacturer'] ?? null, 80) ?? $this->clip($row['titck_firm'] ?? null, 80);
        $typeCode = strtoupper(trim((string) ($row['product_type_code'] ?? '')));
        $productType = $typeCode === 'B'
            ? 'دواء'
            : ($this->clip($row['product_type_label'] ?? null, 40) ?? 'غير محسوم');

        return [
            'source_record_id' => 'TR-'.$barcode,
            'barcode' => $barcode,
            'name' => $name,
            'generic_name' => $generic,
            'strength' => null,
            'dosage_form' => null,
            'pack' => null,
            'company' => $company,
            'company_ar' => null,
            'product_type' => $productType,
            'product_family' => $this->clip($row['atc_code'] ?? null, 40),
            'registration_status' => $this->clip($row['sgk_status'] ?? null, 120),
            'dataset' => 'turkiye-ilac-katalogu',
            'published_category' => $this->clip($row['atc_name'] ?? null, 150),
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

    private function syncLinkedMedications(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('
            UPDATE medications AS m
            INNER JOIN drug_catalogue_items AS c ON c.id = m.catalogue_item_id
            SET
                m.barcode = COALESCE(NULLIF(c.barcode, \'\'), m.barcode)
        ');
    }
}
