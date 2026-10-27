# OP al borrar una OP, deberian advertir que se van a borrar las recepciones de calidad
## Borrado OP
### mostrar advertencia
[(qoder)]

deberia sacar una alerta con el mensaje "no es posible borrar la OP porque tiene recepciones asociadas" 
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`maco`.`recepcion_calidads`, CONSTRAINT `recepcion_calidads_orden_produccion_id_foreign` 