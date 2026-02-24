-- ================================================================
-- Script de Índices MySQL — Sistema de Gestión Escolar Comercio N°1
-- Ejecutar una sola vez (si ya existen, el IF NOT EXISTS evita error)
-- ================================================================

-- Alumnos: búsqueda frecuente por DNI, apellido y nombre
ALTER TABLE alumnos
    ADD INDEX IF NOT EXISTS idx_alumnos_dni      (dni),
    ADD INDEX IF NOT EXISTS idx_alumnos_apellido (apellido),
    ADD INDEX IF NOT EXISTS idx_alumnos_nombre   (nombre);

-- Pagos: filtro por fecha (report de caja)
ALTER TABLE pagos
    ADD INDEX IF NOT EXISTS idx_pagos_fecha     (fecha),
    ADD INDEX IF NOT EXISTS idx_pagos_id_alumno (id_alumno);

-- Inscripciones: filtros por alumno, ciclo y estado
ALTER TABLE inscripciones
    ADD INDEX IF NOT EXISTS idx_inscr_id_alumno  (id_alumno),
    ADD INDEX IF NOT EXISTS idx_inscr_id_ciclo   (id_ciclo_lectivo),
    ADD INDEX IF NOT EXISTS idx_inscr_id_curso   (id_curso),
    ADD INDEX IF NOT EXISTS idx_inscr_estado     (estado);

-- Usuarios: login por nombre de usuario
ALTER TABLE usuarios
    ADD INDEX IF NOT EXISTS idx_usuarios_usuario (usuario);

-- ================================================================
-- VERIFICAR ÍNDICES EXISTENTES (ejecutar para revisar)
-- ================================================================
-- SHOW INDEX FROM alumnos;
-- SHOW INDEX FROM pagos;
-- SHOW INDEX FROM inscripciones;
-- SHOW INDEX FROM usuarios;
