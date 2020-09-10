<?php

/**
 *
 * MMP""MM""YMM               .M"""bgd
 * P'   MM   `7              ,MI    "Y
 *      MM  .gP"Ya   ,6"Yb.  `MMb.   `7MMpdMAo.  ,pW"Wq.   ,pW"Wq.`7MMpMMMb.
 *      MM ,M'   Yb 8)   MM    `YMMNq. MM   `Wb 6W'   `Wb 6W'   `Wb MM    MM
 *      MM 8M""""""  ,pm9MM  .     `MM MM    M8 8M     M8 8M     M8 MM    MM
 *      MM YM.    , 8M   MM  Mb     dM MM   ,AP YA.   ,A9 YA.   ,A9 MM    MM
 *    .JMML.`Mbmmd' `Moo9^Yo.P"Ybmmd"  MMbmmd'   `Ybmd9'   `Ybmd9'.JMML  JMML.
 *                                     MM
 *                                   .JMML.
 * This file is part of TeaSpoon.
 *
 * TeaSpoon is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TeaSpoon is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with TeaSpoon.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author CortexPE
 * @link https://CortexPE.xyz
 *
 */

declare(strict_types = 1);

namespace CortexPE\item;

use CortexPE\entity\projectile\FireworksRocket;
use CortexPE\Main;
use CortexPE\Session;
use CortexPE\task\ElytraRocketBoostTrackingTask;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\utils\Random;

class Fireworks extends Item {

	public const TYPE_SMALL_SPHERE = 0;
	public const TYPE_HUGE_SPHERE = 1;
	public const TYPE_STAR = 2;
	public const TYPE_CREEPER_HEAD = 3;
	public const TYPE_BURST = 4;

	//color = chr(dye metadata)
	public const COLOR_BLACK = "\x00";
	public const COLOR_RED = "\x01";
	public const COLOR_DARK_GREEN = "\x02";
	public const COLOR_BROWN = "\x03";
	public const COLOR_BLUE = "\x04";
	public const COLOR_DARK_PURPLE = "\x05";
	public const COLOR_DARK_AQUA = "\x06";
	public const COLOR_GRAY = "\x07";
	public const COLOR_DARK_GRAY = "\x08";
	public const COLOR_PINK = "\x09";
	public const COLOR_GREEN = "\x0a";
	public const COLOR_YELLOW = "\x0b";
	public const COLOR_LIGHT_AQUA = "\x0c";
	public const COLOR_DARK_PINK = "\x0d";
	public const COLOR_GOLD = "\x0e";
	public const COLOR_WHITE = "\x0f";

	public function __construct(int $meta = 0) {
		parent::__construct(self::FIREWORKS, $meta, "Fireworks");
	}

	public function getFlightDuration(): int {
		return $this->getExplosionsTag()->getByte("Flight", 1);
	}

	public function getRandomizedFlightDuration(): int {
		return ($this->getFlightDuration() + 1) * 10 + mt_rand(0, 5) + mt_rand(0, 6);
	}

	public function setFlightDuration(int $duration): void {
		$tag = $this->getExplosionsTag();
		$tag->setByte("Flight", $duration);
		$this->setNamedTagEntry($tag);
	}

    public function addExplosion(int $type, string $color, string $fade = "", bool $flicker = true, bool $trail = true): void
    {
		$explosion = new CompoundTag();
		$explosion->setByte("FireworkType", $type);
		$explosion->setByteArray("FireworkColor", $color);
		$explosion->setByteArray("FireworkFade", $fade);
        $explosion->setByte("FireworkFlicker", $flicker ? 1 : 0);
        $explosion->setByte("FireworkTrail", $trail ? 1 : 0);

		$tag = $this->getExplosionsTag();
		$explosions = $tag->getListTag("Explosions") ?? new ListTag("Explosions");
		$explosions->push($explosion);
		$tag->setTag($explosions);
		$this->setNamedTagEntry($tag);
	}

	protected function getExplosionsTag(): CompoundTag {
		return $this->getNamedTag()->getCompoundTag("Fireworks") ?? new CompoundTag("Fireworks");
	}

	public function onActivate(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector): bool {
		$nbt = Entity::createBaseNBT($blockReplace->add(0.5, 0, 0.5), new Vector3(0.001, 0.05, 0.001), lcg_value() * 360, 90);
		$check = ["no important"];
		$firework = $this;
		if(!isset($this->getNamedTag()->getValue()["Fireworks"])){
			$check = [];
			goto a;
		} 
		$check = $this->getNamedTag()->getValue()["Fireworks"]->getValue()["Explosions"]->getValue();
		a:
		$firework->setFlightDuration(1);
		if(count($check) == 0){
			$temp = ["\x00","\x01","\x02","\x03","\x04","\x05","\x06","\x07","\x08","\x09","\x0a","\x0b","\x0c","\x0d","\x0e","\x0f"];
			$firework->addExplosion(rand(0,4),$temp[rand(0,15)],$temp[rand(0,15)]);
		}
		$entity = Entity::createEntity("FireworksRocket", $player->getLevel(), $nbt, $firework);

		if($entity instanceof Entity) {
			--$this->count;
			$entity->spawnToAll();
			return true;
		}
		return false;
	}

    //--------------------------------------------
	public function onClickAir(Player $player, Vector3 $directionVector): bool{
		if(Main::$elytraEnabled && Main::$elytraBoostEnabled){
			$session = Main::getInstance()->getSessionById($player->getId());
			if($session instanceof Session){
				if($session->usingElytra && !$player->isOnGround()){
					if($player->getGamemode() != Player::CREATIVE && $player->getGamemode() != Player::SPECTATOR){
						$this->pop();
					}

					$damage = 0;
					$flight = 1;

					if(Main::$fireworksEnabled){
						if($this->getNamedTag()->hasTag("Fireworks", CompoundTag::class)){
							$fwNBT = $this->getNamedTag()->getCompoundTag("Fireworks");
							$flight = $fwNBT->getByte("Flight");
							$explosions = $fwNBT->getListTag("Explosions");
							if(count($explosions) > 0){
								$damage = 7;
							}
						}
					}

					$dir = $player->getDirectionVector();
					$player->setMotion($dir->multiply($flight * 1.25));
					$player->getLevel()->broadcastLevelSoundEvent($player->asVector3(), LevelSoundEventPacket::SOUND_LAUNCH);
					if(Main::$elytraBoostParticles){
						Main::getInstance()->getScheduler()->scheduleRepeatingTask(new ElytraRocketBoostTrackingTask($player, 6), 4);
					}

					if($damage > 0){
						#$ev = new EntityDamageEvent($player, EntityDamageEvent::CAUSE_CUSTOM, 7); // lets wait till PMMP Adds Fireworks damage constant
						#$player->attack($ev);
					}
				}
			}
		}

		return true;
	}
}