LibreDTE: Consola para la Biblioteca PHP (Core)
================================================

Expone cada operación de `libredte/libredte-lib-core` (a través de
`libredte/libredte-lib-core-dispatcher`) como su propio comando de
[Symfony Console](https://symfony.com/doc/current/components/console.html),
usando [`derafu/backbone-console`](https://github.com/derafu/backbone-console)
como base. Pensado para invocarse como proceso aparte (`bin/console`) desde
cualquier lenguaje, o a mano.

Uso
---

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

Sin `-v`/`--verbose` la respuesta es exactamente `{"data": ...}` (éxito) o
el `ProblemDetail` tal cual (falla) — igual que siempre. Con `-v` se agrega
`metadata` (éxito) o `extensions.metadata` (falla): `startedAt`/
`finishedAt`/`realTime`/`userTime`/`systemTime`/`memoryUsed`/`peakMemory`/
`pid`/`loadAverage1Min`/`5Min`/`15Min`.

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

Arquitectura
------------

- `libredte\lib\CoreConsole\ConsoleApplication` extiende directamente
  `libredte\lib\Core\Application` —igual que hace
  `libredte\lib\Pro\ConsoleApplication` para su propia consola de
  desarrollo— y nunca pasa por `Application::getInstance()`: esa clase
  construye la instancia con `new self(...)` (no `new static(...)`), así
  que llamar a su `getInstance()` heredado desde una subclase construiría,
  de forma silenciosa, la clase padre en vez de esta. Por eso siempre se
  instancia directamente (`new ConsoleApplication(...)`, ver `bin/console`).
- `use ConsoleKernelTrait` (de `derafu/console`) agrega el ciclo de vida de
  consola sobre ese Kernel. Como un trait mezcla sus métodos directamente
  en la clase (no los hereda vía `Application`/`MicroKernel`), sobreescribir
  `createConsoleApplication()` requiere alias explícito
  (`createConsoleApplication as private buildBaseConsoleApplication`) para
  poder llamar a la implementación original del trait — `parent::` no
  resuelve métodos de un trait.
- `ConsoleApplication::createConsoleApplication()` instala
  `Derafu\BackboneConsole\Service\OperationCommandLoader` como
  `CommandLoaderInterface` de la aplicación de Symfony Console —comandos
  descubiertos de forma perezosa a partir de `SafeExplorerInterface`, nunca
  una lista escrita a mano— alimentado por el mismo
  `SafeExplorerInterface`/`SafeDispatcherInterface` que expone
  `libredte-lib-core-dispatcher`, y por `libredte\lib\CoreConsole\ExitCodeResolver`
  (ver abajo) en vez del `DefaultExitCodeResolver` puro.
- `bin/console` usa `Derafu\Console\Runtime::run()` para resolver
  `APP_ENV`/`APP_DEBUG` antes de construir el Kernel — nunca lee `$_ENV`
  directo: esa superglobal solo se puebla si `variables_order` (php.ini)
  incluye la `E`, algo que no es el default en toda instalación de PHP (ej.
  Homebrew en macOS trae `GPCS`, sin `E`). `Runtime` mezcla `$_SERVER` y
  `$_ENV`, y defaultea a `prod`/`false` cuando no hay nada seteado — seguro
  por default, ya que esta consola no tiene ningún `.env` que pise ese
  default (a diferencia de una app HTTP típica).
- `libredte\lib\CoreConsole\ExitCodeResolver` extiende `DefaultExitCodeResolver`
  (que ya mapea las 7 excepciones genéricas de `derafu/backbone-dispatcher`
  a códigos `10`–`16`). Es un esqueleto: delega el 100% a `parent::` por
  ahora, con un `// TODO: Agregar mapeo.` marcando dónde agregar códigos
  propios para excepciones de negocio de `libredte-lib-core`
  (`DocumentException`, etc.) el día que se necesiten, sin romper nada de
  lo ya cableado.
- `config/services.yaml` solo importa el de `libredte-lib-core-dispatcher`:
  no declara servicios propios, porque este paquete no agrega ningún
  Deserializer ni operación nueva, solo expone las que ya existen como
  comandos. `kernel.project_dir` se resuelve contra el paquete que actúa
  como punto de entrada, y ese rol lo cumple este paquete cuando se ejecuta
  `bin/console` — el resto de la cadena de imports (hacia
  `libredte-lib-core-dispatcher` y de ahí a `libredte-lib-core`) ya
  sobreescribe `libredte.lib.core.project_dir` en consecuencia.

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
