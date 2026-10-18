
# 15 oct 2026 
## escrito 4ppm 15 oct
en create.vue de ordenproduccion, en caso que no se cumplan los requisitos de app\Http\Requests\StoreOrdenProduccionRequest.php debe mostrar el error. En este momento app\Http\Requests\StoreOrdenProduccionRequest.php esta vacio, pero rellenalo con la siguiente info: 
numero op debe ser "OP-XXX" donde XXX es un numero
debe existir almenos un producto
cantidad planificada debe ser mayor a cero
la cantidad planificada es por cada producto, quiere decir que resources\js\pages\OrdenProduccion\DistribucionTallas.vue necesita estar integrado en la seleecion de productos, cambia el diseño, porque el diseño actual quedaria muy forzado. Al seleccionar producto tambien se seleciona las tallas
fecha creacion debe ser al menos de un año atras (es decir como minimo 15 oct de 2025)
fecha prevista es nullable
el taller, responsable son obligatorios
id shopify y observaciones son opcionales.
quiere decir que solo puedes que modificar 5 archivos, el create.vue, el distribuciontallas, si necesitas crear 1 archivo(opcional), el request y controlador de php.

### ejecutado
