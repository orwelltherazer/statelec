<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\IndicateursService;
use Statelec\Controller\SettingsController;

class IndicateursController
{
    private IndicateursService $service;

    public function __construct()
    {
        $this->service = new IndicateursService();
    }

    /**
     * Affiche la page des indicateurs électriques
     */
    public function showIndicateurs(): array
    {
        $periode = $_GET['periode'] ?? 'jour';
        $data = $this->service->getAllIndicateurs($periode);

        return [
            'page_title' => 'Indicateurs Électriques',
            'currentPage' => 'indicateurs',
            'periode' => $periode,
            'indicateurs' => $data,
            'theme' => SettingsController::getCurrentTheme()
        ];
    }

    /**
     * API: Récupère les indicateurs en JSON
     */
    public function getIndicateursData(): void
    {
        header('Content-Type: application/json');
        
        try {
            $periode = $_GET['periode'] ?? 'jour';
            $data = $this->service->getAllIndicateurs($periode);
            
            echo json_encode($data);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des indicateurs: ' . $e->getMessage()]);
        }
    }
}
