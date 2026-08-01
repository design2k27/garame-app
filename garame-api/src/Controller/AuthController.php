<?php
// src/Controller/AuthController.php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly UserRepository              $userRepository,
        private readonly ValidatorInterface          $validator,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/auth/register
    // -------------------------------------------------------------------------
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'invalid_json',
                'JSON invalide.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        // Champs obligatoires
        if (!$username || !$email || !$password) {
            return $this->errorResponse(
                'missing_required_fields',
                'Les champs username, email et password sont obligatoires.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Unicité
        $taken = $this->userRepository->checkUniqueness($email, $username);
        if ($taken['email']) {
            return $this->errorResponse(
                'email_already_used',
                'Cet email est déjà utilisé.',
                Response::HTTP_CONFLICT
            );
        }
        if ($taken['username']) {
            return $this->errorResponse(
                'username_already_taken',
                'Ce pseudo est déjà pris.',
                Response::HTTP_CONFLICT
            );
        }

        // Création
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));

        // Validation des contraintes de l'entité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->errorResponse(
                'validation_failed',
                'La validation a échoué.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $messages
            );
        }

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login
    // -------------------------------------------------------------------------
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->errorResponse(
                'invalid_json',
                'JSON invalide.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->errorResponse(
                'missing_credentials',
                'Email et mot de passe obligatoires.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->errorResponse(
                'invalid_credentials',
                'Identifiants invalides.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => $this->serializeUser($user),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/auth/me
    // -------------------------------------------------------------------------
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->errorResponse(
                'unauthenticated',
                'Non authentifié.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json(['user' => $this->serializeUser($user)]);
    }

    // -------------------------------------------------------------------------
    // Sérialisation
    // -------------------------------------------------------------------------
    private function serializeUser(User $user): array
    {
        return [
            'id'          => $user->getId(),
            'username'    => $user->getUsername(),
            'email'       => $user->getEmail(),
            'credits'     => $user->getCredits(),
            'gamesPlayed' => $user->getGamesPlayed(),
            'gamesWon'    => $user->getGamesWon(),
            'createdAt'   => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
