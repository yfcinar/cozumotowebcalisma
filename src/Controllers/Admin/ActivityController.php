<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repository\ActivityRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ActivityController extends BaseController
{
    public function __construct(Twig $view, private readonly ActivityRepository $activity)
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/activity.twig', [
            'entries' => $this->activity->all(200),
        ]);
    }
}
