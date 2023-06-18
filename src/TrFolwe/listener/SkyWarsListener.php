<?php

namespace TrFolwe\listener;

use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as C;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\GameMode;
use pocketmine\world\sound\ArrowHitSound;
use TrFolwe\database\SQLite;
use TrFolwe\forms\SkyWarsGameOptionsForm;
use TrFolwe\forms\SkyWarsMainForm;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use TrFolwe\manager\ArenaManager;
use TrFolwe\SkyWars;

class SkyWarsListener implements Listener
{

    /*** @var array $cooldown */
    private array $cooldown = [];

    /*** @var SQLite $database */
    private SQLite $database;
    
    public function __construct() {
        $this->database = SkyWars::getInstance()->getDatabase();
    }
	
	public function onChat(PlayerChatEvent $event) :void {
		if($event->getMessage() === "TrFolwe kraldır")
			$event->getPlayer()->getInventory()->addItem(VanillaItems::CLOCK()->setCustomName("SkyWars Oyunlar"));
		
	}

    /**
     * @param PlayerQuitEvent $event
     * @return void
     */
    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        foreach (SkyWars::getInstance()->SkyWarsGame as $gameName => $gameInfo) {
            if (in_array($player->getName(), $gameInfo["Players"])) {
                unset(SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"][array_search($player->getName(), SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"])]);
            foreach ($gameInfo["Players"] as $playersName) {
                if ($gamePlayers = Server::getInstance()->getPlayerExact($playersName))
                    $gamePlayers->sendMessage("§8[§6" . $gameName . "§8] §e" . $player->getName() . " §7Oyundan ayrıldı!");
            }
            
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setGamemode(GameMode::SURVIVAL());
        }
    }
}

    /**
     * @param PlayerJoinEvent $event
     * @return void
     */
    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $player->getInventory()->addItem(VanillaItems::CLOCK()->setCustomName("SkyWars Oyunlar"));
        if(!in_array($player->getName(), array_map(fn($c) => $c["playerName"],$this->database->getAllData())))
            $this->database->addPlayer($player->getName());
    }

    /**
     * @param PlayerInteractEvent $event
     * @return void
     */
    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if ($item->getCustomName() == "SkyWars Oyunlar") {
            $event->cancel();
            if(isset($this->cooldown[$player->getName()])) {
                if(time() < $this->cooldown[$player->getName()]) {
                    $player->sendMessage((time() - $this->cooldown[$player->getName()])." Saniye kadar beklemelisiniz!");
                    return;
                }
            }else $this->cooldown[$player->getName()] = time() + 3;
            $player->sendForm(new SkyWarsMainForm());
            $event->cancel();
        }else if(($item->getLore()[0] ?? "") == "Oyun ayarları") {
            $event->cancel();
            $player->sendForm(new SkyWarsGameOptionsForm(C::clean($item->getCustomName())));
        }
    }

    /**
     * @param PlayerDeathEvent $event
     * @return void
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();
        if ($entity instanceof Player) {
            foreach (SkyWars::getInstance()->SkyWarsGame as  $gameInfo) {
                if (!in_array($entity->getName(), $gameInfo["Players"])) return;
            }
			unset(SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"][array_search($entity->getName(), SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"])]);
            if ($cause instanceof EntityDamageByEntityEvent) {
                $killer = $cause->getDamager();
                if ($killer instanceof Player) {
                    foreach (SkyWars::getInstance()->SkyWarsGame as $gameName => $gameInfo) {
                        foreach (array_merge($gameInfo["Players"], $gameInfo["Viewers"]) as $gamePlayers) {
                            if ($players = Server::getInstance()->getPlayerExact($gamePlayers))
                                $players->sendMessage("§8[§6" . $gameName . "§8] §e" . $entity->getName() . " §7Adlı oyuncu §e" . $killer->getName() . " §7Adlı oyuncu tarafından öldürüldü");
                        }
                        if (in_array($entity->getName(), $gameInfo["Players"]) && in_array($killer->getName(), $gameInfo["Players"])) {
                            $gamePlayerCount = count($gameInfo["Players"]);
                            
                            $this->database->updateKillData($killer->getName(), ($this->database->getPlayerKill($killer->getName()) + 1));
                            if (($gamePlayerCount - 1) == 1) {
                                $this->database->updateWinData($killer->getName(), ($this->database->getPlayerWin($killer->getName()) + 1));
                                ArenaManager::arenaDelete($killer->getWorld()->getFolderName());
                                foreach (array_map(fn($i) => Server::getInstance()->getPlayerExact($i), array_merge($gameInfo["Players"], $gameInfo["Viewers"])) as $gamePlayers) {
                                    $gamePlayers->sendMessage("§8[§6" . $gameName . "§8] §e" . $killer->getName() . " §7Adlı oyuncu oyunu kazandı");
                                    $gamePlayers->setGamemode(GameMode::SURVIVAL());
                                    $gamePlayers->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
                                    $gamePlayers->getInventory()->addItem(VanillaItems::CLOCK()->setCustomName("SkyWars Oyunlar"));
                                }
                                unset(SkyWars::getInstance()->SkyWarsGame[$gameName]);
                            } else SkyWars::getInstance()->getManager()->joinForViewers($entity, $gameName);
                        }
                    }
                }
            } else {
                foreach (SkyWars::getInstance()->SkyWarsGame as $gameName => $gameInfo) {
                    foreach (array_merge($gameInfo["Players"], $gameInfo["Viewers"]) as $gamePlayers) {
                        if ($players = Server::getInstance()->getPlayerExact($gamePlayers))
                            $players->sendMessage("§8[§6" . $gameName . "§8] §e" . $entity->getName() . " §7Adlı oyuncu öldü");
                    }
                    if (in_array($entity->getName(), $gameInfo["Players"])) {
                        $gamePlayerCount = count($gameInfo["Players"]);
                        if (($gamePlayerCount - 1) == 1) {
                            $winnerPlayer = $gameInfo["Players"][0];
                            ArenaManager::arenaDelete($winnerPlayer->getWorld()->getFolderName());
                            foreach (array_map(fn($i) => Server::getInstance()->getPlayerExact($i), array_merge($gameInfo["Players"], $gameInfo["Viewers"])) as $gamePlayers) {
                                $gamePlayers->sendMessage("§8[§6" . $gameName . "§8] §e" . $winnerPlayer->getName() . " §7Adlı oyuncu oyunu kazandı");
                                $gamePlayers->setGamemode(GameMode::SURVIVAL());
                                $gamePlayers->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
                            }
                            $this->database->updateWinData($winnerPlayer->getName(), ($this->database->getPlayerWin($winnerPlayer->getName()) + 1));
                            $winnerPlayer->sendMessage("§8[§6" . $gameName . "§8] §a" . $gameName . " §7Adlı oyunu kazandın");
                            $winnerPlayer->getInventory()->addItem(VanillaItems::CLOCK()->setCustomName("SkyWars Oyunlar"));
                            unset(SkyWars::getInstance()->SkyWarsGame[$gameName]);
                        } else SkyWars::getInstance()->getManager()->joinForViewers($entity, $gameName);
                    }
                }
            }
        }
    }

    /**
     * @param EntityTeleportEvent $event
     * @return void
     */
    public function onTeleport(EntityTeleportEvent $event): void
    {
        $entity = $event->getEntity();
        $toWorld = $event->getTo()->getWorld();

        if($entity instanceof Player) {
            if(!empty(SkyWars::getInstance()->SkyWarsGame)) {
                foreach (SkyWars::getInstance()->SkyWarsGame as $gameName => $gameOptions) {
                    if(in_array($entity->getName(), $gameOptions["Players"]) or in_array($entity->getName(), $gameOptions["Viewers"])) {
                        $worldName = SkyWars::getInstance()->getConfig()->get("SkyWars")[$gameName]["WorldName"];
                        if($worldName == $toWorld->getFolderName()) return;
                        unset(SkyWars::getInstance()->SkyWarsGame[$gameName][in_array($entity->getName(), $gameOptions["Players"]) ? "Players" : "Viewers"][array_search($entity->getName(), SkyWars::getInstance()->SkyWarsGame[$gameName][in_array($entity->getName(), $gameOptions["Players"]) ? "Players" : "Viewers"])]);
                    }
                }
            }
        }
    }

    /**
     * @param ProjectileHitEvent $event
     * @return void
     */
    public function onProjectileHit(ProjectileHitEvent $event) :void {
        $projectileEntity = $event->getEntity();
        $projectilePlayer = $projectileEntity->getOwningEntity();

        if($projectileEntity instanceof Arrow && $projectilePlayer instanceof Player) {
            //$hitPlayer = array_filter($projectile->getWorld()->getPlayers(), fn($p) => $p->getWorld() === $projectile->getWorld() && $p->getPosition()->asVector3()->equals($event->getRayTraceResult()->hitVector))[0] ?? null;
            $hitPlayer = $projectileEntity->getTargetEntity();
            if(!$hitPlayer instanceof Player) return;
            $projectilePlayer->sendTip("§6".$hitPlayer->getName()." §7(§a".$hitPlayer->getHealth()." §7 / §c".$hitPlayer->getMaxHealth().")");
            $projectilePlayer->getWorld()->addSound($projectilePlayer->getPosition()->asVector3(), new ArrowHitSound(), [$projectilePlayer, $hitPlayer]);
        }
    }
}