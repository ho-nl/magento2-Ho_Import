<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Framework\Filter\Template;
use Symfony\Component\Console\Output\ConsoleOutput;

class TemplateFieldParser extends AbstractRowModifier
{
    /** @var Template  */
    private $filterTemplate;

    /**
     * @var array
     */
    private $templateFields;

    /**
     * TemplateFieldParser constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param Template      $filterTemplate
     * @param array         $templateFields
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Template $filterTemplate,
        $templateFields = []
    ) {
        parent::__construct($consoleOutput);
        $this->filterTemplate = $filterTemplate;
        $this->templateFields = $templateFields;
    }

    /**
     * {@inheritdoc}
     */
    public function process()
    {
        exit;

    }


}
