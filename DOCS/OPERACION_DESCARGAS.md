# Operación: descargas largas

Qué hay que subir en el servidor para que una exportación grande llegue entera,
por qué el código de la aplicación no puede hacerlo solo, y cómo saber que se
cortó.

Nada de esto vive en el repositorio. Los dos relojes que cortan una descarga
—nginx y PHP-FPM— están puestos a mano en el VPS, el despliegue no los toca
(`.github/workflows/deploy.yml:306` recarga `php82-fpm` y nunca `nginx`) y no hay
ningún fichero versionado del que copiarlos. Esta nota es, por ahora, el único
sitio donde constan.

---

## 1. Qué falla, y por qué el usuario no se entera

Una exportación responde `200 OK` con sus cabeceras **antes** de producir la
primera fila: `RespuestaCsv::desdeConsulta` construye el `StreamedResponse` con
status 200 y sólo entonces empieza a leer de la base
(`app/Support/Csv/RespuestaCsv.php:118-122`). Desde ese momento no queda ningún
mecanismo para convertir la respuesta en un error: la cabecera ya salió, el
navegador ya abrió el fichero en Descargas.

Si un timeout del servidor mata el proceso a mitad, lo que queda en el disco del
supervisor es **un CSV perfectamente válido con menos filas**. Se abre en Excel,
los acentos están bien, la última fila está entera o casi. Nadie distingue 40.000
filas de 62.000 mirándolas. Y quien pidió el fichero para conciliar la cobranza
del mes no tiene forma de saber que le falta el último tercio.

Con XLSX es distinto y en cierto modo mejor: OpenSpout escribe el directorio
central del ZIP al cerrar el escritor, así que un corte deja un fichero que
Excel no abre. El usuario se entera — pero por un error de Excel, no por nada
que le diga el CRM. Lo que sí le dice el CRM está en la §8: la huella de esa
descarga queda marcada como incompleta.

## 2. Por qué `set_time_limit(0)` no bastaba

`RespuestaCsv` hace lo que puede desde dentro del proceso, y lo hace bien:

| Qué hace | Dónde | Qué protege |
|---|---|---|
| `set_time_limit(0)` | `RespuestaCsv.php:77` | El `max_execution_time` de `php.ini` |
| `ignore_user_abort(true)` | `RespuestaCsv.php:80` | Que PHP no muera al cerrar el cliente, y llegue a dejar huella |
| `ob_end_flush()` en bucle | `RespuestaCsv.php:181` | El buffer de salida de PHP |
| `flush()` por lote de 500 | `RespuestaCsv.php:191`, `:72` | Que nginx reciba bytes y no dé al upstream por muerto |
| `X-Accel-Buffering: no` | `RespuestaCsv.php:121` | El buffering de nginx, por respuesta |
| `chunkById` | `RespuestaCsv.php:95` | La memoria: cada lote es una consulta con `id >` y `LIMIT` |

Lo que queda fuera de su alcance, y es exactamente lo que corta:

- **`fastcgi_read_timeout` de nginx.** Vive en el proxy, no en el proceso. Ningún
  ajuste de PHP lo mueve.
- **`send_timeout` de nginx.** Igual, y del lado del cliente.
- **`request_terminate_timeout` de PHP-FPM.** Lo aplica el proceso *maestro* de
  FPM, no el hijo. `set_time_limit()` no lo toca; ése es literalmente su motivo
  de existir en la documentación de FPM: matar lo que `max_execution_time` no
  mató.
- **El propio `max_execution_time`, en el caso que más duele.** En Unix no cuenta
  el tiempo pasado fuera del script: una consulta de MySQL que tarde cuatro
  minutos no consume ni un segundo del límite. Es decir, en el escenario típico
  de una exportación lenta —la base tarda, no el PHP— `max_execution_time` nunca
  habría saltado y `set_time_limit(0)` no cambiaba nada. Los que sí saltan son
  los tres de arriba, que miden reloj de pared.

Tres descargas no tienen ni siquiera lo que hace `RespuestaCsv`, porque no pasan
por ella: `StreamerReporteCsv`, `StreamerReporteXlsx` y
`DescargarPlantillaImportacionController` no llaman a `set_time_limit(0)` ni a
`flush()`. Para ellas el `max_execution_time` de `php.ini` sigue gobernando de
verdad. Están apuntadas para arreglar; hasta entonces, el valor de `php.ini` de
la §4 es lo único que las cubre.

## 3. Los tres relojes, y cuál tiene que rendirse primero

El orden entre ellos no es cosmético: decide si el incidente se puede investigar.

- Si **nginx** se rinde primero, cierra el socket. PHP lo nota en el siguiente
  `flush()` vía `connection_aborted()` (`RespuestaCsv.php:107` y `:110`), corta
  el bucle y **alcanza a escribir la huella** con `completa = false`.
- Si **FPM** se rinde primero, mata al worker desde el proceso maestro. El bloque
  `finally` que escribe la huella (`RespuestaCsv.php:111-116`) no llega a
  ejecutarse. La descarga cortada no deja **ningún** rastro: ni completo ni
  incompleto. Desde la auditoría, es como si nadie hubiera pedido nada.

Por eso la regla es fija: **`request_terminate_timeout` siempre estrictamente por
encima de `fastcgi_read_timeout`.** Que abandone nginx, que es el único de los
dos que deja hablar a PHP antes de colgar.

## 4. Qué subir y dónde

Los ficheros exactos hay que confirmarlos en el VPS —no están versionados, así
que no los puedo citar—; abajo van los comandos que los encuentran.

### nginx

Encontrar el fichero efectivo:

```sh
nginx -T | grep -n -E 'server_name|fastcgi_read_timeout|send_timeout|# configuration file'
```

Suele ser `/etc/nginx/sites-available/<el del CRM>` o algo bajo
`/etc/nginx/conf.d/`. En el bloque que habla FastCGI:

```nginx
location ~ \.php$ {
    # ... include fastcgi_params, fastcgi_pass: dejar lo que ya esté ...
    fastcgi_read_timeout 600s;   # por defecto 60s  ← el que corta
    fastcgi_send_timeout 600s;   # por defecto 60s
}
```

Y en el `server`:

```nginx
send_timeout 600s;               # por defecto 60s
```

`fastcgi_read_timeout` no mide la descarga entera: mide **el hueco entre dos
lecturas sucesivas** desde FPM. Como `RespuestaCsv` hace `flush()` cada 500
filas, en teoría bastaría con cubrir el lote más lento. Se dimensiona contra el
total igualmente, porque las tres descargas de la §2 que no llaman a `flush()`
no dan esa garantía y porque un `JOIN` contra
`valores_campo_personalizado` de una cartera grande puede tardar más que todo el
resto junto.

Se aplica sobre `location ~ \.php$`, es decir sobre todo el sitio, y está bien
que así sea: un `fastcgi_read_timeout` alto no ralentiza una página normal, sólo
cambia cuánto se espera cuando algo ya va lento. Acotarlo a las rutas de descarga
obligaría a duplicar el bloque `fastcgi_pass` —el front controller reescribe todo
a `/index.php` antes de que nginx vuelva a resolver `location`— y un bloque
duplicado se desincroniza.

**El despliegue no recarga nginx.** Tras editar:

```sh
nginx -t && systemctl reload nginx
```

### PHP-FPM (pool)

Encontrar el fichero:

```sh
systemctl cat php82-fpm
grep -Rn 'request_terminate_timeout\|pm.max_children\|^pm =' /opt/php82/etc/
```

Suele ser `/opt/php82/etc/php-fpm.d/www.conf`:

```ini
request_terminate_timeout = 660   ; por defecto 0 (desactivado); muchos paquetes lo ponen en 300
```

660 = los 600 de nginx más un minuto. La holgura es el punto: garantiza que quien
se rinda primero sea nginx (§3). Si en el VPS está en `0`, dejarlo en `0` también
es correcto y hasta mejor para este problema —FPM nunca mata— a cambio de que un
worker atascado lo esté para siempre. Lo que **no** es aceptable es un valor por
debajo del de nginx: es el caso que borra la huella.

`pm.max_children` no lleva número recomendado aquí, pero hay que mirarlo: una
descarga larga secuestra un worker durante toda su vida. Subir los timeouts sin
revisarlo cambia el modo de fallo, de CSVs truncados a un CRM que deja de
responder cuando cuatro supervisores exportan a la vez. La regla está en la §6.

### php.ini (el del SAPI de FPM, que puede no ser el del CLI)

```sh
/opt/php82/bin/php --ini                      # el del CLI, informativo
grep -Rn 'max_execution_time\|output_buffering\|zlib.output_compression' /opt/php82/etc/
```

```ini
max_execution_time = 600        ; cubre los 3 exportadores que no llaman a set_time_limit(0)
zlib.output_compression = Off   ; si está On, PHP acumula para comprimir y flush() no sirve de nada
output_buffering = 4096         ; dejarlo así; RespuestaCsv lo cierra, los otros dependen de que sea pequeño
```

`zlib.output_compression = On` es el que más despista: la descarga no falla,
simplemente no sale ni un byte hasta el final, y entonces cualquiera de los dos
timeouts la mata. Tiene que estar `Off`.

## 5. Por qué el buffering se queda desactivado

Con `fastcgi_buffering` activo (que es lo de fábrica) nginx acumula la respuesta
—en memoria y, cuando no cabe, en `fastcgi_temp`— y sólo empieza a mandarla
cuando los búferes se llenan o el upstream termina. Tres consecuencias, la
tercera es la grave:

1. **El usuario no ve nada durante minutos**, así que vuelve a hacer clic. Ahora
   hay dos exportaciones corriendo, dos workers ocupados y dos eventos en la
   auditoría por una sola intención.
2. **Se tira a la basura la memoria que costó ganar.** `chunkById` mantiene el
   consumo en el tamaño del lote (`RespuestaCsv.php:95`); si después nginx
   escribe el fichero entero en `fastcgi_temp` antes de mandarlo, el servidor
   vuelve a manipular el resultado completo de una pieza, sólo que en disco.
3. **PHP deja de poder notar que el cliente se fue.** `connection_aborted()` sólo
   se vuelve cierto cuando falla una escritura. Si nginx se está tragando todo en
   un búfer, la escritura nunca falla: PHP sigue produciendo filas contento para
   un navegador que cerró la pestaña hace diez minutos, y el caso «descarga
   cancelada» jamás se registra como `completa=false`. El buffering no sólo
   retrasa la descarga: **desactiva la señal de la §7.**

Se desactiva con la cabecera `X-Accel-Buffering: no`, no con una directiva del
servidor. Es la granularidad correcta —afecta a la respuesta que la necesita y
deja bufferizadas las páginas normales, que sí se benefician— y ya la mandan las
cuatro salidas: `RespuestaCsv.php:121`, `StreamerReporteCsv.php:57`,
`StreamerReporteXlsx.php:55`, `DescargarPlantillaImportacionController.php:56`.

Son cuatro literales sueltos y nada obliga a que un exportador nuevo los copie.
Si se cae, no falla nada: la descarga sigue saliendo, sólo que bufferizada, y el
síntoma aparece meses después como «a veces se corta». Por eso lleva test propio
en vez de una directiva de nginx.

Sobre `gzip`: los paquetes de Debian y Ubuntu traen `gzip on` en `nginx.conf`. No
hace falta apagarlo por decreto —un CSV comprime cerca de 10 a 1, y ese ahorro es
real para un supervisor con conexión mala—, pero reintroduce un búfer entre PHP y
el socket. Si la comprobación de TTFB de la §7 sale mal, ése es el primer
sospechoso.

## 6. El número no lo puede saber esta nota

Los 600 segundos son un punto de partida razonado, no un valor calculado: depende
del tamaño real de la cartera del cliente, que no está en el repositorio. La
regla para calcularlo:

1. **Encontrar la peor descarga real.** La candidata es `/admin/auditoria/exportar`,
   que cruza todos los proyectos del mandante. Para el resto:
   ```sql
   SELECT proyecto_id, COUNT(*) FROM personas    GROUP BY proyecto_id ORDER BY 2 DESC LIMIT 5;
   SELECT proyecto_id, COUNT(*) FROM casos       WHERE eliminada_en IS NULL GROUP BY proyecto_id ORDER BY 2 DESC LIMIT 5;
   SELECT proyecto_id, COUNT(*) FROM compromisos GROUP BY proyecto_id ORDER BY 2 DESC LIMIT 5;
   SELECT proyecto_id, COUNT(*) FROM auditorias  GROUP BY proyecto_id ORDER BY 2 DESC LIMIT 5;
   ```
   Gestiones no hace falta mirarla igual: su exportación va acotada a 92 días por
   `VentanaDeExportacion::MAXIMO_DIAS`
   (`app/Modules/Gestiones/Domain/ValueObjects/VentanaDeExportacion.php:21`).

2. **Cronometrarla contra el VPS**, con una cookie de sesión de un usuario que
   tenga el permiso:
   ```sh
   curl -sS -b "$COOKIE" -o /tmp/peor.csv \
     -w 'ttfb=%{time_starttransfer} total=%{time_total} bytes=%{size_download}\n' \
     'https://crm.viciconnect.net/proyectos/<id>/casos/exportar?<filtros>'
   ```

3. **`fastcgi_read_timeout` = `send_timeout` = `max(300, 3 × total medido)`**,
   redondeado hacia arriba. El factor 3 es el margen para el día que la base esté
   cargada, no un lujo.

4. **`request_terminate_timeout` = ese valor + 60.** Nunca por debajo (§3).

5. **`pm.max_children`** tiene que quedar por encima de las descargas
   concurrentes de pico más el tráfico normal. No doy número: depende de la RAM
   del VPS y del consumo real de un worker, que se mide con `ps` sobre el pool en
   hora punta.

El techo **no es permanente**. Cuando entre una cartera grande hay que volver a
medir: caduca porque el cliente crece, no porque el código cambie.

## 7. Cómo comprobar que quedó bien

**Que la configuración está donde se cree:**

```sh
nginx -T | grep -n -E 'fastcgi_read_timeout|fastcgi_send_timeout|send_timeout|gzip'
grep -Rn 'request_terminate_timeout|pm.max_children' /opt/php82/etc/
```

**Que se está transmitiendo de verdad y no bufferizando.** Ésta es la buena:

```sh
curl -sS -b "$COOKIE" -o /tmp/x.csv \
  -w 'ttfb=%{time_starttransfer}  total=%{time_total}  bytes=%{size_download}\n' \
  'https://crm.viciconnect.net/proyectos/<id>/casos/exportar'
```

Si `ttfb` es casi igual a `total`, la respuesta llegó de golpe: **hay un búfer en
algún sitio** y la §5 no se está cumpliendo. Con streaming, `ttfb` debe ser una
fracción pequeña del total.

Ver la cabecera con `curl -D-` sólo prueba que la aplicación la mandó, no que
nginx la obedeció. La prueba de que la obedeció es el TTFB.

**Que no se cortó:** `curl` termina con código 18 (`transfer closed with
outstanding read data remaining`) cuando la conexión se corta con datos
pendientes. Es el detector fiable del lado del cliente.

**No usar `wc -l` como comprobación de filas.** `fputcsv` entrecomilla los saltos
de línea, y las notas de gestión los llevan: el conteo de líneas es una cota
inferior del número de filas, no el número. Para contrastar, la auditoría (§8).

**Si hay algo delante de nginx** —un proxy, un CDN— tiene su propio reloj y nada
de esto lo cambia. Un Cloudflare gratuito corta a los 100 segundos. Mirar las
cabeceras de respuesta en busca de un intermediario antes de dar los números por
buenos.

## 8. Qué señal deja en la auditoría una descarga cortada

La ola 04 añadió un cuarto evento a `auditorias`: `exportado`, junto a `creado`,
`actualizado` y `eliminado`
(`database/migrations/2026_09_09_120100_auditoria_auditorias_evento_exportado.php:21`).
Sacar el padrón completo de un cliente es la acción con más peso en privacidad de
la aplicación y era la única sin huella en el sitio donde ese cliente comprueba
quién tocó sus datos.

Cada descarga escribe una fila con `entidad_id = 0` —no hay UNA entidad
exportada, es la tabla entera bajo un recorte— y tres claves en `cambios`:
`filtros`, `total_filas` y **`completa`**
(`RegistroDeExportacionesEloquent.php:41-45`).

`completa` sale de `! connection_aborted()` y se escribe desde un `finally`, así
que se escribe **también cuando la descarga se rompe**
(`RespuestaCsv.php:107-116`). Un supervisor que cancela al 90 % ya se llevó el
90 % de las filas, y eso tiene que constar igual.

**Dónde se ve:** `/proyectos/{id}/auditoria`, filtro de evento → `exportado`
(insignia ámbar, `listado-auditoria.blade.php:43` y `:123`). El detalle pinta
`cambios` como tabla campo × antes × después, así que `completa | — | false`
aparece ahí sin nada especial.

**Consulta directa:**

```sql
SELECT a.creada_en,
       u.name                               AS quien,
       a.entidad_tipo                       AS que,
       a.cambios->>'$.total_filas.despues'  AS filas,
       a.cambios->>'$.completa.despues'     AS completa,
       a.cambios->>'$.filtros.despues'      AS filtros,
       a.ip
FROM auditorias a
LEFT JOIN users u ON u.id = a.usuario_id
WHERE a.evento = 'exportado'
ORDER BY a.id DESC
LIMIT 30;
```

Un `completa = false` es una descarga que se rompió: o el usuario canceló, o un
timeout la cortó. `total_filas` dice cuántas alcanzaron a salir.

**Dos límites que hay que tener presentes, o la señal engaña:**

- **Sin huella no significa sin descarga.** Si el corte vino de
  `request_terminate_timeout`, el `finally` no se ejecutó y no hay fila ninguna
  (§3). Una descarga que el usuario dice haber pedido y no aparece en la
  auditoría es sospechosa de exactamente eso.
- **Una ruta no registra nada, y está bien.** La plantilla de importación es un
  fichero de cabeceras vacías: no lleva datos de nadie. Todas las demás dejan
  huella, incluidos los reportes custom, que además escriben su propia métrica
  en `reportes_ejecuciones` — son dos rastros distintos y los dos hacen falta:
  uno es del módulo y el otro es del cliente.

## 9. Inventario: qué produce respuestas largas

| Ruta | Quién la sirve | Vía `RespuestaCsv` | Huella `exportado` |
|---|---|:--:|:--:|
| `GET /proyectos/{id}/personas/exportar` | `ExportarPersonasController` → `ExportadorCsvPersonas` | sí | sí |
| `GET /proyectos/{id}/casos/exportar` | `ExportarCasosController` → `ExportadorCsvCasos` | sí | sí |
| `GET /proyectos/{id}/gestiones/exportar` | `ExportarGestionesController` → `ExportadorCsvGestiones` | sí | sí |
| `GET /proyectos/{id}/compromisos/exportar` | `ExportarCompromisosController` → `ExportadorCsvCompromisos` | sí | sí |
| `GET /proyectos/{id}/auditoria/exportar` | `ExportarAuditoriaController` → `ExportadorCsvAuditoria` | sí | sí |
| `GET /admin/auditoria/exportar` | `ExportarAuditoriaMandanteController` | sí | sí |
| `GET /proyectos/{id}/importaciones/{ulid}/rechazadas` | `DescargarFilasRechazadasController` | sí | sí |
| `GET /proyectos/{id}/reportes/custom/{def}/exportar?formato=csv` | `StreamerReporteCsv` | no (ver abajo) | sí |
| `GET /proyectos/{id}/reportes/custom/{def}/exportar?formato=xlsx` | `StreamerReporteXlsx` | no (ver abajo) | sí |
| `GET /proyectos/{id}/importaciones/plantilla` | `DescargarPlantillaImportacionController` | **no** | no (correcto: no lleva datos del cliente) |

Las dos con más peligro:

- **`/admin/auditoria/exportar`** cruza todos los proyectos del mandante. Es la
  más pesada de todas y la que primero va a chocar contra cualquier techo.
- **Reportes custom** es la única superficie **sin tope de filas**. Las gestiones
  van acotadas a 92 días por diseño; el DSL de F32 no tiene equivalente, porque
  el usuario elige las columnas y el orden. No pasa por `RespuestaCsv` —esa
  pagina por clave y aquí el orden lo elige quien define el reporte— pero sí
  hace lo demás: `set_time_limit(0)`, `flush()` cada 500 filas, huella con
  `completa`, y la consulta sale por la conexión `mysql_streaming`, que tiene el
  buffer de PDO apagado para no traerse el resultado entero a memoria antes de
  la primera fila. Si algún día alguien apunta `DB_CONEXION_SIN_BUFFER` a
  `mysql`, esa garantía desaparece sin avisar.

## 10. Lo que caduca solo, y hay que dejar que caduque

Tres tareas del planificador borran datos del cliente que ya no hacen falta. No
son opcionales: si el `schedule:run` del VPS no está corriendo, no fallan, no
avisan, y los datos se quedan.

| Tarea | Cuándo | Qué borra |
|---|---|---|
| `importaciones:purgar-obsoletas --dias=7` | 03:30 | Importaciones subidas y nunca lanzadas |
| `importaciones:purgar-payloads` | 03:45 | El contenido del archivo (`payload`) de las importaciones terminadas hace más de 30 días |
| `importaciones:purgar-subidas-temporales` | 03:50 | El fichero que la subida deja en `livewire-tmp/` |

La retención de la segunda se cambia con `IMPORTS_RETENCION_PAYLOAD_DIAS`, y
tiene `--dry-run` para ver qué haría antes de dejarla suelta. Después de que
corra, la descarga de filas rechazadas de esas importaciones responde 410 y la
pantalla deja de ofrecerla: es lo correcto, porque el fichero saldría en blanco.

Comprobar que el planificador está vivo:

```sh
crontab -l | grep schedule:run
cd /var/www/crm && /opt/php82/bin/php artisan schedule:list
```

Y una pendiente que no es del planificador: `importaciones:reparar-encoding`
—que deshace la doble codificación de lo que se cargó antes de que el lector la
corrigiera— no la ha lanzado nadie en producción. Es manual a propósito (toca
nombres de personas), acepta `--dry-run`, y hay que correrla una vez por
proyecto afectado.

## 11. Lo que esta nota no arregla

Sigue siendo una nota. Los valores continúan puestos a mano en el VPS, el
despliegue no los aplica, y el healthcheck de `deploy.yml:314` pide `/login`, que
responde en milisegundos y jamás se acercará a un timeout. Un rebuild del
servidor, o una actualización del paquete de nginx que reescriba el vhost,
devuelve los 60 segundos de fábrica y el CI seguirá dando verde.

Lo que cerraría el agujero de verdad es versionar el vhost y el pool bajo
`deploy/` y copiarlos desde el script de despliegue. Eso cambia el contrato del
deploy —hoy no toca nginx en absoluto— y es una decisión de infraestructura, no
una nota.
