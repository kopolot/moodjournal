<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Service\UserService;
use App\Response\ApiResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Route('/user')]
final class UserController extends AbstractController{

    public function __construct(
        protected UserService $userService,
    )
    {}

    #[Route( '/register', name: 'user_registration', methods: [ 'POST'])]
    public function register(
        #[MapRequestPayload( validationGroups: [ 'create'])] UserDto $userDto,
    ): ApiResponse{
        $this->userService->register( $userDto);
        return new ApiResponse( 
            "user.registration.success",
            Response::HTTP_CREATED
        );
    }

    #[Route( '/login', name: 'user_login', methods: [ 'POST'])]
    public function login(){}

    #[Route( '/checkloggedinuser', methods: [ 'GET'], condition:"'dev' === '%kernel.environment%'" )]
    public function checkUser(#[CurrentUser] ?User $user){
        var_dump(
            $user
        );
        die;
        return new Response;
    }

    #[Route( '/get', methods: [ 'GET'])]
    public function getUserData(#[CurrentUser] User $user): ApiResponse{
        return new ApiResponse( 
            (array)$user,
        );
    }
}
