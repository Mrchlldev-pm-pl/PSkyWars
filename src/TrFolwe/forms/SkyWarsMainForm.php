<?php

namespace TrFolwe\forms;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;
use TrFolwe\SkyWars;

class SkyWarsMainForm extends MenuForm
{

    public function __construct()
    {
        parent::__construct(
            "§lSkyWars",
            "\n",
            [
                new MenuOption("Maç Ara"),
                new MenuOption("Mevcut Maçlar"),
                new MenuOption("Oyun İstatistikleri")
            ],
            function (Player $player, int $selectedOption): void {
                switch ($selectedOption) {
                    case 0:
                        $player->sendMessage("§8[§e!§8] §7Uygun maç aranıyor...");
                        SkyWars::getInstance()->getManager()->joinGame($player);
                        break;
                    case 1:
                        $player->sendForm(new SkyWarsAvailableGamesForm());
                        break;
                    case 2:
                        $player->sendForm(new SkyWarsStatisticsForm());
                        break;
                }
            });
    }
}