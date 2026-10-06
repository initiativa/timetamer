<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer\timetasks;
use GlpiPlugin\Timetamer\TimeTamer;
use CommonDBTM;
use Entity;
use Contract;
use Ticket;
use Ticket_Contract;
use DisplayPreference;

final class TimedTicket extends CommonDBTM
{
	static $rightname;	// init below class definition
	const string VIEW_NAME = 'timetamer_view_timedtickets';
	const string SUB_TABLE_NAME = 'timetamer_view_validticketcontracts';
	
	protected static $notable = true;
	#[\Override]
	public static function getTable($classname = null)
	{
		return self::VIEW_NAME;
	}
	
	#[\Override]
	public function add(array $input, $options = [], $history = true)
	{
		return false;
	}
	
	#[\Override]
	public function update(array $input, $history = true, $options = [])
	{
		return false;
	}
	
	#[\Override]
	public function delete(array $input, $force = false, $history = true): bool
	{
		return false;
	}
	
	#[\Override]
	public function restore(array $input, $history = true): bool
	{
		return false;
	}
	
	#[\Override]
	public static function canCreate(): bool
	{
		return false;
	}
	
	#[\Override]
	public static function canUpdate(): bool
	{
		return false;
	}
	
	#[\Override]
	public static function canDelete(): bool
	{
		return false;
	}
	
	#[\Override]
	public static function canPurge(): bool
	{
		return false;
	}
	
	#[\Override]
	public function getLink($options = []): string
	{
		$id = $this->fields['id'] ?? null;
		$o = Ticket::getById($id);
		if ($o === false) return '';
		return $o->getLink($options);
	}
	
	#[\Override]
	public static function getTypeName($nb = 0)
	{
		return _n('Timed ticket', 'Timed tickets', $nb);
	}
	
	#[\Override]
	public static function getSectorizedDetails() : array
	{
		return TimeTamer::getSectorizedDetails();
	}
	
	static function install() : bool
	{
		global $DB;
		
		$subtable = self::SUB_TABLE_NAME;
		$table = self::VIEW_NAME;
		$t = Ticket::getTable();
		$c = TimingContract::VIEW_NAME;
		$tc = Ticket_Contract::getTable();
		$t_fk = Ticket::getForeignKeyField();
		$c_fk = Contract::getForeignKeyField();
		$e_fk = Entity::getForeignKeyField();
		
		$DB->doQuery("
			CREATE OR REPLACE VIEW `$subtable` AS
			SELECT
				tc.*
			FROM `$tc` tc
			INNER JOIN `$c` c
			ON	c.`id` = tc.`$c_fk`
		");
		
		$DB->doQuery("
			CREATE OR REPLACE VIEW `$table` AS
			SELECT
				t.`id`
			,	t.`name`	AS `title`
			,	t.`$e_fk`
			,	r.`$c_fk`
			FROM `$t` t
			INNER JOIN (
				SELECT
					tc1.`$t_fk`,
					tc1.`$c_fk`
				FROM `$subtable` tc1
				WHERE tc1.`id` = (
					SELECT MIN(tc2.`id`)
					FROM `$subtable` tc2
					WHERE tc2.`$t_fk` = tc1.`$t_fk`
				)
			) r
			ON	t.`id` = r.`$t_fk`
			AND	t.`is_deleted` = 0
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
		$subtable = self::SUB_TABLE_NAME;
		$DB->doQuery("DROP VIEW IF EXISTS `$subtable`");
		$DB->doQuery("DROP VIEW IF EXISTS `$table`");
		
		return true;
	}


	#[\Override]
	public function rawSearchOptions()
	{
		$table = self::VIEW_NAME;
		$taskv = TimeTask::VIEW_NAME;
		$t_fc = Ticket::getForeignKeyField();
		
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
			'field'		=> 'title',
			'name'		=> __('Title'),
			'datatype'	=> 'itemlink',
			'itemtype'	=> Ticket::getType(),
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
			'field'		=> 'num',
			'name'		=> __('Contract\'s time budget'),
			'datatype'	=> 'number',
			'unit'		=> 'h',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'tot_h',
			'computation'	=> "(
				SELECT
					COALESCE(SUM(taskv.`hours`), 0) AS `tot_h`
				FROM
					`$taskv` taskv
				WHERE
					taskv.`$t_fc` = TABLE.`id`
			)",
			'name'		=> __('Total hours'),
			'datatype'	=> 'number',
			'unit'		=> 'h',
			'massiveaction'	=> true,
		];
		
		return $tab;
	}
}

TimedTicket::$rightname = Ticket::$rightname;
