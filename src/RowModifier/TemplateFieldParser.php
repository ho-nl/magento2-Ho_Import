<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Symfony\Component\Console\Output\ConsoleOutput;

class TemplateFieldParser extends AbstractRowModifier
{

    /**
     * TemplateFieldParser constructor.
     *
     * @param ConsoleOutput $consoleOutput
     */
    public function __construct(
        ConsoleOutput $consoleOutput
    ) {
        parent::__construct($consoleOutput);
    }

    /**
     * {@inheritdoc}
     */
    public function process()
    {

    }

    
}
