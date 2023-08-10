<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine;

use Lyrica0954\Chlorine\command\ChlorineCommand;
use Lyrica0954\Chlorine\observing\PacketObserver;
use pocketmine\plugin\PluginBase;
use Symfony\Component\Filesystem\Path;

class Main extends PluginBase {

	private static ?self $instance = null;

	/**
	 * @return Main|null
	 */
	public static function getInstance(): ?Main {
		return self::$instance;
	}

	public static function getTimeMillis(): float {
		return hrtime(true) / 1E+6;
	}

	protected function onLoad(): void {
		self::$instance = $this;

		ChlorineConfig::load(Path::join($this->getDataFolder(), "config.yml"));
		ChlorinePermissions::register();

		$this->getServer()->getCommandMap()->register("chlorine", new ChlorineCommand("chlorine", "Inspect player", "/chlorine [inspect/i] [playerName: string]\n/chlorine [most/most_sus/most_suspicious]"));
	}

	protected function onEnable(): void {
		PacketObserver::init($this);
	}
}
