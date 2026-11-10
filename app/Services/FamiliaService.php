<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Tutor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio de Dominio para la Identidad Familiar en FamilyLock.
 *
 * Establece una distinción matemática y canónica entre la persona física
 * del tutor y la unidad familiar escolar (conjunto de alumnos hermanos y sus tutores legales).
 * Evita colisiones de homónimos, errores por teléfonos compartidos y duplicidades en juntas.
 */
class FamiliaService
{
    /**
     * Resuelve la clave canónica de familia a la que pertenece un tutor.
     *
     * Invariante de Dominio:
     * 1. Si el tutor está formalmente vinculado a alumnos mediante `alumno_tutor` o `matricula_alumno`,
     *    la identidad familiar se define por el grupo conexo de matrículas de alumnos hermanos.
     * 2. Si no tiene alumnos vinculados aún, se genera un identificador aislado de tutor verificado.
     */
    public function obtenerClaveFamilia(Tutor $tutor): string
    {
        $matriculas = [];

        // 1. Matrículas resueltas mediante alumnos_vinculados (soporta alumno_tutor y correspondencia estricta legacy)
        $vinculados = $tutor->alumnos_vinculados;
        if ($vinculados->isNotEmpty()) {
            $matriculas = $vinculados->pluck('matricula')->map(fn ($m) => (string) $m)->all();
        }

        // 2. Matrícula histórica directa
        if (! empty($tutor->matricula_alumno)) {
            $matriculas[] = (string) $tutor->matricula_alumno;
        }

        // 3. Matrículas explícitas desde alumno_tutor
        if (Schema::hasTable('alumno_tutor')) {
            $pivotMatriculas = DB::table('alumno_tutor')
                ->where('tutor_id', $tutor->id)
                ->pluck('alumno_matricula')
                ->map(fn ($m) => (string) $m)
                ->all();

            $matriculas = array_merge($matriculas, $pivotMatriculas);
        }

        $matriculas = array_values(array_unique(array_filter($matriculas)));

        if (! empty($matriculas)) {
            // Expandir a hermanos que compartan tutores con estos alumnos
            $matriculasGrupo = $this->expandirMatriculasHermanos($matriculas);
            sort($matriculasGrupo);

            return 'FAM:' . implode(',', $matriculasGrupo);
        }

        // 3. Fallback determinista para tutores sin alumnos asignados aún
        $nombre = trim(mb_strtoupper((string) ($tutor->t1_nombre ?? ''), 'UTF-8'));
        $contacto = trim((string) ($tutor->t1_telefono ?? '')) . '|' . trim((string) ($tutor->t1_email ?? ''));
        $direccion = trim(mb_strtoupper((string) ($tutor->t1_direccion ?? ''), 'UTF-8'));

        return 'FAM-ISOLATED:' . hash('sha256', "tutor:{$tutor->id}|{$nombre}|{$contacto}|{$direccion}");
    }

    /**
     * Resuelve la clave canónica de familia a la que pertenece un alumno.
     */
    public function obtenerClaveFamiliaAlumno(Alumno $alumno): string
    {
        $matriculas = [(string) $alumno->matricula];

        $matriculasGrupo = $this->expandirMatriculasHermanos($matriculas);
        sort($matriculasGrupo);

        return 'FAM:' . implode(',', $matriculasGrupo);
    }

    /**
     * Determina si dos tutores pertenecen a la misma familia.
     */
    public function sonMismaFamilia(Tutor $tutorA, Tutor $tutorB): bool
    {
        return $this->obtenerClaveFamilia($tutorA) === $this->obtenerClaveFamilia($tutorB);
    }

    /**
     * Agrupa una colección de tutores por su unidad familiar canónica.
     *
     * @param iterable<Tutor> $tutores
     * @return Collection<string, Collection<int, Tutor>>
     */
    public function agruparPorFamilia(iterable $tutores): Collection
    {
        return collect($tutores)->groupBy(fn (Tutor $t) => $this->obtenerClaveFamilia($t));
    }

    /**
     * Cuenta el total de familias únicas en un conjunto de tutores.
     *
     * @param iterable<Tutor> $tutores
     */
    public function contarFamiliasUnicas(iterable $tutores): int
    {
        return $this->agruparPorFamilia($tutores)->count();
    }

    /**
     * Expande recursivamente el grafo de hermanos a través de tutores compartidos en alumno_tutor.
     *
     * @param array<string> $matriculasIniciales
     * @return array<string>
     */
    private function expandirMatriculasHermanos(array $matriculasIniciales): array
    {
        if (! Schema::hasTable('alumno_tutor') || empty($matriculasIniciales)) {
            return $matriculasIniciales;
        }

        // Obtener todos los tutores que atienden a estas matrículas
        $tutorIds = DB::table('alumno_tutor')
            ->whereIn('alumno_matricula', $matriculasIniciales)
            ->pluck('tutor_id')
            ->unique()
            ->all();

        if (empty($tutorIds)) {
            return $matriculasIniciales;
        }

        // Obtener todos los alumnos que son atendidos por esos mismos tutores
        $todasMatriculas = DB::table('alumno_tutor')
            ->whereIn('tutor_id', $tutorIds)
            ->pluck('alumno_matricula')
            ->map(fn ($m) => (string) $m)
            ->unique()
            ->all();

        return array_values(array_unique(array_merge($matriculasIniciales, $todasMatriculas)));
    }
}
