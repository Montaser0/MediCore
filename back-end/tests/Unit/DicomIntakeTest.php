<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Radiology\Infrastructure\Services\DicomService;
use App\Modules\Radiology\Infrastructure\Services\ImagingIntake;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class DicomIntakeTest extends TestCase
{
    public function test_reads_patient_and_study_from_an_explicit_dicom_header(): void
    {
        $path = $this->dicom('1.2.840.10008.1.2.1', [
            $this->explicit(0x0008, 0x0060, 'CS', 'CT'),
            $this->explicit(0x0010, 0x0010, 'PN', 'AHMAD^ALI'),
            $this->explicit(0x0010, 0x0020, 'LO', 'P100'),
            $this->explicit(0x0020, 0x000D, 'UI', "1.2.840.113619.2.55.3\0"),
        ]);

        $identity = (new DicomService())->readIdentity($path);

        $this->assertSame('AHMAD ALI', $identity->patientName);
        $this->assertSame('P100', $identity->patientId);
        $this->assertSame('CT', $identity->modality);
        $this->assertSame('1.2.840.113619.2.55.3', $identity->studyInstanceUid);
        @unlink($path);
    }

    public function test_reads_an_implicit_little_endian_header(): void
    {
        $path = $this->dicom('1.2.840.10008.1.2', [
            $this->implicit(0x0010, 0x0010, 'SARA NOUR'),
            $this->implicit(0x0020, 0x000D, "1.2.3.4.5\0"),
        ]);

        $identity = (new DicomService())->readIdentity($path);

        $this->assertSame('SARA NOUR', $identity->patientName);
        $this->assertSame('1.2.3.4.5', $identity->studyInstanceUid);
        @unlink($path);
    }

    public function test_warns_when_the_dicom_name_does_not_match_the_file(): void
    {
        $dicom = new DicomService();

        $this->assertTrue($dicom->namesConflict('DOE JOHN', 'أحمد محمد'));
        $this->assertFalse($dicom->namesConflict('أحمد^محمد', 'أحمد محمد علي'));
        $this->assertFalse($dicom->namesConflict(null, 'أحمد محمد'));
    }

    public function test_unpacks_a_study_zip_and_drops_the_index_and_clutter(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is not available.');
        }

        $dcm = $this->dicom('1.2.840.10008.1.2.1', [
            $this->explicit(0x0010, 0x0010, 'PN', 'AHMAD^ALI'),
            $this->explicit(0x0020, 0x000D, 'UI', "1.2.840.10008.1\0"),
        ]);
        $zipPath = tempnam(sys_get_temp_dir(), 'hmszip');
        $this->assertNotFalse($zipPath);
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($dcm, 'study/IM0001.dcm');
        $zip->addFromString('study/DICOMDIR', 'index');
        $zip->addFromString('__MACOSX/._IM0001.dcm', 'junk');
        $zip->addFromString('study/notes.txt', 'not an image');
        $zip->addFromString('study/preview.jpg', 'jpeg-bytes');
        $zip->close();

        $package = (new ImagingIntake(new DicomService()))->collect([
            new UploadedFile($zipPath, 'study.zip', 'application/zip', UPLOAD_ERR_OK, true),
        ]);

        try {
            $this->assertCount(2, $package->files);
            $this->assertGreaterThanOrEqual(3, $package->skipped);
            $names = array_map(fn ($image) => $image->file->getClientOriginalName(), $package->files);
            $this->assertContains('IM0001.dcm', $names);
            $this->assertContains('preview.jpg', $names);
            $dcmFile = $package->files[0]->file->getClientOriginalName() === 'IM0001.dcm'
                ? $package->files[0]
                : $package->files[1];
            $this->assertSame('AHMAD ALI', $dcmFile->dicom?->patientName);
            $this->assertSame('application/dicom', $dcmFile->file->getClientMimeType());
        } finally {
            $package->cleanup();
            @unlink($dcm);
            @unlink($zipPath);
        }
    }

    public function test_accepts_extensionless_dicom_and_rejects_a_plain_text_file(): void
    {
        $dcm = $this->dicom('1.2.840.10008.1.2.1', [
            $this->explicit(0x0010, 0x0010, 'PN', 'NOUR ALI'),
        ]);
        $bare = $dcm.'.bare';
        rename($dcm, $bare);

        $package = (new ImagingIntake(new DicomService()))->collect([
            new UploadedFile($bare, 'IM0001', null, UPLOAD_ERR_OK, true),
        ]);
        $package->cleanup();
        @unlink($bare);

        $text = tempnam(sys_get_temp_dir(), 'hmstxt');
        $this->assertNotFalse($text);
        file_put_contents($text, 'hello');

        $this->expectException(BusinessRuleException::class);
        try {
            (new ImagingIntake(new DicomService()))->collect([
                new UploadedFile($text, 'notes.txt', 'text/plain', UPLOAD_ERR_OK, true),
            ]);
        } finally {
            @unlink($text);
        }
    }

    /**
     * @param  array<int, string>  $elements
     */
    private function dicom(string $syntax, array $elements): string
    {
        $meta = $this->explicit(0x0002, 0x0010, 'UI', $syntax."\0");
        $path = tempnam(sys_get_temp_dir(), 'hmsdcm');
        $this->assertNotFalse($path);
        file_put_contents($path, str_repeat("\0", 128).'DICM'.$meta.implode('', $elements));

        return $path;
    }

    private function explicit(int $group, int $element, string $vr, string $value): string
    {
        if (strlen($value) % 2 !== 0) {
            $value .= $vr === 'UI' ? "\0" : ' ';
        }

        $head = pack('v', $group).pack('v', $element).$vr;
        $long = in_array($vr, ['OB', 'OW', 'OF', 'SQ', 'UN', 'UT', 'UC', 'UR'], true);

        if ($long) {
            return $head."\0\0".pack('V', strlen($value)).$value;
        }

        return $head.pack('v', strlen($value)).$value;
    }

    private function implicit(int $group, int $element, string $value): string
    {
        if (strlen($value) % 2 !== 0) {
            $value .= ' ';
        }

        return pack('v', $group).pack('v', $element).pack('V', strlen($value)).$value;
    }
}
