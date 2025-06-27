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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Translation\UserTranslationKeys;

#[Route('/user')]
final class UserController extends AbstractController{

    public function __construct(
        protected UserService $userService,
        protected SerializerInterface&NormalizerInterface $serializer,
    )
    {}

    #[Route( '/register', name: 'user.registration', methods: [ 'POST'])]
    public function register(
        #[MapRequestPayload( validationGroups: [ 'create'])] UserDto $userDto,
    ): ApiResponse{
        $this->userService->register( $userDto);
        return new ApiResponse( 
            UserTranslationKeys::USER_REGISTRATION_SUCCESS,
            true,
            Response::HTTP_CREATED
        );
    }

    #[Route( '/login', name: 'user.login', methods: [ 'POST'])]
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
        $userData  = $this->serializer->normalize(
            $user,
            null,
            [
                'groups' => [ 'user:read']
            ]
        );
        return new ApiResponse( 
            '',
            true,
            Response::HTTP_OK,
            '',
            $userData
        );
    }

    #[Route( '/verify/{token}', methods: [ 'GET'], name: 'user.verify')]
    public function verifyUser( string $token): ApiResponse{
        $this->userService->verifyUser( $token);
        return new ApiResponse(
            UserTranslationKeys::USER_VERIFY_SUCCESS
        );
    }

    #[Route( '/forgotpassword', methods: [ 'POST'], name: 'user.forgot_password')]
    public function forgotPassword(
        #[MapRequestPayload( validationGroups: [ 'reset_password'])] UserDto $userDto
    ): ApiResponse{
        $this->userService->sendResetPasswordEmail( $userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_FORGOT_PASSWORD_SUCCESS ?? '',
        );
    }

    #[Route( '/resetpassword', methods: [ 'POST'], name: 'user.reset_password')]
    public function resetPassword(
        #[MapRequestPayload( validationGroups: [ 'reset_password'])] UserDto $userDto
    ): ApiResponse{
        $this->userService->resetPassword( $userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_RESET_PASSWORD_SUCCESS,
            true,
            Response::HTTP_OK
        );
    }

    #[Route('/disableuser', methods: ['POST'], name: 'user.disable')]
    public function disableUser(#[CurrentUser] User $user): ApiResponse{
        $this->userService->disableUser( $user);
        return new ApiResponse(
            UserTranslationKeys::USER_DISABLE_SUCCESS,
            true,
            Response::HTTP_OK
        );
    }
}
