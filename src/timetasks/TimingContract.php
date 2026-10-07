<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer\timetasks;
use GlpiPlugin\Timetamer\TimeTamer;
use CommonDBTM;
use Entity;
use Contract;
use DisplayPreference;
use Contact;

final class TimingContract extends CommonDBTM
{
	static $rightname;	// init below class definition
	const string VIEW_NAME = 'timetamer_view_timingcontracts';
	
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
	public static function getTypeName($nb = 0)
	{
		return _n('Timing contract', 'Timing contracts', $nb);
	}
	
	#[\Override]
	public static function getSectorizedDetails() : array
	{
		return TimeTamer::getSectorizedDetails();
	}
	
	static function install() : bool
	{
		global $DB;
		
		
		$table = self::VIEW_NAME;
		$c = Contract::getTable();
		$e_fk = Entity::getForeignKeyField();
		
		$DB->doQuery("
			CREATE OR REPLACE VIEW `$table` AS
			SELECT
				c.`id`
			,	c.`name`
			,	c.`is_recursive`
			,	c.`$e_fk`
			,	(c.num + 0)	AS `capacity`
			FROM  `$c` c
			WHERE
				c.`is_deleted` = 0
			AND	c.`is_template` = 0
			AND	c.num REGEXP '^[0-9]*\.?[0-9]+$'
			AND	(c.num + 0) >= 0
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
	public function getLinkURL(): string
	{
		$id = $this->fields['id'] ?? null;
		$o = Contract::getById($id);
		if ($o === false) return '';
		return $o->getLinkURL();
	}
	
	protected static function getSpentHoursQuery(?int $id, string $column_name) : string
	{
		$ID = is_null($id) ?
			'TABLE.`id`'
		:	"$id";
		$taskv = TimeTask::VIEW_NAME;
		$c_fk = Contract::getForeignKeyField();
		return "(
				SELECT
					COALESCE(SUM(taskv.`hours`), 0) AS `$column_name`
				FROM
					`$taskv` taskv
				WHERE
					taskv.`$c_fk` = $ID
			)";
	}
	
	static function getSpentHours(int $id) : float
	{
		global $DB;
		/** @var \DB $DB */
		
		return (float) $DB->doQuery(self::getSpentHoursQuery($id, 'ans'))->fetch_array()['ans'];
	}

	#[\Override]
	public function rawSearchOptions()
	{
		$table = self::VIEW_NAME;
		$ticketv = TimedTicket::VIEW_NAME;
		$c_fk = Contract::getForeignKeyField();
		
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
			'field'		=> 'is_recursive',
			'name'		=> __('Recursive'),
			'datatype'	=> 'bool',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'name',
			'name'		=> __('Name'),
			'datatype'	=> 'itemlink',
			'itemtype'	=> Contract::getType(),
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
			'table'		=> $table,
			'field'		=> 'capacity',
			'name'		=> __('Time budget'),
			'datatype'	=> 'decimal',
//			'unit'		=> 'h',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'tot_h',
			'computation'	=> self::getSpentHoursQuery(
						id: null,
						column_name: 'tot_h',
					),
			'name'		=> __('Time spent'),
			'datatype'	=> 'decimal',
//			'unit'		=> 'h',
			'massiveaction'	=> true,
		];
		
		$tab[] = [
			'id'		=> array_key_last($tab),
			'table'		=> $table,
			'field'		=> 'tot_t',
			'computation'	=> "(
				SELECT
					COUNT(ticketv.`id`) AS `tot_t`
				FROM
					`$ticketv` ticketv
				WHERE
					ticketv.`$c_fk` = TABLE.`id`
			)",
			'name'		=> __('Associated tickets'),
			'datatype'	=> 'number',
			'massiveaction'	=> true,
		];
		
		return $tab;
	}
}

TimingContract::$rightname = Contact::$rightname;
