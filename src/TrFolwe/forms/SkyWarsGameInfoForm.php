<?php

namespace TrFolwe\forms;

use dktapps\pmforms\ModalForm;
use pocketmine\player\Player;
use TrFolwe\SkyWars;

class SkyWarsGameInfoForm extends ModalForm
{

    public function __construct(string $gameName)
    {
        $gamePlayerCount = count(SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"]);
        $gameMaxPlayerCount = (int)SkyWars::getInstance()->getConfig()->get("SkyWars")[$gameName]["MaxPlayer"];
        $gameStatus = SkyWars::getInstance()->SkyWarsGame[$gameName]["GameStatus"];
        parent::__construct(
            $gameName,
            "§7Oyun durumu: ".($gameStatus == "Started" ? "§cOyun Başladı" : ($gamePlayerCount == $gameMaxPlayerCount ? "§aBaşlamak üzere" : "§7Oyunun başlaması için §e".($gameMaxPlayerCount-$gamePlayerCount)." §7Kişi gerekli")),
            function (Player $player, bool $submit) use($gameName) :void{
                if(!array_key_exists($gameName, SkyWars::getInstance()->SkyWarsGame)) return;
                $gameStatus = SkyWars::getInstance()->SkyWarsGame[$gameName]["GameStatus"];
                $gamePlayerCount = count(SkyWars::getInstance()->SkyWarsGame[$gameName]["Players"]);
                $gameMaxPlayerCount = (int)SkyWars::getInstance()->getConfig()->get("SkyWars")[$gameName]["MaxPlayer"];
                if($gameStatus == "Started" or $gamePlayerCount == $gameMaxPlayerCount)
                    SkyWars::getInstance()->getManager()->joinForViewers($player, $gameName);
                else
                    SkyWars::getInstance()->getManager()->joinGame($player, $gameName);
            },  $gameStatus == "Started" ? "İZLE" : ($gamePlayerCount == $gameMaxPlayerCount ? "İZLE" : "KATIL"));
    }
}