<?php

namespace Modules\Corsec\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingType;
use Modules\Corsec\Models\Sender;

class CorsecMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call(DirectorateSeeder::class);
            $this->seedSenders();
            $this->seedLetterTypes();
            $this->seedMeetingTypes();
        });
    }

    private function seedSenders(): void
    {
        foreach ($this->senders() as $data) {
            $sender = Sender::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $this->nullableText($data['description'] ?? null),
                    'status' => (bool) ($data['status'] ?? true),
                    'authorized_status' => 'authorized',
                    'deleted_by' => null,
                ]
            );

            if ($sender->trashed()) {
                $sender->restore();
            }
        }
    }

    private function seedLetterTypes(): void
    {
        foreach ($this->letterTypes() as $data) {
            $letterType = LetterType::withTrashed()->updateOrCreate(
                [
                    'code' => $data['code'],
                    'scope' => $data['scope'],
                ],
                [
                    'name' => $data['name'],
                    'description' => $this->nullableText($data['description'] ?? null),
                    'status' => (bool) ($data['status'] ?? true),
                    'authorized_status' => 'authorized',
                    'deleted_by' => null,
                ]
            );

            if ($letterType->trashed()) {
                $letterType->restore();
            }
        }
    }

    private function seedMeetingTypes(): void
    {
        foreach ($this->meetingTypes() as $data) {
            $meetingType = MeetingType::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $this->nullableText($data['description'] ?? null),
                    'status' => (bool) ($data['status'] ?? true),
                    'authorized_status' => 'authorized',
                    'deleted_by' => null,
                ]
            );

            if ($meetingType->trashed()) {
                $meetingType->restore();
            }
        }
    }

    private function nullableText(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, array{code:string, name:string, description:string, status:bool}>
     */
    private function senders(): array
    {
        return [
            ['code' => '001', 'name' => 'Otoritas Jasa Keuangan (OJK)', 'description' => '', 'status' => true],
            ['code' => '002', 'name' => 'Bank Indonesia (BI)', 'description' => '', 'status' => true],
            ['code' => '003', 'name' => 'KSEI', 'description' => '', 'status' => true],
            ['code' => '004', 'name' => 'Bursa Efek Indonesia (BEI)', 'description' => '', 'status' => true],
            ['code' => '005', 'name' => 'Pasar Modal Indonesia', 'description' => '', 'status' => true],
            ['code' => '006', 'name' => 'Lembaga Penjaminan Simpana (LPS)', 'description' => '', 'status' => true],
            ['code' => '007', 'name' => 'Perbanas', 'description' => '', 'status' => true],
            ['code' => '008', 'name' => 'PPATK', 'description' => '', 'status' => true],
            ['code' => '009', 'name' => 'Kejaksaan Agung', 'description' => '', 'status' => true],
            ['code' => '010', 'name' => 'Pengadilan', 'description' => '', 'status' => true],
            ['code' => '011', 'name' => 'Direktorat Jenderal Pajak (DPJ)', 'description' => '', 'status' => true],
            ['code' => '012', 'name' => 'Counterparty Bank', 'description' => '', 'status' => true],
            ['code' => '013', 'name' => 'Kepolisian', 'description' => '', 'status' => true],
            ['code' => '014', 'name' => 'Kementerian', 'description' => '', 'status' => true],
            ['code' => '015', 'name' => 'Nasabah/Debitur', 'description' => '', 'status' => true],
        ];
    }

    /**
     * @return array<int, array{code:string, name:string, scope:string, description:string, status:bool}>
     */
    private function letterTypes(): array
    {
        return [
            ['code' => '001', 'name' => 'Permintaan Data', 'scope' => LetterType::SCOPE_IN, 'description' => '', 'status' => true],
            ['code' => '002', 'name' => 'Undangan', 'scope' => LetterType::SCOPE_IN, 'description' => '', 'status' => true],
            ['code' => '003', 'name' => 'Sponsorship', 'scope' => LetterType::SCOPE_IN, 'description' => '', 'status' => true],
            ['code' => '004', 'name' => 'Informasi', 'scope' => LetterType::SCOPE_IN, 'description' => '', 'status' => true],
            ['code' => '001', 'name' => 'SURAT KUASA', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '002', 'name' => 'PKS', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '003', 'name' => 'NDA', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '004', 'name' => 'SURAT KELUAR DIRUT', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '005', 'name' => 'SK DIT CORSEC', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '006', 'name' => 'SK CORP AFFAIRS', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '007', 'name' => 'MI SUBDIT CORP AFFAIRS', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '008', 'name' => 'MI DIT CORSEC', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '009', 'name' => 'MAK SUBDIT CORSEC', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '010', 'name' => 'KEPUTUSAN DIREKSI', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
            ['code' => '011', 'name' => 'MAK DIRUT', 'scope' => LetterType::SCOPE_OUT, 'description' => '', 'status' => true],
        ];
    }

    /**
     * @return array<int, array{code:string, name:string, description:string, status:bool}>
     */
    private function meetingTypes(): array
    {
        return [
            ['code' => Meeting::TYPE_KOMISARIS, 'name' => 'Rapat Komisaris', 'description' => '', 'status' => true],
            ['code' => Meeting::TYPE_DIREKSI, 'name' => 'Rapat Direksi', 'description' => '', 'status' => true],
            ['code' => Meeting::TYPE_MANCOMM, 'name' => 'Rapat Management Committee', 'description' => '', 'status' => true],
            ['code' => Meeting::TYPE_DIREKTORAT, 'name' => 'Rapat Direktorat', 'description' => '', 'status' => true],
        ];
    }
}
