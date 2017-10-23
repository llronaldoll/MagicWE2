<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;

use pocketmine\block\Block;
use pocketmine\block\UnknownBlock;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\MagicWE2\shape\ShapeGenerator;


class API{
	/**
	 * "  -p also kills pets.\n" +
	 * "  -n also kills NPCs.\n" +
	 * "  -g also kills Golems.\n" +
	 * "  -a also kills animals.\n" +
	 * "  -b also kills ambient mobs.\n" +
	 * "  -t also kills mobs with name tags.\n" +
	 * "  -f compounds all previous flags.\n" +
	 * "  -r also destroys armor stands.\n" */

	/**
	 * Only replaces the air
	 */
	const FLAG_KEEP_BLOCKS = 0x01; // -r
	/**
	 * Only change non-air blocks
	 */
	const FLAG_KEEP_AIR = 0x02; // -k
	/**
	 * The -a flag makes it not paste air.
	 */
	const FLAG_PASTE_WITHOUT_AIR = 0x03; // -a
	/**
	 * Pastes or sets hollow
	 */
	const FLAG_HOLLOW = 0x04; // -h
	/**
	 * The -n flag makes it only consider naturally occurring blocks.
	 */
	const FLAG_NATURAL = 0x05; // -n
	/**
	 * Without the -p flag, the paste will appear centered at the target location.
	 * With the flag, the paste will appear relative to where you had
	 * stood, relative by the copied area when you copied it.
	 */
	const FLAG_UNCENTERED = 0x06; // -p

	public static function flagParser(array $flags){
		$flagmeta = 1;
		foreach ($flags as $flag){
			switch ($flag){
				case "-keepblocks":
					$flagmeta ^= 1 << self::FLAG_KEEP_BLOCKS;
					break;
				case  "-keepair":
					$flagmeta ^= 1 << self::FLAG_KEEP_AIR;
					break;
				case  "-a":
					$flagmeta ^= 1 << self::FLAG_PASTE_WITHOUT_AIR;
					break;
				case  "-h":
					$flagmeta ^= 1 << self::FLAG_HOLLOW;
					break;
				case  "-n":
					$flagmeta ^= 1 << self::FLAG_NATURAL;
					break;
				case  "-p":
					$flagmeta ^= 1 << self::FLAG_UNCENTERED;
					break;
				default:
					Server::getInstance()->getLogger()->warning("The flag $flag is unknown");
			}
		}
		return $flagmeta;
	}

	/**
	 * Checks if a flag is used
	 * @param int $flags The return value of flagParser
	 * @param int $check The flag to check
	 * @return bool
	 */
	public static function hasFlag(int $flags, int $check){
		return ($flags & (1 << $check)) > 0;
	}

	/**
	 * @param Selection $selection
	 * @param Level $level
	 * @param Block[] $blocks
	 * @param array ...$flagarray
	 * @return string
	 */
	public static function fill(Selection $selection, Level $level, $blocks = [], ...$flagarray){
		$flags = self::flagParser($flagarray);
		$changed = 0;
		$time = microtime(TRUE);
		try{
			foreach ($selection->getBlocksXYZ() as $x){
				foreach ($x as $y){
					foreach ($y as $block){
						if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
						if (API::hasFlag($flags, API::FLAG_HOLLOW) && ($block->x > $selection->getMinVec3()->getX() && $block->x < $selection->getMaxVec3()->getX()) && ($block->y > $selection->getMinVec3()->getY() && $block->y < $selection->getMaxVec3()->getY()) && ($block->z > $selection->getMinVec3()->getZ() && $block->z < $selection->getMaxVec3()->getZ())) continue;
						$newblock = $blocks[array_rand($blocks, 1)];
						if (API::hasFlag($flags, API::FLAG_KEEP_BLOCKS)){
							if ($level->getBlock($block)->getId() !== Block::AIR) continue;
						}
						if (API::hasFlag($flags, API::FLAG_KEEP_AIR)){
							if ($level->getBlock($block)->getId() === Block::AIR) continue;
						}
						if ($level->setBlock($block, $newblock, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Fill succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.";
	}

	/**
	 * @param Selection $selection
	 * @param Level $level
	 * @param Block[] $blocks1
	 * @param Block[] $blocks2
	 * @param array ...$flagarray
	 * @return string
	 */
	public static function replace(Selection $selection, Level $level, $blocks1 = [], $blocks2 = [], ...$flagarray){
		$changed = 0;
		$time = microtime(TRUE);
		try{
			foreach ($selection->getBlocksXYZ(...$blocks1) as $x){
				foreach ($x as $y){
					foreach ($y as $block){
						if ($block->y >= Level::Y_MAX || $block->y < 0) continue;
						$newblock = $blocks2[array_rand($blocks2, 1)];
						if ($level->setBlock($block, $newblock, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}

		return Loader::$prefix . TextFormat::GREEN . "Replace succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks out of " . $selection->getTotalCount() . " changed.";
	}

	public static function copy(Selection $selection, Level $level, Player $player, ...$flagarray){
		$flags = self::flagParser($flagarray);
		try{
			$clipboard = new Clipboard();
			$clipboard->setData($selection->getBlocksRelativeXYZ());
			if (self::hasFlag($flags, self::FLAG_UNCENTERED))//TODO relative or not by flags
				$clipboard->setOffset(new Vector3());
			else
				$clipboard->setOffset($player->getPosition()->subtract($selection->/*getMinVec3()*/
				getMaxVec3()));//SUBTRACT THE LEAST X Y Z OF SELECTION //TODO check if player less than minvec
			Loader::$clipboards[$player->getLowerCaseName()] = $clipboard;
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Copied selection to clipboard";
	}

	public static function paste(Clipboard $clipboard, Level $level, Player $player, ...$flagarray){//TODO: maybe clone clipboard
		$flags = self::flagParser($flagarray);
		$changed = 0;
		$time = microtime(TRUE);
		$vec3 = $player->getPosition();//proper stating pos
		try{
			foreach ($clipboard->getData() as $x => $xaxis){
				foreach ($xaxis as $y => $yaxis){
					foreach ($yaxis as $z => $block){
						/** @var Block $block */
						//flag test
						$blockvec3 = $vec3->add($x, $y, $z);
						if (!self::hasFlag($flags, self::FLAG_UNCENTERED))//TODO relative or not by flags
							$blockvec3 = $blockvec3->subtract($clipboard->getOffset())->subtract(count($clipboard->getData()) - 1, count($xaxis) - 1, count($yaxis) - 1);//todo fix offset
						if ($level->setBlock($blockvec3, $block, false, false)) $changed++;
					}
				}
			}
		} catch (WEException $exception){
			return Loader::$prefix . TextFormat::RED . $exception->getMessage();
		}
		return Loader::$prefix . TextFormat::GREEN . "Pasted selection " . (self::hasFlag($flags, self::FLAG_UNCENTERED) ? "absolute" : "relative") . " to your position, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " blocks changed.";
	}

	public static function blockParser(string $fullstring, array &$messages, bool &$error){
		$blocks = [];
		foreach (self::fromString($fullstring, true) as [$name, $item]){
			if (($item instanceof ItemBlock) or ($item instanceof Item && $item->getBlock()->getId() !== Block::AIR)){
				$block = $item->getBlock();
				$blocks[] = $block;
			} else{
				$error = true;
				$messages[] = Loader::$prefix . TextFormat::RED . "Could not find a block/item with the " . (is_numeric($name) ? "id: " : "name") . ": " . $name;
				continue;
			}
			if ($block instanceof UnknownBlock){
				$messages[] = Loader::$prefix . TextFormat::GOLD . $block . " is an unknown block";
			}
		}

		return $blocks;
	}

	/**
	 * /////////////////////////////////////////////////////////////////////
	 * This fixes ItemFactory::fromString until pmmp get's its shit together
	 * /////////////////////////////////////////////////////////////////////
	 *
	 * Tries to parse the specified string into Item ID/meta identifiers, and returns Item instances it created.
	 *
	 * Example accepted formats:
	 * - `diamond_pickaxe:5`
	 * - `minecraft:string`
	 * - `351:4 (lapis lazuli ID:meta)`
	 *
	 * If multiple item instances are to be created, their identifiers must be comma-separated, for example:
	 * `diamond_pickaxe,wooden_shovel:18,iron_ingot`
	 *
	 * @param string $str
	 * @param bool $multiple
	 *
	 * @return array
	 */
	public static function fromString(string $str, bool $multiple = false){
		if ($multiple === true){
			$blocks = [];
			foreach (explode(",", $str) as $b){
				$blocks[] = self::fromString($b, false);
			}

			return $blocks;
		} else{
			$b = explode(":", str_replace([" ", "minecraft:"], ["_", ""], trim($str)));
			if (!isset($b[1])){
				$meta = 0;
			} else{
				$meta = $b[1] & 0xFFFF;
			}

			if (is_numeric($b[0])){
				$item = ItemFactory::get(((int)$b[0]) & 0xFFFF, $meta);
			} elseif (defined(Item::class . "::" . strtoupper($b[0]))){
				$item = ItemFactory::get(constant(Item::class . "::" . strtoupper($b[0])), $meta);
				if ($item->getId() === Item::AIR and strtoupper($b[0]) !== "AIR"){
					$item = null;
				}
			} else{
				$item = null;
			}

			return [$b[0], $item];
		}
	}

	public static function createBrush(Block $target, CompoundTag $settings){
		$shape = null;
		switch ($settings->getTag("type", StringTag::class)){
			case "Square": {
				$shape = ShapeGenerator::getShape($target->getLevel(), ShapeGenerator::TYPE_SQUARE, self::compoundToArray($settings));
				$shape->setCenter($target->asVector3());//TODO fix the offset?: if you have a uneven number, the center actually is between 2 blocks
				break;
			}

			case null:
			default:
				;
		}
		if (is_null($shape)){
			Server::getInstance()->broadcastMessage("Unknown shape");
			return false;
		}
		Server::getInstance()->broadcastMessage(self::fill($shape, $shape->getLevel(), self::blockParser($shape->options['blocks'])));
		return true;
	}

	public static function compoundToArray(CompoundTag $compoundTag){
		$nbt = new NBT();
		$nbt->setData($compoundTag);
		return $nbt->getArray();
	}
}