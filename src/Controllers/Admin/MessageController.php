<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repository\ActivityRepository;
use App\Repository\MessageRepository;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class MessageController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MessageRepository $messages,
        private readonly ActivityRepository $activity
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filter = (string) ($params['filter'] ?? 'all');
        $search = trim((string) ($params['q'] ?? ''));

        $list = $this->messages->filtered($filter, $search);

        return $this->render($response, 'admin/messages/index.twig', [
            'messages' => $list,
            'filter'   => $filter,
            'search'   => $search,
            'counts'   => [
                'all'     => count($this->messages->all()),
                'unread'  => $this->messages->unreadCount(),
                'starred' => $this->messages->starredCount(),
            ],
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $message = $this->messages->find((int) $args['id']);
        if ($message === null) {
            throw new HttpNotFoundException($request);
        }
        if ((int) $message['is_read'] === 0) {
            $this->messages->markRead((int) $message['id']);
        }
        return $this->render($response, 'admin/messages/show.twig', ['message' => $message]);
    }

    public function toggleStar(Request $request, Response $response, array $args): Response
    {
        $this->messages->toggleStar((int) $args['id']);
        return $this->redirect($response, $request->getHeaderLine('Referer') ?: '/yonetim/mesajlar');
    }

    public function toggleRead(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $message = $this->messages->find($id);
        if ($message !== null) {
            (int) $message['is_read'] === 1 ? $this->messages->markUnread($id) : $this->messages->markRead($id);
        }
        return $this->redirect($response, $request->getHeaderLine('Referer') ?: '/yonetim/mesajlar');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->messages->delete((int) $args['id']);
        $this->activity->log(Session::user()['name'] ?? null, 'sildi', 'Mesaj', 'Mesaj #' . $args['id']);
        Session::flash('success', 'Mesaj silindi.');
        return $this->redirect($response, '/yonetim/mesajlar');
    }

    /** Tüm mesajları CSV olarak indirir. */
    public function export(Request $request, Response $response): Response
    {
        $rows = $this->messages->all();

        $out = fopen('php://temp', 'r+');
        // Excel'in UTF-8 tanıması için BOM.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Ad', 'Telefon', 'E-posta', 'Konu', 'Mesaj', 'Okundu', 'Tarih']);
        foreach ($rows as $m) {
            fputcsv($out, [
                $m['id'], $m['name'], $m['phone'] ?? '', $m['email'] ?? '',
                $m['subject'] ?? '', $m['body'], ((int) $m['is_read'] === 1 ? 'Evet' : 'Hayır'), $m['created_at'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="mesajlar-' . date('Y-m-d') . '.csv"');
    }
}
