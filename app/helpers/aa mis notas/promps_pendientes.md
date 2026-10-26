# al borrar una OP, deberian advertir que se van a borrar las recepciones de calidad

deberia sacar una alerta con el mensaje "no es posible borrar la OP porque tiene recepciones asociadas" 
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`maco`.`recepcion_calidads`, CONSTRAINT `recepcion_calidads_orden_produccion_id_foreign` 

# form cambiar estado OP ((recibiendo))
en el formulario de cambiar estado OP, estado recibiendo, no debe preguntar Pérdidas en mermas *,Materiales sobrantes o recuperados * o  costo real final, eso lo debe calcular el programa.
Materiales sobrantes o recuperados: ese campo borralo completamente
Pérdidas en mermas: es la suma del coste de cada producto considerado en perdida total
costo real final: se calcula desde mucho antes, aqui solo se muestra ese valor (pero se puede corroborar, sumado lo que vale cada producto a medida que se llena el formulario)
ejemplo: si hay 3 productos y se han recibido 2 producto en 500 c/u entonces el costo real final es 1000 por el momento, al momento de recibir el producto faltante debe cuadrar con el valor que se calculo desde la creacion de la OP

# materiales y productos en OP

en la creacion de una nueva OP debe existir la posibilidad de escojer si el producto final sera un prodocuto(como esta ahora) o  si sera un material(obligatoriamente material con proceso)

## 24 oct

# mini bug
en el form cambiar estado (en taller), al guardar un avance, no esta teniendo en cuenta la fecha "comprimiso pendiente"