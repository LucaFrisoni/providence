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
	- ``multifuente.rs``: Es el motor principal, es el que se encarga de realizar todos los hilos, lanzarlos y esperar sus respuestas. 
- ``rust/extension-service/services/modulo6/...``: Acá se encuentra la consulta en si a cada fuente, cada una con su respectiva implementación. Por cada archivo se encuentra una implementación del trait ``Fuente``. De esta manera se puedo estandarizar todo