<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LiquidacionExport implements FromCollection, WithEvents
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = [];

        // Agregar el título en la primera fila
        $rows[] = ['FORMATO ÚNICO DE ATENCIÓN']; // Título en la primera fila
        $rows[] = ['']; // Fila vacía para separación

        // Función para añadir una tabla con encabezado y contenido
        $addSection = function ($title, $headers, $data, $headerColor, $titleColor) use (&$rows) {
            $rows[] = ['']; // Fila vacía para separación
            $rows[] = ['']; // Fila vacía para separación 2
            $rows[] = [$title];  // Título de sección
            $rows[] = $headers;  // Encabezados

            if (!empty($data)) { // Verificar si hay datos antes de agregar filas
                foreach ($data as $row) {
                    $rows[] = array_values($row);  // Contenido
                }
            } else {
                $rows[] = ['No hay datos disponibles']; // Mensaje si no hay datos
            }

            $rows[] = [''];  // Separador
        };

        // Agregar secciones al reporte
        //$addSection('DATA REPORTE', ['Monto total de la atención'], $this->data['DATA_REPORTE'], 'ffffff', '2e74b5');
        $addSection('DATOS DE LA ENTIDAD', ['Número de Formato', 'Fecha Digitación', 'IPRESS'], $this->data['DATOS_DE_LA_ENTIDAD'], 'd9d9d9', 'b6d7a8');
        $addSection('DATOS DEL ASEGURADO', ['Nombres', 'N° Historia', 'Contrato', 'Fecha de Atención'], $this->data['DATOS_DEL_ASEGURADO'], 'd9d9d9', 'f9cb9c');
        $addSection('MEDICAMENTOS', ['Código', 'Nombre', 'Forma Farm.', 'Concentración', 'Pres.', 'Entr.', 'N° Dx', 'Dx', 'Precio', 'Importe'], $this->data['MEDICAMENTOS']['data'], 'd9d9d9', 'cfe2f3');
        $addSection('INSUMOS', ['Código', 'Nombre', 'Pres.', 'Entr.', 'N°', 'Dx', 'Precio', 'Importe'], $this->data['INSUMOS']['data'], 'd9d9d9', 'f4cccc');
        $addSection('PROCEDIMIENTOS', ['Código', 'Nombre', 'Pres.', 'Entr.', 'N°', 'Dx', 'Precio', 'Importe'], $this->data['PROCEDIMIENTOS']['data'], 'd9d9d9', 'ffe599'); // Mover PROCEDIMIENTOS al final

        return collect($rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                //
                $this->insertTotalRow('Monto total de la atención', $sheet, 'A', 4, $highestColumn, $this->calculateOverallTotal());
                //MERGE
                $this->mergeInitialCells($sheet,'A1:J1');
                $this->mergeInitialCells($sheet,'A4:J4');
                $this->mergeInitialCells($sheet,'A6:J6');
                $this->mergeInitialCells($sheet,'C6:F6');
                $this->mergeInitialCells($sheet,'C7:F7');
                $this->mergeInitialCells($sheet,'C13:H13');
                //$this->mergeInitialCells($sheet,'C14:E14');
                // DIMESION COLUMN
                $this->columnDimensionCells($sheet,'A',24);
                $this->columnDimensionCells($sheet,'B',47);
                $this->columnDimensionCells($sheet,'C',8);
                $this->columnDimensionCells($sheet,'D',16);
                $this->columnDimensionCells($sheet,'E',6);
                $this->columnDimensionCells($sheet,'F',24);
                $this->columnDimensionCells($sheet,'G',6);
                $this->columnDimensionCells($sheet,'H',18);
                //STYLE COLUMN
                $this->applyTitleStyle($sheet, 'A1:J1', 10, true, Alignment::HORIZONTAL_CENTER,'data');
                $this->applyTitleStyle($sheet, 'A4:J4', 10, true, Alignment::HORIZONTAL_RIGHT,'data');

                $this->applyTitleStyle($sheet, 'A6:J6', 11, true, Alignment::HORIZONTAL_LEFT,'header1');
                $this->applyTitleStyle($sheet, 'A7:J7', 11, true, Alignment::HORIZONTAL_LEFT,'header2');

                $this->applyTitleStyle($sheet, 'A12:J12', 11, true, Alignment::HORIZONTAL_LEFT,'header1');
                $this->applyTitleStyle($sheet, 'A13:J13', 11, true, Alignment::HORIZONTAL_LEFT,'header2');

                $this->applyTitleStyle($sheet, 'A18:J18', 11, true, Alignment::HORIZONTAL_LEFT,'header1');
                $this->applyTitleStyle($sheet, 'A19:J19', 11, true, Alignment::HORIZONTAL_LEFT,'header2');
                // Procesa las secciones: INSUMOS, MEDICAMENTOS, y PROCEDIMIENTOS
                foreach (['INSUMOS', 'MEDICAMENTOS', 'PROCEDIMIENTOS'] as $section) {
                    if (!empty($this->data[$section]['data'])) {
                        $this->insertSectionTotalRow($section, $sheet, 'I', $highestColumn);
                        $this->alignSectionLeft($section, $sheet, $highestColumn);
                        $this->styleSectionHeader($sheet, $section);
                    }
                }

                // Configura estilos generales
                //$this->applyGeneralStyles($sheet, $highestRow, $highestColumn);
                //$this->mergeInitialCells($sheet);
            },
        ];
    }

    private function insertTotalRow($label, $sheet, $column, $startRow, $highestColumn, $amount)
    {
        $sheet->insertNewRowBefore($startRow, 1);
        $sheet->setCellValue("{$column}{$startRow}", "$label $amount");
        //$sheet->mergeCells("{$column}{$startRow}:{$highestColumn}{$startRow}");
        $sheet->getStyle("{$column}{$startRow}")->getFont()->setBold(true);
    }

    private function insertSectionTotalRow($sectionName, $sheet, $column, $highestColumn)
    {
        $startRow = $this->getSectionStartRow($sectionName, $sheet);
        if ($startRow !== null) {
            $monto = $this->data[$sectionName]['montoTotal'];
            $this->insertTotalRow("Monto Total", $sheet, $column, $startRow, $highestColumn, $monto);
        }
    }

    private function alignSectionLeft($sectionName, $sheet, $highestColumn)
    {
        $startRow = $this->getSectionStartRow($sectionName, $sheet);
        if ($startRow !== null) {
            $endRow = $startRow + 2 + count($this->data[$sectionName]['data']);
            $sheet->getStyle("A{$startRow}:{$highestColumn}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    private function calculateOverallTotal()
    {
        return array_reduce(['INSUMOS', 'MEDICAMENTOS', 'PROCEDIMIENTOS'], function ($total, $section) {
            return $total + ($this->data[$section]['montoTotal'] ?? 0);
        }, 0);
    }

    private function applyGeneralStylesx($sheet, $highestRow, $highestColumn)
    {
        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
            'font' => [
                'name' => 'Calibri',
                'size' => 12,
            ],
        ]);

        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range(2, $highestRow) as $row) {
            $alignment = is_numeric($sheet->getCell("A{$row}")->getValue()) ?
                Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT;
            $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                ->getAlignment()
                ->setHorizontal($alignment);
        }

        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_NONE],
                'bottom' => ['borderStyle' => Border::BORDER_NONE],
                'left' => ['borderStyle' => Border::BORDER_NONE],
                'right' => ['borderStyle' => Border::BORDER_NONE],
            ],
        ]);
    }

    private function mergeInitialCells($sheet,$column)
    {
        $sheet->mergeCells($column);
    }

    private function columnDimensionCells($sheet,$col,$width){
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    private function getSectionStartRow($title, $sheet)
    {
        foreach ($sheet->toArray() as $rowIndex => $row) {
            if (isset($row[0]) && $row[0] === $title) {
                return $rowIndex + 1;
            }
        }
        return null;
    }

    private function applyTitleStyle($sheet, $range, $fontSize = 14, $isBold = true, $alignment,$bg)
    {
        $argb =  'FFCCCCCC';
        $fontColor =  'FFFFFF';
        switch ($bg) {
            case 'header1':
                $argb =  '2E74B5';
                $fontColor =  'FFFFFF';
                break;
            case "header2":
                $argb =  'FFCCCCCC';
                $fontColor =  '797780';
                break;
            case "data":
                $argb =  'FFFFFFFF';
                $fontColor =  '000000';
                break;
            default:
                $argb =  'FFFFFFFF';
                $fontColor =  '000000';
                break;
        }
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => $isBold,
                'size' => $fontSize,
                'color' => ['rgb' => $fontColor],
            ],
            'alignment' => [
                'horizontal' => $alignment,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => $argb, // Color gris claro para las cabeceras
                ],
            ],
        ]);
    }

    private function styleSectionHeader($sheet, $section)
    {
        $startRow = $this->getSectionStartRow($section, $sheet);
        if ($startRow !== null) {
            $this->applyTitleStyle($sheet, "A{$startRow}:J{$startRow}", 11, true, Alignment::HORIZONTAL_LEFT,'header1');
            $this->applyTitleStyle($sheet, "A" . ($startRow + 1) . ":J" . ($startRow + 1), 11, true, Alignment::HORIZONTAL_LEFT,'header2');
        }
    }

}
