<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\Fua;
use App\Exports\LiquidacionExport;
use Maatwebsite\Excel\Facades\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;

class FuaController extends Controller
{
    public function index(Request $request)
    {
        $idEpisodio = $request->query('idEpisodio');
        $datos = $this->liquidacionData($idEpisodio);
        return $datos;
    }

    public function liquidacionData($idEpisodio)
    {
         // Puedes agregar más episodios si es necesario

        // Obtener los datos con la consulta SQL personalizada
        $resultados = DB::table('PLATAFORMA.FUA AS FUA')
            ->leftJoin('PLATAFORMA.FUAMedicamentos AS MEDICAMENTO', 'FUA.IdFUA', '=', 'MEDICAMENTO.IdFUA')
            ->leftJoin('PLATAFORMA.FUAInsumos AS INSUMO', 'FUA.IdFUA', '=', 'INSUMO.IdFUA')
            ->leftJoin('PLATAFORMA.FUADiagnosticos AS DIACNOSTICO', 'FUA.IdFUA', '=', 'DIACNOSTICO.IdFUA')
            ->leftJoin('PLATAFORMA.FUAProcedimientos AS PROCEDIMIENTO', 'FUA.IdFUA', '=', 'PROCEDIMIENTO.IdFUA')
            ->where('FUA.idepisodio', $idEpisodio)
            ->select(
                'FUA.IdFUA', 'FUA.NFUA', 'FUA.FechaInsercion', 'FUA.ApePaterno', 'FUA.ApeMaterno',
                'FUA.PriNombre', 'FUA.HisCli', 'FUA.FecAte',
                'MEDICAMENTO.CodMedicamento', 'MEDICAMENTO.descripcion AS medicamento_descripcion',
                'MEDICAMENTO.FF', 'MEDICAMENTO.CONCENTR', 'MEDICAMENTO.CantPrescrita',
                'MEDICAMENTO.CantEntregada', 'MEDICAMENTO.NroDiagnostico', 'MEDICAMENTO.PrecioUnitario',
                'MEDICAMENTO.Importe',
                'INSUMO.CodInsumo', 'INSUMO.descripcion AS insumo_descripcion',
                'INSUMO.CantPrescrita AS insumo_CantPrescrita', 'INSUMO.CantEntregada AS insumo_CantEntregada',
                'INSUMO.NroDiagnostico AS insumo_NroDiagnostico', 'INSUMO.PrecioUnitario AS insumo_PrecioUnitario',
                'INSUMO.IMPORTE AS insumo_importe',
                'PROCEDIMIENTO.CodProcedimiento', 'PROCEDIMIENTO.descripcion AS procedimiento_descripcion',
                'PROCEDIMIENTO.cantorig', 'PROCEDIMIENTO.CantEjecutado', 'PROCEDIMIENTO.NroDiagnostico',
                'PROCEDIMIENTO.PrecioUnitario AS procedimiento_PrecioUnitario',
                'PROCEDIMIENTO.IMPORTE AS procedimiento_importe'
            )
            ->get();

        // Organizar los datos según la estructura solicitada
        $datos = [
            //"MONTO_TOTAL_ATENCION"=>number_format(12.000,3),
            "DATOS_DE_LA_ENTIDAD" => [
                [
                    "Número de Formato" => $resultados[0]->NFUA ?? '',
                    "Fecha Digitación" => $resultados[0]->FechaInsercion ?? '',
                    "IPRESS" => "0000023 HOSPITAL DE EMERGENCIAS VILLA EL SALVADOR"
                ]
            ],
            "DATOS_DEL_ASEGURADO" => [
                [
                    "Nombres" => $resultados[0]->PriNombre . ' ' . $resultados[0]->ApePaterno . ' ' . $resultados[0]->ApeMaterno,
                    "N° Historia" => $resultados[0]->HisCli ?? '',
                    "Contrato" => "230-E-10825478",  // Valor fijo o dinámico según necesidad
                    "Fecha de Atención" => $resultados[0]->FecAte ?? ''
                ]
            ],
            "MEDICAMENTOS" => [
                "montoTotal" => round($resultados->whereNotNull('CodMedicamento')->sum(function ($item) {
                    return $item->PrecioUnitario * $item->Importe;
                }),2),
                "data" => $resultados->whereNotNull('CodMedicamento')->map(function ($item) {
                    return [
                        "Codigo" => $item->CodMedicamento,
                        "Nombre" => $item->medicamento_descripcion,
                        "FF" => $item->FF,
                        "concentracion" => $item->CONCENTR,
                        "Pres." => $item->CantPrescrita,
                        "Entr." => $item->CantEntregada,
                        "Nro" => $item->NroDiagnostico,
                        "Dx" => "SEPSIS BACTERIANA",  // Ajusta según tu lógica
                        "Precio" => number_format($item->PrecioUnitario, 2),
                        "Importe" => number_format($item->Importe, 2),
                    ];
                })->values()->all(),
            ],
            "PROCEDIMIENTOS" => [
                "montoTotal" => round($resultados->whereNotNull('CodProcedimiento')->sum(function ($item) {
                    return $item->procedimiento_PrecioUnitario * $item->procedimiento_importe;
                }),2),
                "data" => $resultados->whereNotNull('CodProcedimiento')->map(function ($item) {
                    return [
                        "Codigo" => $item->CodProcedimiento,
                        "Nombre" => $item->procedimiento_descripcion,
                        "Pres." => $item->cantorig,
                        "Entr." => $item->CantEjecutado,
                        "N°" => $item->NroDiagnostico,
                        "Dx" => "SEPSIS BACTERIANA",  // Ajusta según tu lógica
                        "Precio" => number_format($item->procedimiento_PrecioUnitario, 2),
                        "Importe" => number_format($item->procedimiento_importe, 2),
                    ];
                })->values()->all(),
            ],
            "INSUMOS" => [
                "montoTotal" => round($resultados->whereNotNull('CodInsumo')->sum(function ($item) {
                    return $item->insumo_PrecioUnitario * $item->insumo_importe;
                }),2),
                "data" => $resultados->whereNotNull('CodInsumo')->map(function ($item) {
                    return [
                        "Codigo" => $item->CodInsumo,
                        "Nombre" => $item->insumo_descripcion,
                        "Pres." => $item->insumo_CantPrescrita,
                        "Entr." => $item->insumo_CantEntregada,
                        "N°" => $item->insumo_NroDiagnostico,
                        "Dx" => "SEPSIS BACTERIANA",  // Ajusta según tu lógica
                        "Precio" => number_format($item->insumo_PrecioUnitario, 2),
                        "Importe" => number_format($item->insumo_importe, 2),
                    ];
                })->values()->all(),
            ]
        ];

        return $datos;
    }

    public function xindex()
    {
        // $fuas = Fua::all();

        // return response()->json($fuas, 200);
        /*return Fua::select([
            'idepisodio',
            'LFua',
            'NFua',
            'IdDisaFormato',
            'LoteFormato',
            'NroFormato',
            'ApePaterno',
            'ApeMaterno',
            'PriNombre',
            'IdServicio',
            'idfua',
            'fecCrea',
            'fecAte',
            'Periodo',
            'Mes',
            'IdUsuarioEnvia'
            ])->take(10)->get();*/
        // $usuario = Fua::find('1738052024');
        $fechaIni = '2024-10-01';
        $fechaFin = '2024-10-10';
        //$mes='10';

        // $datos = DB::select('exec SP_ANEXO_01 "'.$mes.'"');
        //$datos = DB::select('exec SP_ANEXO_01 ?, ?', array($fechaIni, $fechaFin));
        $datos = DB::table('AAA.VEHICULO')->get();
        //return $datos;//response->json($datos);
        return $datos;
    }
    public function reporte_excel(Request $request)
    {
        $idEpisodio = $request->query('idEpisodio');
        $data = $this->liquidacionData($idEpisodio);
        return Excel::download(new LiquidacionExport($data), 'liquidacion.xlsx');
    }
    public function reporte_pdf(Request $request)
    {
        // Datos
        $idEpisodio = $request->query('idEpisodio');
        $data = $this->liquidacionData($idEpisodio);
        // Generar HTML para el PDF
        $html = $this->generateHtml($data);
        // Configuración de Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Courier');
        $dompdf = new Dompdf($options);
        // Cargar HTML
        $dompdf->loadHtml($html);
        // Configurar tamaño y orientación del papel
        $dompdf->setPaper([0, 0, 1200, 842], 'landscape');
        // Renderizar el PDF
        $dompdf->render();
        // Obtener el contenido del PDF como string
        $pdfContent = $dompdf->output();
        // Devolver respuesta con encabezados adecuados
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="documento.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    private function generateHtml($data)
    {
        // Estructura básica del HTML con estilos CSS
        $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            h2 { background-color: #2e74b5; color: white; padding: 10px; }
            .section-title { margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>';

        // Generar secciones dinámicas
        foreach ($data as $section => $rows) {
            $html .= "<h2>$section</h2><table>";

            if (!empty($rows)) {
                // Crear encabezados de la tabla usando las claves del primer elemento
                $headers = array_keys($rows[0]);
                $html .= '<tr>';
                foreach ($headers as $header) {
                    $html .= "<th>$header</th>";
                }
                $html .= '</tr>';

                // Crear filas con los valores de cada elemento
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach ($row as $value) {
                        $html .= '<td>' . htmlspecialchars($value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                // Mensaje para secciones vacías
                $html .= '<tr><td colspan="100%">No hay datos disponibles</td></tr>';
            }

            $html .= '</table>';
        }

        $html .= '</body></html>';

        return $html;
    }
    public function store(Request $request)
    {
        //
    }
    public function show(string $id)
    {
        //
    }
    public function update(Request $request, string $id)
    {
        //
    }
    public function destroy(string $id)
    {
        //
    }
}
