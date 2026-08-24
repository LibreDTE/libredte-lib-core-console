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

use Derafu\BackboneConsole\Service\DefaultExitCodeResolver;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;

/**
 * Extiende `DefaultExitCodeResolver` (que ya mapea las 7 excepciones
 * genéricas de `derafu/backbone-dispatcher`) para, en el futuro, agregar
 * códigos de salida propios de las excepciones de negocio de
 * `libredte-lib-core` (ej. `DocumentException`, `CafException`, etc.).
 *
 * Todavía no hay ninguna mapeada — este es el esqueleto, no el mapeo real.
 * Mientras tanto se comporta exactamente igual que `DefaultExitCodeResolver`
 * (delega el 100% vía `parent::`), así que agregar el mapeo real más
 * adelante es un cambio aditivo, no rompe nada de lo ya cableado.
 */
class ExitCodeResolver extends DefaultExitCodeResolver
{
    /**
     * {@inheritDoc}
     */
    public function resolve(ProblemDetailInterface $problem): int
    {
        // TODO: Agregar mapeo de excepciones de negocio de libredte-lib-core.
        return parent::resolve($problem);
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): array
    {
        // TODO: Agregar mapeo de excepciones de negocio de libredte-lib-core.
        return parent::describe();
    }
}
