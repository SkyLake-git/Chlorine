<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine\command;

use Lyrica0954\Chlorine\ChlorineConfig;
use Lyrica0954\Chlorine\ChlorinePermissions;
use Lyrica0954\Chlorine\Main;
use Lyrica0954\Chlorine\observing\PacketObserver;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use RuntimeException;

class ChlorineCommand extends Command {

	public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = []) {
		parent::__construct($name, $description, $usageMessage, $aliases);

		$this->setPermission(ChlorinePermissions::COMMANDS);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void {
		if (count($args) <= 0) {
			$sender->sendMessage(TextFormat::DARK_PURPLE . "Chlorine " . TextFormat::AQUA . "v" . Main::getInstance()->getDescription()->getVersion());
			$sender->sendMessage($this->getUsage());

			return;
		}


		switch ($args[0]) {
			case "inspect":
			case "i":
				$this->executeInspect($sender, $commandLabel, $args);
				break;
			case "most":
			case "most_sus":
			case "most_suspicious":
				$this->executeMostSuspicious($sender, $commandLabel, $args);
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}

	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param array $args
	 * @return void
	 * @throws InvalidCommandSyntaxException
	 */
	private function executeInspect(CommandSender $sender, string $commandLabel, array $args): void {
		if (count($args) === 1) {
			throw new InvalidCommandSyntaxException();
		}
		$target = $sender->getServer()->getPlayerExact($args[1]);

		if (is_null($target)) {
			$sender->sendMessage("Player \"$args[1]\" is not online");

			return;
		}

		$targetName = $target->getName();

		$observer = PacketObserver::get($target->getNetworkSession());

		$sender->sendMessage(TextFormat::GREEN . "---- " . TextFormat::RESET . "Inspect: $targetName" . TextFormat::GREEN . " ----");

		$results = $observer->getLastResults();
		$resultLength = count($results);

		$averageTime = 0;
		$averagePacketLength = 0;

		$packetReport = [];

		foreach ($results as $watcher) {
			$packetLength = strlen($watcher->packetBuffer);
			$averageTime += $watcher->getDecodeTime();
			$averagePacketLength += $packetLength;

			$pk = PacketPool::getInstance()->getPacketById($watcher->packetId) ?? throw new RuntimeException("Unknown packet (id: {$watcher->packetId})");
			$pkName = $pk->getName();
			$packetReport[$pkName] ??= [0, 0, 0];
			$packetReport[$pkName][0] += $watcher->getDecodeTime();
			$packetReport[$pkName][1] += $packetLength;
			$packetReport[$pkName][2] += 1;
		}

		$averageTime /= $resultLength;
		$averagePacketLength /= $resultLength;

		foreach ($packetReport as $k => $data) {
			$packetReport[$k][0] /= $data[2];
			$packetReport[$k][1] /= $data[2];
		}


		if ($observer->isBypassing()) {
			$sender->sendMessage(TextFormat::GOLD . "This player is bypassing checks. (has permission)");
		}

		$tickUsageInMs = (Server::getInstance()->getTickUsage() / 100) * 50;

		$sender->sendMessage(TextFormat::GOLD . "Violations: " . TextFormat::RED . $observer->getViolations() . " / " . ChlorineConfig::getInstance()->getMaxViolation());
		$sender->sendMessage(TextFormat::GOLD . "Average Decode Time: " . TextFormat::RED . round($averageTime, 2) . "ms" . TextFormat::GREEN . " (" . round(($averageTime / $tickUsageInMs) * 100, 2) . "% of server load, " . round(($averageTime / 50) * 100, 2) . "% of tick length)");
		$sender->sendMessage(TextFormat::GOLD . "Average Packet Length: " . TextFormat::RED . round($averagePacketLength, 1));
		$sender->sendMessage(TextFormat::GOLD . "Packet Report ({$observer->getHistorySize()}): ");

		foreach ($packetReport as $name => $datum) {
			$sender->sendMessage("  - " . TextFormat::DARK_AQUA . $name . TextFormat::BLUE . " x" . $datum[2] . TextFormat::GRAY);
			$sender->sendMessage("    - " . TextFormat::GOLD . "Average Decode Time: " . TextFormat::RED . round($datum[0], 2) . "ms");
			$sender->sendMessage("    - " . TextFormat::GOLD . "Average Packet Length: " . TextFormat::RED . round($datum[1], 1));
		}
	}

	private function executeMostSuspicious(CommandSender $sender, string $commandLabel, array $args): void {
		$time = PHP_INT_MIN;
		$observerByTime = null;
		$packetLength = PHP_INT_MIN;
		$observerByPacketLength = null;


		foreach (PacketObserver::getAllObservers() as $observer) {
			$averageTime = array_sum(array_map(fn($watcher) => $watcher->getDecodeTime(), $observer->getLastResults())) / $observer->getHistorySize();
			$averagePacketLength = array_sum(array_map(fn($watcher) => strlen($watcher->packetBuffer), $observer->getLastResults())) / $observer->getHistorySize();

			if ($averageTime > $time) {
				$time = $averageTime;
				$observerByTime = $observer;
			}

			if ($averagePacketLength > $packetLength) {
				$packetLength = $averagePacketLength;
				$observerByPacketLength = $observer;
			}
		}

		if (!is_null($observerByTime) && !is_null($observerByPacketLength) && $observerByTime === $observerByPacketLength) {
			$sender->sendMessage(TextFormat::GREEN . "Most suspicious: ");
			$this->executeInspect($sender, $commandLabel, ["", $observerByTime->getSession()->getPlayerInfo()->getUsername()]);

			return;
		}

		if (!is_null($observerByTime)) {
			$sender->sendMessage(TextFormat::GREEN . "Most suspicious (at decode time): ");
			$this->executeInspect($sender, $commandLabel, ["", $observerByTime->getSession()->getPlayerInfo()->getUsername()]);
		}

		if (!is_null($observerByPacketLength)) {
			$sender->sendMessage(TextFormat::GREEN . "Most suspicious (at packet length): ");
			$this->executeInspect($sender, $commandLabel, ["", $observerByPacketLength->getSession()->getPlayerInfo()->getUsername()]);
		}

		if (is_null($observerByTime) && is_null($observerByPacketLength)) {
			$sender->sendMessage(TextFormat::GREEN . "Not found");
		}
	}
}
