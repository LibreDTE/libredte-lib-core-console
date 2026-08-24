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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runs `bin/console` as a real subprocess — the same executable a caller
 * in another language reaches via `exec()`/`system()`/`subprocess.run()`,
 * not `ArrayInput`/`BufferedOutput` in-process like `ConsoleApplicationTest`.
 *
 * This is the only test in this project that would have caught the real
 * `$_ENV`-only bug found earlier in this bridge's development (`bin/console`
 * read only `$_ENV['APP_DEBUG']`, which on some PHP installs is never
 * populated by real process environment variables): an in-process test
 * builds `ConsoleApplication` directly, so it never exercises `bin/console`
 * itself, its shebang, its permissions, or the real `$_SERVER`/`$_ENV`/
 * `argv` a spawned process actually gets.
 *
 * Fixtures are real `libredte-lib-core` operations already exercised
 * elsewhere in this project (`caf_loader`, `caf_faker`), not a purpose-built
 * fixture package — deterministic and stable without needing one.
 */
#[CoversNothing]
final class BinConsoleTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/../..';

    /**
     * Runs `bin/console` as a real subprocess.
     *
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runBinConsole(array $args, string $stdin = ''): array
    {
        $process = proc_open(
            array_merge([\PHP_BINARY, self::PROJECT_DIR . '/bin/console'], $args),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            self::PROJECT_DIR,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    public function testListsRealLibredteCoreOperationCommands(): void
    {
        $result = $this->runBinConsole(['list']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString(
            'billing:identifier:caf_faker:create',
            $result['stdout'],
        );
    }

    public function testShowsHelpForARealOperationWithItsParametersAndExitCodes(): void
    {
        $result = $this->runBinConsole(['help', 'billing:identifier:caf_loader:load']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('xml', $result['stdout']);
        $this->assertStringContainsString('Exit codes:', $result['stdout']);
        $this->assertStringContainsString('65 - Malformed request data', $result['stdout']);
    }

    public function testFailsDeterministicallyOnUnparseableXml(): void
    {
        $result = $this->runBinConsole(
            ['billing:identifier:caf_loader:load'],
            '{"parameters": {"xml": "not-a-real-caf"}}',
        );

        // XmlParseException (derafu/xml's own) is not one of the 7
        // dispatcher-generic exceptions DefaultExitCodeResolver maps, so
        // it falls back to the generic 1 — deterministic and stable,
        // since a string that is not XML at all can never legitimately
        // parse regardless of environment or future business logic.
        $this->assertSame(1, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertStringContainsString('XmlParseException', $result['stderr']);
    }
}
