<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine;

use pocketmine\utils\Config;
use RuntimeException;

class ChlorineConfig {

	private static ?self $instance = null;

	private readonly Config $config;

	private function __construct(string $file) {
		$this->config = new Config($file, Config::YAML, [
			"disconnect_message"                 => "Suspicious activity detected",
			"disconnect_screen_message"          => "Disconnected from server",
			"decoding_load_threshold"            => 10,
			"packet_length_threshold"            => 2 ** 21,
			"max_violation"                      => 350,
			"history_size"                       => 200,
			"block_address_timeout"              => 300,
			"decoding_load_violation_thresholds" => [
				"level_alert"     => 0.25,
				"level_warn"      => 0.4,
				"level_critical"  => 0.75,
				"level_emergency" => 1.2
			]
		]);
	}

	public static function load(string $file): self {
		return self::$instance = new self($file);
	}

	/**
	 * @return ChlorineConfig
	 */
	public static function getInstance(): ChlorineConfig {
		return self::$instance ?? throw new RuntimeException("Not loaded");
	}

	public function getBlockAddressTimeout(): int {
		return (int) $this->config->get("block_address_timeout");
	}

	public function getMaxViolation(): int {
		return (int) $this->config->get("max_violation");
	}

	public function getHistorySize(): int {
		return (int) $this->config->get("history_size");
	}

	public function getDisconnectMessage(): string {
		return $this->config->get("disconnect_message");
	}

	public function getPacketLengthThreshold(): int {
		return (int) $this->config->get("packet_length_threshold");
	}

	public function getDisconnectScreenMessage(): string {
		return $this->config->get("disconnect_screen_message");
	}

	public function getDecodingLoadThreshold(): float {
		return $this->config->get("decoding_load_threshold");
	}

	/**
	 * @return array{
	 *     alert: float,
	 *     warn: float,
	 *     critical: float,
	 *     emergency: float
	 * }
	 */
	public function getDecodingLoadViolationThresholds(): array {
		$levels = $this->config->get("decoding_load_violation_thresholds");

		return [
			"alert"     => $levels["level_alert"],
			"warn"      => $levels["level_warn"],
			"critical"  => $levels["level_critical"],
			"emergency" => $levels["level_emergency"]
		];
	}
}
