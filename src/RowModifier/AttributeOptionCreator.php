<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Magento\Catalog\Api\ProductAttributeOptionManagementInterface as OptionManagement;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\OptionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @todo implement using Factory pattern.
 *
 * Class AttributeOptionCreator
 * @package Ho\Import\RowModifier
 */
class AttributeOptionCreator extends AbstractRowModifier
{
    /**
     * Option management interface
     *
     * @var OptionManagement
     */
    protected $optionManagement;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * Entity attribute option model factory
     *
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * Array of attributes to automatically fill
     *
     * @var array
     */
    protected $attributes;

    /**
     * @param OptionManagement                    $optionManagement
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param OptionFactory                       $optionFactory
     * @param ConsoleOutput                       $consoleOutput
     * @param Log                                 $log
     * @param string[]                            $attributes
     */
    public function __construct(
        OptionManagement $optionManagement,
        ProductAttributeRepositoryInterface $attributeRepository,
        OptionFactory $optionFactory,
        ConsoleOutput $consoleOutput,
        Log $log,
        $attributes = []
    ) {
        parent::__construct($consoleOutput, $log);

        $this->optionManagement = $optionManagement;
        $this->attributeRepository = $attributeRepository;
        $this->optionFactory = $optionFactory;
        $this->attributes = $attributes;
    }

    /**
     * Automatically create attribute options.
     *
     * @throws InputException
     * @throws StateException
     * @throws NoSuchEntityException
     *
     * @return void
     */
    public function process()
    {
        $attributes = implode(', ', $this->attributes);
        $this->consoleOutput->writeln("<info>Creating attribute options for: {$attributes}</info>");
        $this->log->addInfo('Creating attribute options for:'. $attributes);

        foreach ($this->attributes as $attribute) {
            $this->createForAttribute($attribute);
        }
    }

    /**
     * Create attribute options
     *
     * @param string $attributeCode
     *
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     *
     * @return void
     */
    public function createForAttribute(string $attributeCode): void
    {
        $uniqueOptions = $this->getNonExistingAttributes($attributeCode);
        $attribute = $this->attributeRepository->get($attributeCode);
        $attribute->setStoreId(0); // Load option values from adminhtml.

        foreach ($attribute->getOptions() as $option) {
            /** @var \Magento\Eav\Api\Data\AttributeOptionInterface $option */

            if (\in_array($option->getLabel(), $uniqueOptions, false)) {
                unset($uniqueOptions[$option->getLabel()]);
            }
        }

        foreach ($uniqueOptions as $optionLabel) {
            $optionModel = $this->optionFactory->create();
            $optionModel->setAttributeId($attributeCode);
            $optionModel->setLabel($optionLabel);

            $this->optionManagement->add($attributeCode, $optionModel);
        }
    }

    /**
     * Get a list of attributes that need to be created.
     *
     * @param string $attribute
     *
     * @return array
     */
    protected function getNonExistingAttributes(string $attribute): array
    {
        $uniqueValues = [];
        foreach ($this->items as $identifier => $item) {
            if (empty($item[$attribute])) {
                continue;
            }

            if (! \is_string($item[$attribute])) {
                $this->consoleOutput->writeln(
                    "<error>AttributeOptionCreator: Invalid value for {$attribute} {$identifier}</error>"
                );
                $this->log->addError("AttributeOptionCreator: Invalid value for {$attribute} {$identifier}");

                $item[$attribute] = '';
                continue;
            }

            $uniqueValues[$item[$attribute]] = $item[$attribute];
        }

        return $uniqueValues;
    }


    /**
     * Sets the attributes
     * @param string[] $attributes
     * @deprecated Please us the factory AttributeOptionCreatorFactory to set the value.
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
