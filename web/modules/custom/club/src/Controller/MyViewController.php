<?php

namespace Drupal\club\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mymodule\Repository\MySqlViewRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MyViewController extends ControllerBase {

  /**
   * The custom MySQL view repository.
   */
  protected MySqlViewRepository $viewRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(MySqlViewRepository $view_repository) {
    $this->viewRepository = $view_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function static create(ContainerInterface $container) {
    return new static(
      $container->get('club.mysql_view_repository')
    );
  }

  /**
   * Displays the MySQL view data.
   */
  public function content(): array {
    $data = $this->viewRepository->getViewData();

    // Process data into a render array (e.g., a table)
    $rows = [];
    foreach ($data as $item) {
      $rows[] = [$item->nid, $item->title, $item->created];
    }

    return [
      '#type' => 'table',
      '#header' => ['NID', 'Title', 'Created'],
      '#rows' => $rows,
    ];
  }
}
