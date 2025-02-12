<?php

namespace App\Controller;

use App\Dto\UserRegistrationDto;
use App\Entity\User;
use App\Exception\NotFoundException;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
final class UserController extends AbstractController{

    public function __construct(
        protected UserService $userService,
    )
    {
    }

    #[Route( '/register', name: 'user_registration', methods: [ 'POST'])]
    public function register(
        #[MapRequestPayload] UserRegistrationDto $userDto,
    ): JsonResponse{

        throw new NotFoundException();
        // $this->userService->register( $userDto);

        return $this->json([
            
        ]);
    }
}
