-- Módulo 2: resultado "pending" en el registro de cambios.
-- Un cambio se registra como pendiente antes de que el plugin escriba los archivos, y pasa a
-- "applied" o "failed" cuando el plugin informa el resultado. Si el plugin se interrumpe a la mitad,
-- el cambio queda pendiente en lugar de perderse.
ALTER TABLE m2_change_log
    MODIFY result ENUM('pending', 'applied', 'rejected', 'failed', 'conflict') NOT NULL;
