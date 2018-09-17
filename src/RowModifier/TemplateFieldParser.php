<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Magento\Framework\Filter\Template;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class TemplateFieldParser extends AbstractRowModifier
{
    /** @var Template $filterTemplate */
    private $filterTemplate;

    /** @var ManagerInterface $messageManager */
    private $messageManager;

    /** @var array $templateFields */
    private $templateFields;

    /**
     * @param ConsoleOutput    $consoleOutput
     * @param Template         $filterTemplate
     * @param ManagerInterface $messageManager
     * @param Log              $log
     * @param array            $templateFields
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Template $filterTemplate,
        ManagerInterface $messageManager,
        Log $log,
        $templateFields = []
    ) {
        parent::__construct($consoleOutput, $log);

        $this->filterTemplate = $filterTemplate;
        $this->messageManager = $messageManager;
        $this->templateFields = $templateFields;
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
                    $this->log->addError($e->getMessage());
                    $this->messageManager->addExceptionMessage($e);
                }
            }
        }
    }
}
