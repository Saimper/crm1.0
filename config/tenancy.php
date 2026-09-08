<?php

declare(strict_types=1);

return [

    /*
     | Modo estricto de los global scopes de tenant.
     |
     | Los scopes de PerteneceAProyecto y PerteneceAMandante nacieron fallando
     | ABIERTO: si no hay contexto en el contenedor, no filtran nada. El camino
     | de error era, por tanto, el camino sin aislamiento — bastaba con que la
     | resolución del proyecto fallara para acabar consultando sobre todos los
     | clientes.
     |
     | ENCENDIDO desde la Fase 3. Una consulta sobre un modelo con scope y sin
     | contexto lanza ConsultaSinContextoDeTenant en vez de devolver las filas
     | de todos los clientes. Antes fallaba abierto, que es como decir que el
     | aislamiento no era una propiedad del modelo sino de haber pasado por un
     | middleware HTTP concreto.
     |
     | Se pudo encender porque la superficie que corre sin contexto ya declara
     | lo suyo: los CATORCE comandos de consola (dos más de los que decía este
     | inventario: `importaciones:purgar-payloads` y
     | `importaciones:purgar-subidas-temporales`), los 4 jobs y los 13 listeners
     | consultan con `DB::table` —que el scope nunca ha tocado— o escriben
     | `sinScopeProyecto()` a mano. Las pantallas /admin, igual.
     |
     | Lo que este interruptor NO hace, y conviene no confundirlo: cierra las
     | LECTURAS. Las escrituras las cierra el guardia de `PerteneceAProyecto`,
     | que es otra cosa y vive en otro sitio — con un proyecto activo, ninguna
     | escritura puede caer en otro; sin contexto, la plataforma escribe donde
     | necesita.
     |
     | Apagarlo con TENANCY_SCOPE_ESTRICTO=false devuelve el fallo abierto. Es
     | una salida de emergencia, no una opción de configuración: si hace falta
     | usarla, lo que hay debajo es un sitio que consulta sin declarar contexto
     | y el log del guardia dice cuál.
     */
    'scope_estricto' => (bool) env('TENANCY_SCOPE_ESTRICTO', true),

    /*
     | Deja constancia en el log de cada consulta que corre sin contexto de
     | tenant, con el modelo y el punto del código que la lanzó. Sirve para
     | inventariar de verdad —no por grep— cuánto código depende hoy del fallo
     | abierto. Ruidoso a propósito: enciéndelo un rato, no de continuo.
     */
    'avisar_sin_contexto' => (bool) env('TENANCY_AVISAR_SIN_CONTEXTO', false),

    /*
     | Valores de la plataforma cuando un mandante no declara los suyos, y
     | cuando no hay mandante activo (comandos, jobs, pantallas cross-cliente).
     |
     | UTC y USD reproducen lo que el sistema hacía antes de que la
     | configuración regional existiera, así que estrenarla no mueve ningún
     | número: sólo lo mueve el cliente que ajusta el suyo.
     |
     | OJO: esto NO es `app.timezone`, que sigue y debe seguir en UTC. Lo que
     | hay guardado son instantes UTC; cambiar el huso de la aplicación haría
     | que Eloquent los reinterpretara como hora local al hidratarlos.
     */
    'zona_horaria_por_defecto' => env('TENANCY_ZONA_HORARIA', 'UTC'),
    'moneda_por_defecto' => env('TENANCY_MONEDA', 'USD'),

];
