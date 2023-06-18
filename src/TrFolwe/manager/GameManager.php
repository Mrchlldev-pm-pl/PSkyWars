<?php

namespace TrFolwe\manager;

use pocketmine\block\tile\Chest;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use TrFolwe\SkyWars;
use TrFolwe\task\GameRunningTask;

class GameManager
{

    /*** @var array $SkyWarsGame */
    private array $SkyWarsGame;

    public function __construct()
    {
        $this->SkyWarsGame = SkyWars::getInstance()->SkyWarsGame;
    }

    /**
     * @param Player $player
     * @param string|null $selectedGame
     * @return void
     */
    public function joinGame(Player $player, string $selectedGame = null): void
    {
        $config = SkyWars::getInstance()->getConfig();
        $player->sendMessage("§8[§a?§8] §7Uygun oyun bulundu, Aktarılıyorsunuz...!");
        if (!empty($this->SkyWarsGame)) {
            if (!$selectedGame) {
                do {
                    $selectedGame = array_keys($this->SkyWarsGame)[array_rand(array_keys($this->SkyWarsGame))];
                } while ($config->get("SkyWars")[$selectedGame]["MaxPlayer"] == count(SkyWars::getInstance()->SkyWarsGame[$selectedGame]["Players"]) and $this->SkyWarsGame[$selectedGame]["GameStatus"] == "Waiting");
            }
            $gamePositionInfo = array_map(fn($i) => $i * 1, explode(":", $config->get("SkyWars")[$selectedGame]["SpawnPosition"][count($this->SkyWarsGame[$selectedGame]["Players"])]));
            $player->teleport(new Position($gamePositionInfo[0], $gamePositionInfo[1], $gamePositionInfo[2], Server::getInstance()->getWorldManager()->getWorldByName($config->get("SkyWars")[$selectedGame]["WorldName"])));
            SkyWars::getInstance()->SkyWarsGame[$selectedGame]["Players"][] = $player->getName();
        } else {
            $createGame = array_keys($config->get("SkyWars"))[array_rand(array_keys($config->get("SkyWars")))];
            $time = time();
			ArenaManager::arenaCreate($createGame, function() use($config, $createGame, $player) :void {
				$gamePositionInfo = array_map(fn($i) => $i * 1, explode(":", $config->get("SkyWars")[$createGame]["SpawnPosition"][0]));
            $worldName = $config->get("SkyWars")[$createGame]["WorldName"];
            $player->teleport(new Position($gamePositionInfo[0], $gamePositionInfo[1], $gamePositionInfo[2], Server::getInstance()->getWorldManager()->getWorldByName($worldName)));
            SkyWars::getInstance()->getScheduler()->scheduleRepeatingTask(new GameRunningTask($createGame), 20);
            SkyWars::getInstance()->SkyWarsGame[$createGame] = [
                "WorldName" => $worldName,
                "Players" => [
                    $player->getName()
                ],
                "Viewers" => [],
                "GameStatus" => "Waiting"
            ];
			});
        }
        $player->setNoClientPredictions();
        $player->setGamemode(GameMode::SPECTATOR());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
    }

    /**
     * @param Player $player
     * @param string $gameName
     * @return void
     */
    public function joinForViewers(Player $player, string $gameName): void
    {
        $gameWorld = SkyWars::getInstance()->getServer()->getWorldManager()->getWorldByName($this->SkyWarsGame[$gameName]["WorldName"]);
        SkyWars::getInstance()->SkyWarsGame[$gameName]["Viewers"][] = $player->getName();
        $player->setGamemode(GameMode::SPECTATOR());
        $player->teleport(new Position($gameWorld->getSpawnLocation()->getX(), $gameWorld->getSpawnLocation()->getY() + 10, $gameWorld->getSpawnLocation()->getZ(), $gameWorld));
        $player->sendMessage("§8[§6" . $gameName . "§8] §7İzleyici olarak katıldın");
        $gameItem = VanillaItems::PAPER()->setCustomName("§a" . $gameName)->setLore([
            "Oyun ayarları"
        ]);
        $gameItem->getNamedTag()->setString("gameItem", true);
        $player->getInventory()->setItem(4, $gameItem);
    }

    /**
     * @param string $gameName
     * @return void
     */
    public function addChestItems(string $gameName): void
    {
        $gameWorld = Server::getInstance()->getWorldManager()->getWorldByName(SkyWars::getInstance()->SkyWarsGame[$gameName]["WorldName"]);
        $chestItems = SkyWars::getInstance()->getConfig()->get("SkyWars")[$gameName]["ChestItems"];
        foreach (array_merge(...array_map(fn($c) => $c->getTiles(), $gameWorld->getLoadedChunks())) as $chestTiles) {
            if ($chestTiles instanceof Chest) {
                //$chestTiles->getInventory()->clearAll();
                foreach ($chestItems as $itemInfo => $enchantmentsInfo) {
                    $itemExplodeInfo = explode("-", $itemInfo);
                    $item = StringToItemParser::getInstance()->parse($itemExplodeInfo[0])->setCount($itemExplodeInfo[1] * 1);
                    if (!empty($enchantmentsInfo)) {
                        foreach ($enchantmentsInfo as $enchantment) {
                            $enchantmentId = explode("-", $enchantment)[0] * 1;
                            $enchantmentLevel = explode("-", $enchantment)[1] * 1;
                            $item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId($enchantmentId), $enchantmentLevel));
                        }
                    }
                    $chestTiles->getInventory()->addItem($item);
                }
            }
        }
    }
}