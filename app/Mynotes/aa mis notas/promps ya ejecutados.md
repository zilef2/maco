## Orden produccion ##----------------------------------------------

# OP al borrar una OP, deberian advertir que se van a borrar las recepciones de calidad
## Borrado OP
### mostrar advertencia
[(qoder)]

deberia sacar una alerta con el mensaje "no es posible borrar la OP porque tiene recepciones asociadas" 
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`maco`.`recepcion_calidads`, CONSTRAINT `recepcion_calidads_orden_produccion_id_foreign` 

# OP
## form cambiar estado OP ((recibiendo))
en el formulario de cambiar estado OP, estado recibiendo, no debe preguntar Pérdidas en mermas *,Materiales sobrantes o recuperados * o  costo real final, eso lo debe calcular el programa.
Materiales sobrantes o recuperados: ese campo borralo completamente
Pérdidas en mermas: es la suma del coste de cada producto considerado en perdida total
costo real final: se calcula desde mucho antes, aqui solo se muestra ese valor (pero se puede corroborar, sumado lo que vale cada producto a medida que se llena el formulario)
ejemplo: si hay 3 productos y se han recibido 2 producto en 500 c/u entonces el costo real final es 1000 por el momento, al momento de recibir el producto faltante debe cuadrar con el valor que se calculo desde la creacion de la OP


# mini bug
en el form cambiar estado (en taller), al guardar un avance, no esta teniendo en cuenta la fecha "comprimiso pendiente"




----------------------------------------------## PRODUCTO Y MATERIALES##----------------------------------------------

# producto
## tallas y cantidad del producto
1. hay que cambiar la forma en que se guarda la cantidad de un producto, ya que se debe especificar que cantidad de que talla del producto, es decir, un producto puede tener 10 talla S y 10 talla M por tanto la cantidad total seria 20. 

2. en ordenproduccion, en el modal de Cambiar estado — OP-100
Estado actual

En taller
si una OP pasa a recibiendo, y se guarda el avanze con el boton "guardar avanze" debe aumentar la cantidad del producto de esa talla


----------------------------------------------## dashboard ##----------------------------------------------

# dashboard
## primer vistazo al dashboard
el dashboard, debe tener una zona para graficos, otra para resultados del mes y por ultimo una zona para las notificaciones rojas. por el momento busca una buena extension de graficos, muy llamativa, instala la mejor extension para graficos con vue o js, deben haber almenos 2 graficos.
se debe responder a: Reportes de existencias, movimientos, compras, producción, costos y desviaciones por período, claro esta que debe ser en tiempo real, no estatico.

la zona de las notificaciones solo sera visible si hay notificaciones rojas, deben decir exactamente lo mismo que las notificaciones y estar en la parte superior del dashboard


# dashboard
## testing
genera tests para el dashboard, has una diferencia entre valores numericos simples, como pares aprobados, y una grafica. ambas categorias deben tener su archivo de tests diferente


----------------------------------------------## CierreOrdenProduccion ##----------------------------------------------

# CierreOrdenProduccion

hay que mejorar el modulo de CierreOrdenProduccion, ya que muestra datos pero no son tan utiles a la vista. No se porque no sale nada en la columan orden_produccion_id, pero esa columan deberia mostrar el codigo de la orden (ejemplo: OP-101) debe agrupar por OP, pero solo cuando se aplique el filtro de OP, ademas El numero de paginacion debe ser mayor al numero de filas resultantes cuando se aplique dicho filtro de OP, ejemplo: si la OP-101 tiene 11 registros en cierreordenproduccion, entonces la paginacion debe cambiar automaticamente a 25 que es el numero mas pequeño entre las paginaciones pero mayor a 11.

este index de cierreordenproduccion no debe tener barra de busqueda, pero si un filtro para fecha_cierre, responsable.

usa el diseño de vista igual al index.vue de producto. 

en dato esperado, dato real y diferencia son equivalentes a los valores en el form de "cambiar estado" de las OP solo que se llaman "ESPERADA", "APROBADA" respectivamente (diferencia es un dato calculado, es la resta entre esperado y real), asi que falta tener encuenta "NO RECUPERABLE"


----------------------------------------------## FICHATECNICAMATERIALES ##----------------------------------------------
# FICHATECNICAMATERIALES
Modulo fichatecnicamateriales, al editar una ficha, no debe permitir cambiar el nombre, y debe mostrar o "material con proceso" o "producto" no ambos, de hecho eso no es editable, porque se hace click en el producto/material(con proceso) que se desear editar, asi que no deberia preguntarlo. lo unico que deberia editarse es la cantidad de los materiales o si hay que agregar mas materiales o borrar (en la parte llamada "Materiales de la ficha")