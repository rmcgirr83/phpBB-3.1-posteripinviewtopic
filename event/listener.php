<?php
/**
*
* @package phpBB Extension - Poster IP in viewtopic
* @copyright (c) 2016 RMcGirr83 (Rich McGirr)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace rmcgirr83\posteripinviewtopic\event;

use phpbb\auth\auth;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\controller\helper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\controller\helper */
	protected $helper;

	public function __construct(
		auth $auth,
		language $language,
		template $template,
		helper $helper)
	{
		$this->auth = $auth;
		$this->language = $language;
		$this->template = $template;
		$this->helper = $helper;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_extensions_run_action_after'	=>	'acp_extensions_run_action_after',
			'core.viewtopic_post_rowset_data'		=> 'add_posterip_in_rowset',
			'core.viewtopic_modify_post_row'		=> 'display_posterip_viewtopic',
		);
	}

	/* Display additional metdate in extension details
	*
	* @param $event			event object
	* @param return null
	* @access public
	*/
	public function acp_extensions_run_action_after($event)
	{
		if ($event['ext_name'] == 'rmcgirr83/posteripinviewtopic' && $event['action'] == 'details')
		{
			$this->language->add_lang('common', $event['ext_name']);
			$this->template->assign_vars([
				'L_BUY_ME_A_BEER_EXPLAIN'	=> $this->language->lang('BUY ME A BEER_EXPLAIN', '<a href="' . $this->language->lang('BUY_ME_A_BEER_URL') . '" target="_blank" rel=”noreferrer noopener”>', '</a>'),
				'S_BUY_ME_A_BEER_PIIV' => true,
			]);
		}
	}

	/**
	* Add posterip into rowset
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function add_posterip_in_rowset($event)
	{
		$rowset = $event['rowset_data'];
		$row = $event['row'];

		$rowset['poster_ip'] = $row['poster_ip'];

		$event['rowset_data'] = $rowset;
	}

	/**
	* Display posterip on each topic
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function display_posterip_viewtopic($event)
	{
		$poster_ip = $event['row']['poster_ip'];
		$forum_id = $event['row']['forum_id'];

		if (($this->auth->acl_gets('a_', 'm_') || $this->auth->acl_get('m_', (int) $forum_id)) && (!empty($poster_ip) && $poster_ip != '127.0.0.1'))
		{
			$query_url = $this->helper->route('rmcgirr83_posteripinviewtopic_core_freegeoip', array('poster_ip' => $poster_ip, 'forum_id' => (int) $forum_id));

			$event['post_row'] = array_merge($event['post_row'], [
				'POSTER_IP_VISIBLE' => true,
				'POSTER_IP'			=> $poster_ip,
				'QUERY_URL'			=> $query_url,
			]);
		}
	}
}
