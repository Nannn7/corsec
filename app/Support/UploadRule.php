<?php

namespace Modules\Corsec\Support;

final class UploadRule
{
    private const DEFAULT_MAX_FILE_SIZE_MB = 10;

    public static function maxFileSizeMb(): int
    {
        return (int) config('corsec.upload.max_file_size_mb', self::DEFAULT_MAX_FILE_SIZE_MB);
    }

    public static function maxFileSizeKb(): int
    {
        return self::maxFileSizeMb() * 1024;
    }

    public static function maxRule(): string
    {
        return 'max:' . self::maxFileSizeKb();
    }

    public static function label(): string
    {
        return self::maxFileSizeMb() . ' MB';
    }
}
