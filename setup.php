<?php
declare(strict_types=1);

require_once __DIR__ . '/src/PluginDescriptor.php';
require_once __DIR__ . '/src/SemVer.php';

use Glpi\Plugin\Hooks;
use GlpiPlugin\Timetamer\PluginDescriptor;


function plugin_init_timetamer() : void
{
	global $PLUGIN_HOOKS;
	$descriptor = PluginDescriptor::get();
	$key = $descriptor->key;

	$PLUGIN_HOOKS['csrf_compliant'][$key] = true;
	
	if (!((new Plugin())->isActivated($key))) {
		return;
	}
	
	$descriptor->init();
}

function plugin_version_timetamer() : array
{
	$descriptor = PluginDescriptor::get();
	return [
	  'name' => $descriptor->name,
	  'version' => (string) $descriptor->version,
	];
}

function plugin_timetamer_check_prerequisites() : bool
{
	return PluginDescriptor::check_prerequisites();
}

function plugin_timetamer_check_config(bool $verbose = false) : bool
{
	return PluginDescriptor::check_config($verbose);
}
