# FamilyLock — Documentación Oficial del Sistema (v1.4.8)

Bienvenido a la documentación técnica, arquitectónica y operativa de **FamilyLock**, sistema de gestión de asistencia escolar y vinculación familiar para centros educativos.

---

## 1. Índice de Documentación

| Directorio | Documento | Contenido Principal |
| :--- | :--- | :--- |
| **`architecture/`** | [`overview.md`](architecture/overview.md) | Filosofía del sistema, stack tecnológico, módulos y responsabilidades. |
| **`database/`** | [`schema_and_migrations.md`](database/schema_and_migrations.md) | Modelo entidad-relación, tablas principales, restricciones de integridad y claves foráneas. |
| **`development/`** | [`guidelines.md`](development/guidelines.md) | Estándares de código (PSR-12), SOLID, directrices de Vue 3, Inertia v2 y tipado. |
| **`security/`** | [`security_model.md`](security/security_model.md) | Roles y autorizaciones RBAC (`AlumnoPolicy`), tokenización HMAC y protección de PII. |
|  | [`protocolo_vinculacion_telegram.md`](security/protocolo_vinculacion_telegram.md) | Protocolo seguro para la vinculación de cuentas de Telegram mediante tokens de un solo uso. |
| **`testing/`** | [`testing_strategy.md`](testing/testing_strategy.md) | Arquitectura de pruebas, arnés de aislamiento en memoria, suites dinámicas y Windows Sandbox. |
| **`operations/`** | [`deployment.md`](operations/deployment.md) | Requisitos de servidor, variables de entorno, instalación, actualización grácil y workers. |
|  | [`sandbox/`](operations/sandbox/) | Plantillas y manifiestos de configuración para Windows Sandbox (`.wsb`). |
| **`api/`** | [`endpoints.md`](api/endpoints.md) | Catálogo completo de rutas web, middlewares de autorización y contratos de datos. |
| **`historical/`** | [`agent_phases/`](historical/agent_phases/) | Auditorías y planes de trabajo correspondientes a las fases de desarrollo anteriores. |
|  | [`superpowers/`](historical/superpowers/) | Registros y planes de hardening históricos de superpowers. |

---

## 2. Mapa Rápido de Referencia

- **¿Cómo calcular la participación en una junta escolar?**  
  Consulte [`architecture/overview.md`](architecture/overview.md#c-módulo-de-convocatorias-y-juntas-eventocontroller-estadisticascontroller) y el servicio de dominio [`FamiliaService`](file:///c:/Users/YERALDI%20EMMMANUEL/Desktop/Proyecto_Cam48/app/Services/FamiliaService.php).
- **¿Cómo ejecutar las pruebas sin tocar el sistema anfitrión?**  
  Consulte [`testing/testing_strategy.md`](testing/testing_strategy.md#4-ejecución-en-windows-sandbox).
- **¿Cómo actualizar de forma segura en producción?**  
  Consulte [`operations/deployment.md`](operations/deployment.md#4-procedimiento-de-actualización-grácil).
