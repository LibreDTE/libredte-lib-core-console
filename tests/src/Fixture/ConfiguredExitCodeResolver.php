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

namespace libredte\lib\TestsCoreConsole\Fixture;

use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use libredte\lib\CoreConsole\ExitCodeResolver;

/**
 * Aporte de un consumidor para comprobar el resolutor compartido por DI.
 */
final class ConfiguredExitCodeResolver extends ExitCodeResolver
{
    /**
     * {@inheritDoc}
     */
    public function resolve(ProblemDetailInterface $problem): int
    {
        return $problem->getThrowable()->getClass() === NoDeserializerFoundException::class
            ? 42
            : parent::resolve($problem);
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): array
    {
        return [NoDeserializerFoundException::class => 42] + parent::describe();
    }
}
