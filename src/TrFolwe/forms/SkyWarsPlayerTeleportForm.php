<?php

namespace TrFolwe\forms;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Dropdown;
use pocketmine\player\Player;
use pocketmine\Server;
use TrFolwe\SkyWars;

class SkyWarsPlayerTeleportForm extends CustomForm
{
    public function __construct(string $gameName)
    {
        $gamePlayers = [];

        foreach (array_map(fn($i) => Server::getInstance()->getPlayerExact($i)->getName(), SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"]) as $players)
            $gamePlayers[] = $players;

        parent::__construct(
            $gameName . " - Oyuncu Işınlan",
            [
                new Dropdown("element0", "Oyuncu seçiniz", $gamePlayers)
            ],
            function (Player $player, CustomFormResponse $response): void {
                if ($selectedPlayer = Server::getInstance()->getPlayerExact($this->getElement(0)->getOption($response->getString("element0"))))
                    $player->teleport($selectedPlayer->getPosition()->asVector3());
            });
    }
}