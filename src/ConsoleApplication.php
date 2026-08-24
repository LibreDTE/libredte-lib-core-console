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

namespace libredte\lib\CoreConsole;

use Derafu\BackboneConsole\Service\OperationCommandLoader;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\Console\ConsoleKernelTrait;
use libredte\lib\Core\Application;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Punto de entrada de consola de `libredte-lib-core` (ver `bin/console`).
 *
 * Extiende directamente `libredte\lib\Core\Application` —igual que hace
 * `libredte\lib\Pro\ConsoleApplication` para su propia consola de
 * desarrollo— y no usa `Application::getInstance()` en ningún punto: esa
 * clase construye la instancia con `new self(...)` (no `new static(...)`),
 * así que llamar a su `getInstance()` heredado desde una subclase
 * construiría, de forma silenciosa, la clase padre en vez de esta. Por eso
 * esta clase siempre se instancia directamente (`new ConsoleApplication(...)`,
 * ver `bin/console`), nunca a través de `getInstance()`.
 *
 * A diferencia de `Bootstrap` en `libredte-lib-core-dispatcher` (que expone
 * un único `SafeDispatcherInterface`/`SafeExplorerInterface` para que un
 * consumidor arme sus propias solicitudes), acá cada operación descubierta
 * se expone como su propio comando de Symfony Console, vía
 * `OperationCommandLoader` de `derafu/backbone-console` — auto-descubierto
 * de forma perezosa (`CommandLoaderInterface`), nunca una lista de comandos
 * escrita a mano.
 */
class ConsoleApplication extends Application
{
    use ConsoleKernelTrait {
        createConsoleApplication as private buildBaseConsoleApplication;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(
        ContainerConfigurator $configurator,
        ContainerBuilder $container
    ): void {
        parent::configure($configurator, $container);

        $this->configureConsole($container);
    }

    /**
     * {@inheritDoc}
     *
     * Overrides, rather than extends, `ConsoleKernelTrait`'s own
     * `createConsoleApplication()`: a trait's methods are mixed directly
     * into this class, not inherited through `Application`/`MicroKernel`,
     * so `parent::createConsoleApplication()` would not resolve — the
     * trait's original implementation is reached instead through the
     * `buildBaseConsoleApplication()` alias declared above.
     */
    protected function createConsoleApplication(
        ContainerInterface $container
    ): SymfonyConsoleApplication {
        $application = $this->buildBaseConsoleApplication($container);

        $application->setCommandLoader(new OperationCommandLoader(
            $container->get(SafeExplorerInterface::class),
            $container->get(SafeDispatcherInterface::class),
            exitCodeResolver: new ExitCodeResolver(),
        ));

        return $application;
    }
}
