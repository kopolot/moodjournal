<?php

namespace App\Controller;

use App\Response\ApiResponse;
use App\Service\TranslationService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class TranslationController extends AbstractController
{

    public function __construct(
        private TranslationService $translationService
    ){
    }


    #[Route('/translations/{locale}', name: 'get_translations', methods: ['GET'])]
    public function getTranslations(string $locale): ApiResponse
    {
        $translations = $this->translationService->getTranslations($locale);
        return new ApiResponse(
            $locale,
            true,
            200,
            '',
            $translations
        );
    }
    
}