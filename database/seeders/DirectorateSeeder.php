<?php

namespace Modules\Corsec\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Corsec\Models\Directorate;

class DirectorateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $data) {
            $directorate = Directorate::withTrashed()->firstOrNew([
                'code' => $data['code'],
            ]);

            $directorate->fill([
                'name' => $data['name'],
                'tabulation_label' => $this->nullableText($data['tabulation_label'] ?? null) ?? $data['name'],
                'description' => $this->nullableText($data['description'] ?? null),
                'status' => (bool) ($data['status'] ?? true),
                'is_meeting_operational' => (bool) ($data['is_meeting_operational'] ?? false),
                'authorized_status' => 'authorized',
                'authorized_at' => $directorate->authorized_at ?? now(),
                'deleted_by' => null,
            ]);

            if (!$directorate->uuid && !empty($data['uuid'])) {
                $directorate->uuid = $data['uuid'];
            }

            $directorate->save();

            if ($directorate->trashed()) {
                $directorate->restore();
            }
        }
    }

    private function nullableText(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Snapshot master data direktorat dari environment development.
     *
     * @return array<int, array{
     *     code:string,
     *     uuid:string,
     *     name:string,
     *     tabulation_label:string,
     *     description:?string,
     *     status:bool,
     *     is_meeting_operational:bool
     * }>
     */
    public function data(): array
    {
        return [
            ['code' => '001', 'uuid' => '36d62bf0-c575-4bf9-9ea4-d8898f158724', 'name' => 'Direktur Utama', 'tabulation_label' => 'Direktur Utama', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '002', 'uuid' => 'eb651bf3-b9ce-4263-a2f7-2e1f2de951de', 'name' => 'Wakil Direktur Utama', 'tabulation_label' => 'Wakil Direktur Utama', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '003', 'uuid' => 'fec65ff7-c269-4573-911c-abc2fbd1d174', 'name' => 'Sekretaris Komisaris', 'tabulation_label' => 'Sekretaris Komisaris', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '004', 'uuid' => '79e4e859-14d7-4776-899a-f67c406b0d1a', 'name' => 'Internal Audit (SKAI)', 'tabulation_label' => 'Internal Audit (SKAI)', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '005', 'uuid' => '1fbb3dc1-fc52-4dd7-9210-2f3c1107f766', 'name' => 'Corporate Secretary', 'tabulation_label' => 'Corporate Secretary', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '006', 'uuid' => '5bd97a0e-beb5-4aeb-9518-ddfa1bdc966a', 'name' => 'Legal Bureau', 'tabulation_label' => 'Legal Bureau', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '007', 'uuid' => '302f4e21-ea46-4e1d-ac09-1ad3678d029c', 'name' => 'Direktur Finance & Asset recovery', 'tabulation_label' => 'Direktur Finance & Asset recovery', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '008', 'uuid' => '253198c0-32a2-4d30-8f46-7fc917cedfd6', 'name' => 'Deputi Direktur Fincon', 'tabulation_label' => 'Deputi Direktur Fincon', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '009', 'uuid' => 'c23aee40-4205-4ec6-8ee0-77ae2dd6fe1d', 'name' => 'Deputi Direktur Asset Consumer & Retail', 'tabulation_label' => 'Deputi Direktur Asset Consumer & Retail', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '010', 'uuid' => '29938adc-84c2-42bb-9524-25bdf4063044', 'name' => 'Deputi Direktur Asset Corporate', 'tabulation_label' => 'Deputi Direktur Asset Corporate', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '011', 'uuid' => 'c0497972-31e2-495f-bd0c-9e7ee66b0a24', 'name' => 'Deputi Direktur Asset Comemrcial', 'tabulation_label' => 'Deputi Direktur Asset Comemrcial', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '012', 'uuid' => 'ba60a830-86e0-45c0-92e4-5408de53951f', 'name' => 'Direktur Compliance & Risk', 'tabulation_label' => 'Direktur Compliance & Risk', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '013', 'uuid' => '913ad07c-34a2-484e-8aa5-7dd3b5247c1e', 'name' => 'Deputi Direktur Risk Management', 'tabulation_label' => 'Deputi Direktur Risk Management', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '014', 'uuid' => '947126be-9316-40ea-a2ae-63d3ba003ca4', 'name' => 'Deputi Direktur Compliance', 'tabulation_label' => 'Deputi Direktur Compliance', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '015', 'uuid' => 'e67e6418-3878-47e5-b8a8-3aa76affcd62', 'name' => 'Head of Bureau', 'tabulation_label' => 'Head of Bureau', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '016', 'uuid' => '7ba76c3a-9b8a-4573-887d-06c613ee9bc2', 'name' => 'Vice Head of Bureau', 'tabulation_label' => 'Vice Head of Bureau', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '017', 'uuid' => '01128ad1-5245-44b8-8018-ae67149f3788', 'name' => 'Deputi Direktur Personalia', 'tabulation_label' => 'Deputi Direktur Personalia', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '018', 'uuid' => '887e56f0-0fd8-406a-abf1-89f518be1600', 'name' => 'Deputi Direktur Genereal Affair', 'tabulation_label' => 'Deputi Direktur Genereal Affair', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '019', 'uuid' => '944c6fe7-ee52-4f33-9243-91ff6c0aa665', 'name' => 'Chief Operation Officer', 'tabulation_label' => 'Chief Operation Officer', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '020', 'uuid' => '3fcfedaf-eed8-4687-90fc-7ec846dd8a06', 'name' => 'Deputi Direktur Branch Operation', 'tabulation_label' => 'Deputi Direktur Branch Operation', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '021', 'uuid' => '17f1fcab-e2df-4304-b8b5-98859cd64409', 'name' => 'Deputi Direktur Digital & Payment', 'tabulation_label' => 'Deputi Direktur Digital & Payment', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '022', 'uuid' => 'e83acec8-4d63-412e-b1fb-f776f31c6fcb', 'name' => 'Deputi Direktur Lending Operation', 'tabulation_label' => 'Deputi Direktur Lending Operation', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '023', 'uuid' => 'c9c97a81-ff43-4a14-be34-3ae274af171f', 'name' => 'Chief Technology Officer', 'tabulation_label' => 'Chief Technology Officer', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '024', 'uuid' => 'f8b33a43-9aaa-4fae-94ce-eb1e6d5d998a', 'name' => 'Deputi Direktur IT Service Delivery & Cyber Sec', 'tabulation_label' => 'Deputi Direktur IT Service Delivery & Cyber Sec', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '025', 'uuid' => '131f83ad-f11d-4b3b-853a-bbb4123ee807', 'name' => 'Deputi Direktur  IT Digital Service', 'tabulation_label' => 'Deputi Direktur  IT Digital Service', 'description' => null, 'status' => true, 'is_meeting_operational' => true],
            ['code' => '026', 'uuid' => '4b8008f6-b078-483d-bd20-3a40ac66a894', 'name' => 'Deputi Direktur  IT Infra, Network, Data Mgt', 'tabulation_label' => 'Deputi Direktur  IT Infra, Network, Data Mgt', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '027', 'uuid' => 'c633d1cd-9c70-46fa-a24a-33deed43d1d9', 'name' => 'Deputi Direktur  Project Mgt & Sys Dev', 'tabulation_label' => 'Deputi Direktur  Project Mgt & Sys Dev', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '028', 'uuid' => 'a52922b8-2157-4c12-ab48-0b96f8bcaf26', 'name' => 'Deputi Direktur Credit Analyst', 'tabulation_label' => 'Deputi Direktur Credit Analyst', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '029', 'uuid' => '96c21cd0-c107-407c-9024-4d4038dadc7f', 'name' => 'Ass Direktur Credit Analyst', 'tabulation_label' => 'Ass Direktur Credit Analyst', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '030', 'uuid' => '6671f5eb-b2f2-460d-b423-cc759ca50f8d', 'name' => 'Direktur Bisnis Wholesale', 'tabulation_label' => 'Direktur Bisnis Wholesale', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '031', 'uuid' => '764b4e8b-ebf3-4904-ad05-8011120ec48e', 'name' => 'Deputi Direktur Finance Institution', 'tabulation_label' => 'Deputi Direktur Finance Institution', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '032', 'uuid' => 'aa5e5482-d0f3-47ea-ae1a-de9921bc9465', 'name' => 'Deputi Direktur  Treasury', 'tabulation_label' => 'Deputi Direktur  Treasury', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '033', 'uuid' => '38ca4a8f-b38e-45a9-86b1-6f11fd27d8e6', 'name' => 'Deputi Direktur Whole sale Banking', 'tabulation_label' => 'Deputi Direktur Whole sale Banking', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '034', 'uuid' => '1b9c2a1e-b1f1-462d-8345-36f10149c9d4', 'name' => 'Direktur Retail & Bisnis Banking', 'tabulation_label' => 'Direktur Retail & Bisnis Banking', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '035', 'uuid' => 'd3fbaa91-e695-4c88-9c21-89884aaf8192', 'name' => 'Ass Direktur Retail Banking', 'tabulation_label' => 'Ass Direktur Retail Banking', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '036', 'uuid' => 'eb900781-46c4-4ab5-80a3-5d5a3d443e98', 'name' => 'Regional Head 1', 'tabulation_label' => 'Regional Head 1', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '037', 'uuid' => '5135e666-abab-4e56-90bb-1075a0690b0b', 'name' => 'Regional Head 2', 'tabulation_label' => 'Regional Head 2', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '038', 'uuid' => '9daccf12-cffd-4d3d-9550-cd77d38f99d2', 'name' => 'Regional Head 3', 'tabulation_label' => 'Regional Head 3', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '039', 'uuid' => 'ca44a3e6-e27a-495e-ab25-bae95dd5c4df', 'name' => 'Regional Head 4', 'tabulation_label' => 'Regional Head 4', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
            ['code' => '040', 'uuid' => '927d0eac-090c-438f-9ac0-37a935eb5d95', 'name' => 'Deputi Direktur Digital', 'tabulation_label' => 'Deputi Direktur Digital', 'description' => null, 'status' => true, 'is_meeting_operational' => false],
        ];
    }
}
