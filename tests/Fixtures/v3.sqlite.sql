-- SQLite test schema derived from V3.sql DDL. No production data.
CREATE TABLE tbl_cargo (
    id_cargo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_cargo VARCHAR(50) NOT NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_cargo_nombre UNIQUE (nombre_cargo)
);

CREATE TABLE tbl_especialidad (
    id_especialidad INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_especialidad VARCHAR(100) NOT NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_especialidad_nombre UNIQUE (nombre_especialidad)
);

CREATE TABLE tbl_personal (
    id_personal INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cargo INT NOT NULL,
    id_especialidad INT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    dpi CHAR(13) NOT NULL,
    telefono VARCHAR(9) NULL,
    correo VARCHAR(150) NULL,
    numero_colegiado VARCHAR(30) NULL,
    es_externo BOOLEAN NOT NULL DEFAULT FALSE,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_personal_dpi UNIQUE (dpi),
    CONSTRAINT uq_personal_correo UNIQUE (correo),
    CONSTRAINT fk_personal_cargo FOREIGN KEY (id_cargo)
        REFERENCES tbl_cargo (id_cargo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_personal_especialidad FOREIGN KEY (id_especialidad)
        REFERENCES tbl_especialidad (id_especialidad)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_personal_dpi CHECK (dpi REGEXP '^[0-9]{13}$'),
    CONSTRAINT chk_personal_telefono CHECK (
        telefono IS NULL OR telefono REGEXP '^[0-9]{4}-[0-9]{4}$'
    )
);

CREATE TABLE tbl_rol (
    id_rol INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_rol VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_rol_nombre UNIQUE (nombre_rol)
);

CREATE TABLE tbl_modulo (
    id_modulo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_modulo VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_modulo_nombre UNIQUE (nombre_modulo)
);

CREATE TABLE tbl_usuario (
    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
    id_personal INT NOT NULL,
    id_rol INT NOT NULL,
    nombre_usuario VARCHAR(50) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo', 'Bloqueado')) NOT NULL DEFAULT 'Activo',
    intentos_fallidos SMALLINT NOT NULL DEFAULT 0,
    ultimo_acceso DATETIME NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuario_personal UNIQUE (id_personal),
    CONSTRAINT uq_usuario_nombre UNIQUE (nombre_usuario),
    CONSTRAINT fk_usuario_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol)
        REFERENCES tbl_rol (id_rol)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_permiso (
    id_permiso INTEGER PRIMARY KEY AUTOINCREMENT,
    id_modulo INT NOT NULL,
    accion TEXT CHECK (accion IN ('Ver', 'Ingresar', 'Editar', 'Desactivar', 'Imprimir')) NOT NULL,
    descripcion VARCHAR(180) NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_permiso_modulo_accion UNIQUE (id_modulo, accion),
    CONSTRAINT fk_permiso_modulo FOREIGN KEY (id_modulo)
        REFERENCES tbl_modulo (id_modulo)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_rol_permiso (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rol_permiso_rol FOREIGN KEY (id_rol)
        REFERENCES tbl_rol (id_rol)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso)
        REFERENCES tbl_permiso (id_permiso)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE tbl_bitacora (
    ip VARCHAR(45) NULL,
    navegador VARCHAR(150) NULL,
    sistema_operativo VARCHAR(100) NULL,
    agente_usuario VARCHAR(1024) NULL,
    tabla VARCHAR(100) NULL,
    id_registro BIGINT NULL,
    id_bitacora INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INT NULL,
    id_modulo INT NULL,
    usuario_intentado VARCHAR(50) NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    resultado TEXT CHECK (resultado IN ('Exitoso', 'Fallido')) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bitacora_modulo FOREIGN KEY (id_modulo)
        REFERENCES tbl_modulo (id_modulo)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_paciente (
    id_paciente INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_expediente VARCHAR(30) NOT NULL,
    estado_identificacion TEXT CHECK (estado_identificacion IN ('Identificado', 'No identificado')) NOT NULL DEFAULT 'Identificado',
    codigo_temporal VARCHAR(30) NULL,
    nombres VARCHAR(100) NULL,
    apellidos VARCHAR(100) NULL,
    dpi CHAR(13) NULL,
    fecha_nacimiento DATE NULL,
    sexo TEXT CHECK (sexo IN ('Masculino', 'Femenino')) NULL,
    telefono VARCHAR(9) NULL,
    direccion VARCHAR(255) NULL,
    tipo_sangre TEXT CHECK (tipo_sangre IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')) NULL,
    alergias TEXT NULL,
    antecedentes_medicos TEXT NULL,
    antecedentes_familiares TEXT NULL,
    observaciones_generales TEXT NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_paciente_expediente UNIQUE (numero_expediente),
    CONSTRAINT uq_paciente_codigo_temporal UNIQUE (codigo_temporal),
    CONSTRAINT uq_paciente_dpi UNIQUE (dpi),
    CONSTRAINT fk_paciente_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_paciente_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_paciente_dpi CHECK (
        dpi IS NULL OR dpi REGEXP '^[0-9]{13}$'
    ),
    CONSTRAINT chk_paciente_telefono CHECK (
        telefono IS NULL OR telefono REGEXP '^[0-9]{4}-[0-9]{4}$'
    ),
    CONSTRAINT chk_paciente_identificacion CHECK (
        (estado_identificacion = 'Identificado'
            AND nombres IS NOT NULL
            AND apellidos IS NOT NULL
            AND telefono IS NOT NULL)
        OR
        (estado_identificacion = 'No identificado'
            AND codigo_temporal IS NOT NULL)
    )
);

CREATE TABLE tbl_contacto_paciente (
    id_contacto INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INT NOT NULL,
    nombre VARCHAR(150) NULL,
    parentesco VARCHAR(50) NULL,
    telefono VARCHAR(20) NULL,
    CONSTRAINT fk_contacto_paciente FOREIGN KEY (id_paciente)
        REFERENCES tbl_paciente (id_paciente)
        ON UPDATE RESTRICT ON DELETE RESTRICT
);

CREATE TABLE tbl_ingreso (
    id_ingreso_anterior INT NULL,
    id_ingreso INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INT NOT NULL,
    tipo_atencion TEXT CHECK (tipo_atencion IN ('Hospitalización', 'Consulta externa')) NOT NULL DEFAULT 'Hospitalización',
    id_personal_registra INT NOT NULL,
    fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo_ingreso VARCHAR(255) NULL,
    documento_firmado BOOLEAN NOT NULL DEFAULT FALSE,
    estado TEXT CHECK (estado IN ('Activo', 'Dado de alta', 'Cancelado')) NOT NULL DEFAULT 'Activo',
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ingreso_paciente FOREIGN KEY (id_paciente)
        REFERENCES tbl_paciente (id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ingreso_personal FOREIGN KEY (id_personal_registra)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ingreso_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ingreso_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_turno_personal (
    id_turno INTEGER PRIMARY KEY AUTOINCREMENT,
    id_personal INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NOT NULL,
    tipo_turno TEXT CHECK (tipo_turno IN ('Diurno', 'Nocturno', '24 horas', 'Otro')) NOT NULL,
    estado TEXT CHECK (estado IN ('Programado', 'En curso', 'Finalizado', 'Cancelado')) NOT NULL DEFAULT 'Programado',
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_turno_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_turno_usuario FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_turno_fechas CHECK (fecha_hora_fin > fecha_hora_inicio)
);

CREATE TABLE tbl_asignacion_personal_ingreso (
    id_asignacion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_personal INT NOT NULL,
    tipo_asignacion TEXT CHECK (tipo_asignacion IN ('Médico responsable', 'Médico de apoyo', 'Enfermería', 'Otro')) NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hora_fin DATETIME NULL,
    observaciones TEXT NULL,
    estado TEXT CHECK (estado IN ('Activa', 'Finalizada', 'Cancelada')) NOT NULL DEFAULT 'Activa',
    id_usuario_creacion INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asignacion_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_usuario FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_asignacion_fechas CHECK (
        fecha_hora_fin IS NULL OR fecha_hora_fin > fecha_hora_inicio
    ),
    CONSTRAINT uq_asignacion_inicio UNIQUE (id_ingreso, id_personal, tipo_asignacion, fecha_hora_inicio)
);

CREATE TABLE tbl_habitacion (
    id_habitacion INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_habitacion VARCHAR(20) NOT NULL,
    tipo VARCHAR(50) NULL,
    estado_operativo TEXT CHECK (estado_operativo IN ('Activa', 'Mantenimiento', 'Inactiva')) NOT NULL DEFAULT 'Activa',
    observaciones TEXT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_habitacion_numero UNIQUE (numero_habitacion)
);

CREATE TABLE tbl_hospitalizacion (
    id_hospitalizacion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME NULL,
    motivo VARCHAR(255) NULL,
    estado TEXT CHECK (estado IN ('Activa', 'Finalizada', 'Cancelada')) NOT NULL DEFAULT 'Activa',
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_hospitalizacion_ingreso UNIQUE (id_ingreso),
    CONSTRAINT fk_hospitalizacion_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hospitalizacion_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hospitalizacion_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_hospitalizacion_fechas CHECK (
        fecha_fin IS NULL OR fecha_fin > fecha_inicio
    )
);

CREATE TABLE tbl_hospitalizacion_habitacion (
    id_hospitalizacion_habitacion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_hospitalizacion INT NOT NULL,
    id_habitacion INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hora_fin DATETIME NULL,
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hosp_habitacion_hospitalizacion FOREIGN KEY (id_hospitalizacion)
        REFERENCES tbl_hospitalizacion (id_hospitalizacion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hosp_habitacion_habitacion FOREIGN KEY (id_habitacion)
        REFERENCES tbl_habitacion (id_habitacion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hosp_habitacion_usuario FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_hosp_habitacion_fechas CHECK (
        fecha_hora_fin IS NULL OR fecha_hora_fin > fecha_hora_inicio
    ),
    CONSTRAINT uq_hosp_habitacion_inicio UNIQUE (id_hospitalizacion, fecha_hora_inicio)
);

CREATE TABLE tbl_evolucion_clinica (
    id_evolucion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_personal INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo_atencion VARCHAR(255) NULL,
    sintomas TEXT NULL,
    evaluacion_medica TEXT NULL,
    diagnostico TEXT NULL,
    indicaciones TEXT NULL,
    observaciones TEXT NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evolucion_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evolucion_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evolucion_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evolucion_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_signos_vitales (
    id_signos_vitales INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_personal INT NOT NULL,
    tipo_control TEXT CHECK (tipo_control IN ('Ingreso', 'Consulta', 'Preoperatorio', 'Recuperación', 'Rutina')) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    presion_sistolica SMALLINT NULL,
    presion_diastolica SMALLINT NULL,
    frecuencia_cardiaca SMALLINT NULL,
    frecuencia_respiratoria SMALLINT NULL,
    temperatura DECIMAL(4,1) NULL,
    saturacion_oxigeno TINYINT NULL,
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_signos_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_signos_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_signos_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_signos_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_signos_presion CHECK (
        (presion_sistolica IS NULL AND presion_diastolica IS NULL)
        OR
        (presion_sistolica IS NOT NULL AND presion_diastolica IS NOT NULL
            AND presion_sistolica BETWEEN 30 AND 300
            AND presion_diastolica BETWEEN 20 AND 200)
    ),
    CONSTRAINT chk_signos_fc CHECK (
        frecuencia_cardiaca IS NULL OR frecuencia_cardiaca BETWEEN 1 AND 300
    ),
    CONSTRAINT chk_signos_fr CHECK (
        frecuencia_respiratoria IS NULL OR frecuencia_respiratoria BETWEEN 1 AND 100
    ),
    CONSTRAINT chk_signos_temperatura CHECK (
        temperatura IS NULL OR temperatura BETWEEN 25.0 AND 50.0
    ),
    CONSTRAINT chk_signos_saturacion CHECK (
        saturacion_oxigeno IS NULL OR saturacion_oxigeno BETWEEN 0 AND 100
    )
);

CREATE TABLE tbl_medicamento (
    id_medicamento INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_medicamento VARCHAR(150) NOT NULL,
    presentacion VARCHAR(100) NOT NULL DEFAULT '',
    concentracion VARCHAR(100) NOT NULL DEFAULT '',
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_medicamento_nombre UNIQUE (nombre_medicamento, presentacion, concentracion)
);

CREATE TABLE tbl_tratamiento (
    id_tratamiento INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_evolucion INT NULL,
    id_personal_indica INT NOT NULL,
    id_medicamento INT NULL,
    tipo_tratamiento TEXT CHECK (tipo_tratamiento IN ('Medicamento', 'No farmacológico')) NOT NULL DEFAULT 'Medicamento',
    descripcion_tratamiento VARCHAR(255) NULL,
    dosis VARCHAR(100) NULL,
    via_administracion VARCHAR(80) NULL,
    frecuencia VARCHAR(100) NULL,
    duracion VARCHAR(100) NULL,
    indicaciones TEXT NULL,
    fecha_indicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio DATETIME NULL,
    fecha_fin DATETIME NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Finalizado', 'Suspendido', 'Cancelado')) NOT NULL DEFAULT 'Activo',
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tratamiento_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tratamiento_evolucion FOREIGN KEY (id_evolucion)
        REFERENCES tbl_evolucion_clinica (id_evolucion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tratamiento_personal FOREIGN KEY (id_personal_indica)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tratamiento_medicamento FOREIGN KEY (id_medicamento)
        REFERENCES tbl_medicamento (id_medicamento)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_tratamiento_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tratamiento_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_tratamiento_fechas CHECK (
        fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio
    )
);

CREATE TABLE tbl_administracion_medicamento (
    id_administracion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_tratamiento INT NOT NULL,
    id_personal INT NOT NULL,
    fecha_hora_programada DATETIME NULL,
    fecha_hora_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado TEXT CHECK (estado IN ('Administrado', 'Omitido', 'Rechazado')) NOT NULL,
    dosis_administrada VARCHAR(100) NULL,
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_administracion_tratamiento FOREIGN KEY (id_tratamiento)
        REFERENCES tbl_tratamiento (id_tratamiento)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_administracion_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_administracion_usuario FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_atencion_enfermeria (
    id_atencion_enfermeria INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_personal INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado_paciente VARCHAR(20) NULL CHECK (estado_paciente IN ('Mejoró', 'Sin cambios', 'Empeoró')),
    id_signos_vitales INTEGER NULL,
    actividad VARCHAR(180) NULL,
    observaciones TEXT NULL,
    aviso_medico TINYINT(1) NOT NULL DEFAULT 0,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_atencion_enfermeria_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atencion_enfermeria_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atencion_enfermeria_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atencion_enfermeria_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atencion_enfermeria_signos FOREIGN KEY (id_signos_vitales)
        REFERENCES tbl_signos_vitales (id_signos_vitales)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_atencion_enfermeria_actividad (
    id_actividad INTEGER PRIMARY KEY AUTOINCREMENT,
    id_atencion_enfermeria INTEGER NOT NULL,
    actividad VARCHAR(100) NOT NULL,
    CONSTRAINT fk_actividad_atencion_enfermeria FOREIGN KEY (id_atencion_enfermeria)
        REFERENCES tbl_atencion_enfermeria (id_atencion_enfermeria)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_alta_medica (
    id_alta INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_personal_medico INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diagnostico_final TEXT NULL,
    condicion_egreso VARCHAR(150) NULL,
    indicaciones_alta TEXT NULL,
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_alta_ingreso UNIQUE (id_ingreso),
    CONSTRAINT fk_alta_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alta_medico FOREIGN KEY (id_personal_medico)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alta_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alta_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_alta_medicamento (
    id_alta_medicamento INTEGER PRIMARY KEY AUTOINCREMENT,
    id_alta INT NOT NULL,
    medicamento VARCHAR(255) NOT NULL,
    dosis VARCHAR(100) NOT NULL,
    via_administracion VARCHAR(80) NULL,
    frecuencia VARCHAR(100) NOT NULL,
    duracion VARCHAR(100) NULL,
    CONSTRAINT fk_alta_medicamento_alta FOREIGN KEY (id_alta)
        REFERENCES tbl_alta_medica (id_alta)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_documento_clinico (
    id_documento INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INT NULL,
    id_ingreso INT NULL,
    id_personal_responsable INT NULL,
    categoria TEXT CHECK (categoria IN ('Radiografía', 'Ultrasonido', 'Laboratorio', 'Electrocardiograma', 'Informe', 'Otro')) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    tipo_documento VARCHAR(150) NULL,
    descripcion TEXT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(20) NOT NULL,
    fecha_documento DATETIME NULL,
    estado TEXT CHECK (estado IN ('Activo', 'Inactivo')) NOT NULL DEFAULT 'Activo',
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documento_paciente FOREIGN KEY (id_paciente)
        REFERENCES tbl_paciente (id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_personal FOREIGN KEY (id_personal_responsable)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_cirugia (
    id_cirugia INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ingreso INT NOT NULL,
    id_evolucion INT NULL,
    id_personal_indica INT NOT NULL,
    tipo_operacion VARCHAR(150) NOT NULL,
    fecha_programada DATETIME NOT NULL,
    estado TEXT CHECK (estado IN ('Programada', 'Evaluación preoperatoria', 'Aprobada', 'Realizada', 'Cancelada')) NOT NULL DEFAULT 'Programada',
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cirugia_ingreso FOREIGN KEY (id_ingreso)
        REFERENCES tbl_ingreso (id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cirugia_evolucion FOREIGN KEY (id_evolucion)
        REFERENCES tbl_evolucion_clinica (id_evolucion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cirugia_personal_indica FOREIGN KEY (id_personal_indica)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cirugia_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cirugia_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_evaluacion_preoperatoria (
    id_evaluacion_preop INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cirugia INT NOT NULL,
    id_personal_evaluador INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    electrocardiograma TEXT NULL,
    radiografia TEXT NULL,
    ultrasonido TEXT NULL,
    resultado_laboratorio_relevante TEXT NULL,
    apto_cirugia BOOLEAN NOT NULL,
    observaciones TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_preop_cirugia FOREIGN KEY (id_cirugia)
        REFERENCES tbl_cirugia (id_cirugia)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_preop_personal FOREIGN KEY (id_personal_evaluador)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_preop_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_preop_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_cirugia_equipo (
    id_cirugia_equipo INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cirugia INT NOT NULL,
    id_personal INT NOT NULL,
    rol_quirurgico TEXT CHECK (rol_quirurgico IN (
        'Cirujano principal',
        'Cirujano externo',
        'Anestesiólogo',
        'Enfermera instrumentista',
        'Enfermera circulante',
        'Especialista externo',
        'Otro'
    )) NOT NULL,
    estado TEXT CHECK (estado IN ('Asignado', 'Participó', 'Cancelado')) NOT NULL DEFAULT 'Asignado',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_cirugia_equipo UNIQUE (id_cirugia, id_personal, rol_quirurgico),
    CONSTRAINT fk_cirugia_equipo_cirugia FOREIGN KEY (id_cirugia)
        REFERENCES tbl_cirugia (id_cirugia)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cirugia_equipo_personal FOREIGN KEY (id_personal)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE tbl_registro_operatorio (
    id_registro_operatorio INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cirugia INT NOT NULL,
    id_personal_responsable INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NULL,
    procedimiento_realizado TEXT NOT NULL,
    tecnica_utilizada TEXT NULL,
    hallazgos TEXT NULL,
    complicaciones TEXT NULL,
    indicaciones_posteriores TEXT NULL,
    id_usuario_creacion INT NOT NULL,
    id_usuario_modificacion INT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_registro_operatorio_cirugia UNIQUE (id_cirugia),
    CONSTRAINT fk_registro_operatorio_cirugia FOREIGN KEY (id_cirugia)
        REFERENCES tbl_cirugia (id_cirugia)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_registro_operatorio_personal FOREIGN KEY (id_personal_responsable)
        REFERENCES tbl_personal (id_personal)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_registro_operatorio_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_registro_operatorio_usuario_modificacion FOREIGN KEY (id_usuario_modificacion)
        REFERENCES tbl_usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_registro_operatorio_fechas CHECK (
        fecha_hora_fin IS NULL OR fecha_hora_fin > fecha_hora_inicio
    )
);


CREATE TABLE tbl_cargo_especialidad (id_cargo INTEGER NOT NULL REFERENCES tbl_cargo(id_cargo), id_especialidad INTEGER NOT NULL REFERENCES tbl_especialidad(id_especialidad), PRIMARY KEY (id_cargo,id_especialidad));
