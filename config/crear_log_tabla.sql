-- ================================================================
-- Log de Auditoría — Sistema de Gestión Escolar Comercio N°1
-- Ejecutar una sola vez antes de usar el sistema de auditoría
-- ================================================================

CREATE TABLE IF NOT EXISTS logs_actividad (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario     INT NOT NULL,
    nombre_usuario VARCHAR(120) NOT NULL,
    accion         VARCHAR(60)  NOT NULL,   -- 'ALTA_ALUMNO', 'ELIMINAR_PAGO', etc.
    detalle        TEXT,                    -- Descripción legible del evento
    ip             VARCHAR(45),
    fecha          DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_fecha   (fecha),
    INDEX idx_log_usuario (id_usuario),
    INDEX idx_log_accion  (accion)
);
