<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingAgenda;
use Modules\Corsec\Models\MeetingDecision;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DirectorateMinutesTemplateExport
{
    private const TEMPLATE_PATH = 'resources/templates/directorate_minutes_template.xlsx';
    private const DATA_START_ROW = 8;
    private const PLACEHOLDER_ROWS = 5;
    private const SIGNATURE_START_ROW = 13;
    private const SIGNATURE_NAME_ROW = 16;

    public function __construct(
        private readonly Meeting $meeting
    ) {}

    public function storeTemporaryFile(): string
    {
        $spreadsheet = $this->spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->fillSheet($sheet);

        $tempPath = tempnam(storage_path('app'), 'directorate_minutes_');
        if ($tempPath === false) {
            throw new \RuntimeException('Gagal membuat file sementara untuk template notulen direktorat.');
        }

        $filePath = $tempPath . '.xlsx';
        @rename($tempPath, $filePath);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $filePath;
    }

    private function spreadsheet(): Spreadsheet
    {
        $templatePath = module_path('Corsec', self::TEMPLATE_PATH);
        if (is_file($templatePath)) {
            try {
                return IOFactory::load($templatePath);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->fallbackSpreadsheet();
    }

    private function fallbackSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Notulen');

        $sheet->mergeCells('B2:G2');
        $sheet->setCellValue('B2', 'TEMPLATE NOTULEN RAPAT DIREKTORAT');
        $sheet->setCellValue('B3', 'Direktorat : -');
        $sheet->setCellValue('B4', 'Tanggal');
        $sheet->setCellValue('C4', '-');

        $headings = [
            'B7' => 'No',
            'C7' => 'Agenda / Topik',
            'D7' => 'PIC',
            'E7' => 'Tindak Lanjut',
            'F7' => 'Target',
            'G7' => 'Status',
        ];
        foreach ($headings as $cell => $heading) {
            $sheet->setCellValue($cell, $heading);
        }

        $sheet->mergeCells('B13:C13');
        $sheet->mergeCells('D13:G13');
        $sheet->setCellValue('B13', 'Notulis');
        $sheet->setCellValue('D13', 'Mengetahui / Approval');
        $sheet->setCellValue('B16', 'Nama');
        $sheet->setCellValue('C16', 'EO dan DD');

        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(18);

        $sheet->getStyle('B2:G2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B2:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B7:G7')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE5E7EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('B7:G12')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF9CA3AF'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('B13:G16')->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF9CA3AF'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        for ($row = self::DATA_START_ROW; $row < self::SIGNATURE_START_ROW; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(32);
        }

        return $spreadsheet;
    }

    private function fillSheet(Worksheet $sheet): void
    {
        $rows = $this->minutesRows();
        $rowCount = max(1, $rows->count());
        $additionalRows = max(0, $rowCount - self::PLACEHOLDER_ROWS);

        if ($additionalRows > 0) {
            $sheet->insertNewRowBefore(self::SIGNATURE_START_ROW, $additionalRows);
        }

        $this->prepareDataRows($sheet, $rowCount);

        foreach ($rows as $index => $row) {
            $sheetRow = self::DATA_START_ROW + $index;
            $sheet->setCellValue("B{$sheetRow}", $index + 1);
            $sheet->setCellValue("C{$sheetRow}", $row['topic']);
            $sheet->setCellValue("D{$sheetRow}", $row['pic']);
            $sheet->setCellValue("E{$sheetRow}", $row['followup']);
            $sheet->setCellValue("F{$sheetRow}", $row['target']);
            $sheet->setCellValue("G{$sheetRow}", $row['status']);
        }

        $sheet->setCellValue('B3', 'Direktorat : ' . $this->directorateLabel());
        $sheet->setCellValue('C4', $this->meeting->meeting_at?->format('d/m/Y') ?? '-');

        $nameRow = self::SIGNATURE_NAME_ROW + $additionalRows;
        $sheet->setCellValue("B{$nameRow}", $this->notulisLabel());
        $sheet->setCellValue("C{$nameRow}", $this->approvalLabel());
    }

    private function prepareDataRows(Worksheet $sheet, int $rowCount): void
    {
        $style = $sheet->getStyle('B' . self::DATA_START_ROW . ':G' . self::DATA_START_ROW);
        $rowHeight = $sheet->getRowDimension(self::DATA_START_ROW)->getRowHeight();
        $lastPreparedRow = self::DATA_START_ROW + max(self::PLACEHOLDER_ROWS, $rowCount) - 1;

        for ($row = self::DATA_START_ROW; $row <= $lastPreparedRow; $row++) {
            $sheet->duplicateStyle($style, "B{$row}:G{$row}");
            if ($rowHeight > 0) {
                $sheet->getRowDimension($row)->setRowHeight($rowHeight);
            }

            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $column) {
                $sheet->setCellValue("{$column}{$row}", '');
            }
        }
    }

    private function minutesRows(): Collection
    {
        $rows = $this->meeting->agendas
            ->sortBy(fn(MeetingAgenda $agenda) => (int) ($agenda->order_no ?? 0))
            ->values()
            ->map(function (MeetingAgenda $agenda) {
                /** @var MeetingDecision|null $decision */
                $decision = $agenda->minutesDecision;

                return [
                    'topic' => trim((string) ($agenda->title ?? '')) ?: '-',
                    'pic' => $this->picLabel($agenda),
                    'followup' => trim((string) ($decision?->decision_text ?? '')) ?: '-',
                    'target' => $decision?->target_date ? $decision->target_date->format('d/m/Y') : '-',
                    'status' => $this->statusLabel($decision?->status),
                ];
            });

        if ($rows->isNotEmpty()) {
            return $rows;
        }

        return collect([[
            'topic' => '-',
            'pic' => '-',
            'followup' => '-',
            'target' => '-',
            'status' => '-',
        ]]);
    }

    private function directorateLabel(): string
    {
        $names = $this->meeting->participants
            ->map(fn($participant) => $participant->directorate?->displayName())
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : '-';
    }

    private function notulisLabel(): string
    {
        return trim((string) (
            $this->meeting->minutes?->submitter?->name
            ?? $this->meeting->createdBy?->name
            ?? 'Nama'
        )) ?: 'Nama';
    }

    private function approvalLabel(): string
    {
        return trim((string) ($this->meeting->minutes?->approver?->name ?? 'EO dan DD')) ?: 'EO dan DD';
    }

    private function picLabel(MeetingAgenda $agenda): string
    {
        $parts = collect([
            $agenda->picUser?->name,
            $agenda->ownerDirectorate?->displayName(),
        ])->filter()->values();

        return $parts->isNotEmpty() ? $parts->implode(' / ') : '-';
    }

    private function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            MeetingDecision::STATUS_IN_PROGRESS => 'on progress',
            MeetingDecision::STATUS_CONTINUOUS => 'berkelanjutan',
            MeetingDecision::STATUS_DONE => 'done',
            MeetingDecision::STATUS_PENDING => 'pending',
            MeetingDecision::STATUS_DROPPED => 'drop',
            default => '-',
        };
    }
}
