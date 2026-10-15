
# 15 oct 2026 

en create.vue de ordenproduccion, en caso que no se cumplan los requisitos de app\Http\Requests\StoreOrdenProduccionRequest.php debe mostrar el error. En este momento app\Http\Requests\StoreOrdenProduccionRequest.php esta vacio, pero rellenalo con la siguiente info: 
numero op debe ser "OP-XXX" donde XXX es un numero
debe existir almenos un producto
cantidad planificada debe ser mayor a cero
¿¿¿¿ la cantidad planificada es por cada producto???
fecha creacion debe ser al menos de un año atras (es decir como minimo 15 oct de 2025)
las tallas deben ser almenos 1
fecha prevista es nullable
el taller, responsable son obligatorios
id shopify y observaciones son opcionales.
quiere decir que solo puedes que modificar 3 archivos, el create.vue, el request y controlador de php.