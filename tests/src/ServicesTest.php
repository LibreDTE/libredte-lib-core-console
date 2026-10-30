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

use Derafu\Kernel\Environment;
use Derafu\Translation\Exception\Core\TranslatableLogicException;
use libredte\lib\CoreConsole\ConsoleApplication;
use libredte\lib\CoreConsole\ExitCodeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Comprueba que el loader publicado respeta el resolutor del YAML consumidor.
 */
#[CoversClass(ConsoleApplication::class)]
#[CoversClass(ExitCodeResolver::class)]
final class ServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname(__DIR__, 2) . '/var/cache/services-test');
    }

    public function testCommandUsesTheExitCodeResolverConfiguredInYaml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'core-console-services-');
        self::assertIsString($path);
        try {
            file_put_contents($path, '{"parameters":{"xml":"not-a-real-caf"}}');
            $output = new BufferedOutput();
            $code = $this->application()->run(new ArrayInput([
                'command' => 'billing:identifier:caf_loader:load',
                'input' => $path,
            ]), $output);

            self::assertSame(42, $code);
            self::assertStringContainsString('NoDeserializerFoundException', $output->fetch());
        } finally {
            unlink($path);
        }
    }

    public function testHelpUsesTheSameConfiguredExitCodeMapping(): void
    {
        $output = new BufferedOutput();
        self::assertSame(0, $this->application()->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'billing:identifier:caf_loader:load',
        ]), $output));
        self::assertStringContainsString('42 -', $output->fetch());
    }

    public function testInvalidLoaderConfigurationProducesATranslatableError(): void
    {
        $application = $this->application('invalid-loader');
        $this->expectException(TranslatableLogicException::class);
        $this->expectExceptionMessage(CommandLoaderInterface::class);
        $application->run(new ArrayInput(['command' => 'list']), new BufferedOutput());
    }

    private function application(string $fixture = 'services'): ConsoleApplication
    {
        return new ConsoleApplication(new Environment('test', true, [
            'PROJECT_DIR' => dirname(__DIR__) . '/fixtures/' . $fixture,
        ], ['cache' => dirname(__DIR__, 2) . '/var/cache/services-test']));
    }
}
