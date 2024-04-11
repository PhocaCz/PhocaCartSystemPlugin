<?php
/**
 * @package   Phoca Cart
 * @author    Jan Pavelka - https://www.phoca.cz
 * @copyright Copyright (C) Jan Pavelka https://www.phoca.cz
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 and later
 * @cms       Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Joomla\Plugin\System\PhocaCart\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\AdministratorMenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Phoca\PhocaCart\User\AdvancedACL;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * PhocaCart System plugin
 *
 * @since  5.0.0
 */
final class PhocaCart extends CMSPlugin
{

	public function onPreprocessMenuItems(string $context, array $items): void
	{
		if ($context !== 'com_menus.administrator.module') {
			return;
		}

		$component = ComponentHelper::getComponent('com_phocacart', true);
		if (!$component->enabled) {
			return;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php';

		$params = \PhocacartUtils::getComponentParameters();
		if (!$params->get('use_advanced_permissions')) {
			return;
		}

		/**
		 * @var int $index
		 * @var AdministratorMenuItem $item
		 */
		foreach ($items as $item) {
			if ($item->component_id !== $component->id) {
				continue;
			}

			$uri = new Uri($item->link);
			$view = $uri->getVar('view');
			if (!$view) {
				continue;
			}

			$action = AdvancedACL::getActionFromView($view);
			if (empty($action)) {
				continue;
			}

			if (!AdvancedACL::authorise($action)) {
				$item->getParent()->removeChild($item);
			}
		}
	}

	public function onAfterRoute()
	{
		$app = $this->getApplication();

		if (!$app->isClient('administrator')) {
			return;
		}

		if ($app->getInput()->getCmd('option') != 'com_mails') {
			return;
		}

		$language = $app->getLanguage();
		$language->load('com_phocacart');

		$strings = [];

		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('id, title')
			->from('#__phocacart_order_statuses');
		$db->setQuery($query);
		$statuses = $db->loadObjectList();

		foreach ($statuses as $status) {
			$strings['COM_PHOCACART_MAIL_ORDER_STATUS_' . $status->id . '_TITLE'] = Text::_('COM_PHOCACART_MAIL_ORDER_STATUS_TITLE') . ' - ' . Text::_($status->title);
			$strings['COM_PHOCACART_MAIL_ORDER_STATUS_' . $status->id . '_DESC'] = Text::_('COM_PHOCACART_MAIL_ORDER_STATUS_DESC');
			$strings['COM_PHOCACART_MAIL_OTHERS_ORDER_STATUS_' . $status->id . '_TITLE'] = Text::_('COM_PHOCACART_MAIL_OTHERS_ORDER_STATUS_TITLE') . ' - ' . Text::_($status->title);
		}

		$closure = \Closure::bind(function (array $strings) {
			$this->strings = array_replace($this->strings, $strings, $this->override);
		}, $language, get_class($language));

		$closure($strings);
	}

}
