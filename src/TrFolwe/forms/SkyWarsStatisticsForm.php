<?php

namespace TrFolwe\forms;

use dktapps\pmforms\MenuForm;
use pocketmine\player\Player;
use TrFolwe\SkyWars;

class SkyWarsStatisticsForm extends MenuForm
{

    public function __construct()
    {
        $statisticText = "§cVERİ YOK!";
        $database = SkyWars::getInstance()->getDatabase();
        if(!empty($database->getAllData())) {
            $statisticText = "";
            $i = 1;
            foreach (array_map(fn($i) => $i["playerName"], $database->getAllData()) as $playersName) {
                $statisticText .= "§6".$i." - §7".$playersName."\nKill Sayısı: §6".$database->getPlayerKill($playersName)."\n§7Oyun Kazanma Sayısı: §6".$database->getPlayerWin($playersName)."\n\n";
                $i++;
            }
        }

        parent::__construct(
            "SkyWars İstatistikleri",
            $statisticText,
            [],
            function():void{}
        );
    }
}