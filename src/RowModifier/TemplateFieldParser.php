<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Framework\Filter\Template;
use Magento\Framework\Message\ManagerInterface;
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
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * TemplateFieldParser constructor.
     *
     * @param ConsoleOutput    $consoleOutput
     * @param Template         $filterTemplate
     * @param array            $templateFields
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Template $filterTemplate,
        $templateFields = [],
    ManagerInterface $messageManager
    ) {
        parent::__construct($consoleOutput);
        $this->filterTemplate = $filterTemplate;
        $this->templateFields = $templateFields;
        $this->messageManager = $messageManager;
    }

    /**
     * {@inheritdoc}
     */
    public function process()
    {
        foreach ($this->items as &$item) {
            $this->filterTemplate->setVariables($item);
            foreach ($this->templateFields as $fieldName => $template) {
                try {
                    $item[$fieldName] = $this->filterTemplate->filter($template);
                } catch (\Exception $e) {
                    $this->messageManager->addExceptionMessage($e);
                }
            }
        }
    }


}
