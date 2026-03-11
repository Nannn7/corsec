<?php

namespace Modules\Corsec\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingType;
use Modules\Corsec\Models\Sender;

class CorsecMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedDirectorates();
            $this->seedSenders();
            $this->seedLetterTypes();
            $this->seedMeetingTypes();
        });
    }

    private function seedDirectorates(): void
    {
        foreach ($this->directorates() as $data) {
            $directorate = Directorate::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $this->nullableText($data['description'] ?? null),
                    'status' => (bool) ($data['status'] ?? true),
                    'is_meeting_operational' => (bool) ($data['is_meeting_operational'] ?? false),
                    'authorized_status' => 'authorized',
                    'deleted_by' => null,
                ]
            );

            if ($directorate->trashed()) {
                $directorate->restore();
            }
        }
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
     * @return array<int, array{code:string, name:string, description:string, status:bool, is_meeting_operational?:bool}>
     */
    private function directorates(): array
    {
        return [
            ['code' => '001', 'name' => 'Direktur Utama', 'description' => '', 'status' => true],
            ['code' => '002', 'name' => 'Wakil Direktur Utama', 'description' => '', 'status' => true],
            ['code' => '003', 'name' => 'Sekretaris Komisaris', 'description' => '', 'status' => true],
            ['code' => '004', 'name' => 'Internal Audit (SKAI)', 'description' => '', 'status' => true],
            ['code' => '005', 'name' => 'Corporate Secretary', 'description' => '', 'status' => true],
            ['code' => '006', 'name' => 'Legal Bureau', 'description' => '', 'status' => true],
            ['code' => '007', 'name' => 'Direktur Finance & Asset recovery', 'description' => '', 'status' => true],
            ['code' => '008', 'name' => 'Deputi Direktur Fincon', 'description' => '', 'status' => true],
            ['code' => '009', 'name' => 'Deputi Direktur Asset Consumer & Retail', 'description' => '', 'status' => true],
            ['code' => '010', 'name' => 'Deputi Direktur Asset Corporate', 'description' => '', 'status' => true],
            ['code' => '011', 'name' => 'Deputi Direktur Asset Comemrcial', 'description' => '', 'status' => true],
            ['code' => '012', 'name' => 'Direktur Compliance & Risk', 'description' => '', 'status' => true],
            ['code' => '013', 'name' => 'Deputi Direktur Risk Management', 'description' => '', 'status' => true],
            ['code' => '014', 'name' => 'Deputi Direktur Compliance', 'description' => '', 'status' => true],
            ['code' => '015', 'name' => 'Head of Bureau', 'description' => '', 'status' => true],
            ['code' => '016', 'name' => 'Vice Head of Bureau', 'description' => '', 'status' => true],
            ['code' => '017', 'name' => 'Deputi Direktur Personalia', 'description' => '', 'status' => true],
            ['code' => '018', 'name' => 'Deputi Direktur Genereal Affair', 'description' => '', 'status' => true],
            ['code' => '019', 'name' => 'Chief Operation Officer', 'description' => '', 'status' => true],
            ['code' => '020', 'name' => 'Deputi Direktur Branch Operation', 'description' => '', 'status' => true],
            ['code' => '021', 'name' => 'Deputi Direktur Digital & Payment', 'description' => '', 'status' => true],
            ['code' => '022', 'name' => 'Deputi Direktur Lending Operation', 'description' => '', 'status' => true],
            ['code' => '023', 'name' => 'Chief Technology Officer', 'description' => '', 'status' => true],
            ['code' => '024', 'name' => 'Deputi Direktur IT Service Delivery & Cyber Sec', 'description' => '', 'status' => true],
            ['code' => '025', 'name' => 'Deputi Direktur  IT Digital Service', 'description' => '', 'status' => true],
            ['code' => '026', 'name' => 'Deputi Direktur  IT Infra, Network, Data Mgt', 'description' => '', 'status' => true],
            ['code' => '027', 'name' => 'Deputi Direktur  Project Mgt & Sys Dev', 'description' => '', 'status' => true],
            ['code' => '028', 'name' => 'Deputi Direktur Credit Analyst', 'description' => '', 'status' => true],
            ['code' => '029', 'name' => 'Ass Direktur Credit Analyst', 'description' => '', 'status' => true],
            ['code' => '030', 'name' => 'Direktur Bisnis Wholesale', 'description' => '', 'status' => true],
            ['code' => '031', 'name' => 'Deputi Direktur Finance Institution', 'description' => '', 'status' => true],
            ['code' => '032', 'name' => 'Deputi Direktur  Treasury', 'description' => '', 'status' => true],
            ['code' => '033', 'name' => 'Deputi Direktur Whole sale Banking', 'description' => '', 'status' => true],
            ['code' => '034', 'name' => 'Direktur Retail & Bisnis Banking', 'description' => '', 'status' => true],
            ['code' => '035', 'name' => 'Ass Direktur Retail Banking', 'description' => '', 'status' => true],
            ['code' => '036', 'name' => 'Regional Head 1', 'description' => '', 'status' => true],
            ['code' => '037', 'name' => 'Regional Head 2', 'description' => '', 'status' => true],
            ['code' => '038', 'name' => 'Regional Head 3', 'description' => '', 'status' => true],
            ['code' => '039', 'name' => 'Regional Head 4', 'description' => '', 'status' => true],
            ['code' => '040', 'name' => 'Deputi Direktur Digital', 'description' => '', 'status' => true],
        ];
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
