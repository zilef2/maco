# OP al borrar una OP, deberian advertir que se van a borrar las recepciones de calidad
## Borrado OP
### mostrar advertencia
[(qoder)]

deberia sacar una alerta con el mensaje "no es posible borrar la OP porque tiene recepciones asociadas" 
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`maco`.`recepcion_calidads`, CONSTRAINT `recepcion_calidads_orden_produccion_id_foreign` 


# mini bug
en el form cambiar estado (en taller), al guardar un avance, no esta teniendo en cuenta la fecha "comprimiso pendiente"

# produc 
## tallas y cantidad del producto
1. hay que cambiar la forma en que se guarda la cantidad de un producto, ya que se debe especificar que cantidad de que talla del producto, es decir, un producto puede tener 10 talla S y 10 talla M por tanto la cantidad total seria 20. 

2. en ordenproduccion, en el modal de Cambiar estado — OP-100
Estado actual

En taller
si una OP pasa a recibiendo, y se guarda el avanze con el boton "guardar avanze" debe aumentar la cantidad del producto de esa talla



# (ya se corrio?)
en OrdenProduccion, hay un par de cosas que no son como deberian. cuando una OP llega al estado 
Cambiar estado — OP-100
Estado actual

En taller

solo deberia poder ir a recibiendo, el estado pendiente, ya no deberia existir. Ademas, del estado "recibiendo" no debe dejar que el usuario seleccione "cerrada", de manera automatica el software debe ponerla en modo cerrada cuando no quede ningun producto y todas sus tallas pendientes. ejemplo, si hay 3 tallas pendientes del producto1 entonces en el momento que se seleccione 1 en aprobada y 2 en no recuperable, ya esta completa la OP y al presionar "guardar" la OP queda cerrada

otra tarea: si hay almenos una talla en reparacion, debe pedir fecha de compromiso pendiente (campo que no existe aun) asi que agregalo a una nueva migracion, pero debe ser multiple ya que si a lo largo de los dias se van varias veces a reparacion, entonces cada vez pedirá la fecha de compromiso pendiente, debe crearse por defecto dos notificaciones, una el dia de fecha de compromiso pendiente y otra dos dias antes