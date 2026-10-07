<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer;
use GlpiPlugin\Timetamer\PluginDescriptor;
use GlpiPlugin\Timetamer\timetasks\TimingContract;
use GlpiPlugin\Timetamer\timetasks\TimedTicket;
use GlpiPlugin\Timetamer\timetasks\TimeTask;

use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;

use CommonGLPI;
use Entity;
use Contract;
use Ticket;
use User;
use Session;
use Dropdown;
use TicketTask;

final class TimeTamer extends CommonGLPI
{
	#[\Override]
	public static function getMenuContent()
	{
		$key = PluginDescriptor::get()->key;
		$menu = parent::getMenuContent();
		$menu['links'][__('Overview')] = 'plugins/timetamer/front/timetamer.php';
		$menu['links']["<span id=$key-tticketlink>" . __('Tickets') . '</span>'] = 'plugins/timetamer/front/timetasks/timedticket.php';
		$menu['links']["<span id=$key-ttasklink>" . __('Tasks') . '</span>'] = 'plugins/timetamer/front/timetasks/timetask.php';
		$menu['links']["<span id=$key-tcontractlink>" . __('Contracts') . '</span>'] = 'plugins/timetamer/front/timetasks/timingcontract.php';
		return $menu;
	}
	
	
	#[\Override]
	static function getTypeName($nb = 0)
	{
		return 'TimeTamer';
	}
	
	#[\Override]
	static function getMenuName()
	{
		return 'Time Tamer';
	}
	
	static function getIcon()
	{
		return 'ti ti-alert-circle';
	}
	
	#[\Override]
	static function canView() : bool
	{
		return Ticket::canView() && Contract::canView();
	}
	
	#[\Override]
	public static function getSectorizedDetails() : array
	{
		return ['management', self::class];
	}

	#[\Override]
	function getTabNameForItem(
		CommonGLPI $item,
		$withtemplate = 0
	) : string | array
	{
		return match ($item::class) {
			default => 'test'
		};
	}
	
	#[\Override]
	static function displayTabContentForItem(
		CommonGLPI $item,
		$tabnum = 1,
		$withtemplate = 0
	) : bool
	{
		$ts = self::getTabs($item);
		$t = $ts[$tabnum] ?? null;
		if (null === $t) {
			return false;
		}
		return self::displayContent($item, $withtemplate);
	}
	
	static function getEntities() : array
	{
		$ids = [];
		$recursive = [];
		$e_fk = Entity::getForeignKeyField();
		foreach ((new TimingContract())->find() as $row) {
			$e = $row[$e_fk];
			if ($row['is_recursive']) {
				$recursive[$e] = $e;
			} else {
				$ids[$e] = $e;
			}
		}
		$entity = new Entity();
		while ($recursive) {
			$new = [];
			foreach ($recursive as $e) {
				if (array_key_exists($e, $ids)) continue;
				$ids[$e] = $e;
				foreach ($entity->find([$e_fk => $e]) as $row) {
					$ee = $row['id'];
					$new[$ee] = $ee;
				}
			}
			$recursive = $new;
		}
		$user_entities = $_SESSION['glpiactiveentities'] ?? [];
		$user_entities = is_array($user_entities) ? $user_entities : [$user_entities];
		$filter_entities = [];
		foreach ($user_entities as $id) {
				$filter_entities[$id] = true;
		}
		$ans = [];
		foreach ($ids as $id) {
			if (!($filter_entities[$id] ?? false)) continue;
			$o = new Entity();
			$o->getFromDB($id);
			$ans[$id] = $o;
		}
		return $ans;
	}
	
	static function getContracts(int $e_id) : array
	{
		$e_fk = Entity::getForeignKeyField();
		$ids = [];
		$contract = new TimingContract();
		$entity = new Entity();
		foreach ($contract->find([$e_fk => $e_id]) as $row) {
			$c = $row['id'];
			$ids[$c] = $c;
		}
		while ($e_id) {
			$e_id = $entity->find(['id' => $e_id])[$e_id][$e_fk];
			foreach ($contract->find([$e_fk => $e_id, 'is_recursive' => 1]) as $row) {
				$c = $row['id'];
				$ids[$c] = $c;
			}
		}
		$ans = [];
		foreach ($ids as $id) {
			$o = new TimingContract();
			$o->getFromDB($id);
			$ans[$id] = $o;
		}
		return $ans;
	}

	static function getTickets(int $e_id, int $c_id) : array
	{
		$e_fk = Entity::getForeignKeyField();
		$c_fk = Contract::getForeignKeyField();
		$ticket = new TimedTicket();
		$ans = [];
		foreach ($ticket->find([
			$e_fk => $e_id,
			$c_fk => $c_id,
		]) as $row) {
			$id = $row['id'];
			$o = new TimedTicket();
			$o->getFromDB($id);
			$ans[$id] = $o;
		}
		return $ans;
	}
	
	static function getTasks(
		int $t_id,
		?int $year = null,
		?int $month = null,
		?int $day = null
	) : array
	{
		$t_fk = Ticket::getForeignKeyField();
		$task = new TimeTask();
		$ans = [];
		$params = [$t_fk => $t_id];
		foreach (explode(' ', 'year month day') as $k) {
			$v = $$k;
			if (is_null($v)) continue;
			$params[$k] = $v;
		}
		foreach ($task->find($params) as $row) {
			$id = $row['id'];
			$o = new TimeTask();
			$o->getFromDB($id);
			$ans[$id] = $o;
		}
		return $ans;
	}

	static public function displayContent(
		\CommonGLPI | null $item = null,
		int $withtemplate = 0
	) : bool
	{
//		echo '<pre>', var_export($_POST, true), '</pre>';
		$key = PluginDescriptor::get()->key;
		$data['prefix'] = $key;
		
		$uid = (int) Session::getLoginUserID();
		
		// init template data with received date
		$date_raw = filter_input(INPUT_POST, "$key-date") ?: '';
		$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_raw)?
			$date_raw
		:	date('Y-m-d');
		[$year, $month, $day] = array_map(fn($s) => (int) $s, explode('-', $date));
		$days_in_month = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
		$data += [
			'date'		=> $date,
			'day'		=> $day,
			'month'		=> $month,
			'year'		=> $year,
			'days_in_month'	=> $days_in_month,
			'months'	=> [
				1 =>	__('January'),	2 =>	__('February'),	3 =>	__('March'),
				4 =>	__('April'),	5 =>	__('May'),	6 =>	__('June'),
				7 =>	__('July'),	8 =>	__('August'),	9 =>	__('September'),
				10 =>	__('October'),	11 =>	__('November'),	12 =>	__('December'),
			],
		];
		
		// get form input data and update db
		$newdata = json_decode(
			filter_input(INPUT_POST, "$key-newdata") ?: '{}',
			associative: true,
		);
		$allowed_keys = [
			'hours' => true,
			'description' => true,
			'cat' => true,
		];
		$tticket = new TimedTicket();
		$entity = new Entity();
		$e_fk = Entity::getForeignKeyField();
		$tt = new TimeTask();
		foreach ($newdata as $tid => $current)
		{
			$row = $tticket->find(['id' => $tid])[$tid] ?? false;
			if ($row === false) continue;
			if (!$entity->can($row[$e_fk], READ)) continue;
			foreach ($current as $id => $datum) {
				if ($datum['remove'] ?? false) {
					$tt->check($id, PURGE);
					$tt->delete(['id' => $id], force: true);
					continue;
				}
				$param = [];
				foreach ($datum as $k => $v) {
					$allowed = $allowed_keys[$k] ?? false;
					if (!$allowed) continue;
					$param[$k] = $v;
				}
				switch (true) {
//				case (!$id) && !($param['actiontime'] ?? false):
//					break;
				case !$id:
					$param['tickets_id'] = $tid;
					$param['date'] = $date . ' 00:00:01.000';
					$param['users_id_tech'] = $uid;
					$tt->check(-1, CREATE, $param);
					$tt->add($param);
					break;
				case !$param:
					break;
				default:
					$param['id'] = $id;
					$tt->check($id, UPDATE);
					$tt->update($param);
				}
			}
		}

		// populate template data
		$user = new User();
		$tickettask = new TicketTask();
		$recap = [];
		$base_recap_entry = array_fill(0, $days_in_month, 0);
		$entities = [];
		$all_entities = self::getEntities();
		$multiple_entities = count($all_entities) > 1;
		foreach ($all_entities as $e) {
			/** @var Entity $e */
			$entity = [];
			$e_id = $e->fields['id'];
			$name_array = explode('> ', $e->fields['completename']);
			if (sizeof($name_array) > 1) {
				$name_array[0] = '';
			}
//			$e_prefix = array_last($name_array);
			$e_prefix = end($name_array);
			$e_prefix = $e_prefix ? "($e_prefix) " : '';
			$e_prefix = $multiple_entities ? $e_prefix : '';

			$entity['full_name'] = implode('> ', $name_array);
			$entity['id'] = $e_id;
			$entity['url'] = $e->getLinkURL();
			
			$contracts = [];
			foreach (self::getContracts(e_id: $e_id) as $c) {
				/** @var TimingContract $c */
				$c_id = $c->fields['id'];
				$contract = [];
				$contract['id'] = $c_id;
				$contract['url'] = $c->getLinkURL();
				$contract['name'] = $c->fields['name'];
				$capacity = $c->fields['capacity'];
				$contract['capacity'] = $capacity;
				$used_h = $c::getSpentHours($c_id);
				$contract['used'] = $used_h * 3600;
				$contract['hours'] = $capacity - (int) $used_h;
			
				$recap_entry = false;
				
				$tickets = [];
				foreach(self::getTickets(
					e_id: $e_id,
					c_id: $c_id
				) as $t) {
					/** @var TimedTicket $t */
					$t_id = $t->fields['id'];
					$ticket = [];
					$ticket['id'] = $t_id;
					$ticket['link'] = $t->getLink(['complete' => true]);
//					$ticket['title'] = $t->fields['title'];
//					$ticket['date'] = $t->fields['date'];
					
					
					$tasks = [];
					foreach(self::getTasks(
						t_id: $t_id,
						year: $year,
						month: $month,
					) as $tt) {
						/** @var TimeTask $tt */
						$seconds = $tt->fields['seconds'];
						$day = $tt->fields['day'];
						
						// consider in monthly recap
						if ($tt->fields['user'] == $uid) {	// TODO: check for strict comparison opportunity
							$recap_entry = $recap_entry ?: $base_recap_entry ;
							$recap_entry[$day - 1] += $seconds;
						}
						
						// populate daily recap
						$task = [];
						foreach (
							explode(' ', 'id date seconds begin end')
							as $k
						) {
							$task[$k] = $tt->fields[$k];
						}
						$user_id = $tt->fields["user"];
						$task['user'] = $user->find(['id' => $user_id])[$user_id]['name'] ?? $user_id;
						$task['cat'] = Dropdown::getDropdownName('glpi_taskcategories', $tt->fields['taskcategories_id']) ?: '-';
						$lines = explode("\n", RichText::getTextFromHtml(
							content: $tt->fields['comment'],
							keep_presentation: false,
							compact: true,
							encode_output: true,
							preserve_line_breaks: true,
						));
						$l = $lines[0];
//						$prev = (strlen($l) <= 30) ? $l : (substr($l, 0, 26) . ' ...');
						$prev = $l;
						$task['preview'] = $prev;
//						$task['preview'] = $tt->fields['content'];
						
						if ($tickettask->can($task['id'], READ)) {
							$tasks[] = $task;
						}
					}
					$ticket['tasks'] = $tasks;
					$ticket['tasktypename'] = \TicketTask::getTypeName(nb: count($tasks));
					
					$tickets[] = $ticket;
				}
				$contract['tickets'] = $tickets;
				
				$contracts[] = $contract;
				
				if ($recap_entry) {
					$name = $e_prefix . $contract["name"];
					$limit = 20;
					$name = (strlen($name) <= $limit) ?
						$name
					:	(substr($name, 0, $limit - 4) . ' ...');
					$recap[$c_id] = [
						'name' => $name,
						'seconds' => $recap_entry,
					];
				}
			}
			$entity['contracts'] = $contracts;
			
			if ($contracts) {
				$entities[] = $entity;
			}
		}
		$data['entities'] = $entities;
		usort($recap, fn($e) => $e['name']);
		$data['recap'] = $recap;
		$data['item'] = new static();
		TemplateRenderer::getInstance()->display('@' . $key . '/task_table.html.twig', $data);
		
		return true;
		// Debug data
		
		echo '<pre>';
		echo "\n\nDATA:\n";
		var_dump($data);
		echo "\n\nEntities:\n";
		foreach(self::getEntities() as $e) {
			$e0 = $e;
			echo $e->fields['id'], "\t", var_export($e, true), "\n";
		}
		echo "\n\nContracts:\n";
		foreach(self::getContracts($e0->fields['id']) as $c) {
			$c0 = $c;
			echo $c->fields['id'], "\t",  var_export($c, true), "\n";
		}
		echo "\n\nTickets:\n";
		foreach(self::getTickets($e0->fields['id'], $c0->fields['id']) as $t) {
			$t0 = $t;
			echo $t->fields['id'], "\t",  var_export($t, true), "\n";
		}
		echo "\n\nTasks:\n";
		foreach(self::getTasks($t0->fields['id']) as $tt) {
			$tt0 = $tt;
		echo "{$tt->fields['id']}\t", var_export($tt, true), "\n";
		}
		echo '</pre>';
		
		return true;
	}
}
