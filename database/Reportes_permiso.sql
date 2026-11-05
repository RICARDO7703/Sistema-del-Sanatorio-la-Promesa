-- Ejecutar en la base seleccionada: sistema_sanatorio en local o railway en la nube.
-- No modifica pacientes, atenciones ni documentos. No duplica el permiso existente.
START TRANSACTION;
INSERT INTO tbl_modulo (nombre_modulo, descripcion, estado)
SELECT 'Reportes', 'Consultar y generar reportes PDF.', 'Activo'
WHERE NOT EXISTS (SELECT 1 FROM tbl_modulo WHERE nombre_modulo = 'Reportes');
INSERT INTO tbl_permiso (id_modulo, accion, descripcion, estado)
SELECT m.id_modulo, 'Ver', 'Consultar y generar reportes PDF.', 'Activo'
FROM tbl_modulo m WHERE m.nombre_modulo = 'Reportes'
AND NOT EXISTS (SELECT 1 FROM tbl_permiso p WHERE p.id_modulo = m.id_modulo AND p.accion = 'Ver');
COMMIT;
-- El administrador ya tiene acceso. Para otros usuarios, asignar Reportes > Ver
-- desde Usuarios, además del permiso de consulta del módulo de origen.
