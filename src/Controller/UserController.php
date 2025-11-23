<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Service\AuthService;
use App\Service\Logger;

/**
 * Contrôleur de gestion des utilisateurs
 *
 * CRUD des utilisateurs (admin uniquement)
 */
class UserController extends BaseController
{
    private UserService $userService;
    private AuthService $authService;

    public function __construct($twig, $db, $config)
    {
        parent::__construct($twig, $db, $config);
        $this->userService = new UserService($db);
        $this->authService = new AuthService($db, $config);
    }

    /**
     * Liste des utilisateurs
     */
    public function index(): void
    {
        $page = (int) ($this->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->userService->getAllUsers($limit, $offset);
        $total = $this->userService->countUsers();
        $totalPages = (int) ceil($total / $limit);
        $stats = $this->userService->getUserStats();

        echo $this->twig->render('pages/admin/users/index.twig', [
            'users' => $users,
            'stats' => $stats,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_users' => $total,
            'csrf_token' => $this->authService->generateCsrfToken()
        ]);
    }

    /**
     * Formulaire de création d'utilisateur
     */
    public function create(): void
    {
        echo $this->twig->render('pages/admin/users/create.twig', [
            'csrf_token' => $this->authService->generateCsrfToken()
        ]);
    }

    /**
     * Enregistrement d'un nouvel utilisateur
     */
    public function store(): void
    {
        // Vérifier CSRF
        if (!$this->authService->verifyCsrfToken($this->post('csrf_token', ''))) {
            $this->setFlash('error', 'Token de sécurité invalide');
            $this->redirect('/admin/users');
        }

        $email = $this->post('email', '');
        $name = $this->post('name', '');
        $password = $this->post('password', '');
        $role = $this->post('role', 'user');

        $result = $this->userService->createUser($email, $name, $password, $role);

        if ($result['success']) {
            $this->setFlash('success', $result['message']);
            $this->redirect('/admin/users');
        } else {
            $this->setFlash('error', $result['message']);
            $this->redirect('/admin/users/create');
        }
    }

    /**
     * Formulaire d'édition d'utilisateur
     */
    public function edit(int $id): void
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            $this->setFlash('error', 'Utilisateur introuvable');
            $this->redirect('/admin/users');
        }

        echo $this->twig->render('pages/admin/users/edit.twig', [
            'user' => $user,
            'csrf_token' => $this->authService->generateCsrfToken()
        ]);
    }

    /**
     * Mise à jour d'un utilisateur
     */
    public function update(int $id): void
    {
        // Vérifier CSRF
        if (!$this->authService->verifyCsrfToken($this->post('csrf_token', ''))) {
            $this->setFlash('error', 'Token de sécurité invalide');
            $this->redirect('/admin/users');
        }

        $data = [
            'email' => $this->post('email'),
            'name' => $this->post('name'),
            'role' => $this->post('role'),
            'is_active' => $this->post('is_active', '0') === '1'
        ];

        $result = $this->userService->updateUser($id, $data);

        if ($result['success']) {
            $this->setFlash('success', $result['message']);
        } else {
            $this->setFlash('error', $result['message']);
        }

        $this->redirect("/admin/users/edit/$id");
    }

    /**
     * Suppression d'un utilisateur
     */
    public function delete(int $id): void
    {
        // Vérifier CSRF
        if (!$this->authService->verifyCsrfToken($this->post('csrf_token', ''))) {
            $this->jsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
            return;
        }

        $result = $this->userService->deleteUser($id);
        $this->jsonResponse($result);
    }

    /**
     * Toggle du statut d'un utilisateur
     */
    public function toggleStatus(int $id): void
    {
        // Vérifier CSRF
        if (!$this->authService->verifyCsrfToken($this->post('csrf_token', ''))) {
            $this->jsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
            return;
        }

        $result = $this->userService->toggleUserStatus($id);
        $this->jsonResponse($result);
    }

    /**
     * Recherche d'utilisateurs
     */
    public function search(): void
    {
        $query = $this->get('q', '');

        if (strlen($query) < 2) {
            $this->jsonResponse(['users' => []]);
            return;
        }

        $users = $this->userService->searchUsers($query);
        $this->jsonResponse(['users' => $users]);
    }

    /**
     * Réinitialisation du mot de passe
     */
    public function resetPassword(int $id): void
    {
        // Vérifier CSRF
        if (!$this->authService->verifyCsrfToken($this->post('csrf_token', ''))) {
            $this->jsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
            return;
        }

        $newPassword = $this->post('new_password', '');

        if (empty($newPassword)) {
            $this->jsonResponse(['success' => false, 'message' => 'Mot de passe requis']);
            return;
        }

        $result = $this->authService->resetPassword($id, $newPassword);
        $this->jsonResponse($result);
    }
}
