<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer;

use CommonGLPI;
use \Glpi\Plugin\Hooks;
use GlpiPlugin\Timetamer\timetasks\TimeTask;
use GlpiPlugin\Timetamer\timetasks\TimedTicket;
use GlpiPlugin\Timetamer\timetasks\TimingContract;

final class PluginDescriptor
{
	protected bool $init_done = false;
	protected function __construct(
		readonly SemVer $version = new SemVer(0, 0, 2),
		readonly string $key = 'timetamer',
		readonly string $name = 'Time Tamer',
	)
	{}

	static function get() : static
	{
		static $instance = null;
		return $instance ??= new static();
	}

	function install() : bool
	{
		TimingContract::install();
		TimedTicket::install();
		TimeTask::install();
		return true;
	}

	function uninstall() : bool
	{
		TimeTask::uninstall();
		TimedTicket::uninstall();
		TimingContract::uninstall();
		return true;
	}

	function init() : void
	{
		if ($this->init_done) return;
		$this->init_done = true;

		global $PLUGIN_HOOKS;
		$PLUGIN_HOOKS[Hooks::MENU_TOADD][$this->key]['management'] = [TimeTamer::class];  
		
	}
	static function check_prerequisites() : bool
	{
		return true;
	}
	static function check_config(bool $verbose = false) : bool
	{
		return true;
	}
}
