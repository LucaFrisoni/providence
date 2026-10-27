# Módulo 6: Servicios externos e identificadores  

[← Volver al README principal](../../README.md)

  

- **Plugin:** `app/plugins/modulo6/` 

- **Rutas del servicio Rust:** `/api/modulo6`

- **Responsable:** Francisco Dómina
	
  

Integrar información de fuentes externas y gestionar identificadores externos (URI) asociados a registros, aprovechando y extendiendo `InformationService` de CollectiveAccess.

  

## Capacidades nativas de Providence

CollectiveAccess ya resuelve la consulta de fuentes externas, pero esta presenta sus limitaciones. Para empezar el servicio InformationService estaba limitado a solamente consultar una sola fuente elegible a través de sus servicios, esto a partir de la extensión propuesta cambia, ademas de poder consultar los registros que no contienen una URI asociada.

## Qué agrega el módulo

  
Se propone un nuevo servicio a CollectiveAccess (Asi como si fuese Wikipedia, VIAF, etc) llamado "MultiSource" el cual funciona únicamente con nuestro microservicio realizado en RUST, este se conecta mediante el archivo `` MultiSource.php``que se puede encontrar en ``app/lib/Plugins/InformationService``. Al ser un microservicio de extensión, este archivo tuvo que ser agregado en el núcleo de CollectiveAccess, sin embargo, se intentó respetar lo máximo posible lo pedido por la gente de FILO por lo que el unico archivo agregado es el ya mecionado, no hay ningún otro archivo modificado en este módulo.

La idea central de MultiSource es poder nutrirse de lo ya implementado en InformationService y poder extenderlo. MultiSource se encarga de consultar a múltiples fuentes elegidas por el usuario con una única búsqueda , estas pueden ser 1, 2, 3 o todas las disponibles. Para ser justos con la gente de FIlo se volvieron a crear los archivos que hacen las consultas en php pero con un mecanismo en Rust, es decir, se encuentran disponibles todas las fuentes ya existentes por defecto con InformationService. Además se propone el agregado de mas fuentes externas que se puedan llegar a necesitar, por ahora, la única agregada es "Wikidata".

Además se agregó una nueva pestaña dentro de gestion, la cual es "Registros sin URI " la cual se encarga de mostrar los registros (Objetos,Entidades) que tengan implementado un metadato de tipo MultiSource, que no tengan una URI asociada.  

## Qué no se modifica

No se toca ningún archivo del núcleo de CollectiveAccess, pero si se agrega un archivo dentro de él que se interconecta con InformationService.
  

## Integración con otros módulos

Se relaciona con la utilización de identificadores en mappings y perfiles. Los identificadores se guardan de manera nativa en CollectiveAccess.  

Módulos relacionados: [módulo 4](../modulo4/README.md), [módulo 5](../modulo5/README.md).

### Infraestructura

Las rutas dentro del código fuente que se modificaron fueron las siguientes:

##### Providence
- `` app/lib/Plugins/InformationService/MultiSource.php``: Este archivo se encarga, muy resumidamente, de realizar las consultas al microservicio en Rust, le hace una consulta http, recibe la respuesta y la muestra dentro de CA
- ``app/plugins/modulo6`` : Se encarga de toda la visualización de la pestaña "Registros sin URI" sirve principalmente para poder mostrar registros que no tienen asociada ninguna URI. RESTRICCION: Se muestran únicamente los registros sin URI que tienen por defecto el servicio MultiSource como meta dato.


#### Rust
- ``rust/extension-service/routes/modulo6.rs`` : Es donde el modulo se encarga de manejar los endpoints de una consulta http, los endpoints que maneja son ``/api/modulo6/...:
	- ``lookup``: Consulta a las fuentes pedidas y devuelve el resultado de las consultas en el formato que pide CollectiveAccess.
	- ``sources``: Se usa para que MultiSource pueda mostrar cuales son las fuentes que se pueden consultar (Que están implementadas en Rust).
	- ``details``: Se usa para consultar la información extendida del registro.
	- `` health``: Común para todos los módulos, muestra que la API funciona.
- `` rust/extension-service/types/modulo6/fuente.rs ``: Define la estructura de datos que se va a usar en el engranaje de la consulta de fuentes.
- ``rust/extension-service/services/modulo6/...`` :  Acá se encuentra todo el motor y engranaje principal de la extensión, hay múltiples archivos los cuales están hechos para poder hacer consultas o para poder leer devoluciones de distintos protocolos, por eso se encuentran archivos como ``sparql.rs`` o ``xml.rs``los cuales están hechos para parsear este tipo de consultas y transformarlas a Json. 
	- ``multifuente.rs``: Es el motor principal, es el que se encarga de realizar todos los hilos, lanzarlos y esperar sus respuestas. Se lanza un hilo por fuente y cada pedido corta a los `` M6_TIMEOUT_MS`` que se encuentra en el .env y en el dockercompose.yaml 
- ``rust/extension-service/services/modulo6/...``: Acá se encuentra la consulta en si a cada fuente, cada una con su respectiva implementación. Por cada archivo se encuentra una implementación del trait ``Fuente``. De esta manera se puedo estandarizar todo


### docker
Con respecto al docker se agregan variables de entorno, hay fuentes que necesitan obligartoriamente un codigo para poder entrar (Como el caso de collectiveaccess). Todo lo relacionado se puede encontrar en el ``.env.example`` y en ``dockercompose.yaml``

### pruebas
`` tests/extension/test-rust.sh unit modulo6 `` cada fuente prueba como arma la consulta y como lee la respuesta, sin conectarse a internet.

### Recordar para probar
Para poder probar todo lo hecho tienen que volver a buildear el proyecto con `` docker compose up -d --build `` además si quieren probar como funciona todo el tema tienen que crear un tipo de metadato que sea InformationService y dentro tienen que usar el servicio "MultiSource". Luego, tienen que restringir el metadato para objetos en la parte de abajo y guardar el tipo de metadato. Luego editarlo, al editarlo van a aparecer las opciones para poder elegir que fuentes consultar, para poder elegir muchas hay que apretar ctl+click en cada opción y guardar los cambios. Finalmente tienen que crear un objeto (o el que hayan elegido a la hora de restringir el metadato) y les va a aparecer el nombre que le dieron al metadato, agregan ese tipo y les va a dejar escribir. En ese momento van a poder escribir y se realiza la multiconsulta.



### Que es lo que falta:
- Poder agregar dentro del panel de gestion la pestaña "Registros sin URIs", no quise manosear el código de Luca, pero si lo ven necesario me comentan aca en el PR y manoteo sin drama
- Consultar a la gente de FILO las fuentes externas que les gustarian agregar: Esto se los voy a preguntar mañana. Agregar una fuente como ya se dice antes es agregar un archivo y poco más. Si prefieren esperar a que mañana me contesten y despues de agregarlo aceptar el PULL request hacemos eso, si no, ya el esqueleto y realmente todo el trabajo pesado está realizado. 