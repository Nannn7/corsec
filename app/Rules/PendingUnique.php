<?php

namespace Modules\Corsec\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class PendingUnique implements ValidationRule
{
    public function __construct(
        protected string $table,                // tabel live, mis: 'direktorat'
        protected string $column,               // kolom unik, mis: 'code'
        protected ?string $ignoreId = null,     // id yang diabaikan saat update
        protected ?string $modelClass = null,   // model yang dipakai di log
        protected ?string $targetId = null      // target id saat update
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // cek di data live
        $q = DB::table($this->table)->where($this->column, $value);
        if ($this->ignoreId) {
            $q->where('id', '!=', $this->ignoreId);
        }
        if ($q->exists()) {
            $fail("{$attribute} sudah digunakan (live).");
            return;
        }

        // cek di request pending
        $existsInPending = DB::table('approval_requests')
            ->where('status', 'pending')
            ->where('model', $this->modelClass)
            ->whereIn('action', ['create', 'update'])
            ->where(function ($qq) use ($value) {
                // cari di JSON request_new kolom yang sama
                $qq->whereJsonContains("request_new->" . $this->column, $value)
                    ->orWhere(DB::raw("JSON_EXTRACT(request_new, '$.\"{$this->column}\"')"), $value);  // untuk MySQL lama
            })
            // jika update di record yg sama, abaikan
            ->when($this->targetId, fn($qq) => $qq->where('target_id', '!=', $this->targetId))
            ->exists();

        if ($existsInPending) {
            $fail("{$attribute} sedang diajukan di request pending.");
        }
    }
}
