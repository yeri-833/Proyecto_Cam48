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
            // Expandir de forma exhaustiva el componente conexo completo de alumnos y tutores
            $matriculasGrupo = $this->expandirComponenteConexoAlumnos($matriculas, [$tutor->id]);
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
        $matriculasGrupo = $this->expandirComponenteConexoAlumnos([(string) $alumno->matricula]);
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
     * Expande de forma exhaustiva el componente conexo completo del grafo bipartito Alumnos <-> Tutores (BFS).
     *
     * Invariante matemático:
     * Si el Tutor A atiende al Alumno 1, el Tutor B atiende a los Alumnos 1 y 2,
     * el Tutor C atiende a los Alumnos 2 y 3, y el Tutor D atiende al Alumno 3,
     * todos pertenecen al mismo componente conexo escolar y resuelven la misma clave FAM:1,2,3
     * independientemente del tutor o alumno desde el que se inicie la consulta.
     *
     * @param array<string> $matriculasSemilla
     * @param array<int> $tutoresSemilla
     * @return array<string> Matrículas pertenecientes al componente conexo completo ordenadas lexicográficamente
     */
    private function expandirComponenteConexoAlumnos(array $matriculasSemilla, array $tutoresSemilla = []): array
    {
        $alumnosVisitados = [];
        $tutoresVisitados = [];

        $colaAlumnos = [];
        $colaTutores = [];

        foreach ($matriculasSemilla as $m) {
            $mStr = trim((string) $m);
            if ($mStr !== '' && ! isset($alumnosVisitados[$mStr])) {
                $alumnosVisitados[$mStr] = true;
                $colaAlumnos[] = $mStr;
            }
        }

        foreach ($tutoresSemilla as $tId) {
            $tInt = (int) $tId;
            if ($tInt > 0 && ! isset($tutoresVisitados[$tInt])) {
                $tutoresVisitados[$tInt] = true;
                $colaTutores[] = $tInt;
            }
        }

        $tieneAlumnoTutor = Schema::hasTable('alumno_tutor');
        $tieneTutores = Schema::hasTable('tutores');
        $tieneMatriculaCol = $tieneTutores && Schema::hasColumn('tutores', 'matricula_alumno');

        while (! empty($colaAlumnos) || ! empty($colaTutores)) {
            // 1. Procesar alumnos pendientes en la cola para descubrir tutores adyacentes
            if (! empty($colaAlumnos)) {
                $loteAlumnos = $colaAlumnos;
                $colaAlumnos = [];

                $tutoresEncontrados = [];

                if ($tieneAlumnoTutor) {
                    $fromPivot = DB::table('alumno_tutor')
                        ->whereIn('alumno_matricula', $loteAlumnos)
                        ->pluck('tutor_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $tutoresEncontrados = array_merge($tutoresEncontrados, $fromPivot);
                }

                if ($tieneMatriculaCol) {
                    $fromLegacy = DB::table('tutores')
                        ->whereIn('matricula_alumno', $loteAlumnos)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $tutoresEncontrados = array_merge($tutoresEncontrados, $fromLegacy);
                }

                foreach ($tutoresEncontrados as $tId) {
                    if ($tId > 0 && ! isset($tutoresVisitados[$tId])) {
                        $tutoresVisitados[$tId] = true;
                        $colaTutores[] = $tId;
                    }
                }
            }

            // 2. Procesar tutores pendientes en la cola para descubrir alumnos adyacentes (hermanos)
            if (! empty($colaTutores)) {
                $loteTutores = $colaTutores;
                $colaTutores = [];

                $alumnosEncontrados = [];

                if ($tieneAlumnoTutor) {
                    $fromPivot = DB::table('alumno_tutor')
                        ->whereIn('tutor_id', $loteTutores)
                        ->pluck('alumno_matricula')
                        ->map(fn ($m) => (string) $m)
                        ->all();
                    $alumnosEncontrados = array_merge($alumnosEncontrados, $fromPivot);
                }

                if ($tieneMatriculaCol) {
                    $fromLegacy = DB::table('tutores')
                        ->whereIn('id', $loteTutores)
                        ->whereNotNull('matricula_alumno')
                        ->where('matricula_alumno', '!=', '')
                        ->pluck('matricula_alumno')
                        ->map(fn ($m) => (string) $m)
                        ->all();
                    $alumnosEncontrados = array_merge($alumnosEncontrados, $fromLegacy);
                }

                foreach ($alumnosEncontrados as $mat) {
                    $matStr = trim((string) $mat);
                    if ($matStr !== '' && ! isset($alumnosVisitados[$matStr])) {
                        $alumnosVisitados[$matStr] = true;
                        $colaAlumnos[] = $matStr;
                    }
                }
            }
        }

        $resultado = array_keys($alumnosVisitados);
        sort($resultado);

        return $resultado;
    }
}
