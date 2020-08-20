<?php

namespace Drupal\shortlinks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Form\FormBuilder;
use Drupal\shortlinks\Form\LinkForm;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheTagsInvalidator;

/**
 * Main controller.
 */
class LinkController extends ControllerBase {

  /**
   * Class constructor.
   */
  public function __construct(FormBuilder $form_builder, EntityManager $entity_manager, CacheTagsInvalidator $cache_invalidator) {
    $this->formBuilder = $form_builder;
    $this->linkStorage = $entity_manager->getStorage('link');
    $this->cacheInvalidator = $cache_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('form_builder'),
    $container->get('entity.manager'),
    $container->get('cache_tags.invalidator')
    );
  }

  /**
   * Displays link form.
   */
  public function showForm() {
    $build = [];
    $build['form'] = $this->formBuilder->getForm(LinkForm::class);
    return $build;
  }

  /**
   * Simple view of the link details.
   */
  public function view($code) {
    $link = $this->getLink($code);

    return [
      '#cache' => [
        'tags' => [
          'links:' . $link->id(),
        ],
      ],
      '#theme' => 'ldetails',
      '#item' => $link->renderArray(),
    ];
  }

  /**
   * Update the counter and redirects to the field.
   */
  public function goto($code) {
    $link = $this->getLink($code);
    $path = $link->get('path')->getString();
    $counter = (int) $link->get('redirects')->getString();
    $counter++;
    $this->cacheInvalidator->invalidateTags(['links:' . $link->id()]);
    $link->set('redirects', $counter);
    $link->save();

    return (new TrustedRedirectResponse($path))
      ->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
  }

  /**
   * Helper funcion to check the code or 404.
   */
  private function getLink($code) {
    $link = $this->linkStorage->loadByProperties(['code' => $code]);
    if (empty($link)) {
      throw new NotFoundHttpException();
    }

    return reset($link);
  }

}
