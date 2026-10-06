<?php
declare(strict_types=1);

namespace GlpiPlugin\Timetamer;


class SemVer implements \Stringable
{
	function __construct(
		readonly int $major,
		readonly int $minor,
		readonly int $patch,
	)
	{}
	
	function lt(self $other) : bool
	{
		return
			$this->major < $other->major || (
			$this->major === $other->major && (
			$this->minor < $other->minor || (
			$this->minor === $other->minor && (
			$this->patch < $other->patch
		))));
	}
	
	#[\Override]
	function __toString() : string
	{
		return "{$this->major}.{$this->minor}.{$this->patch}";
	}
	
	static function fromString(string $s) : static
	{
		$acc = [];
		foreach (explode('.', $s) as $x) {
			$acc[] = (int) $x;
		}
		return new static(...$acc);
	}


	function reduced() : static
	{
		return new static($this->major, $this->minor, 0);
	}
}
