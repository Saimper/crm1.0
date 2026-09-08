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
     | En estricto, una consulta sobre un modelo con scope y sin contexto lanza
     | ConsultaSinContextoDeTenant en vez de devolverlo todo. Es lo correcto,
     | pero encenderlo hoy rompería la aplicación entera: 5 comandos, 4 jobs, 9
     | listeners, 4 tareas del scheduler y todas las pantallas /admin corren sin
     | contexto de proyecto. Cada uno tiene que declarar el suyo (o pedir
     | explícitamente sinScopeProyecto) antes de que esto pueda ponerse en true.
     |
     | Se enciende cuando la Fase 3 haya terminado. Mientras tanto:
     |   - en pruebas se enciende por caso, para probar el mecanismo;
     |   - `avisar_sin_contexto` permite medir el alcance real en un entorno de
     |     preproducción antes de dar el paso, sin romper nada.
     */
    'scope_estricto' => (bool) env('TENANCY_SCOPE_ESTRICTO', false),

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
