<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

class PdfReportes
{
    private static ?string $logo = null;

    public function generar(string $titulo, array $filas, array $filtros = [], ?array $documento = null): string
    {
        $persona = DB::table('tbl_usuario as u')->join('tbl_personal as p', 'p.id_personal', '=', 'u.id_personal')->where('u.id_usuario', session('id_usuario'))->first(['p.nombres', 'p.apellidos']);
        $generadoPor = $persona ? trim($persona->nombres.' '.$persona->apellidos) : 'Sistema';
        $fechaGeneracion = now('America/Guatemala')->format('d/m/Y H:i');
        $logo = $this->logo();
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isJavascriptEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('reportes.pdf', compact('titulo', 'filas', 'filtros', 'documento', 'generadoPor', 'fechaGeneracion', 'logo'))->render());
        $pdf->setPaper('A4', $documento ? 'portrait' : 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text($pdf->getCanvas()->get_width() - 126, $pdf->getCanvas()->get_height() - 25, 'Página {PAGE_NUM} de {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.32, 0.38, 0.47]);

        return $pdf->output();
    }

    /**
     * Logo reducido al tamaño en que se imprime (unos 230 px), con resolución suficiente para papel.
     * El original pesa más de 1 MB y se repetía en cada PDF.
     */
    private function logo(): string
    {
        if (self::$logo !== null) {
            return self::$logo;
        }
        $bytes = file_get_contents(public_path('imagenes/logo-sanatorio.jpg'));
        $imagen = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if ($imagen !== false && imagesx($imagen) > 900) {
            $reducida = imagescale($imagen, 900);
            if ($reducida !== false) {
                ob_start();
                imagejpeg($reducida, null, 88);
                $bytes = ob_get_clean();
                imagedestroy($reducida);
            }
        }
        if ($imagen !== false) {
            imagedestroy($imagen);
        }

        return self::$logo = 'data:image/jpeg;base64,'.base64_encode($bytes);
    }
}
