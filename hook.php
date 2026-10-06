<?php
declare(strict_types=1);

use GlpiPlugin\TimeTamer\PluginDescriptor;

function plugin_timetamer_install() : bool
{
	$descriptor = PluginDescriptor::get();
	return $descriptor->install();
}

function plugin_timetamer_uninstall() : bool
{
	$descriptor = PluginDescriptor::get();
	return $descriptor->uninstall();
}
