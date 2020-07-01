<?php
namespace Safecharge\Safecharge\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
	private $eavSetupFactory;

	public function __construct(EavSetupFactory $eavSetupFactory)
	{
		$this->eavSetupFactory = $eavSetupFactory;
	}
	
	public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		$eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
		
		$eavSetup->addAttribute(
			\Magento\Catalog\Model\Product::ENTITY,
			\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME,
			[
				'type' => 'int',
				'label' => 'SafeCharge Subscription Plans',
				'input' => 'select',
				'source' => 'Safecharge\Safecharge\Model\Config\Source\ScSubsPlansOptions',
				'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
				'visible' => true,
				'required' => false,
				'user_defined' => true,
				'default' => '',
				'searchable' => true,
				'filterable' => true,
				'visible_on_front' => true,
				'used_in_product_listing' => true,
				'group' => 'General',
				'option' => [ 
					'values' => [],
				],
			]
		);
	}
}