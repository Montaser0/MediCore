<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

use Illuminate\Http\UploadedFile;

/**
 * Recognises a DICOM object and reads the study identifiers from its header.
 * The pixel bytes stay on disk; a viewer or PACS can be attached later
 * without changing the callers.
 */
final class DicomService
{
    private const DICOM_MAGIC = 'DICM';

    private const DICOM_MAGIC_OFFSET = 128;

    /** @var array<int, string> */
    private const LONG_VR = ['OB', 'OD', 'OF', 'OL', 'OV', 'OW', 'SQ', 'SV', 'UC', 'UN', 'UR', 'UT', 'UV'];

    public function isDicom(UploadedFile $file): bool
    {
        return $this->isDicomPath($file->getRealPath() ?: '', $file->getClientOriginalName());
    }

    public function isDicomPath(string $path, string $originalName = ''): bool
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['dcm', 'dicom'], true)) {
            return true;
        }

        return $this->hasMagic($path);
    }

    public function readIdentity(string $path): DicomIdentity
    {
        if ($path === '' || ! is_file($path)) {
            return DicomIdentity::empty();
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return DicomIdentity::empty();
        }

        try {
            if (! $this->hasMagic($path)) {
                return DicomIdentity::empty();
            }

            fseek($handle, self::DICOM_MAGIC_OFFSET + 4);
            $meta = $this->readElements($handle, explicit: true, metaOnly: true);
            $syntax = trim($meta['0002,0010'] ?? '', "\0 ");
            $tags = $this->readDataSet($handle, $syntax);
            $charset = $tags['0008,0005'] ?? null;

            return new DicomIdentity(
                patientName: $this->personName($this->text($tags['0010,0010'] ?? '', $charset)),
                patientId: $this->blankToNull($this->text($tags['0010,0020'] ?? '', $charset)),
                studyInstanceUid: $this->uid($tags['0020,000D'] ?? ''),
                accessionNumber: $this->blankToNull($this->text($tags['0008,0050'] ?? '', $charset)),
                modality: $this->blankToNull($this->text($tags['0008,0060'] ?? '', $charset)),
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * True when both names are present and share no meaningful token.
     * An Arabic file against a Latin DICOM header warns; it never blocks the save.
     */
    public function namesConflict(?string $dicomName, ?string $patientName): bool
    {
        $left = $this->tokens($dicomName);
        $right = $this->tokens($patientName);

        if ($left === [] || $right === []) {
            return false;
        }

        foreach ($left as $token) {
            if (in_array($token, $right, true)) {
                return false;
            }
        }

        return true;
    }

    private function hasMagic(string $path): bool
    {
        if ($path === '' || ! is_file($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            if (fseek($handle, self::DICOM_MAGIC_OFFSET) !== 0) {
                return false;
            }

            return fread($handle, 4) === self::DICOM_MAGIC;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, string>
     */
    private function readDataSet($handle, string $syntax): array
    {
        if ($syntax === '1.2.840.10008.1.2.2' || str_contains($syntax, '1.2.840.10008.1.2.1.99')) {
            return [];
        }

        if ($syntax !== '' && ! str_starts_with($syntax, '1.2.840.10008.1.2')) {
            return [];
        }

        $explicit = $syntax !== '' && $syntax !== '1.2.840.10008.1.2';

        return $this->readElements($handle, $explicit, false);
    }

    /**
     * @return array<string, string>
     */
    private function readElements($handle, bool $explicit, bool $metaOnly): array
    {
        $tags = [];
        $start = ftell($handle);
        $seen = 0;

        if ($start === false) {
            return [];
        }

        while (! feof($handle) && $seen < 8000 && (ftell($handle) - $start) < 8_000_000) {
            $seen++;
            $tag = fread($handle, 4);

            if (! is_string($tag) || strlen($tag) < 4) {
                break;
            }

            $group = $this->uint16(substr($tag, 0, 2));
            $element = $this->uint16(substr($tag, 2, 2));

            if ($metaOnly && $group !== 0x0002) {
                fseek($handle, -4, SEEK_CUR);
                break;
            }

            if (! $metaOnly && $group > 0x0028 && $group !== 0xFFFE) {
                break;
            }

            if ($group === 0x7FE0 && $element === 0x0010) {
                break;
            }

            if ($group === 0xFFFE) {
                fread($handle, 4);
                continue;
            }

            if ($explicit) {
                $vr = fread($handle, 2);

                if (! is_string($vr) || strlen($vr) < 2 || preg_match('/^[A-Z]{2}$/', $vr) !== 1) {
                    break;
                }

                if (in_array($vr, self::LONG_VR, true)) {
                    fread($handle, 2);
                    $length = $this->readUint32($handle);
                } else {
                    $short = fread($handle, 2);
                    $length = is_string($short) && strlen($short) === 2 ? $this->uint16($short) : null;
                }
            } else {
                $length = $this->readUint32($handle);
            }

            if ($length === null) {
                break;
            }

            if ($length === 0xFFFFFFFF) {
                $this->skipUndefined($handle, $explicit);
                continue;
            }

            $key = sprintf('%04X,%04X', $group, $element);

            if ($this->wanted($key) && $length > 0 && $length < 1024) {
                $raw = fread($handle, $length);
                $tags[$key] = is_string($raw) ? $raw : '';
                continue;
            }

            if ($length > 0) {
                fseek($handle, $length, SEEK_CUR);
            }
        }

        return $tags;
    }

    private function skipUndefined($handle, bool $explicit): void
    {
        $guard = 0;

        while (! feof($handle) && $guard < 100000) {
            $guard++;
            $tag = fread($handle, 4);

            if (! is_string($tag) || strlen($tag) < 4) {
                return;
            }

            $group = $this->uint16(substr($tag, 0, 2));
            $element = $this->uint16(substr($tag, 2, 2));

            if ($group === 0xFFFE && $element === 0xE0DD) {
                fread($handle, 4);

                return;
            }

            if ($group === 0xFFFE && $element === 0xE000) {
                $length = $this->readUint32($handle);

                if ($length === null) {
                    return;
                }

                if ($length === 0xFFFFFFFF) {
                    $this->skipItem($handle, $explicit);
                } elseif ($length > 0) {
                    fseek($handle, $length, SEEK_CUR);
                }

                continue;
            }

            fseek($handle, -4, SEEK_CUR);

            if (! $this->skipOneElement($handle, $explicit)) {
                return;
            }
        }
    }

    private function skipItem($handle, bool $explicit): void
    {
        $guard = 0;

        while (! feof($handle) && $guard < 100000) {
            $guard++;
            $tag = fread($handle, 4);

            if (! is_string($tag) || strlen($tag) < 4) {
                return;
            }

            $group = $this->uint16(substr($tag, 0, 2));
            $element = $this->uint16(substr($tag, 2, 2));

            if ($group === 0xFFFE && $element === 0xE00D) {
                fread($handle, 4);

                return;
            }

            fseek($handle, -4, SEEK_CUR);

            if (! $this->skipOneElement($handle, $explicit)) {
                return;
            }
        }
    }

    private function skipOneElement($handle, bool $explicit): bool
    {
        $tag = fread($handle, 4);

        if (! is_string($tag) || strlen($tag) < 4) {
            return false;
        }

        if ($explicit) {
            $vr = fread($handle, 2);

            if (! is_string($vr) || strlen($vr) < 2) {
                return false;
            }

            if (in_array($vr, self::LONG_VR, true)) {
                fread($handle, 2);
                $length = $this->readUint32($handle);
            } else {
                $short = fread($handle, 2);
                $length = is_string($short) && strlen($short) === 2 ? $this->uint16($short) : null;
            }
        } else {
            $length = $this->readUint32($handle);
        }

        if ($length === null) {
            return false;
        }

        if ($length === 0xFFFFFFFF) {
            $this->skipUndefined($handle, $explicit);

            return true;
        }

        if ($length > 0) {
            fseek($handle, $length, SEEK_CUR);
        }

        return true;
    }

    private function wanted(string $key): bool
    {
        return in_array($key, ['0002,0010', '0008,0005', '0008,0050', '0008,0060', '0010,0010', '0010,0020', '0020,000D'], true);
    }

    private function readUint32($handle): ?int
    {
        $bytes = fread($handle, 4);

        if (! is_string($bytes) || strlen($bytes) < 4) {
            return null;
        }

        return unpack('V', $bytes)[1];
    }

    private function uint16(string $bytes): int
    {
        return unpack('v', $bytes)[1];
    }

    private function text(string $raw, ?string $charset): string
    {
        $raw = rtrim($raw, " \0");

        if ($raw === '') {
            return '';
        }

        if (($charset !== null && str_contains($charset, '192')) || mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $converted = iconv('Windows-1256', 'UTF-8//IGNORE', $raw);

        return is_string($converted) && $converted !== '' ? $converted : $raw;
    }

    private function personName(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', str_replace('^', ' ', $value)));

        return $value === '' ? null : $value;
    }

    private function uid(string $raw): ?string
    {
        $raw = trim($raw, "\0 ");

        if ($raw === '' || preg_match('/^[0-9.]+$/', $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [];
        }

        $name = mb_strtolower(str_replace('^', ' ', $name));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return array_values(array_filter(
            $parts,
            fn ($part) => is_string($part) && mb_strlen($part) >= 3,
        ));
    }
}
