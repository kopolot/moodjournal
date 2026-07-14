<?php

namespace App\Controller;

use App\Dto\MoodEntryDto;
use App\Entity\User;
use App\Response\ApiResponse;
use App\Service\MoodService;
use App\Translation\MoodTranslationKeys;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/mood')]
final class MoodController extends AbstractController
{
    public function __construct(
        private MoodService $moodService,
        private SerializerInterface&NormalizerInterface $serializer,
    ) {
    }

    #[Route('', name: 'mood.create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(validationGroups: ['create'])] MoodEntryDto $dto,
    ): ApiResponse {
        $entry = $this->moodService->create($user, $dto);

        return new ApiResponse(
            MoodTranslationKeys::MOOD_CREATED,
            true,
            Response::HTTP_CREATED,
            '',
            [
                'entry' => $this->normalizeEntry($entry),
                'stats' => $this->moodService->stats($user),
            ]
        );
    }

    #[Route('', name: 'mood.list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        Request $request,
    ): ApiResponse {
        $limit = (int) $request->query->get('limit', 30);
        $offset = (int) $request->query->get('offset', 0);
        $result = $this->moodService->list($user, $limit, $offset);

        return new ApiResponse(
            '',
            true,
            Response::HTTP_OK,
            '',
            [
                'items' => array_map(fn ($entry) => $this->normalizeEntry($entry), $result['items']),
                'total' => $result['total'],
            ]
        );
    }

    #[Route('/stats', name: 'mood.stats', methods: ['GET'])]
    public function stats(#[CurrentUser] User $user): ApiResponse
    {
        return new ApiResponse(
            '',
            true,
            Response::HTTP_OK,
            '',
            $this->moodService->stats($user)
        );
    }

    #[Route('/{id}', name: 'mood.get', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function get(
        #[CurrentUser] User $user,
        string $id,
    ): ApiResponse {
        $entry = $this->moodService->get($user, $id);

        return new ApiResponse(
            '',
            true,
            Response::HTTP_OK,
            '',
            ['entry' => $this->normalizeEntry($entry)]
        );
    }

    #[Route('/{id}', name: 'mood.update', methods: ['PATCH'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function update(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload(validationGroups: ['edit'])] MoodEntryDto $dto,
    ): ApiResponse {
        $entry = $this->moodService->update($user, $id, $dto);

        return new ApiResponse(
            MoodTranslationKeys::MOOD_UPDATED,
            true,
            Response::HTTP_OK,
            '',
            ['entry' => $this->normalizeEntry($entry)]
        );
    }

    #[Route('/{id}', name: 'mood.delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(
        #[CurrentUser] User $user,
        string $id,
    ): ApiResponse {
        $this->moodService->delete($user, $id);

        return new ApiResponse(
            MoodTranslationKeys::MOOD_DELETED,
            true,
            Response::HTTP_OK
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEntry(object $entry): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($entry, null, ['groups' => ['mood:read']]);

        return $data;
    }
}
