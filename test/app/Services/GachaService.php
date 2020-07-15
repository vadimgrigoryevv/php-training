<?php

/**
 * Gacha Service
 */

namespace App\Services;

use App\Facades\Error;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\CreateGachaRequest;
// Repositories
use App\Repositories\MstCardDataRepository;
use App\Repositories\MstGachaInfoRepository;
use App\Repositories\MstGachaToRarityMapRepository;
use App\Repositories\MstRarityToCardMapRepository;
use App\Repositories\UserGachaCardsRepository;

class GachaService
{
    private $mstCardDataRepository;
    private $mstGachaInfoRepository;
    private $mstGachaToRarityMapRepository;
    private $mstRarityToCardMapRepository;
    private $userGachaCardsRepository;

    public function __construct(
        MstCardDataRepository $mstCardDataRepository,
        MstGachaInfoRepository $mstGachaInfoRepository,
        MstGachaToRarityMapRepository $mstGachaToRarityMapRepository,
        MstRarityToCardMapRepository $mstRarityToCardMapRepository,
        UserGachaCardsRepository $userGachaCardsRepository
    ) {
        $this->mstCardDataRepository = $mstCardDataRepository;
        $this->mstGachaInfoRepository = $mstGachaInfoRepository;
        $this->mstGachaToRarityMapRepository = $mstGachaToRarityMapRepository;
        $this->mstRarityToCardMapRepository = $mstRarityToCardMapRepository;
        $this->userGachaCardsRepository = $userGachaCardsRepository;
    }

    /** Helper functions */

    private function assignIndexFromPercentageArray($percentageWeightArray)
    {
        // Generates a random number between 0 and 1
        $randNum = mt_rand() / mt_getrandmax();

        $cumulativeWeight = 0;
        $itemIndex = 0;

        // Assign the rarity level based on the database value
        foreach ($percentageWeightArray as $percentageWeight) {
            if ($randNum >= $cumulativeWeight && $randNum < $cumulativeWeight + $percentageWeight) {
                return $itemIndex;
            }
            // Increment the weight
            $cumulativeWeight = $cumulativeWeight + $percentageWeight;
            // Increment the counter
            $itemIndex++;
        }

        // If for some reason no rarity level was assigned, assign first
        return $itemIndex;
    }

    private function generatePctArrayAndAssignIndex($gachaToRarityMap)
    {
        // Variables for the total weight and the percentage spread
        $gachaPercentageWeightArray = array();

        $totalWeight = array_sum(array_column($gachaToRarityMap, 'weight'));

        foreach ($gachaToRarityMap as $rarity_item) {
            array_push($gachaPercentageWeightArray, $rarity_item['weight'] / $totalWeight);
        }

        return $this->assignIndexFromPercentageArray($gachaPercentageWeightArray);
    }


    /**
     * Create a gacha card
     *
     * @param CreateGachaRequest $request
     * @return object
     */
    function createGacha(CreateGachaRequest $request)
    {
        $userId = intval($request->user_id);
        $gachaId = intval($request->gacha_id);

        $gachaToRarityMap = $this->mstGachaToRarityMapRepository->getMapForGacha($gachaId);

        // Use function to determine weighted rarity level
        $indexOfSelectedRarity = $this->generatePctArrayAndAssignIndex($gachaToRarityMap);
        $cardRarityToUse = $gachaToRarityMap[$indexOfSelectedRarity]['card_rarity'];

        // Generate a random card within the pool of that card's rarity
        $cardArray = $this->mstRarityToCardMapRepository->getCardsWithRarityLevel($gachaId, $cardRarityToUse);

        // Can reuse the function to get index of the card to issue
        $indexOfCardToIssue = $this->generatePctArrayAndAssignIndex($cardArray);

        $cardInfo = $cardArray[$indexOfCardToIssue];
        $cardId = $cardInfo['card_id'];

        // Add  new to the return object before adding card to the db
        $userCardsArray = $this->userGachaCardsRepository->getUserCards($userId);
        $uniqueIdList = array_unique(array_column($userCardsArray, 'id'));

        // Add card to the db
        $addUserCardResponse = $this->userGachaCardsRepository->addSelectedCardToUserTable($userId, $cardId);

        // Set whether it is a new card
        $addUserCardResponse['new'] = !array_key_exists($cardId, $uniqueIdList);
        // Add the rarity level to the return object also
        $addUserCardResponse['card_rarity'] = $cardRarityToUse;

        return $addUserCardResponse;
    }

    /**
     * Create consecutive gacha cards
     *
     * @param CreateGachaRequest $request
     * @return object
     */
    function issueConsecutiveGachas(CreateGachaRequest $request)
    {
        $userId = intval($request->user_id);
        $gachaId = intval($request->gacha_id);

        // Get the weights of gacha to card rarities
        $gachaToRarityMap = $this->mstGachaToRarityMapRepository->getMapForGacha($gachaId);
        // Get the number of cards to be issued to user
        $masterGachaInfo = $this->mstGachaInfoRepository->getGachaMasterInfo($gachaId);

        $numOfGachaCards = $masterGachaInfo['number_of_cards'];
        $maximum_rarity = $masterGachaInfo['maximum_rarity'];

        // Use function to determine weighted rarity level
        $indexOfSelectedRarity = $this->generatePctArrayAndAssignIndex($gachaToRarityMap);
        $cardRarityToUse = $gachaToRarityMap[$indexOfSelectedRarity]['card_rarity'];

        // Generate a random card within the pool of that card's rarity
        $cardArray = $this->mstRarityToCardMapRepository->getCardsWithRarityLevel($gachaId, $cardRarityToUse);

        $totalWeight = 0;
        $cardPercentageWeightArray = array();
        $returnGachaCardArray = array();

        foreach ($cardArray as $card_item) {
            $totalWeight += $card_item['weight'];
        }

        foreach ($cardArray as $card_item) {
            array_push($cardPercentageWeightArray, $card_item['weight'] / $totalWeight);
        }

        // Call x times for the first x-1 randomly generated gacha cards
        for ($i = 0; $i < $numOfGachaCards - 1; $i++) {
            // Use function to determine weighted rarity level
            $indexOfCardToIssue = $this->assignIndexFromPercentageArray($cardPercentageWeightArray);

            $cardInfo = $cardArray[$indexOfCardToIssue];
            $cardId = $cardInfo['card_id'];

            array_push($cardInsertData, ['user_id' => $userId, 'master_card_id' => $cardId]);

            // Add  new to the return object before adding card to the db
            $cardExists = $this->userGachaCardsRepository->cardExistsForUser($userId, $cardId);
            $gachaCard['user_id'] = $userId;
            // Add the new value to the return array object (do this separate from loop)
            $gachaCard['new'] = !$cardExists;
            // Add the rarity level to the return object also
            $gachaCard['rarity'] = $cardRarityToUse;
            $gachaCard['card_id'] = $cardId;

            array_push($returnGachaCardArray, $gachaCard);
        }

        // Generate one more gacha card for rare rarity or higher
        // Reset variables for the total weight and the percentage spread
        $totalWeight = 0;
        $gachaPercentageWeightArray = array();

        // Calculate new total weight for rare categories
        $cardsOfRarityOrAbove = $this->mstRarityToCardMapRepository->getCardsWithRarityLevelOrAbove($gachaId, $maximum_rarity);

        foreach ($cardsOfRarityOrAbove as $rarity_item) {
            $totalWeight += $rarity_item['tentimes_weight'];
        }

        foreach ($cardsOfRarityOrAbove as $rarity_item) {
            array_push($gachaPercentageWeightArray, $rarity_item['tentimes_weight'] / $totalWeight);
        }

        // Use function to determine weighted rarity level
        $indexOfSelectedRarity = $this->assignIndexFromPercentageArray($gachaPercentageWeightArray);
        $cardIdToIssue = $cardsOfRarityOrAbove[$indexOfSelectedRarity]['card_id'];
        $rarityOfIssuedCard = $cardsOfRarityOrAbove[$indexOfSelectedRarity]['rarity_level'];
        // Get the new value before adding card to the db
        $cardExists = $this->userGachaCardsRepository->cardExistsForUser($userId, $cardIdToIssue);
        $newValue = !$cardExists;

        // Add the cards to the db
        $this->userGachaCardsRepository->addCardsToUserTableFromArray($cardInsertData);
        // Add the last issued card to the db
        $this->userGachaCardsRepository->addSelectedCardToUserTable($userId, $cardIdToIssue);

        // Set the card_id of the last card on the return object
        $gachaCard['card_id'] = $cardIdToIssue;
        // Add the new value to the return array object
        $gachaCard['new'] = $newValue;
        // Add the rarity level to the return object also
        $gachaCard['rarity'] = $rarityOfIssuedCard;
        array_push($returnGachaCardArray, $gachaCard);

        return $returnGachaCardArray;
    }
}
