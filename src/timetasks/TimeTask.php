<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer\timetasks;
use GlpiPlugin\Timetamer\TimeTamer;
use CommonDBTM;
use Entity;
use Contract;
use Ticket;
use TicketTask;
use User;
use TaskCategory;
use DisplayPreference;
use Glpi\RichText\RichText;

final class TimeTask extends CommonDBTM
{
	static $rightname;	// init below class definition
	const string VIEW_NAME = 'timetamer_view_timetasks';
	
	protected static $notable = true;
	#[\Override]
	public static function getTable($classname = null)
	{
		return self::VIEW_NAME;
	}
	
	#[\Override]
	public function can($ID, int $right, ?array &$input = null): bool
	{
		$current_id = $this->fields['id'] ?? 0;
		$o = $current_id ?
			TicketTask::getById($current_id)
		:	new TicketTask();
		return $o->can($ID, $right, $input);
	}
	
	// WARNING: invariant check here and in install query: keep consistency!
	#[\Override]
	public function add(array $input, $options = [], $history = true)
	{
		$t_fk = Ticket::getForeignKeyField();
		$overridden = self::translateInput($input);
		$this->input = $overridden;
		$tid = $input[$t_fk] ?? 0;
		if (!$tid) return false;
		$t = TimedTicket::getById($tid);
		if ($t === false) return false;
		$tech = $input['users_id_tech'] ?? 0;
		if (!$tech) return false;
		$o = new TicketTask();
		$id = $o->add($overridden, $options, $history);
		if ($id === false) return false;
		$this->getFromDB($id);
		return $id;
	}
	
	#[\Override]
	public function update(array $input, $history = true, $options = [])
	{
		$overridden = self::translateInput($input);
		$this->input = $overridden;
		$tech = $input['users_id_tech'] ?? -1;
		if (!$tech) return false;
		$id = $input['id'] ?? 0;
		if ($id) {
			$this->getFromDB($id);
		}
		$o = new TicketTask();
		$id = $this->fields['id'] ?? 0;
		if ($id) {
			$o->getFromDB($id);
		}
		$ans = $o->update($overridden, $options, $history);
		if ($ans) {
			$this->getFromDB($o->fields['id']);
		}
		return $ans;
	}
	
	#[\Override]
	public function delete(array $input, $force = false, $history = true) : bool
	{
		$input = self::translateInput($input);
		$this->input = $input;
		$o = new TicketTask();
		$id = $input['id'] ?? 0;
		if ($id) {
			$this->getFromDB($id);
		}
		$id = $this->fields['id'] ?? 0;
		if ($id) {
			$o->getFromDB($id);
		}
		return $o->delete($input, $force, $history);
	}
	
	#[\Override]
	public function restore(array $input, $history = true) : bool
	{
		// TicketTask has no is_deleted state
		return false;
	}
	
	#[\Override]
	public static function canUpdate(): bool
	{
		return false;
	}
	
	#[\Override]
	public static function getTypeName($nb = 0)
	{
		return _n('Time task', 'Time tasks', $nb);
	}
	
	#[\Override]
	public static function getSectorizedDetails() : array
	{
		return TimeTamer::getSectorizedDetails();
	}
	
	protected static function translateInput($input)
	{
		$ans = [];
		foreach ($input as $k => $v) {
			[$kk, $vv] = match ($k) {
				'hours'		=> ['actiontime', $v * 3600],
				'seconds'	=> ['actiontime', $v],
				'user'		=> ['users_id_tech', $v],
				'cat'		=> ['taskcategories_id', $v],
				'description'	=> [
							'content',
							'<p>' . htmlspecialchars($v) . '</p>'
						],
				'content', 'comment', 'year', 'month', 'day'
						=> [null, null],
				default		=> [$k, $v]
			};
			if (is_null($kk)) continue;
			$ans[$kk] = $vv;
		}
		return ['state' => 2, 'is_private' => 1] + $ans;
	}
	
	static function install() : bool
	{
		global $DB;
		$table = self::VIEW_NAME;
		$tt = TicketTask::getTable();
		$ttv = TimedTicket::VIEW_NAME;
		$t_fk = Ticket::getForeignKeyField();
		$tc_fk = TaskCategory::getForeignKeyField();
		$e_fk = Entity::getForeignKeyField();
		$c_fk = Contract::getForeignKeyField();
		$DB->doQuery("
			CREATE OR REPLACE VIEW `$table` AS
			SELECT
				tt.`id`
			,	tt.`date`
			,	tt.`begin`
			,	tt.`end`
			,	tt.`$t_fk`
			,	tt.`$tc_fk`
			,	ttv.`$e_fk`
			,	ttv.`$c_fk`
			,	tt.`actiontime` / 3600	AS `hours`
			,	tt.`actiontime`		AS `seconds`
			,	tt.`content`		AS `comment`
			,	tt.`users_id_tech`	AS `user`
			,	DAY	(tt.`date`)	AS `day`
			,	MONTH	(tt.`date`)	AS `month`
			,	YEAR	(tt.`date`)	AS `year`
			FROM `$tt` tt
			INNER JOIN `$ttv` ttv
			ON	ttv.`id` = tt.`$t_fk`
			WHERE
				tt.`state` = 2
			AND	tt.`is_private` = 1
			AND	tt.`users_id_tech` > 0
		");
		
		$o = new self();
		$pref = new DisplayPreference();
		foreach ($o->rawSearchOptions() as $rank => $entry) {
			$id = $entry['id'];
			if (!is_numeric($id)) continue;
			$pref->add([
				'itemtype'	=> self::class,
				'users_id'	=> 0,
//				'interface'	=> 'central',
				'rank'		=> $rank,
				'num'		=> $id,
			]);
		}
		
		return true;
	}
	
	static function uninstall() : bool
	{
		global $DB;
		
		$DB->delete(DisplayPreference::getTable(), [
			'itemtype' => self::class
		]);
		
		$table = self::VIEW_NAME;
		$DB->doQuery("DROP VIEW IF EXISTS `$table`");
		
		return true;
	}

	#[\Override]
	public static function getSpecificValueToDisplay(
		$field,
		$values,
		array $options = []
	)
	{
		if ($field === 'comment') {
			$limit = 80;
			$lines = explode("\n", RichText::getTextFromHtml(
				content: $values[$field],
				keep_presentation: false,
				compact: true,
				encode_output: true,
				preserve_line_breaks: true,
			));
			$l = $lines[0];
			$prev = (strlen($l) <= $limit) ?
				$l
			:	(substr($l, 0, $limit - 3) . '...');
			return $prev;
		}
		echo '<pre>';
		var_export(['field' => $field, 'values' => $values, 'options' => $options]);
		echo '</pre>';
		return parent::getSpecificValueToDisplay($field, $values, $options);
	}

	#[\Override]
	public function rawSearchOptions()
	{
		$table = self::VIEW_NAME;
		
		$tab = [];
		
		$tab[] = [
			'id'		=> 'common',
			'name'		=> __('Characteristics'),
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'id',
			'name'		=> __('ID'),
			'datatype'	=> 'number',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'hours',
			'name'		=> __('Hours'),
			'datatype'	=> 'number',
			'unit'		=> 'h',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'comment',
			'name'		=> __('Comment'),
			'datatype'	=> 'specific',
			'itemtype'	=> self::class,
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> Ticket::getTable(),
			'linkfield'	=> Ticket::getForeignKeyField(),
			'field'		=> 'name',
			'name'		=> __('Ticket'),
			'datatype'	=> 'itemlink',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> TaskCategory::getTable(),
			'linkfield'	=> TaskCategory::getForeignKeyField(),
			'field'		=> 'completename',
			'name'		=> __('Category'),
			'datatype'	=> 'itemlink',
			'massiveaction'	=> true,
		];

		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> User::getTable(),
			'linkfield'	=> 'user',
			'field'		=> 'name',
			'name'		=> __('User'),
			'datatype'	=> 'itemlink',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> Entity::getTable(),
			'linkfield'	=> Entity::getForeignKeyField(),
			'field'		=> 'name',
			'name'		=> __('Entity'),
			'datatype'	=> 'itemlink',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> Contract::getTable(),
			'linkfield'	=> Contract::getForeignKeyField(),
			'field'		=> 'name',
			'name'		=> __('Contract'),
			'datatype'	=> 'itemlink',
			'massiveaction'	=> true,
		];
		
		return $tab;
	}
}

TimeTask::$rightname = TicketTask::$rightname;
