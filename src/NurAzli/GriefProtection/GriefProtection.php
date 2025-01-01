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

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Load or create configuration for protected areas
        $this->saveResource("config.yml");
        $this->data = new Config($this->getDataFolder() . "areas.json", Config::JSON);
        $this->protectedAreas = $this->data->getAll();

        $this->getLogger()->info("GriefProtection enabled successfully!");
    }

    public function onDisable(): void {
        // Save protected areas to file on disable
        $this->data->setAll($this->protectedAreas);
        $this->data->save();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return true;
        }

        switch (strtolower($cmd->getName())) {
            case "claim":
                $this->claimArea($sender);
                return true;

            case "addowner":
                if (count($args) < 1) {
                    $sender->sendMessage("Usage: /addowner <player>");
                    return true;
                }
                $this->addOwner($sender, $args[0]);
                return true;

            default:
                return false;
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $pos = $event->getBlock()->getPosition();
        if ($this->isProtected($pos) && !$this->hasPermission($player, $pos)) {
            $event->cancel();
            $player->sendMessage("You are not allowed to break blocks here!");
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $pos = $event->getBlockAgainst()->getPosition();
        if ($this->isProtected($pos) && !$this->hasPermission($player, $pos)) {
            $event->cancel();
            $player->sendMessage("You are not allowed to place blocks here!");
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if ($this->isProtected($block->getPosition()) && !$this->hasPermission($player, $block->getPosition())) {
            $event->cancel();
            $player->sendMessage("You are not allowed to interact here!");
        }
    }

    private function claimArea(Player $player): void {
        $pos = $player->getPosition();
        $area = [
            'minX' => $pos->getX() - 10,
            'maxX' => $pos->getX() + 10,
            'minY' => $pos->getY() - 10,
            'maxY' => $pos->getY() + 10,
            'minZ' => $pos->getZ() - 10,
            'maxZ' => $pos->getZ() + 10,
            'owners' => [$player->getName()]
        ];

        $this->protectedAreas[] = $area;
        $player->sendMessage("You have successfully claimed a protected area!");
    }

    private function addOwner(Player $player, string $ownerName): void {
        $areaIndex = $this->getPlayerAreaIndex($player);
        if ($areaIndex === -1) {
            $player->sendMessage("You do not own any area to add an owner.");
            return;
        }

        $this->protectedAreas[$areaIndex]['owners'][] = $ownerName;
        $player->sendMessage("$ownerName has been added as an owner to your area.");
    }

    private function isProtected(Vector3 $pos): bool {
        foreach ($this->protectedAreas as $area) {
            if (
                $pos->getX() >= $area['minX'] && $pos->getX() <= $area['maxX'] &&
                $pos->getY() >= $area['minY'] && $pos->getY() <= $area['maxY'] &&
                $pos->getZ() >= $area['minZ'] && $pos->getZ() <= $area['maxZ']
            ) {
                return true;
            }
        }
        return false;
    }

    private function hasPermission(Player $player, Vector3 $pos): bool {
        foreach ($this->protectedAreas as $area) {
            if (
                $pos->getX() >= $area['minX'] && $pos->getX() <= $area['maxX'] &&
                $pos->getY() >= $area['minY'] && $pos->getY() <= $area['maxY'] &&
                $pos->getZ() >= $area['minZ'] && $pos->getZ() <= $area['maxZ'] &&
                in_array($player->getName(), $area['owners'], true)
            ) {
                return true;
            }
        }
        return false;
    }

    private function getPlayerAreaIndex(Player $player): int {
        foreach ($this->protectedAreas as $index => $area) {
            if (in_array($player->getName(), $area['owners'], true)) {
                return $index;
            }
        }
        return -1;
    }
}
