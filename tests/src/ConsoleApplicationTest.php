<?php

declare(strict_types=1);

/**
 * LibreDTE: Consola para la Biblioteca PHP (Core).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada por
 * la Fundación para el Software Libre, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * GARANTÍA ALGUNA; ni siquiera la garantía implícita MERCANTIL o de APTITUD
 * PARA UN PROPÓSITO DETERMINADO. Consulte los detalles de la Licencia Pública
 * General Affero de GNU para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de
 * GNU junto a este programa.
 *
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace libredte\lib\TestsCoreConsole;

use libredte\lib\CoreConsole\ConsoleApplication;
use libredte\lib\CoreConsole\ExitCodeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test de integración de punta a punta: construye la `ConsoleApplication`
 * real (su propio `config/services.yaml`, que reenvía al de
 * libredte-lib-core-dispatcher y de ahí al de libredte-lib-core) y ejercita
 * `run()` con un `ArrayInput`/`BufferedOutput` en lugar del STDIN/STDOUT
 * real del proceso —soportado a propósito por `ConsoleKernelTrait::run()`
 * para poder testear sin depender del proceso real, el mismo mecanismo que
 * `bin/console` usa en producción con sus valores por defecto (`null`)—.
 * Sin mocks en ningún punto de la cadena: operaciones reales de
 * `libredte-lib-core`, contenedor real, `OperationCommandLoader` real.
 */
#[CoversClass(ConsoleApplication::class)]
#[UsesClass(ExitCodeResolver::class)]
class ConsoleApplicationTest extends TestCase
{
    private ConsoleApplication $app;

    protected function setUp(): void
    {
        $this->app = new ConsoleApplication('test', true);
    }

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'libredte-core-console-test-');
        file_put_contents($path, $content);

        return $path;
    }

    public function testListDiscoversARealLibredteCoreOperationCommand(): void
    {
        $output = new BufferedOutput();
        $this->app->run(new ArrayInput(['command' => 'list']), $output);

        $this->assertStringContainsString(
            'billing:identifier:caf_faker:create',
            $output->fetch(),
        );
    }

    public function testHelpShowsTheRealReflectedParametersOfARealOperation(): void
    {
        $output = new BufferedOutput();
        $this->app->run(
            new ArrayInput([
                'command' => 'help',
                'command_name' => 'billing:identifier:caf_loader:load',
            ]),
            $output,
        );

        $this->assertStringContainsString('xml', $output->fetch());
    }

    public function testDispatchesARealOperationAndWritesTheResultAsJson(): void
    {
        $path = $this->writeTempFile((string) json_encode([
            'parameters' => [
                'emisor' => ['rut' => '76192083-9', 'razon_social' => 'SASCO SpA'],
                'codigoDocumento' => 33,
                'folioDesde' => 1,
                'folioHasta' => 10,
            ],
        ]));

        $output = new BufferedOutput();
        $exitCode = $this->app->run(
            new ArrayInput([
                'command' => 'billing:identifier:caf_faker:create',
                'input' => $path,
            ]),
            $output,
        );
        unlink($path);

        $data = json_decode($output->fetch(), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(33, $data['data']['tipoDocumento']);
        $this->assertSame(10, $data['data']['cantidadFolios']);
    }

    public function testFailurePathReturnsANonZeroExitCodeAndAProblemDetailAsJson(): void
    {
        $path = $this->writeTempFile((string) json_encode([
            'parameters' => ['xml' => 'not-a-real-caf'],
        ]));

        $output = new BufferedOutput();
        $exitCode = $this->app->run(
            new ArrayInput([
                'command' => 'billing:identifier:caf_loader:load',
                'input' => $path,
            ]),
            $output,
        );
        unlink($path);

        $problem = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame('billing.identifier.caf_loader::load', $problem['instance']);
    }
}
