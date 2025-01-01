<?php

namespace NurAzli\GriefProtection;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;

class GriefProtection extends PluginBase implements Listener {

    private array $protectedAreas = [];
    private Config $data;
    private int $maxAreasPerPlayer = 3;
    private int $claimSize = 10;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("config.yml");
        $this->data = new Config($this->getDataFolder() . "areas.json", Config::JSON);
        $this->protectedAreas = $this->data->getAll();
    }

    public function onDisable(): void {
        $this->data->setAll($this->protectedAreas);
        $this->data->save();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if (!$sender instanceof Player && !in_array(strtolower($cmd->getName()), ["setmaxareas", "clearallareas"], true)) {
            return true;
        }

        switch (strtolower($cmd->getName())) {
            case "claim":
                if ($this->getPlayerAreaCount($sender) >= $this->maxAreasPerPlayer) {
                    return true;
                }
                $this->claimArea($sender);
                return true;

            case "protectinfo":
                $this->showProtectInfo($sender);
                return true;

            case "transferowner":
                if (count($args) < 1) {
                    return true;
                }
                $this->transferOwner($sender, $args[0]);
                return true;

            case "clearallareas":
                if (!$sender->hasPermission("griefprotection.admin")) {
                    return true;
                }
                $this->clearAllAreas();
                return true;

            case "setmaxareas":
                if (!$sender->hasPermission("griefprotection.admin")) {
                    return true;
                }
                if (count($args) < 1 || !is_numeric($args[0])) {
                    return true;
                }
                $this->setMaxAreas((int)$args[0]);
                return true;

            case "listareas":
                $this->listAreas($sender);
                return true;

            case "setarea":
                if (count($args) < 1 || !is_numeric($args[0])) {
                    return true;
                }
                $this->setAreaSize($sender, (int)$args[0]);
                return true;

            default:
                return false;
        }
    }

    private function setAreaSize(Player $player, int $size): void {
        if ($size < 5 || $size > 50) {
            return;
        }
        $this->claimSize = $size;
    }

    private function listAreas(Player $player): void {
        $areas = [];
        foreach ($this->protectedAreas as $area) {
            if (in_array($player->getName(), $area['owners'], true)) {
                $areas[] = "Bounds: X({$area['minX']}-{$area['maxX']}), Y({$area['minY']}-{$area['maxY']}), Z({$area['minZ']}-{$area['maxZ']})";
            }
        }
        if (empty($areas)) {
            return;
        }
    }

    private function showProtectInfo(Player $player): void {
        $pos = $player->getPosition();
        foreach ($this->protectedAreas as $area) {
            if ($this->isInsideArea($pos, $area)) {
                return;
            }
        }
    }

    private function transferOwner(Player $player, string $newOwner): void {
        $areaIndex = $this->getPlayerAreaIndex($player);
        if ($areaIndex === -1) {
            return;
        }

        $this->protectedAreas[$areaIndex]['owners'] = [$newOwner];
    }

    private function clearAllAreas(): void {
        $this->protectedAreas = [];
    }

    private function setMaxAreas(int $max): void {
        $this->maxAreasPerPlayer = $max;
    }

    private function isInsideArea(Vector3 $pos, array $area): bool {
        return (
            $pos->x >= $area['minX'] && $pos->x <= $area['maxX'] &&
            $pos->y >= $area['minY'] && $pos->y <= $area['maxY'] &&
            $pos->z >= $area['minZ'] && $pos->z <= $area['maxZ']
        );
    }

    private function getPlayerAreaIndex(Player $player): int {
        foreach ($this->protectedAreas as $index => $area) {
            if (in_array($player->getName(), $area['owners'], true)) {
                return $index;
            }
        }
        return -1;
    }

    private function claimArea(Player $player): void {
        $pos = $player->getPosition();
        $area = [
            'minX' => $pos->x - $this->claimSize,
            'maxX' => $pos->x + $this->claimSize,
            'minY' => $pos->y - $this->claimSize,
            'maxY' => $pos->y + $this->claimSize,
            'minZ' => $pos->z - $this->claimSize,
            'maxZ' => $pos->z + $this->claimSize,
            'owners' => [$player->getName()]
        ];
        $this->protectedAreas[] = $area;
    }

    private function getPlayerAreaCount(Player $player): int {
        $count = 0;
        foreach ($this->protectedAreas as $area) {
            if (in_array($player->getName(), $area['owners'], true)) {
                $count++;
            }
        }
        return $count;
    }
}
