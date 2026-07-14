<?php

namespace App\Controller;

use App\Dto\SubscriptionCheckoutDto;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Response\ApiResponse;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/subscription')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
    ) {
    }

    #[Route('/plans', name: 'subscription.plans', methods: ['GET'])]
    public function plans(): ApiResponse
    {
        return new ApiResponse('', true, Response::HTTP_OK, '', [
            'plans' => SubscriptionTier::catalog(),
        ]);
    }

    #[Route('/current', name: 'subscription.current', methods: ['GET'])]
    public function current(#[CurrentUser] User $user): ApiResponse
    {
        return new ApiResponse('', true, Response::HTTP_OK, '', $this->subscriptionService->current($user));
    }

    #[Route('/checkout', name: 'subscription.checkout', methods: ['POST'])]
    public function checkout(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SubscriptionCheckoutDto $dto,
    ): ApiResponse {
        $result = $this->subscriptionService->checkout($user, $dto);

        return new ApiResponse(
            'subscription.payment.success',
            true,
            Response::HTTP_OK,
            '',
            $result
        );
    }

    #[Route('/cancel', name: 'subscription.cancel', methods: ['POST'])]
    public function cancel(#[CurrentUser] User $user): ApiResponse
    {
        return new ApiResponse(
            'subscription.canceled',
            true,
            Response::HTTP_OK,
            '',
            $this->subscriptionService->cancel($user)
        );
    }
}
