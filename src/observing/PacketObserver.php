<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine\observing;

use LogicException;
use Lyrica0954\Chlorine\ChlorineConfig;
use Lyrica0954\Chlorine\ChlorinePermissions;
use Lyrica0954\Chlorine\PacketWatcher;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketDecodeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Traversable;
use WeakMap;

class PacketObserver {

	private static ?WeakMap $sessions = null;

	/**
	 * @var PacketWatcher|null
	 * No new decoding starts before decoding is complete... Right?
	 */
	protected ?PacketWatcher $watcher;

	/**
	 * @var PacketWatcher[]
	 */
	protected array $lastResults;

	protected bool $flaggedForDispose;

	protected int $violations;

	private function __construct(
		private readonly NetworkSession $session
	) {
		$this->watcher = null;
		$this->flaggedForDispose = false;
		$this->lastResults = [];
		$this->violations = 0;
	}

	public static function init(Plugin $plugin): void {
		Server::getInstance()->getPluginManager()->registerEvent(
			DataPacketDecodeEvent::class,
			self::onPacketDecode(...),
			EventPriority::MONITOR,
			$plugin
		);

		Server::getInstance()->getPluginManager()->registerEvent(
			DataPacketReceiveEvent::class,
			self::onPacketReceive(...),
			EventPriority::LOWEST,
			$plugin,
			true
		);
	}

	/**
	 * @return Traversable<mixed, PacketObserver>
	 */
	public static function getAllObservers(): Traversable {
		return self::getSessions()->getIterator();
	}

	private static function getSessions(): WeakMap {
		return self::$sessions ??= new WeakMap();
	}

	protected static function onPacketDecode(DataPacketDecodeEvent $event): void {
		$origin = $event->getOrigin();

		$observer = self::get($origin);

		if (!is_null($observer->watcher)) {
			throw new LogicException("A new decode started. This shouldn't happen");
		}

		$watcher = new PacketWatcher($origin, $event->getPacketId(), $event->getPacketBuffer());
		$watcher->onDecodeStart();

		$observer->handleDecode($watcher);

		if ($observer->flaggedForDispose) {
			$event->cancel();

			return;
		}

		$observer->watcher = $watcher;
	}

	public static function get(NetworkSession $session): self {
		return self::getSessions()[$session] ??= new self($session);
	}

	protected function handleDecode(PacketWatcher $watcher): void {
		if ($this->flaggedForDispose) {
			return;
		}

		if ($this->isBypassing()) {
			return;
		}

		$buffer = $watcher->packetBuffer;

		$length = strlen($buffer);

		if ($length > ChlorineConfig::getInstance()->getPacketLengthThreshold()) {
			$this->session->getLogger()->debug(TextFormat::RED . "Activity warning: packet length is $length");
			$this->detect();
		}
	}

	public function isBypassing(): bool {
		$player = $this->session->getPlayer();

		return !is_null($player) && $player->hasPermission(ChlorinePermissions::BYPASS);
	}

	protected function detect(): void {
		$this->session->disconnect(ChlorineConfig::getInstance()->getDisconnectMessage(), ChlorineConfig::getInstance()->getDisconnectScreenMessage());
		$timeout = ChlorineConfig::getInstance()->getBlockAddressTimeout();
		if ($timeout > 0) {
			Server::getInstance()->getNetwork()->blockAddress($this->session->getIp(), $timeout);
		}
		$this->flaggedForDispose = true;
	}

	protected static function onPacketReceive(DataPacketReceiveEvent $event): void {
		$origin = $event->getOrigin();

		$observer = self::get($origin);
		$watcher = $observer->watcher;

		if (is_null($watcher)) {
			throw new LogicException("Decoding is complete, but PacketWatcher is not set. This shouldn't happen");
		}

		$watcher->onDecodeComplete();

		$observer->handleComplete($watcher, $event->getPacket());
		$observer->watcher = null;

		$observer->lastResults[] = $watcher;

		if (count($observer->lastResults) > $observer->getHistorySize()) {
			unset($observer->lastResults[array_key_first($observer->lastResults)]);
		}

		if ($observer->flaggedForDispose) {
			$event->cancel();
		}
	}

	protected function handleComplete(PacketWatcher $watcher, ServerboundPacket $packet): void {
		if ($this->flaggedForDispose) {
			return;
		}

		if ($this->isBypassing()) {
			return;
		}

		$time = $watcher->getDecodeTime();
		$timeHumanFriendly = round($time, 2);
		$load = $time / 50;
		$loadHumanFriendly = round($load * 100, 1);

		$thresholds = ChlorineConfig::getInstance()->getDecodingLoadViolationThresholds();
		if ($load >= $thresholds["alert"]) {
			$level = match (true) {
				$load >= $thresholds["emergency"] => 165,
				$load >= $thresholds["critical"] => 135,
				$load >= $thresholds["warn"] => 80,
				default => 40
			};

			$color = match (true) {
				$load >= $thresholds["critical"] => TextFormat::RED,
				$load >= $thresholds["warn"] => TextFormat::YELLOW,
				default => ""
			};
			$this->session->getLogger()->debug($color . "Activity warning: {$packet->getName()} decoding took {$timeHumanFriendly}ms, This is $loadHumanFriendly% of the tick length");

			$this->violate($level);
		} else {
			$this->reward();
		}

		if ($load > ChlorineConfig::getInstance()->getDecodingLoadThreshold()) {
			$this->detect();
		}
	}

	public function violate(int $level = 1): void {
		$this->violations += $level;

		if ($this->violations > ChlorineConfig::getInstance()->getMaxViolation()) {
			$this->detect();
		}
	}

	public function reward(): void {
		$this->violations--;
		if ($this->violations < 0) {
			$this->violations = 0;
		}
	}

	public function getHistorySize(): int {
		return ChlorineConfig::getInstance()->getHistorySize();
	}

	/**
	 * @return int
	 */
	public function getViolations(): int {
		return $this->violations;
	}

	/**
	 * @return PacketWatcher[]
	 */
	public function getLastResults(): array {
		return $this->lastResults;
	}

	/**
	 * @return NetworkSession
	 */
	public function getSession(): NetworkSession {
		return $this->session;
	}

	/**
	 * @return PacketWatcher|null
	 */
	public function getCurrentWatcher(): ?PacketWatcher {
		return $this->watcher;
	}
}
