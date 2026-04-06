<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingAgenda;
use Modules\Corsec\Models\MeetingDecision;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
        $templatePath = module_path('Corsec', self::TEMPLATE_PATH);
        $spreadsheet = IOFactory::load($templatePath);
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
