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

class Liquidacion2Export implements FromCollection, WithEvents
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

                //MONTO TOTAL
                $startRow2 = 4;

                $InsumosMontoTotal = $this->data['INSUMOS']['montoTotal'];
                $MedicamentosMontoTotal = $this->data['MEDICAMENTOS']['montoTotal'];
                $ProcedimientosMontoTotal = $this->data['PROCEDIMIENTOS']['montoTotal'];

                $MontoTotalizado = $InsumosMontoTotal + $MedicamentosMontoTotal+$ProcedimientosMontoTotal;
                $sheet->insertNewRowBefore($startRow2, 1); // Inserta una nueva fila antes del inicio de la sección
                $sheet->setCellValue("D" . ($startRow2), "Monto total de la atención ". ($MontoTotalizado));
                $sheet->mergeCells("D" . ($startRow2) . ":{$highestColumn}" . ($startRow2)); // Fusionar celdas para la fila "Monto Total"
                $sheet->getStyle("D" . ($startRow2))->getFont()->setBold(true);



                // Función para insertar una fila con la etiqueta "Monto Total" encima de una sección
                $insertMontoTotalRow = function ($sectionName, $sheet, $highestColumn,$monto) {
                    $startRow = $this->getSectionStartRow($sectionName, $sheet);
                    $sheet->insertNewRowBefore($startRow, 1); // Inserta una nueva fila antes del inicio de la sección
                    $sheet->setCellValue("I" . ($startRow), "Monto Total ". ($monto));
                    $sheet->mergeCells("I" . ($startRow) . ":{$highestColumn}" . ($startRow)); // Fusionar celdas para la fila "Monto Total"
                    $sheet->getStyle("I" . ($startRow))->getFont()->setBold(true);
                };


                // Fusionar celdas A1 hasta G1
                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('C6:F6');
                $sheet->mergeCells('C7:F7');
                // Fusión de celdas C13 a H13
                $sheet->mergeCells('C13:H13');
                // Establecer anchos de columnas
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(16);
                $sheet->getColumnDimension('E')->setWidth(6);
                $sheet->getColumnDimension('F')->setWidth(24);
                $sheet->getColumnDimension('G')->setWidth(6);
                $sheet->getColumnDimension('H')->setWidth(18);

                // Ajustar ancho automático solo donde hay datos
                foreach (range('A', $highestColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Aplicar tamaño de texto y fuente consistente
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Calibri',
                        'size' => 12,
                    ],
                ]);

                // Alinear los textos de forma lógica
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Alineación inteligente según tipo de dato
                foreach (range(2, $highestRow) as $row) {
                    if (is_numeric($sheet->getCell("A{$row}")->getValue())) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } else {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }

                // Establecer bordes como invisibles
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'left' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'right' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                    ],
                ]);

                // Estilos para el título
                $sheet->getStyle("A1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,  // Aumentar tamaño del título
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Aplicar estilos a los encabezados (Títulos y encabezados de columnas)
                for ($row = 6; $row <= $highestRow; $row +=6) { // Comenzamos desde la tercera fila
                    $highestColumnInRow = $sheet->getHighestColumn($row);

                    // Estilo para los títulos (Primera cabecera)
                    $sheet->getStyle("A{$row}:{$highestColumnInRow}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '2e74b5'],
                        ],
                        'font' => [
                            'color' => ['rgb' => 'FFFFFF'],
                            'bold' => true,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT, // Alinear a la izquierda
                        ],
                    ]);

                    // Estilo para los encabezados de columnas (Segunda cabecera)
                    $nextRow = $row + 1;
                    $sheet->getStyle("A{$nextRow}:{$highestColumnInRow}{$nextRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'd9d9d9'],
                        ],
                        'font' => [
                            'color' => ['rgb' => '78757e'],
                            'bold' => true,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT, // Alinear a la izquierda
                        ],
                    ]);
                }

                // Llamadas a la función con los 3 argumentos
                $insertMontoTotalRow('INSUMOS', $sheet, $highestColumn,$this->data['INSUMOS']['montoTotal']);
                $insertMontoTotalRow('MEDICAMENTOS', $sheet, $highestColumn,$this->data['MEDICAMENTOS']['montoTotal']);
                $insertMontoTotalRow('PROCEDIMIENTOS', $sheet, $highestColumn,$this->data['PROCEDIMIENTOS']['montoTotal']);

                // Alinear a la izquierda los datos de MEDICAMENTOS
                $medicamentosStartRow = $this->getSectionStartRow('MEDICAMENTOS', $sheet);
                $medicamentosEndRow = $medicamentosStartRow+2 + count($this->data['MEDICAMENTOS']['data']);
                $sheet->getStyle("A{$medicamentosStartRow}:{$highestColumn}{$medicamentosEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Alinear a la izquierda los datos de INSUMOS
                $insumosStartRow = $this->getSectionStartRow('INSUMOS', $sheet);
                $insumosEndRow = $insumosStartRow+2 + count($this->data['INSUMOS']['data']);
                $sheet->getStyle("A{$insumosStartRow}:{$highestColumn}{$insumosEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Alinear a la izquierda los datos de PROCEDIMIENTOS
                $procedimientosStartRow = $this->getSectionStartRow('PROCEDIMIENTOS', $sheet);
                $procedimientosEndRow = $procedimientosStartRow+2 + count($this->data['PROCEDIMIENTOS']['data']);
                $sheet->getStyle("A{$procedimientosStartRow}:{$highestColumn}{$procedimientosEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            },
        ];
    }

    private function getSectionStartRow($title, $sheet)
    {
        // Obtiene el inicio de la sección
        foreach ($sheet->toArray() as $rowIndex => $row) {
            if (isset($row[0]) && $row[0] === $title) {
                return $rowIndex + 1; // Retorna la fila justo después del título
            }
        }
        return null; // Si no se encuentra la sección, retorna nulo
    }
}
