<?php

return [
    'name' => 'Corsec',
    'direktur_utama' => env('CORSEC_DIREKTUR_UTAMA_CODE', '001'),
    'wakil_direktur_utama_directorate_code' => env('CORSEC_WAKIL_DIREKTUR_UTAMA_DIRECTORATE_CODE', '002'),
    'sekretaris_komisaris_directorate_code' => env('CORSEC_SEKRETARIS_KOMISARIS_DIRECTORATE_CODE', '003'),
    'sekretaris_direksi_directorate_code' => env('CORSEC_SEKRETARIS_DIREKSI_DIRECTORATE_CODE', '004'),
    'eo_corp_affair_directorate_code' => env('CORSEC_EO_DIRECTORATE_CODE', '006'),
    'compliance_directorate_code' => env('CORSEC_COMPLIANCE_DIRECTORATE_CODE', '014'),
    'customer_sender_name' => env('CORSEC_CUSTOMER_SENDER_NAME', 'Nasabah/Debitur'),
    'upload' => ['max_file_size_mb' => (int) env('CORSEC_UPLOAD_MAX_FILE_SIZE_MB', 10)],
];
