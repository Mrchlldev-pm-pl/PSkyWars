<?php

namespace TrFolwe\manager;

use pocketmine\Server;
use TrFolwe\SkyWars;
use TrFolwe\threads\ArenaProcessThread;

class ArenaManager
{

    public static function arenaLoad(): void
    {
        $arenaWorldsDir = SkyWars::getInstance()->getServer()->getDataPath()."MgMaps/";
        $config = SkyWars::getInstance()->getConfig();
        $arenaWorlds = [];
        foreach(new \RecursiveDirectoryIterator($arenaWorldsDir, \FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isDir() && in_array($file->getFilename(), array_map(fn($c) => $c["WorldName"], $config->get("SkyWars"))))
                $arenaWorlds[] = $file->getFilename();
        }
        Server::getInstance()->getLogger()->notice("§aSkyWars arena maps loaded. [".implode(", ", $arenaWorlds)."]");
    }

    /**
     * @param string $gameName
     * @return void
     */
    public static function arenaCreate(string $gameName, \Closure $callback): void
    {
        $dataPath = SkyWars::getInstance()->getServer()->getDataPath();
        $worldName = SkyWars::getInstance()->getConfig()->get("SkyWars")[$gameName]["WorldName"];
        SkyWars::getInstance()->getServer()->getAsyncPool()->submitTask(new ArenaProcessThread(
            $dataPath . "MgMaps/" . $worldName,
            $dataPath . "worlds/" . $worldName,
            "copy",
            function() use($worldName, $callback) :void {
				var_dump($worldName." yÜKLENDİ");
				Server::getInstance()->getWorldManager()->loadWorld($worldName);
				$callback();
			}
        ));
    }

    public static function arenaDelete(string $worldName) :void
    {
        $dataPath = SkyWars::getInstance()->getServer()->getDataPath();
		Server::getInstance()->getWorldManager()->unloadWorld(Server::getInstance()->getWorldManager()->getWorldByName($worldName));
        SkyWars::getInstance()->getServer()->getAsyncPool()->submitTask(new ArenaProcessThread(
            $dataPath."worlds/".$worldName,
            "",
            "delete",
            function(){}
        ));
    }
}