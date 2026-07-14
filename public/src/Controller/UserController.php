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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Translation\UserTranslationKeys;

#[Route('/user')]
final class UserController extends AbstractController
{

    public function __construct(
        protected UserService $userService,
        protected SerializerInterface&NormalizerInterface $serializer,
    ) {
    }

    #[Route('/register', name: 'user.registration', methods: ['POST'])]
    public function register(
        #[MapRequestPayload(validationGroups: ['create'])] UserDto $userDto,
    ): ApiResponse {
        $this->userService->register($userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_REGISTRATION_SUCCESS,
            true,
            Response::HTTP_CREATED
        );
    }

    #[Route('/login', name: 'user.login', methods: ['POST'])]
    public function login()
    {
    }

    #[Route('/checkloggedinuser', methods: ['GET'], condition: "'dev' === '%kernel.environment%'")]
    public function check(#[CurrentUser] ?User $user)
    {
        var_dump(
            $user
        );
        die;
        return new Response;
    }

    #[Route('/get', methods: ['GET'])]
    public function getData(#[CurrentUser] User $user): ApiResponse
    {
        $userData = $this->serializer->normalize(
            $user,
            null,
            [
                'groups' => ['user:read']
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

    #[Route('/verify/{token}', methods: ['GET'], name: 'user.verify')]
    public function verify(Request $request, string $token): Response
    {
        $wantsJson = $request->query->get('format') === 'json'
            || str_contains((string) $request->headers->get('Accept'), 'application/json');

        try {
            $this->userService->verifyUser($token);
            if ($wantsJson) {
                return new ApiResponse(UserTranslationKeys::USER_VERIFY_SUCCESS);
            }

            return $this->render('user/verify.html.twig', [
                'success' => true,
                'message' => null,
            ]);
        } catch (HttpExceptionInterface $e) {
            if ($wantsJson) {
                throw $e;
            }

            return $this->render('user/verify.html.twig', [
                'success' => false,
                'message' => $e->getMessage(),
            ], new Response('', $e->getStatusCode()));
        }
    }

    #[Route('/forgotpassword', methods: ['POST'], name: 'user.forgot_password')]
    public function forgotPassword(
        #[MapRequestPayload(validationGroups: ['reset_password'])] UserDto $userDto
    ): ApiResponse {
        $this->userService->sendResetPasswordEmail($userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_FORGOT_PASSWORD_SUCCESS ?? '',
        );
    }

    #[Route('/resetpassword', methods: ['POST'], name: 'user.reset_password')]
    public function resetPassword(
        #[MapRequestPayload(validationGroups: ['reset_password'])] UserDto $userDto
    ): ApiResponse {
        $this->userService->resetPassword($userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_RESET_PASSWORD_SUCCESS,
            true,
            Response::HTTP_OK
        );
    }

    #[Route('/disableuser', methods: ['POST'], name: 'user.disable')]
    public function disable(#[CurrentUser] User $user): ApiResponse
    {
        $this->userService->disableUser($user);
        return new ApiResponse(
            UserTranslationKeys::USER_DISABLE_SUCCESS,
            true,
            Response::HTTP_OK
        );
    }

    #[Route('/edit', methods: ['PATCH'], name: 'user.edit')]
    public function edit(
        #[CurrentUser] User $user,
        #[MapRequestPayload(validationGroups: ['edit'])] UserDto $userDto
    ): ApiResponse {
        $this->userService->editUser($user, $userDto);
        return new ApiResponse(
            UserTranslationKeys::USER_EDIT_SUCCESS,
            true,
            Response::HTTP_OK
        );
    }

    // #[Route( '/')]
}
