<?php

namespace App\Controller;

use App\Service\HomeStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly HomeStatusService $homeStatusService,
    ) {}

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $services = $this->homeStatusService->getStatuses();

        return $this->render('home/index.html.twig', [
            'services' => $services,
            'healthyServices' => $this->homeStatusService->countHealthy($services),
            'totalServices' => count($services),
        ]);
    }

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok'], Response::HTTP_OK);
    }

    #[Route('/reglement', name: 'app_rules', methods: ['GET'])]
    public function rules(): Response
    {
        return $this->render('home/rules.html.twig');
    }

    #[Route('/documentation/api', name: 'app_api_docs', methods: ['GET'])]
    public function apiDocumentation(): Response
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');

        return $this->render('home/api_docs.html.twig', [
            'apiDoc' => $this->readDoc($projectDir.'/docs/API.md'),
            'realtimeDoc' => $this->readDoc($projectDir.'/docs/RealtimeClient.md'),
        ]);
    }

    private function readDoc(string $path): string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return 'Documentation indisponible.';
        }

        return $content;
    }
}
