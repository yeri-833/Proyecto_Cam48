# FamilyLock v1.4.8 — Línea Base de Auditoría y Limpieza

Este documento establece la línea base formal antes de la reestructuración, optimización de recursos y estandarización profesional del repositorio.

---

## 1. Identificación del Entorno y Versión

| Parámetro | Detalle de Línea Base |
| :--- | :--- |
| **Repositorio** | `yeri-833/Proyecto_Cam48` |
| **Rama Autorizada** | `feature/familylock-v1.4.8-consolidacion` |
| **HEAD Inicial** | `ebbf471` (`docs(changelog): documentacion integral...`) |
| **Tag de Respaldo** | `backup-pre-cleanup-ebbf471` |
| **Rama de Respaldo** | `backup/pre-cleanup-baseline-ebbf471` |
| **Respaldo Físico** | `C:\Users\YERALDI EMMMANUEL\Desktop\FamilyLock-Backup-PreCleanup-20261110` |
| **PHP Runtime** | PHP 8.5.9 (Aislado en Windows Sandbox) |
| **Laravel Framework** | 13.8.x |
| **Motor de BD** | MariaDB 12.3 / SQLite en Memoria |

---

## 2. Métricas de Tamaño Iniciales

- **Archivos Fuente (excluyendo `.git`, `vendor`, `node_modules`)**: 1,862 archivos.
- **Peso Total de Archivos Fuente**: 62.44 MB.
- **Recurso de Mayor Tamaño Identificado**: `public/Logo del CAM.png` (7,006,876 bytes / ~7.00 MB, sin referencias activas en el código).
- **Archivos Duplicados Identificados**:
  - `public/isotipo-cam-512.png` == `public/icono-512.png` (SHA256: `F5CFCB175643A722810CC029254A0145774C9D6B842CE50EDCAFC604310A975D`).
  - `public/isotipo-cam.svg` == `public/favicon.svg` (SHA256: `34D13A87399B85CE2738BE2AA19517F2C1BD5A188F4B727F14A89FE2A391A528`).
  - `.gemini/skills/antislop*` == `.agents/skills/antislop*` (Mismos hashes SHA256).

---

## 3. Línea Base de Pruebas Dinámicas (Windows Sandbox)

- **Total de Pruebas Unitarias y de Integración**: 223 pruebas.
- **Total de Aserciones Verificadas**: 1,240 aserciones.
- **Fallos**: 0.
- **Errores**: 0.
- **Tasa de Aprobación**: 100% PASS.

---

## 4. Objetivos del Proceso de Limpieza y Estandarización

1. Organizar la documentación técnica en una jerarquía profesional bajo `docs/`.
2. Preservar archivos históricos en `docs/historical/`.
3. Estandarizar configuraciones locales y MCP eliminando rutas fijas de usuario.
4. Optimizar recursos gráficos públicos eliminando duplicados idénticos y reduciendo el peso de assets pesados.
5. Retirar la dependencia `@tailwindcss/vite` no utilizada manteniendo PostCSS + Tailwind 3.
6. Aplicar buenas prácticas de ingeniería de software (SOLID, PSR-12, tipado, DRY).
7. Revalidar el 100% de la suite de pruebas y compilación en Windows Sandbox sin ninguna regresión funcional.
