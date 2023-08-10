<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine;

use Closure;
use LogicException;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\utils\ObjectSet;
use RuntimeException;

class PacketWatcher {

	protected float $decodeStartTime;

	protected float $decodeCompleteTime;

	/**
	 * @var ObjectSet<Closure(PacketWatcher): void>
	 */
	protected ObjectSet $decodeCompleteHooks;

	public function __construct(
		public readonly NetworkSession $origin,
		public readonly int            $packetId,
		public readonly string         $packetBuffer,
	) {
		$this->decodeCompleteTime = -1;
		$this->decodeStartTime = -1;
		$this->decodeCompleteHooks = new ObjectSet();
	}

	/**
	 * @return ObjectSet
	 */
	public function getDecodeCompleteHooks(): ObjectSet {
		return $this->decodeCompleteHooks;
	}

	public function onDecodeStart(): void {
		if ($this->decodeCompleteTime > 0) {
			throw new LogicException("Already marked as completed");
		}

		$this->decodeStartTime = Main::getTimeMillis();
	}

	public function onDecodeComplete(): void {
		if ($this->decodeStartTime < 0) {
			throw new LogicException("Decoding not started");
		}

		$this->decodeCompleteTime = Main::getTimeMillis();

		foreach ($this->decodeCompleteHooks as $hook) {
			$hook($this);
		}
	}

	/**
	 * @return float
	 *
	 * Returns the time(milliseconds) taken to decode
	 */
	public function getDecodeTime(): float {
		if ($this->decodeStartTime < 0) {
			throw new RuntimeException("Decode not started");
		}

		if ($this->decodeCompleteTime < 0) {
			return Main::getTimeMillis() - $this->decodeStartTime;
		}

		return $this->decodeCompleteTime - $this->decodeStartTime;
	}

	/**
	 * @return NetworkSession
	 */
	public function getOrigin(): NetworkSession {
		return $this->origin;
	}


}
