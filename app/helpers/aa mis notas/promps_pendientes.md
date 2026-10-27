

# OP
## form cambiar estado OP ((recibiendo))
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

# dashboard
el dashboard, debe tener una zona para graficos, otra para resultados del mes y por ultimo una zona para las notificaciones rojas. por el momento busca una buena extension de graficos, muy llamativa, instalala e inventa datos para que se vean un par de graficos.
las metas deben ser 5, inventalos
y la zona de las notificaciones deben decir exactamente lo mismo que las notificaciones rojas


# produc (creo que ya se corrio)
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

