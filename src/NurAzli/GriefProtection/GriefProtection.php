<?php

namespace NurAzli\GriefProtection;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class GriefProtection extends PluginBase implements Listener
{
    private array $protectedAreas = [];
    private array $claimedAreas = [];
    private string $language = "english";

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("config.yml");
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->language = $config->get("language", "english");
        $this->getLogger()->info(TF::GREEN . "GriefProtection has been enabled.");
        $this->initDatabase();
        $this->loadAreasFromDatabase();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool
    {
        switch (strtolower($cmd->getName())) {
            case "claim":
                if ($sender instanceof Player) {
                    if (!in_array($sender->getName(), $this->claimedAreas)) {
                        $this->claimArea($sender);
                        $this->claimedAreas[] = $sender->getName();
                    } else {
                        $sender->sendMessage($this->translate("already_claimed"));
                    }
                } else {
                    $sender->sendMessage($this->translate("console_not_allowed"));
                }
                return true;

            case "addowner":
                if ($sender instanceof Player) {
                    $this->addOwner($sender, $args);
                } else {
                    $sender->sendMessage($this->translate("console_not_allowed"));
                }
                return true;

            case "language":
                if (count($args) < 1) {
                    $sender->sendMessage($this->translate("language_usage"));
                    return true;
                }
                $this->setLanguage($sender, $args[0]);
                return true;

            default:
                return false;
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $pos = $event->getBlock()->getPosition();
        if ($this->isProtected($pos)) {
            if (!$this->hasPermission($player, "break")) {
                $event->cancel();
                $player->sendMessage($this->translate("no_break_permission"));
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $pos = $event->getBlock()->getPosition();
        if ($this->isProtected($pos)) {
            if (!$this->hasPermission($player, "place")) {
                $event->cancel();
                $player->sendMessage($this->translate("no_place_permission"));
            }
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();
        if ($item->equals(VanillaItems::GOLDEN_SHOVEL())) {
            $this->selectArea($player, $block->getPosition());
            $event->cancel();
        }
    }

    public function isProtected(Vector3 $pos): bool
    {
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

    public function hasPermission(Player $player, string $action): bool
    {
        return $player->isOp();
    }

    public function claimArea(Player $player): void
    {
        $pos = $player->getPosition();
        $area = [
            'minX' => $pos->getX() - 15,
            'maxX' => $pos->getX() + 15,
            'minY' => $pos->getY() - 15,
            'maxY' => $pos->getY() + 15,
            'minZ' => $pos->getZ() - 15,
            'maxZ' => $pos->getZ() + 15,
            'owners' => [$player->getName()]
        ];
        $this->protectedAreas[] = $area;
        $player->sendMessage($this->translate("area_claimed"));
        $this->saveAreaToDatabase($area);
    }

    public function selectArea(Player $player, Vector3 $block): void
    {
        $this->protectedAreas[$player->getName()]['pos1'] = $block;
        $player->sendMessage($this->translate("pos1_selected"));
    }

    public function initDatabase(): void
    {
        $db = new \SQLite3($this->getDataFolder() . "griefprotection.db");
        $db->exec("CREATE TABLE IF NOT EXISTS protected_areas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            min_x FLOAT,
            max_x FLOAT,
            min_y FLOAT,
            max_y FLOAT,
            min_z FLOAT,
            max_z FLOAT,
            owners TEXT
        )");
        $db->close();
    }

    public function saveAreaToDatabase(array $area): void
    {
        $db = new \SQLite3($this->getDataFolder() . "griefprotection.db");
        $owners = implode(",", $area['owners']);
        $statement = $db->prepare("INSERT INTO protected_areas (min_x, max_x, min_y, max_y, min_z, max_z, owners)
                                    VALUES (:minX, :maxX, :minY, :maxY, :minZ, :maxZ, :owners)");
        $statement->bindValue(":minX", $area['minX']);
        $statement->bindValue(":maxX", $area['maxX']);
        $statement->bindValue(":minY", $area['minY']);
        $statement->bindValue(":maxY", $area['maxY']);
        $statement->bindValue(":minZ", $area['minZ']);
        $statement->bindValue(":maxZ", $area['maxZ']);
        $statement->bindValue(":owners", $owners);
        $statement->execute();
        $db->close();
    }

    public function loadAreasFromDatabase(): void
    {
        $db = new \SQLite3($this->getDataFolder() . "griefprotection.db");
        $result = $db->query("SELECT * FROM protected_areas");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $owners = explode(",", $row['owners']);
            $area = [
                'minX' => $row['min_x'],
                'maxX' => $row['max_x'],
                'minY' => $row['min_y'],
                'maxY' => $row['max_y'],
                'minZ' => $row['min_z'],
                'maxZ' => $row['max_z'],
                'owners' => $owners
            ];
            $this->protectedAreas[] = $area;
        }
        $db->close();
    }

    private function translate(string $key): string
    {
        $translations = [
            "area_claimed" => "Area has been claimed!",
            "already_claimed" => "You have already claimed an area.",
            "no_break_permission" => "You are not allowed to break here!",
            "no_place_permission" => "You are not allowed to place blocks here!",
            "console_not_allowed" => "This command can only be used by players.",
            "pos1_selected" => "Position 1 has been selected!"
        ];

        return $translations[$key] ?? "Translation not found.";
    }
}
