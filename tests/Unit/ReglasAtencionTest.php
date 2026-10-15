<?php

namespace Tests\Unit;

use App\Services\ReglasAtencion;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReglasAtencionTest extends TestCase
{
    #[DataProvider('estados')]
    public function test_solo_un_ingreso_activo_admite_cambios(string $estado, bool $esperado): void
    {
        self::assertSame($esperado, ReglasAtencion::ingresoActivo($estado));
    }

    public static function estados(): array
    {
        return [['Activo', true], ['Dado de alta', false], ['Cancelado', false], ['', false]];
    }

    #[DataProvider('fechas')]
    public function test_fecha_clinica_respeta_los_limites_del_episodio(string $fecha, bool $esperado): void
    {
        self::assertSame($esperado, ReglasAtencion::fechaValida(new DateTimeImmutable($fecha), new DateTimeImmutable('2026-10-10 08:00:00'), new DateTimeImmutable('2026-10-11 12:00:00')));
    }

    public static function fechas(): array
    {
        return [['2026-10-10 07:59:59', false], ['2026-10-10 08:00:00', true], ['2026-10-11 10:00:00', true], ['2026-10-11 12:00:00', true], ['2026-10-11 12:00:01', false]];
    }

    #[DataProvider('cierres')]
    public function test_cierre_requiere_un_intervalo_positivo(string $fin, bool $esperado): void
    {
        self::assertSame($esperado, ReglasAtencion::cierreValido(new DateTimeImmutable($fin), new DateTimeImmutable('2026-10-11 12:00:00')));
    }

    public static function cierres(): array
    {
        return [['2026-10-11 11:59:59', false], ['2026-10-11 12:00:00', false], ['2026-10-11 12:00:01', true]];
    }

    #[DataProvider('tratamientos')]
    public function test_el_tipo_de_tratamiento_exige_su_informacion(string $tipo, ?int $medicamento, ?string $descripcion, bool $esperado): void
    {
        self::assertSame($esperado, ReglasAtencion::tratamientoValido($tipo, $medicamento, $descripcion));
    }

    public static function tratamientos(): array
    {
        return [['Medicamento', 1, null, true], ['Medicamento', null, 'Texto', false], ['Medicamento', 0, null, false], ['No farmacológico', null, 'Reposo', true], ['No farmacológico', null, '  ', false], ['Otro', 1, 'Texto', false]];
    }
}
