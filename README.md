LibreDTE: Consola para la Biblioteca PHP (Core)
================================================

Expone cada operación de `libredte/libredte-lib-core` (a través de
`libredte/libredte-lib-core-dispatcher`) como su propio comando de
[Symfony Console](https://symfony.com/doc/current/components/console.html),
usando [`derafu/backbone-console`](https://github.com/derafu/backbone-console)
como base. Pensado para invocarse como proceso aparte (`bin/console`) desde
cualquier lenguaje, o a mano.

Con Docker (recomendado)
-------------------------

La imagen no sirve nada por HTTP ni tiene ningún puerto que mapear: el
proceso principal solo mantiene el contenedor vivo para poder ejecutarle
comandos puntuales con `docker exec`.

### `docker run`, con la imagen ya publicada

```bash
docker run -d --name libredte-lib-core-console --restart unless-stopped \
  ghcr.io/libredte/libredte-lib-core-console:latest
```

```bash
docker exec libredte-lib-core-console bin/console list
docker exec libredte-lib-core-console bin/console help billing:identifier:caf_loader:load

echo '{"parameters": {"xml": "<caf>...</caf>"}}' \
  | docker exec -i libredte-lib-core-console bin/console billing:identifier:caf_loader:load
```

`-i` en el `exec` es necesario cuando la entrada va por STDIN (como en el
`echo` de arriba); para un archivo como argumento no hace falta:

```bash
docker cp archivo.json libredte-lib-core-console:/tmp/archivo.json
docker exec libredte-lib-core-console bin/console billing:identifier:caf_loader:load /tmp/archivo.json
```

Para ver los logs o detenerlo:

```bash
docker logs -f libredte-lib-core-console
docker stop libredte-lib-core-console
```

### Uso único, sin dejar nada corriendo

Sobreescribiendo el `CMD` de la imagen se evita levantar un contenedor
persistente cuando solo se necesita ejecutar un comando una vez:

```bash
echo '{"parameters": {"xml": "<caf>...</caf>"}}' \
  | docker run --rm -i ghcr.io/libredte/libredte-lib-core-console:latest \
      bin/console billing:identifier:caf_loader:load
```

### `docker compose`

```bash
git clone git@github.com:LibreDTE/libredte-lib-core-console.git
cd libredte-lib-core-console
docker compose up -d

docker compose exec libredte-lib-core-console bin/console list
docker compose down
```

### Construir la imagen localmente

```bash
docker build -t libredte-lib-core-console .
```

Sin Docker (desarrollo)
------------------------

```bash
composer install

# Listar todas las operaciones disponibles como comandos.
bin/console list

# Ver los parámetros reales (reflejados) de una operación, y sus exit codes.
bin/console help billing:identifier:caf_loader:load

# Ejecutar una operación. La entrada es un archivo JSON/YAML/XML (parámetros
# bajo la clave "parameters"), como argumento o por stdin.
echo '{"parameters": {"xml": "<caf>...</caf>"}}' \
    | bin/console billing:identifier:caf_loader:load

bin/console billing:identifier:caf_loader:load archivo.json
```

La salida respeta el formato de la entrada (JSON/YAML/XML) y, en caso de
error, escribe el `ProblemDetail` (RFC 7807-like) a STDERR con el código de
salida que le corresponda — nunca lanza excepciones ni corta el proceso de
forma abrupta.

### Forzar el destino y el formato de la salida: `--output`/`--error-output`

```bash
# La extensión decide el formato de la respuesta (independiente del de la
# entrada); una extensión no reconocida cae al formato de la entrada.
bin/console billing:identifier:caf_loader:load archivo.json --output=resultado.yaml

# Igual, pero solo para la rama de falla (nunca se usa si la operación tuvo éxito).
bin/console billing:identifier:caf_loader:load archivo.json --error-output=problema.xml
```

Sin estas opciones, la redirección de shell (`>`/`2>`) ya funciona, pero
mezcla el payload estructurado con cualquier otra cosa que el proceso
escriba. `--output`/`--error-output` garantizan que solo el payload
termine en el archivo. Una escritura fallida (directorio inexistente,
permisos) nunca se reporta como éxito silencioso: corta con exit code `73`
y el motivo real por STDERR.

### Ver tiempos/memoria/CPU: `-v`

```bash
bin/console -v billing:identifier:caf_loader:load archivo.json
```

La respuesta exitosa siempre viene envuelta en `{"meta": {"timestamp": ...,
"data_type": ...}, "data": ...}` (mismo formato que
[Backbone Console](https://www.derafu.dev/docs/core/backbone-console) usa
para cualquier proyecto Backbone) y una falla es el `ProblemDetail` tal
cual, con `extensions.timestamp`/`extensions.data_type` incluidos. Sin
`-v`/`--verbose` eso es todo lo que trae `meta`/`extensions`; con `-v` se
agrega el resto de la metadata de ejecución ahí mismo (nunca una clave
nueva): `startedAt`/`finishedAt`/`realTime`/`userTime`/`systemTime`/
`memoryUsed`/`peakMemory`/`pid`/`loadAverage1Min`/`5Min`/`15Min`.

### Exit codes

`bin/console help <comando>` siempre lista los códigos reales de ese
comando. Los fijos (no dependen de la operación):

| Código | Significado |
| --- | --- |
| `0` | Éxito. |
| `1` | La operación falló, sin nada más específico. |
| `10`–`16` | Una de las 7 excepciones genéricas de `derafu/backbone-dispatcher` (`OperationNotFound`, `OperationNotAllowed`, `MissingParameter`, `InvalidParameterType`, `ClassNotFound`, `FromArrayMethodNotFound`, `NoDeserializerFound`) — ver `DefaultExitCodeResolver`. |
| `65` (`EX_DATAERR`) | La entrada no se pudo parsear como JSON/YAML/XML. |
| `66` (`EX_NOINPUT`) | El archivo de entrada no existe o no es legible. |
| `70` (`EX_SOFTWARE`) | Error interno inesperado (bug, no error de uso ni de negocio). |
| `73` (`EX_CANTCREAT`) | No se pudo crear el archivo de `--output`/`--error-output`. |

Desarrollo
----------

```bash
composer install
composer tests    # PHPUnit con cobertura
composer phpstan  # Nivel 5
composer phpcs    # php-cs-fixer (dry-run)
```

Términos y condiciones de uso
------------------------------

Licenciado bajo AGPL-3.0+. Disponibles en archivo [COPYING](COPYING).
