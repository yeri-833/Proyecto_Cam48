-- =========================================================
-- 1. RECONSTRUCCIÓN TOTAL DE LA BASE DE DATOS
-- =========================================================
DROP DATABASE IF EXISTS proyecto_colegio;
CREATE DATABASE proyecto_colegio;
USE proyecto_colegio;

-- =========================================================
-- 2. ESTRUCTURA DE TODAS LAS TABLAS (Fiel a tu Diagrama)
-- =========================================================

CREATE TABLE usuarios (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre_completo VARCHAR(100),
    correo VARCHAR(100),
    telefono VARCHAR(20),
    password VARCHAR(255),
    tipo_usuario VARCHAR(50),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE alumnos (
    matricula VARCHAR(20) NOT NULL,
    nombre VARCHAR(50),
    apellido VARCHAR(50),
    sexo ENUM('H', 'M'),
    edad INT(11),
    nivel_educativo VARCHAR(50),
    grado VARCHAR(20),
    grupo VARCHAR(20),
    curp VARCHAR(18),
    fecha_nacimiento DATE,
    discapacidad VARCHAR(100),
    comentarios TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rol VARCHAR(50),
    PRIMARY KEY (matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tutores (
    id INT(11) NOT NULL AUTO_INCREMENT,
    matricula_alumno VARCHAR(20),
    t1_nombre VARCHAR(100),
    t1_parentesco VARCHAR(30),
    t1_telefono VARCHAR(20),
    t1_direccion TEXT,
    t1_profesion VARCHAR(50),
    t2_nombre VARCHAR(100),
    t2_parentesco VARCHAR(30),
    t2_telefono VARCHAR(20),
    PRIMARY KEY (id),
    KEY idx_matricula_alumno (matricula_alumno),
    CONSTRAINT fk_tutor_alumno FOREIGN KEY (matricula_alumno) 
        REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE registros_asistencia (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_persona VARCHAR(50),
    rol VARCHAR(30),
    fecha DATE,
    hora TIME,
    modo_registro VARCHAR(20),
    PRIMARY KEY (id),
    KEY idx_asistencia_persona_fecha (id_persona, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE archivos_sistema (
    id INT(11) NOT NULL AUTO_INCREMENT,
    matricula VARCHAR(20),
    nombre_original VARCHAR(255),
    nombre_servidor VARCHAR(255),
    tipo_mime VARCHAR(100),
    tamano_bytes INT(11),
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_archivo_matricula (matricula),
    CONSTRAINT fk_archivo_matricula FOREIGN KEY (matricula) 
        REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE calificaciones (
    id INT(11) NOT NULL AUTO_INCREMENT,
    matricula_alumno VARCHAR(20),
    materia VARCHAR(100),
    calificacion DECIMAL(4,2),
    PRIMARY KEY (id),
    KEY idx_calif_matricula (matricula_alumno),
    CONSTRAINT fk_calif_alumno FOREIGN KEY (matricula_alumno) 
        REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE eventos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre_evento VARCHAR(100),
    fecha_hora DATETIME,
    ubicacion VARCHAR(150),
    PRIMARY KEY (id),
    KEY idx_eventos_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE personal (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rfc VARCHAR(15),
    nombre_completo VARCHAR(150),
    categoria VARCHAR(50),
    funcion VARCHAR(100),
    plaza VARCHAR(100),
    grado_grupo VARCHAR(20),
    telefono VARCHAR(15),
    activo TINYINT(1) DEFAULT 1,
    creado_por INT(11),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rfc (rfc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 3. INSERCIÓN DE DATOS COMPLETOS
-- =========================================================

-- --- TABLA: usuarios ---
INSERT INTO usuarios (id, nombre_completo, correo, telefono, password, tipo_usuario, fecha_registro) VALUES 
(1, 'Oussama Chiñas', 'director@cam48.com', '9210000000', '$2y$10$8.UnS3K4u3pS6kP5Gv8E9uE7A8S2tZ6vW9mR1qT3hY4jO5pL6kN7m', 'Directivo', '2026-04-13 16:59:11'),
(10, 'Secretaria Escolar', 'secretaria@cam48.com', '9210000001', '$2y$10$K9Y8Z7X6W5V4U3T2S1R0O.nBvCxZzYyXxWwVvUuTtSsRrQqPpOoNn', 'Secretario', '2026-04-15 04:56:57');

-- --- TABLA: personal ---
INSERT INTO personal (id, rfc, nombre_completo, categoria, funcion, plaza, grado_grupo, fecha_registro) VALUES 
(1, 'IALN7001173QA', 'IGNACIO LOPEZ NOE', 'Administrativo', 'Director', '079737 E06290005001180-1', 'Sin Grupo', '2026-04-13 22:19:59'),
(2, 'AAMA8905123Z9', 'ARAOZ MARTINEZ ALMA VICTORIA', 'Docente', 'Profesor de Educación Especial', '079737 E06870009000011', '23-A', '2026-04-13 22:19:59'),
(3, 'CUVM021122RL3', 'DE LA CRUZ VELEZ MONSERRAT ESTEFANIA', 'Docente', 'Profesor de Educación Especial', '079737 E0687000300299', '23-B', '2026-04-13 22:19:59'),
(4, 'FAMS640622658', 'FABRE MATA SERGIO ENRIQUE', 'Docente', 'Psicólogo', '079737 E06890003000560-1', 'Sin Grupo', '2026-04-13 22:19:59'),
(5, 'GOCJ821231370', 'GONZALEZ COBOS JUAN MANUEL', 'Intendencia', 'Intendente o Conserje', '073004S018030002000390-1', 'Sin Grupo', '2026-04-13 22:19:59'),
(6, 'HEBP7512054J6', 'HERNANDEZ BALDERAS PETRA ESMERALDA', 'Docente', 'Profesor de Educación Especial', '079737 E0687000300576', '12-A', '2026-04-13 22:19:59'),
(7, 'HEME8702082S5', 'HERNANDEZ MENDEZ ERIKA', 'Apoyo', 'Trabajador(a) Social', '073004T268030003001040-1', 'Sin Grupo', '2026-04-13 22:19:59'),
(8, 'LAHJ940910J7', 'ALANDA HERNANDEZ JOCELYN', 'Docente', 'Profesor de Educación Especial', '079737 E0687000300860', '34-A', '2026-04-13 22:19:59'),
(9, 'LUCA770415PC8', 'LUIS CUELLO MARIA ANTONIA', 'Docente', 'Profesor de Educación Especial', '079737 E0687000300081', '12-A', '2026-04-13 22:19:59'),
(10, 'OACG711231RG6', 'OVANDO CASANOVA GUADALUPE DEL CARMEN', 'Docente', 'Profesor de Educación Especial', '079737 E0687000300402', '56-A', '2026-04-13 22:19:59'),
(11, 'RAHM8411232R8', 'RAMIREZ HERNANDEZ MAIRELI', 'Docente', 'Profesor de Educación Especial', '079737 E068100050007', '23-A', '2026-04-13 22:19:59');

-- --- TABLA: alumnos ---
INSERT INTO alumnos (matricula, nombre, apellido, sexo, nivel_educativo, grado, grupo, curp, discapacidad, activo, fecha_registro, rol) VALUES 
('LAB-01', 'Karen Arlette', 'Román Rodríguez', 'M', 'Formación Laboral', 'Laboral', 'A', 'RORK930105MVZMDR00', 'Síndrome de down', 1, '2026-04-13 16:59:11', 'Alumno'),
('LAB-02', 'Guadalupe Adriana', 'Montero González', 'M', 'Formación Laboral', 'Laboral', 'A', 'MOGG070522MDFNNDA6', 'Discapacidad visual', 1, '2026-04-13 16:59:11', 'Alumno'),
('LAB-03', 'Grettell Juliette', 'Arias Guerrero', 'M', 'Formación Laboral', 'Laboral', 'A', 'AAIGG101011MVZRRRA', 'Intelectual', 1, '2026-04-13 16:59:11', 'Alumno'),
('LAB-04', 'Emmanuel', 'Goiz Reyes', 'H', 'Formación Laboral', 'Laboral', 'A', 'GORE870118HVZZYM01', 'Retraso psicomotor', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRE-01', 'Yahira Guadalupe', 'García Gustamante', 'M', 'Preescolar', '3', 'A', 'GAGA040907MVZRSLA7', 'Retraso psicomotor', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRE-02', 'Bibiana Novali', 'Hernández Rodriguez', 'M', 'Preescolar', '3', 'A', 'HERB200914MVZRDBA9', 'Síndrome de Down', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRE-03', 'Yislianni Merary', 'Hernández Cuervo', 'M', 'Preescolar', '3', 'A', 'HECY200330MVZRRSA3', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRE-04', 'Raul Emiliano', 'Escobar Delgado', 'H', 'Preescolar', '3', 'A', 'EODR201128HVZSLLA4', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRE-05', 'Jose Fernando', 'Lobo Colin', 'H', 'Preescolar', '3', 'A', 'COET750520MMCLSR00', 'DI', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRI-01', 'Ever Javier', 'Hernandez Horosco', 'H', 'Primaria', '1', 'A', 'HEOE190709HVZRRVA3', 'DI', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRI-02', 'Pablo Caleb', 'Martinez Morales', 'H', 'Primaria', '1', 'A', 'MORS841130MVZRYMO7', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRI-03', 'Alessia Antonella', 'Rios Hernandez', 'M', 'Primaria', '1', 'A', 'RIHA190406MVZSRLA3', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRI-04', 'Milagros', 'Castro Gonzalez', 'M', 'Primaria', '1', 'A', 'CAGM190711MVZSNLA5', 'Hipoacusia', 1, '2026-04-13 16:59:11', 'Alumno'),
('PRI-09', 'JOSE MIGUEL', 'RAMÍREZ GARCÍA', 'H', 'PRIMARIA', '1', 'A', NULL, 'Ninguna', 1, '2026-04-14 01:47:34', 'ALUMNO'),
('SEC-01', 'Jose Said', 'Dominguez Arias', 'H', 'Secundaria', '1', 'A', 'DOAS120514HVZMRDA8', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('SEC-02', 'Yelsin Ruben', 'Cruz Salazar', 'H', 'Secundaria', '1', 'A', 'CUSY130101HVZRLLA2', 'TEA', 1, '2026-04-13 16:59:11', 'Alumno'),
('SEC-03', 'Alisson Celeste', 'Cruz Martinez', 'M', 'Secundaria', '2', 'A', 'CUMA130616MVZRRLA1', 'Intelectual', 1, '2026-04-13 16:59:11', 'Alumno'),
('SEC-04', 'Jose Joaquin', 'Martinez Dominguez', 'H', 'Secundaria', '3', 'A', 'MADJ070512HVZRMQA2', 'DI', 1, '2026-04-13 16:59:11', 'Alumno');

-- --- TABLA: tutores ---
INSERT INTO tutores (matricula_alumno, t1_nombre, t1_telefono) VALUES 
('PRE-01', 'Alondra Rubi Garcia Gustamante', '2831266558'),
('PRE-02', 'Maria Karilu Hernández Rodríguez', '9211726603'),
('PRE-03', 'Landy Merari Cuervo Martinez', '9213108665'),
('PRE-04', 'Irasema Del Carmen Delgado', '9213404337'),
('PRE-05', 'Teresa Colina Estrada', '9212072053'),
('PRI-01', 'Yessica Orozco Cortes', '9212296957'),
('PRI-02', 'Samai Morales Reyes', '9211515028'),
('PRI-03', 'Yaritza Del Socorro Hernandez Barrera', '9211926459'),
('PRI-04', 'Perla Victoria Castro Gonzalez', '9214262870'),
('SEC-01', 'Nereida Arias Narvaez', '9214052904'),
('SEC-02', 'Maria Vasquez Soto', '9213115682'),
('SEC-03', 'Evelyn Yunueh Cruz Martinez', '9211699721'),
('SEC-04', 'Ariadnna Gabriela Dominguez Alvarado', '9212300000'),
('LAB-01', 'Julio Cesar Román Rodríguez', '9210000000'),
('LAB-02', 'Adrián Antonio Montero Carrillo', '9210000000'),
('LAB-03', 'Lorena Guerrero Sarmiento', '9210000000'),
('LAB-04', 'Santa Delgado Reyes', '9210000000'),
('PRI-09', 'MARIA GARCÍA', '2831087272');

-- --- TABLA: registros_asistencia ---
INSERT INTO registros_asistencia (id, id_persona, rol, fecha, hora, modo_registro) VALUES 
(1, 'SEC-03', 'Alumno', '2026-04-14', '00:25:08', 'automatico'),
(2, 'SEC-03', 'Alumno', '2026-04-14', '03:04:32', 'automatico'),
(3, 'SEC-03', 'Alumno', '2026-04-14', '03:09:34', 'automatico'),
(4, 'LAB-03', 'Alumno', '2026-04-13', '21:42:25', 'automatico'),
(5, 'LAB-03', 'Alumno', '2026-04-13', '21:42:28', 'automatico'),
(6, 'SEC-02', 'Alumno', '2026-04-14', '00:58:31', 'automatico'),
(7, '15', 'Tutor', '2026-04-14', '01:11:00', 'automatico'),
(8, '3', 'Tutor', '2026-04-14', '01:13:03', 'automatico'),
(9, '2', 'Docente', '2026-04-14', '02:24:41', 'automatico'),
(10, '4', 'Docente', '2026-04-14', '02:26:07', 'automatico'),
(11, '4', 'Docente', '2026-04-15', '00:29:16', 'automatico'),
(12, 'SEC-04', 'Alumno', '2026-04-15', '10:45:00', 'automatico'),
(13, '7', 'Apoyo', '2026-04-15', '10:48:24', 'automatico'),
(14, '7', 'Apoyo', '2026-04-15', '11:30:19', 'automatico'),
(15, '7', 'Apoyo', '2026-04-15', '12:14:45', 'automatico'),
(16, '7', 'Apoyo', '2026-04-17', '22:13:15', 'automatico'),
(17, '15', 'Tutor', '2026-04-21', '22:51:00', 'automatico');
