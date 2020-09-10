<?php

/*
 * Credits to @thebigsmileXD (XenialDan)
 * Original Repository: https://github.com/thebigsmileXD/fireworks
 * Ported to TeaSpoon as TeaSpoon overrides the fireworks item (as Elytra Booster)
 * Licensed under the MIT License (January 1, 2018)
 *
 * Modified to add explosion damage and a few fixes
 * */

declare(strict_types = 1);

namespace CortexPE\entity\projectile;

use CortexPE\item\Fireworks;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\Player;
use pocketmine\utils\Random;

class FireworksRocket extends Entity {

	public const NETWORK_ID = Entity::FIREWORKS_ROCKET;

	public const DATA_FIREWORK_ITEM = 16; //firework item

	public $width = 0.25;
	public $height = 0.25;

	/** @var int */
	protected $lifeTime = 0;

	public function __construct(Level $level, CompoundTag $nbt, ?Fireworks $fireworks = null){
		parent::__construct($level, $nbt);

		if($fireworks !== null && $fireworks->getNamedTagEntry("Fireworks") instanceof CompoundTag) {
            $this->propertyManager->setCompoundTag(self::DATA_FIREWORK_ITEM, $fireworks->getNamedTag());
			$this->setLifeTime($fireworks->getRandomizedFlightDuration());
		}

       	$level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_LAUNCH);
	}

	protected function tryChangeMovement(): void {
		$this->motion->x *= 1.15;
		$this->motion->y += 0.04;
		$this->motion->z *= 1.15;
	}

	public function entityBaseTick(int $tickDiff = 1): bool {
		if($this->closed) {
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->doLifeTimeTick()) {
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function setLifeTime(int $life): void {
		$this->lifeTime = $life;
	}

	protected function doLifeTimeTick(): bool {
		if(!$this->isFlaggedForDespawn() and --$this->lifeTime < 0) {
			$this->doExplosionAnimation();
			$this->flagForDespawn();
			return true;
		}

		return false;
	}

	protected function doExplosionAnimation(): void {
		$this->broadcastEntityEvent(ActorEventPacket::FIREWORK_PARTICLES);
	}
}