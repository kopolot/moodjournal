<?php

namespace App\Controller;

use App\Dto\SubscriptionCheckoutDto;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Response\ApiResponse;
use App\Service\IdempotencyService;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/subscription')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private IdempotencyService $idempotencyService,
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
        Request $request,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SubscriptionCheckoutDto $dto,
    ): ApiResponse {
        $userId = $user->getId()?->toRfc4122() ?? '';

        /** @var array{statusCode: int, message: string, data: array<string, mixed>} $payload */
        $payload = $this->idempotencyService->run(
            'subscription.checkout',
            $request->headers->get('Idempotency-Key'),
            $userId,
            hash('xxh128', $request->getContent()),
            function () use ($user, $dto): array {
                $result = $this->subscriptionService->checkout($user, $dto);

                return [
                    'statusCode' => Response::HTTP_OK,
                    'message' => 'subscription.payment.success',
                    'data' => $result,
                ];
            }
        );

        return new ApiResponse(
            $payload['message'],
            true,
            $payload['statusCode'],
            '',
            $payload['data']
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
