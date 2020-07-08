<?php

/**
 * Gacha Service
 */

namespace App\Services;

use App\Facades\Error;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\CreateGachaRequest;
use App\Repositories\GachaRarityWeightlistRepository;
use App\Repositories\GachaMasterDataRepository;
use App\Repositories\UserGachaCardsRepository;

class GachaService
{
    private $gachaWeightlistRepository;
    private $gachaMasterDataRepository;
    private $userGachaCardsRepository;

    public $rarity_map = [
        1 => 'SR',
        2 => 'R',
        3 => 'N',
    ];

    public function __construct(
        GachaRarityWeightlistRepository $gachaWeightlistRepository,
        GachaMasterDataRepository $gachaMasterDataRepository,
        UserGachaCardsRepository $userGachaCardsRepository
    ) {
        $this->gachaWeightlistRepository = $gachaWeightlistRepository;
        $this->gachaMasterDataRepository = $gachaMasterDataRepository;
        $this->userGachaCardsRepository = $userGachaCardsRepository;
    }

    function createGacha(CreateGachaRequest $request)
    {
        $userId = intval($request->id);

        $gachaWeightlist = $this->gachaWeightlistRepository->getWeightlist();

        // Get percentage weight of each card
        $superRareCardWeight = $gachaWeightlist[0]['card_rarity'];
        $rareCardWeight = $gachaWeightlist[1]['card_rarity'];
        $commonCardWeight = $gachaWeightlist[2]['card_rarity'];
        $totalWeight = $superRareCardWeight + $rareCardWeight + $commonCardWeight;

        // Calculate percentage values
        $superRareCardPct = $gachaWeightlist[0]['card_rarity'] / $totalWeight;
        $rareCardPct = $gachaWeightlist[1]['card_rarity'] / $totalWeight;
        $commonCardPct = $gachaWeightlist[2]['card_rarity'] / $totalWeight;

        // Generates a random number between 0 and 1
        $randNum = mt_rand() / mt_getrandmax();

        // Assign the rarity level based on the database value
        if ($randNum >= 0 && $randNum < $commonCardPct) {
            $rarityLevel = 3;
        } elseif ($randNum >= $commonCardPct && $randNum < 1 - $superRareCardPct) {
            $rarityLevel = 2;
        } else {
            // It's greater than these, thus a super rare card
            $rarityLevel = 1;
        }

        // Generate a random card within the pool of that card's rarity
        $cardArray = $this->gachaMasterDataRepository->getCardsWithRarityLevel($rarityLevel);
        $cardInfo = $cardArray[array_rand($cardArray)];
        $cardId = $cardInfo['id'];
        $addUserCardResponse = $this->userGachaCardsRepository->addSelectedCardToUserTable($userId, $cardId);

        return $addUserCardResponse;
    }
}