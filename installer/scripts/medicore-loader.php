<?php

$keyFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'content.key';
if (!is_file($keyFile)) {
    return;
}
$key = file_get_contents($keyFile);
if (!is_string($key) || strlen($key) !== 32) {
    return;
}

final class MediCoreStream
{
    private const MAGIC = 'MCENC1';

    /** @var resource|null */
    private $handle;

    /** @var resource|null */
    private $dir;

    private string $buffer = '';

    private int $pos = 0;

    private bool $memory = false;

    private static string $key = '';

    public static function boot(string $key): void
    {
        self::$key = $key;
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $local = self::normalize($path);
        if (self::isRead($mode) && self::shouldInspect($local)) {
            $raw = self::native(static fn () => file_get_contents($local));
            if (is_string($raw) && str_starts_with($raw, self::MAGIC)) {
                $plain = self::decrypt($raw);
                if ($plain === false) {
                    return false;
                }
                $this->buffer = $plain;
                $this->memory = true;
                $this->pos = 0;
                return true;
            }
            if (is_string($raw)) {
                $this->buffer = $raw;
                $this->memory = true;
                $this->pos = 0;
                return true;
            }
        }

        $this->handle = self::native(static fn () => fopen($local, $mode));
        return is_resource($this->handle);
    }

    public function stream_read($count)
    {
        if ($this->memory) {
            $chunk = substr($this->buffer, $this->pos, $count);
            $this->pos += strlen($chunk);
            return $chunk;
        }
        $chunk = fread($this->handle, $count);
        return $chunk === false ? '' : $chunk;
    }

    public function stream_write($data)
    {
        if ($this->memory) {
            return 0;
        }
        $written = fwrite($this->handle, $data);
        return $written === false ? 0 : $written;
    }

    public function stream_tell()
    {
        return $this->memory ? $this->pos : ftell($this->handle);
    }

    public function stream_eof()
    {
        return $this->memory ? $this->pos >= strlen($this->buffer) : feof($this->handle);
    }

    public function stream_seek($offset, $whence)
    {
        if ($this->memory) {
            $length = strlen($this->buffer);
            if ($whence === SEEK_SET) {
                $next = $offset;
            } elseif ($whence === SEEK_CUR) {
                $next = $this->pos + $offset;
            } else {
                $next = $length + $offset;
            }
            if ($next < 0 || $next > $length) {
                return false;
            }
            $this->pos = $next;
            return true;
        }
        return fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_flush()
    {
        return $this->memory ? true : fflush($this->handle);
    }

    public function stream_close()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        return true;
    }

    public function stream_stat()
    {
        if ($this->memory) {
            return self::statArray(strlen($this->buffer));
        }
        return fstat($this->handle);
    }

    public function url_stat($path, $flags)
    {
        $local = self::normalize($path);
        $stat = self::native(static function () use ($local, $flags) {
            if ($flags & STREAM_URL_STAT_LINK) {
                return @lstat($local);
            }
            return @stat($local);
        });
        return $stat ?: false;
    }

    public function dir_opendir($path, $options)
    {
        $local = self::normalize($path);
        $this->dir = self::native(static fn () => opendir($local));
        return $this->dir !== false;
    }

    public function dir_readdir()
    {
        $name = readdir($this->dir);
        return $name === false ? false : $name;
    }

    public function dir_rewinddir()
    {
        rewinddir($this->dir);
        return true;
    }

    public function dir_closedir()
    {
        closedir($this->dir);
        return true;
    }

    public function unlink($path)
    {
        $local = self::normalize($path);
        return self::native(static fn () => unlink($local));
    }

    public function rename($from, $to)
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        return self::native(static fn () => rename($from, $to));
    }

    public function mkdir($path, $mode, $options)
    {
        $local = self::normalize($path);
        return self::native(static fn () => $options ? mkdir($local, $mode, true) : mkdir($local, $mode));
    }

    public function rmdir($path, $options)
    {
        $local = self::normalize($path);
        return self::native(static fn () => rmdir($local));
    }

    public function stream_truncate($new_size)
    {
        return $this->memory ? false : ftruncate($this->handle, $new_size);
    }

    public function stream_lock($operation)
    {
        return $this->memory ? true : flock($this->handle, $operation);
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    public function stream_metadata($path, $option, $value)
    {
        $local = self::normalize($path);
        return self::native(static function () use ($local, $option, $value) {
            if ($option === STREAM_META_TOUCH) {
                return touch($local, $value[0] ?? time(), $value[1] ?? time());
            }
            if ($option === STREAM_META_OWNER_NAME || $option === STREAM_META_OWNER) {
                return chown($local, $value);
            }
            if ($option === STREAM_META_GROUP_NAME || $option === STREAM_META_GROUP) {
                return chgrp($local, $value);
            }
            if ($option === STREAM_META_ACCESS) {
                return chmod($local, $value);
            }
            return false;
        });
    }

    private static function isRead(string $mode): bool
    {
        return str_starts_with($mode, 'r') && !str_contains($mode, '+');
    }

    private static function shouldInspect(string $path): bool
    {
        $base = strtolower(basename($path));
        if ($base === 'content.key') {
            return false;
        }
        if (in_array($base, ['.env', 'app.env', 'mysql-root.secret'], true)) {
            return true;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['php', 'js', 'mjs', 'cjs', 'json', 'css', 'html', 'htm'], true);
    }

    private static function decrypt(string $raw): string|false
    {
        $iv = substr($raw, 6, 16);
        $cipher = substr($raw, 22);
        return openssl_decrypt($cipher, 'aes-256-cbc', self::$key, OPENSSL_RAW_DATA, $iv);
    }

    private static function normalize(string $path): string
    {
        if (str_starts_with($path, 'file://')) {
            $path = substr($path, 7);
            $path = urldecode($path);
            if (preg_match('#^/[A-Za-z]:#', $path) === 1) {
                $path = substr($path, 1);
            }
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private static function native(callable $callback): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $callback();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    private static function statArray(int $size): array
    {
        $now = time();
        $stat = [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => $now,
            'mtime' => $now,
            'ctime' => $now,
            'blksize' => 512,
            'blocks' => (int) ceil($size / 512),
        ];
        $stat += array_values($stat);
        return $stat;
    }
}

MediCoreStream::boot($key);
