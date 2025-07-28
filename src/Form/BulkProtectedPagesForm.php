<?php

namespace Drupal\protected_pages_bulk\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\protected_pages\ProtectedPagesStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an add bulk protected pages form.
 */
class BulkProtectedPagesForm extends FormBase {

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Provides the password hashing service object.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * Provides messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Check scheduler module exists.
   *
   * @var bool
   */
  protected $schedulerExists;

  /**
   * Constructs a new ProtectedPagesAddForm.
   *
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   The password hashing service.
   * @param \Drupal\protected_pages\ProtectedPagesStorage $protectedPagesStorage
   *   The protected pages storage.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\path_alias\AliasManager $aliasManager
   *   The path alias manager service.
   */
  public function __construct(PathValidatorInterface $path_validator, PasswordInterface $password, ProtectedPagesStorage $protectedPagesStorage, Messenger $messenger, AliasManagerInterface $aliasManager) {
    $this->pathValidator = $path_validator;
    $this->password = $password;
    $this->protectedPagesStorage = $protectedPagesStorage;
    $this->messenger = $messenger;
    $this->aliasManager = $aliasManager;
    $this->schedulerExists = function_exists('protected_pages_scheduler_form_alter');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.validator'),
      $container->get('password'),
      $container->get('protected_pages.storage'),
      $container->get('messenger'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'protected_pages_bulk_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['rules_list'] = [
      '#title' => $this->t('Add protected page urls and password.'),
      '#type' => 'details',
      '#open' => TRUE,
    ];
    $form['rules_list']['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs to protect'),
      '#description' => $this->t('Enter one URL per line (e.g., /secret1, /secret2).'),
      '#required' => TRUE,
    ];

    $form['rules_list']['password'] = [
      '#type' => 'password_confirm',
      '#description' => $this->t('Enter one or more passwords separated by commas.'),
      '#required' => TRUE,
    ];

    if ($this->schedulerExists) {
      $remove_time = NULL;
      $form['rules_list']['remove_time'] = [
        '#type' => 'datetime',
        '#title' => $this->t('Automatically remove password on'),
        '#default_value' => $remove_time ? DrupalDateTime::createFromTimestamp($remove_time) : NULL,
        '#description' => $this->t('Date and time when the password protection is automatically removed.<br/><strong>Important:</strong> Password removal might be delayed depending how often CRON jobs are scheduled (i.e. for up to a minute accuracy, CRON must run every minute). Also, the seconds are irrelevant.'),
      ];
    }

    $form['rules_list']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Protected Pages'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $urls = array_filter(array_map('trim', explode("\n", $form_state->getValue('urls'))));
    foreach ($urls as $url) {
      $entered_path = rtrim(trim($url), " \\/");
      if (!str_starts_with($entered_path, '/')) {
        $form_state->setErrorByName('urls', $this->t('The path needs to start with a slash.'));
      }
      else {
        $normal_path = $this->aliasManager->getPathByAlias($url);
        $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($url));
        if (!$this->pathValidator->isValid($normal_path)) {
          $form_state->setErrorByName('urls', $this->t('Please enter a correct path: %path', ['%path' => $path_alias]));
        }
        $conditions = [];
        $conditions['or'][] = [
          'field' => 'path',
          'value' => $normal_path,
          'operator' => '=',
        ];
        $conditions['or'][] = [
          'field' => 'path',
          'value' => $path_alias,
          'operator' => '=',
        ];

        $pid = $this->protectedPagesStorage->loadProtectedPage(['pid'], $conditions, TRUE);
        if ($pid) {
          $form_state->setErrorByName('urls', $this->t('Duplicate path: %path not allowed. Path already exists or its alias exists.', ['%path' => $path_alias]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $urls = array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('urls')))));
    $password = $this->password->hash(Html::escape($form_state->getValue('password')));
    $count = 0;
    foreach ($urls as $url) {
      $page_data = [
        'password' => $password,
        'title' => '',
        'path' => Html::escape($url),
      ];
      if ($this->schedulerExists) {
        $page_data['remove_time'] = $form_state->getValue('remove_time') ? $form_state->getValue('remove_time')->getTimestamp() : NULL;
      }
      $pid = $this->protectedPagesStorage->insertProtectedPage($page_data);
      if ($pid) {
        if ($this->schedulerExists) {
          $page_data = [];
          $page_data['remove_time'] = $form_state->getValue('remove_time') ? $form_state->getValue('remove_time')->getTimestamp() : NULL;
          $this->protectedPagesStorage->updateProtectedPage($page_data, $pid);
        }
        $count++;
      }
    }
    if ($count) {
      $this->messenger()->addStatus($this->t('Protected pages created: @count', ['@count' => $count]));
    }
    drupal_flush_all_caches();
    $form_state->setRedirect('protected_pages_list');
  }

}
