<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class LibraryItem extends Model
{
    use SoftDeletes, HasUuidColumn;

    public const CATEGORY_APP_GUIDELINE = 'app_guideline';
    public const CATEGORY_CORSEC_REFERENCE = 'corsec_reference';
    public const CATEGORY_EXTERNAL_CORSEC = 'external_corsec';
    public const CATEGORY_REFERENCE_LINK = 'reference_link';
    public const CATEGORY_MEDIA_NEWS = 'media_news';

    protected $table = 'corsec_library_items';

    protected $fillable = [
        'uuid',
        'category_code',
        'title',
        'description',
        'file_disk',
        'file_path',
        'original_name',
        'file_name',
        'file_mime',
        'file_extension',
        'file_size',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_APP_GUIDELINE => 'Internal - Guideline Aplikasi / Prosedur',
            self::CATEGORY_CORSEC_REFERENCE => 'Corsec / RUPS / AR / SR dan Lainnya',
            self::CATEGORY_EXTERNAL_CORSEC => 'Eksternal - Ketentuan Corsec',
            self::CATEGORY_REFERENCE_LINK => 'Reference Link',
            self::CATEGORY_MEDIA_NEWS => 'Media Berita',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category_code] ?? (string) $this->category_code;
    }

    public function downloadFileName(): string
    {
        return (string) ($this->original_name ?: $this->file_name ?: $this->title ?: 'library-file');
    }
}
