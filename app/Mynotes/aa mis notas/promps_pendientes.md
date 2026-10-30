# 29 oct
## Producto

hay que cambiar en el modulo de Producto, ese campo que se llama precio_estimado, debe llamarse costo_estimado, este valor se calcula de la ficha tecnica, ya que es ahi donde se especifican los materiales necesarios para hacerlo. Ademas, esta el "Costo del proceso" que se pregunta en la OP. Por tanto, un producto necesita tener al menos una OP que respalde su costo_estimado ya que no siempre es suficiente con una sola OP para hacer un producto. A veces, para calcular el costo_estimado de un producto se necesita:
1. registrar una o varias OP para facturar un material (con proceso)
2. registrar otra OP para producir el producto

el app entonces debe tener un campo en producto que especifique cuantas OP atras de él fueron necesarias para facturar dicho producto. Si un producto no esta en ninguna OP (cerrada o recibiendo) no hay manera de saber el costo_estimado



# kit proceso
en Completar kit de proceso, esta bien, pero me falto especificarte algunos detalles:
cuando se de click a un registro de kitproceso en el boton de editar, debe salir la lista entera de la misma OP.
Cantidad disponible es la cantidad del material en la tabla de material. el campo "falta" es la resta entre cantidad disponible - cantidad preparada
kit listo no se pregunta al cliente, se asumen cuando ya que tiene todas las cantidades preparadas de cada registro del kitproceso. 

