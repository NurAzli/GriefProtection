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
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\SQLite3;

class GriefProtection extends PluginBase implements Listener {

    private $protectedAreas = [];
    private $claimedAreas = [];
    private $language;

    public function onEnable():void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("config.yml");
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->language = $config->get("language", "english");
        $this->getLogger()->info(TF::GREEN . "GriefProtection has been enabled.");
        $this->initDatabase();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        switch(strtolower($cmd->getName())){
            case "claim":
                if($sender instanceof Player){
                    if(!in_array($sender->getName(), $this->claimedAreas)){
                        $this->claimArea($sender);
                        $this->claimedAreas[] = $sender->getName();
                    }else{
                        $sender->sendMessage($this->translate("already_claimed"));
                    }
                }else{
                    $sender->sendMessage($this->translate("console_not_allowed"));
                }
                return true;
            case "addowner":
                if($sender instanceof Player){
                    $this->addOwner($sender, $args);
                }else{
                    $sender->sendMessage($this->translate("console_not_allowed"));
                }
                return true;
            case "language":
                if(count($args) < 1){
                    $sender->sendMessage($this->translate("language_usage"));
                    return true;
                }
                $this->setLanguage($sender, $args[0]);
                return true;
            default:
                return false;
        }
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $pos = $block->getPosition();
        if($this->isProtected($pos)){
            if(!$this->hasPermission($player, "break")){
                $event->setCancelled();
                $player->sendMessage($this->translate("no_break_permission"));
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $pos = $block->getPosition();
        if($this->isProtected($pos)){
            if(!$this->hasPermission($player, "place")){
                $event->setCancelled();
                $player->sendMessage($this->translate("no_place_permission"));
            }
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();
        if($item->getId() === Item::GOLDEN_SHOVEL){
            $this->selectArea($player, $block);
            $event->setCancelled();
        }
    }

    public function isProtected($pos){
        foreach($this->protectedAreas as $area){
            if($pos->getX() >= $area['minX'] && $pos->getX() <= $area['maxX'] &&
               $pos->getY() >= $area['minY'] && $pos->getY() <= $area['maxY'] &&
               $pos->getZ() >= $area['minZ'] && $pos->getZ() <= $area['maxZ']){
                return true;
            }
        }
        return false;
    }

    public function hasPermission(Player $player, $action){
        if($player->isOp()){
            return true;
        }
        return false;
    }

    public function claimArea(Player $player){
        $area = [
            'minX' => $player->getX() - 15,
            'maxX' => $player->getX() + 15,
            'minY' => $player->getY() - 15,
            'maxY' => $player->getY() + 15,
            'minZ' => $player->getZ() - 15,
            'maxZ' => $player->getZ() + 15,
            'owners' => [$player->getName()]
        ];
        $this->protectedAreas[] = $area;
        $player->sendMessage($this->translate("area_claimed"));
    }

    public function selectArea(Player $player, Vector3 $block){
        if(isset($this->selectedAreas[$player->getName()])){
            unset($this->selectedAreas[$player->getName()]);
        }
        $this->selectedAreas[$player->getName()]['pos1'] = $block;
        $player->sendMessage($this->translate("pos1_selected"));
    }

    public function addOwner(Player $player, $args){
        if(count($args) < 2){
            $player->sendMessage($this->translate("addowner_usage"));
            return;
        }
        $targetPlayer = $this->getServer()->getPlayer($args[0]);
        if($targetPlayer !== null){
            $areaIndex = $this->getPlayerAreaIndex($player);
            if($areaIndex !== -1){
                $area = &$this->protectedAreas[$areaIndex];
                array_push($area['owners'], $targetPlayer->getName());
                $player->sendMessage($this->translate("owner_added"));
                return;
            }
            $player->sendMessage($this->translate("area_not_claimed"));
        }else{
            $player->sendMessage($this->translate("player_not_found"));
        }
    }

    public function translate($key){
        switch($this->language){
            case "english":
                $lang = [
                    "area_claimed" => "Area has been claimed!",
                    "already_claimed" => "You have already claimed an area.",
                    "no_break_permission" => "You are not allowed to break here!",
                    "no_place_permission" => "You are not allowed to place blocks here!",
                    "console_not_allowed" => "This command can only be used by players.",
                    "pos1_selected" => "Position 1 has been selected!",
                    "addowner_usage" => "Usage: /addowner <player>",
                    "owner_added" => "Owner added successfully!",
                    "area_not_claimed" => "You have not claimed any area yet.",
                    "player_not_found" => "Player not found or is not online.",
                    "language_usage" => "Usage: /language <language>"
                ];
                break;
            // Add other languages here
            default:
                $lang = [
                    // Default language is English
                    "area_claimed" => "Area has been claimed!",
                    "already_claimed" => "You have already claimed an area.",
                    "no_break_permission" => "You are not allowed to break here!",
                    "no_place_permission" => "You are not allowed to place blocks here!",
                    "console_not_allowed" => "This command can only be used by players.",
                    "pos1_selected" => "Position 1 has been selected!",
                    "addowner_usage" => "Usage: /addowner <player>",
                    "owner_added" => "Owner added successfully!",
                    "area_not_claimed" => "You have not claimed any area yet.",
                    "player_not_found" => "Player not found or is not online.",
                    "language_usage" => "Usage: /language <language>"
                ];
                break;
            case "indonesian":
                $lang = [
                    "area_claimed" => "Area telah berhasil di-klaim!",
                    "already_claimed" => "Kamu telah mengklaim area sebelumnya.",
                    "no_break_permission" => "Kamu tidak diizinkan untuk merusak di sini!",
                    "no_place_permission" => "Kamu tidak diizinkan untuk meletakkan blok di sini!",
                    "console_not_allowed" => "Perintah ini hanya bisa digunakan oleh pemain.",
                    "pos1_selected" => "Posisi 1 telah dipilih!",
                    "addowner_usage" => "Penggunaan: /addowner <pemain>",
                    "owner_added" => "Pemilik berhasil ditambahkan!",
                    "area_not_claimed" => "Kamu belum mengklaim area.",
                    "player_not_found" => "Pemain tidak ditemukan atau tidak online.",
                    "language_usage" => "Penggunaan: /language <bahasa>"
                ];
                break;
            // Add other languages here
            default:
                $lang = [
                    "area_claimed" => "Area has been claimed!",
                    "already_claimed" => "You have already claimed an area.",
                    "no_break_permission" => "You are not allowed to break here!",
                    "no_place_permission" => "You are not allowed to place blocks here!",
                    "console_not_allowed" => "This command can only be used by players.",
                    "pos1_selected" => "Position 1 has been selected!",
                    "addowner_usage" => "Usage: /addowner <player>",
                    "owner_added" => "Owner added successfully!",
                    "area_not_claimed" => "You have not claimed any area yet.",
                    "player_not_found" => "Player not found or is not online.",
                    "language_usage" => "Usage: /language <language>"
                ];
                break;
        }
        return $lang[$key] ?? "Translation not found for key: $key";
    }

    public function setLanguage(Player $player, $language){
        $languages = ["english", "indonesian"]; // Add other languages here
        if(in_array(strtolower($language), $languages)){
            $this->language = strtolower($language);
            $player->sendMessage($this->translate("language_changed"));
        }else{
            $player->sendMessage($this->translate("invalid_language"));
        }
    }

    public function initDatabase(){
        $db = new SQLite3($this->getDataFolder() . "griefprotection.db");
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

    public function saveAreaToDatabase($area){
        $db = new SQLite3($this->getDataFolder() . "griefprotection.db");
        $owners = implode(",", $area['owners']);
        $statement = $db->prepare("INSERT INTO protected_areas (min_x, max_x, min_y, max_y, min_z, max_z, owners)
                                    VALUES (:minX, :maxX, :minY, :maxY, :minZ, :maxZ, :owners)");
        $statement->bindValue(":minX", $area['minX'], SQLITE3_FLOAT);
        $statement->bindValue(":maxX", $area['maxX'], SQLITE3_FLOAT);
        $statement->bindValue(":minY", $area['minY'], SQLITE3_FLOAT);
        $statement->bindValue(":maxY", $area['maxY'], SQLITE3_FLOAT);
        $statement->bindValue(":minZ", $area['minZ'], SQLITE3_FLOAT);
        $statement->bindValue(":maxZ", $area['maxZ'], SQLITE3_FLOAT);
        $statement->bindValue(":owners", $owners, SQLITE3_TEXT);
        $statement->execute();
        $db->close();
    }

    public function loadAreasFromDatabase(){
        $db = new SQLite3($this->getDataFolder() . "griefprotection.db");
        $result = $db->query("SELECT * FROM protected_areas");
        while($row = $result->fetchArray(SQLITE3_ASSOC)){
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
}
