<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Arma un archivo .zip sin depender de la extensión zip de PHP.
 * Los archivos se guardan sin volver a comprimir: los PDF ya vienen comprimidos.
 */
class ArchivoZip
{
    /** @var resource */
    private $flujo;

    /** @var list<string> */
    private array $directorio = [];

    private int $desplazamiento = 0;

    public function __construct()
    {
        $this->flujo = fopen('php://temp/maxmemory:'.(16 * 1024 * 1024), 'w+b');
    }

    /**
     * Agrega un archivo. La ruta usa «/» para indicar carpetas, por ejemplo «Carpeta/archivo.pdf».
     */
    public function agregar(string $ruta, string $contenido, ?CarbonInterface $fecha = null): void
    {
        $fecha ??= now('America/Guatemala');
        $hora = ($fecha->hour << 11) | ($fecha->minute << 5) | intdiv($fecha->second, 2);
        $dia = ((max(1980, $fecha->year) - 1980) << 9) | ($fecha->month << 5) | $fecha->day;
        $crc = crc32($contenido);
        $tamano = strlen($contenido);

        $encabezado = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, $hora, $dia, $crc, $tamano, $tamano, strlen($ruta), 0).$ruta;
        fwrite($this->flujo, $encabezado);
        fwrite($this->flujo, $contenido);

        $this->directorio[] = pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, $hora, $dia, $crc, $tamano, $tamano, strlen($ruta), 0, 0, 0, 0, 0, $this->desplazamiento).$ruta;
        $this->desplazamiento += strlen($encabezado) + $tamano;
    }

    /**
     * Devuelve el contenido completo del .zip.
     */
    public function contenido(): string
    {
        $directorio = implode('', $this->directorio);
        $total = count($this->directorio);
        fwrite($this->flujo, $directorio);
        fwrite($this->flujo, pack('VvvvvVVv', 0x06054B50, 0, 0, $total, $total, strlen($directorio), $this->desplazamiento, 0));
        rewind($this->flujo);

        return stream_get_contents($this->flujo);
    }
}
