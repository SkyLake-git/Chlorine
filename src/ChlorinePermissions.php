<?php

declare(strict_types=1);

namespace Lyrica0954\Chlorine;

use pocketmine\permission\DefaultPermissionNames;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;

class ChlorinePermissions {

	const COMMANDS = "chroline.commands";
	const BYPASS = "chroline.bypass";

	public static function register(): void {
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissionNames::GROUP_OPERATOR);

		DefaultPermissions::registerPermission(
			new Permission(
				self::COMMANDS,
				"Allow the use of chlorine commands"
			),
			[
				$operator
			]
		);

		DefaultPermissions::registerPermission(
			new Permission(
				self::BYPASS,
				"Bypasses check"
			)
		);
	}
}
