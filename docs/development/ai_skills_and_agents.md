# FamilyLock — Inventario y Clasificación de Herramientas IA (v1.4.8)

## 1. Clasificación de Herramientas y Habilidades

En cumplimiento con el proceso de estandarización y limpieza profesional, las herramientas de asistencia por Inteligencia Artificial y habilidades (`skills`) presentes en el repositorio se clasifican en las siguientes categorías operativas:

### Categoría A: Necesarias para Programar FamilyLock (Flujo de Desarrollo)
- **`subagent-driven-development`**: Coordinación de implementación con subtareas independientes y verificación paso a paso.
- **`executing-plans`**: Ejecución en línea de planes de implementación manteniendo contexto técnico.
- **`writing-plans`**: Elaboración de planes técnicos detallados antes de modificar código fuente.
- **`using-git-worktrees`**: Gestión y aislamiento de ramas de trabajo en repositorios Git.
- **`using-superpowers`**: Enrutador y despachador de habilidades de ingeniería de software.
- **`writing-skills`**: Creación y validación de nuevas directivas operativas.

### Categoría B: Necesarias para Pruebas y Auditorías
- **`test-driven-development`**: Metodología de pruebas unitarias y de integración previa a la codificación.
- **`systematic-debugging`**: Diagnóstico sistemático de causa raíz ante fallos de pruebas.
- **`verification-before-completion`**: Verificación estricta mediante evidencia reproducible antes de declarar tareas culminadas.
- **`requesting-code-review`**: Verificación de cumplimiento de requisitos de calidad previos a confirmación.
- **`receiving-code-review`**: Análisis técnico riguroso de retroalimentación recibida.
- **`diagnosing-superpowers`**: Diagnóstico de sesiones de asistencia y trazabilidad operativa.

### Categoría C: Herramientas Generales de Diseño e Interfaz
- **`ui-ux-pro-max`**: Inteligencia de diseño de interfaces, accesibilidad (WCAG), paletas de color y tipografía institucional.
- **`ui-styling`**: Componentes accesibles, microinteracciones y estilos con Tailwind CSS.
- **`apple-design`**: Principios de diseño fluido, retroalimentación táctil y diseño físico adaptado a la web.
- **`brand`**: Gestión de voz de marca, consistencia de identidad visual y reglas de aplicación de logotipos.
- **`design` / `design-system`**: Especificaciones de diseño tokenizado y estándares de identidad.
- **`banner-design` / `slides` / `brainstorming`**: Generación de presentaciones, gráficos explicativos y conceptualización.

### Categoría D: Herramientas Duplicadas y Resueltas
- **`antislop*` (`antislop`, `antislop-ui`, `antislop-code`, `antislop-human`, `antislop-copywriting`, `antislop-layoutmobile`)**:
  - Ubicación 1: `.agents/skills/antislop*`
  - Ubicación 2: `.gemini/skills/antislop*`
  - **Dictamen**: Los hashes SHA-256 de ambas ubicaciones son 100% idénticos. Se conservan intencionalmente en ambas rutas para garantizar compatibilidad bi-agente tanto con Antigravity (`AGENTS.md`) como con Gemini CLI (`GEMINI.md`), evitando rupturas de reglas en diferentes arneses.

### Categoría E: Configuraciones Personales Estandarizadas
- **`.agents/mcp_config.json`**:
  - Antes: Contenía la ruta absoluta dependiente de la máquina host del desarrollador (`C:\php\php.exe`).
  - Ahora: Estandarizado al comando genérico portable `"php"` y respaldado mediante la plantilla genérica `.agents/mcp_config.example.json`.
- **`AGENTS.md`**:
  - Antes: Invocaba comandos de prueba referenciando `C:\php\php.exe artisan`.
  - Ahora: Estandarizado a `php artisan` para portabilidad en cualquier entorno de desarrollo, servidor Linux o contenedor Docker.
