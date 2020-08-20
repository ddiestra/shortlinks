<?php

namespace Drupal\shortlinks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Component\Utility\Random;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the links form.
 */
class LinkForm extends FormBase {

  /**
   * Class constructor.
   */
  public function __construct(EntityManager $entity_manager) {
    $this->linkStorage = $entity_manager->getStorage('link');
    $this->randomGenerator = new Random();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'links_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="edit-container">',
      '#suffix' => '</div>',
    ];

    $form['wrapper']['path'] = [
      '#type' => 'url',
      '#title' => $this->t('Path'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Enter URL to be shortened'),
      ],
    ];

    $form['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Shorten'),
      '#ajax' => [
        'callback' => '::createLink',
        'wrapper' => 'edit-container',
        'progress' => [],
      ],
    ];

    $form['wrapper']['links'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Link'),
        $this->t('Path'),
        $this->t('Details'),
      ],
    ];

    $form['#attached']['library'][] = 'shortlinks/main';
    return $form;
  }

  /**
   * Validates the code generated is correct.
   */
  public static function validateCode($string) {

    if (!ctype_alnum($string)) {
      return FALSE;
    }

    $links = $this->linkStorage->loadByProperties(['code' => $code]);

    if (!empty($links)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createLink(array &$form, FormStateInterface &$form_state) {

    $values = $form_state->getValues();

    if (filter_var($values['path'], FILTER_VALIDATE_URL)) {
      $code = $this->randomGenerator->name(8, TRUE, '::validateCode');

      $link = $this->linkStorage->create([
        'path' => $values['path'],
        'code' => $code,
        'redirects' => 0,
      ]);

      $link->save();

      unset($form['wrapper']['path']['#value']);

      $storage = $form_state->getStorage();
      if (!isset($storage['links'])) {
        $storage['links'] = [];
      }

      $link = $link->renderArray();
      $render = [];

      $url = Url::fromRoute('shortlinks.goto', ['code' => $link['code']]);
      $url->setAbsolute(TRUE);

      $render[] = [
        '#type' => 'link',
        '#title' => $url->toString(),
        '#url' => $url,
        '#attributes' => [
          'target' => '_blank',
        ],
      ];

      $render[] = ['#markup' => $link['path']];
      $render[] = [
        '#type' => 'link',
        '#title' => $this->t('Details'),
        '#url' => Url::fromRoute('shortlinks.view', ['code' => $link['code']]),
        '#attributes' => [
          'target' => '_blank',
        ],
      ];

      $form['wrapper']['links'][] = $render;

    }

    return $form['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
